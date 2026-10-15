import { Inject, Injectable } from '@nestjs/common';
import {
  ActiveContext,
  MeResponse,
  MembershipSummary,
  dashboardFor,
  permissionsFor,
} from '@fauvovet/contracts';
import { AuditService } from '../audit/audit.service';
import { CONFIG, Env } from '../config/env';
import { DbService } from '../db/db.service';
import { Errors } from '../common/errors';
import type { AuthContext } from '../common/request-context';
import { emailFingerprint, getDummyHash, hashToken, newToken, verifyPassword } from './tokens';

interface MembershipRow {
  membership_id: string;
  tenant_id: string;
  tenant_name: string;
  roles: string[];
  units: { id: string; name: string }[];
}

@Injectable()
export class AuthService {
  constructor(
    private readonly db: DbService,
    private readonly audit: AuditService,
    @Inject(CONFIG) private readonly config: Env,
  ) {}

  async login(email: string, password: string, meta: { userAgent?: string; requestId: string }) {
    const found = await this.db.pool.query('SELECT * FROM app.auth_find_user($1)', [email]);
    const user = found.rows[0] as
      | {
          id: string;
          name: string;
          password_hash: string;
          status: string;
          locked_until: Date | null;
        }
      | undefined;

    if (!user) {
      await verifyPassword(await getDummyHash(), password); // tempo constante aproximado
      await this.db.withContext({}, (c) =>
        this.audit.record(c, {
          tenantId: null,
          actorUserId: null,
          action: 'auth.login_failed',
          metadata: { reason: 'unknown_email', emailFp: emailFingerprint(email) },
          requestId: meta.requestId,
        }),
      );
      throw Errors.invalidCredentials();
    }
    if (user.locked_until && user.locked_until > new Date()) throw Errors.locked();

    const ok = user.status === 'active' && (await verifyPassword(user.password_hash, password));
    await this.db.pool.query('SELECT app.auth_register_login_result($1, $2, $3, $4)', [
      user.id,
      ok,
      this.config.LOGIN_MAX_FAILURES,
      this.config.LOGIN_LOCK_MINUTES,
    ]);
    if (!ok) {
      await this.db.withContext({ userId: user.id }, (c) =>
        this.audit.record(c, {
          tenantId: null,
          actorUserId: user.id,
          action: 'auth.login_failed',
          metadata: { reason: user.status === 'active' ? 'bad_password' : 'disabled' },
          requestId: meta.requestId,
        }),
      );
      throw Errors.invalidCredentials();
    }

    const token = newToken();
    const csrfToken = newToken();
    const expiresAt = new Date(Date.now() + this.config.SESSION_TTL_HOURS * 3600_000);
    await this.db.withContext({ userId: user.id }, async (c) => {
      await c.query(
        `INSERT INTO user_sessions (user_id, token_hash, csrf_token, user_agent, expires_at)
         VALUES ($1, $2, $3, $4, $5)`,
        [user.id, hashToken(token), csrfToken, meta.userAgent?.slice(0, 300) ?? null, expiresAt],
      );
      await this.audit.record(c, {
        tenantId: null,
        actorUserId: user.id,
        action: 'auth.login',
        requestId: meta.requestId,
      });
    });

    // Contexto automático quando há exatamente um vínculo com no máximo uma unidade.
    const memberships = await this.memberships(user.id);
    if (memberships.length === 1 && memberships[0]!.units.length <= 1) {
      const m = memberships[0]!;
      await this.applyContext(user.id, token, m.membershipId, m.units[0]?.id, meta.requestId);
    }
    return { token, expiresAt };
  }

  async resolveSession(token: string): Promise<AuthContext | null> {
    const r = await this.db.pool.query('SELECT * FROM app.auth_resolve_session($1)', [
      hashToken(token),
    ]);
    const row = r.rows[0];
    if (!row) return null;
    return {
      sessionId: row.session_id,
      userId: row.user_id,
      userName: row.user_name,
      userEmail: row.user_email,
      csrfToken: row.csrf_token,
      tenantId: row.tenant_id,
      tenantName: row.tenant_name,
      membershipId: row.membership_id,
      unitId: row.tenant_id ? row.unit_id : null,
      roles: row.roles ?? [],
    };
  }

  async memberships(userId: string): Promise<MembershipSummary[]> {
    const rows = await this.db.withContext({ userId }, async (c) => {
      const r = await c.query<MembershipRow>('SELECT * FROM app.my_memberships()');
      return r.rows;
    });
    return rows.map((r) => ({
      membershipId: r.membership_id,
      tenantId: r.tenant_id,
      tenantName: r.tenant_name,
      roles: r.roles,
      units: r.units,
    }));
  }

  async me(auth: AuthContext): Promise<MeResponse> {
    const memberships = await this.memberships(auth.userId);
    let context: ActiveContext | null = null;
    if (auth.tenantId && auth.membershipId && auth.tenantName) {
      context = {
        tenantId: auth.tenantId,
        tenantName: auth.tenantName,
        membershipId: auth.membershipId,
        unitId: auth.unitId,
        roles: auth.roles,
        permissions: permissionsFor(auth.roles),
        dashboard: dashboardFor(auth.roles),
      };
    }
    return {
      user: { id: auth.userId, name: auth.userName, email: auth.userEmail },
      memberships,
      context,
      csrfToken: auth.csrfToken,
    };
  }

  /**
   * Seleciona tenant/unidade ativos. O tenant NUNCA vem do cliente: é derivado do vínculo,
   * que precisa pertencer ao usuário autenticado (consulta via app.my_memberships()).
   */
  async selectContext(
    auth: AuthContext,
    membershipId: string,
    unitId: string | undefined,
    requestId: string,
  ) {
    return this.applyContextBySession(auth.userId, auth.sessionId, membershipId, unitId, requestId);
  }

  private async applyContext(
    userId: string,
    token: string,
    membershipId: string,
    unitId: string | undefined,
    requestId: string,
  ) {
    const session = await this.resolveSession(token);
    if (!session) throw Errors.unauthenticated();
    return this.applyContextBySession(userId, session.sessionId, membershipId, unitId, requestId);
  }

  private async applyContextBySession(
    userId: string,
    sessionId: string,
    membershipId: string,
    unitId: string | undefined,
    requestId: string,
  ) {
    const memberships = await this.memberships(userId);
    const m = memberships.find((x) => x.membershipId === membershipId);
    if (!m) throw Errors.forbidden(); // vínculo inexistente ou de outro usuário
    let chosenUnit: string | null = null;
    if (unitId) {
      if (!m.units.some((u) => u.id === unitId)) throw Errors.forbidden();
      chosenUnit = unitId;
    } else if (m.units.length === 1) {
      chosenUnit = m.units[0]!.id;
    }
    await this.db.withContext({ userId, tenantId: m.tenantId }, async (c) => {
      const r = await c.query(
        `UPDATE user_sessions SET active_tenant_id = $1, active_membership_id = $2, active_unit_id = $3, last_seen_at = now()
          WHERE id = $4 AND revoked_at IS NULL`,
        [m.tenantId, m.membershipId, chosenUnit, sessionId],
      );
      if (r.rowCount !== 1) throw Errors.unauthenticated();
      await this.audit.record(c, {
        tenantId: m.tenantId,
        actorUserId: userId,
        action: 'auth.context_selected',
        targetType: 'unit',
        targetId: chosenUnit ?? undefined,
        requestId,
      });
    });
    return { tenantId: m.tenantId, unitId: chosenUnit };
  }

  async logout(auth: AuthContext, requestId: string): Promise<void> {
    await this.db.withContext({ userId: auth.userId, tenantId: auth.tenantId }, async (c) => {
      await c.query('UPDATE user_sessions SET revoked_at = now() WHERE id = $1', [auth.sessionId]);
      await this.audit.record(c, {
        tenantId: auth.tenantId,
        actorUserId: auth.userId,
        action: 'auth.logout',
        requestId,
      });
    });
  }
}
