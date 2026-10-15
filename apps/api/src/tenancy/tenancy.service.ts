import { Injectable } from '@nestjs/common';
import {
  CreateUnitRequest,
  DEFAULT_THEME,
  MemberDto,
  TenantDto,
  ThemeTokens,
  UnitDto,
} from '@fauvovet/contracts';
import { AuditService } from '../audit/audit.service';
import { DbService } from '../db/db.service';
import { Errors } from '../common/errors';
import type { AuthContext } from '../common/request-context';

const ctxOf = (a: AuthContext) => ({ tenantId: a.tenantId, userId: a.userId });

interface UnitRow {
  id: string;
  name: string;
  timezone: string;
  address_line: string | null;
  city: string | null;
  state: string | null;
  created_at: Date;
}

const toUnit = (r: UnitRow): UnitDto => ({
  id: r.id,
  name: r.name,
  timezone: r.timezone,
  addressLine: r.address_line,
  city: r.city,
  state: r.state,
  createdAt: r.created_at.toISOString(),
});

/**
 * Todas as consultas rodam com o contexto do tenant ativo; o RLS do banco é a barreira final
 * mesmo que um filtro seja esquecido aqui. Ainda assim, filtramos explicitamente por tenant_id
 * (defesa em profundidade e melhor uso de índices).
 */
@Injectable()
export class TenancyService {
  constructor(
    private readonly db: DbService,
    private readonly audit: AuditService,
  ) {}

  async getTenant(
    auth: AuthContext,
  ): Promise<TenantDto & { counts: { units: number; members: number } }> {
    return this.db.withContext(ctxOf(auth), async (c) => {
      const r = await c.query(
        `SELECT t.id, t.trade_name, t.person_type, th.primary_color, th.secondary_color, th.mode,
                (SELECT count(*)::int FROM units u WHERE u.tenant_id = t.id) AS units,
                (SELECT count(*)::int FROM memberships m WHERE m.tenant_id = t.id AND m.status = 'active') AS members
           FROM tenants t LEFT JOIN tenant_themes th ON th.tenant_id = t.id
          WHERE t.id = $1`,
        [auth.tenantId],
      );
      const row = r.rows[0];
      if (!row) throw Errors.notFound();
      return {
        id: row.id,
        tradeName: row.trade_name,
        personType: row.person_type,
        theme: row.primary_color
          ? { primary: row.primary_color, secondary: row.secondary_color, mode: row.mode }
          : DEFAULT_THEME,
        counts: { units: row.units, members: row.members },
      };
    });
  }

  async updateTheme(
    auth: AuthContext,
    theme: ThemeTokens,
    requestId: string,
  ): Promise<ThemeTokens> {
    return this.db.withContext(ctxOf(auth), async (c) => {
      await c.query(
        `INSERT INTO tenant_themes (tenant_id, primary_color, secondary_color, mode, updated_by)
         VALUES ($1, $2, $3, $4, $5)
         ON CONFLICT (tenant_id) DO UPDATE
           SET primary_color = EXCLUDED.primary_color, secondary_color = EXCLUDED.secondary_color,
               mode = EXCLUDED.mode, updated_by = EXCLUDED.updated_by`,
        [
          auth.tenantId,
          theme.primary.toLowerCase(),
          theme.secondary.toLowerCase(),
          theme.mode,
          auth.userId,
        ],
      );
      await this.audit.record(c, {
        tenantId: auth.tenantId,
        actorUserId: auth.userId,
        action: 'tenant.theme_updated',
        targetType: 'tenant',
        targetId: auth.tenantId!,
        metadata: { ...theme },
        requestId,
      });
      return {
        ...theme,
        primary: theme.primary.toLowerCase(),
        secondary: theme.secondary.toLowerCase(),
      };
    });
  }

  async listUnits(auth: AuthContext): Promise<UnitDto[]> {
    return this.db.withContext(ctxOf(auth), async (c) => {
      const r = await c.query<UnitRow>(
        `SELECT id, name, timezone, address_line, city, state, created_at
           FROM units WHERE tenant_id = $1 ORDER BY name LIMIT 500`,
        [auth.tenantId],
      );
      return r.rows.map(toUnit);
    });
  }

  async getUnit(auth: AuthContext, id: string): Promise<UnitDto> {
    return this.db.withContext(ctxOf(auth), async (c) => {
      const r = await c.query<UnitRow>(
        `SELECT id, name, timezone, address_line, city, state, created_at FROM units WHERE tenant_id = $1 AND id = $2`,
        [auth.tenantId, id],
      );
      if (!r.rows[0]) throw Errors.notFound(); // 404 também para recurso de outro tenant (não revela existência)
      return toUnit(r.rows[0]);
    });
  }

  async createUnit(
    auth: AuthContext,
    input: CreateUnitRequest,
    requestId: string,
  ): Promise<UnitDto> {
    return this.db.withContext(ctxOf(auth), async (c) => {
      try {
        const r = await c.query<UnitRow>(
          `INSERT INTO units (tenant_id, name, timezone, address_line, city, state)
           VALUES ($1, $2, $3, $4, $5, $6)
           RETURNING id, name, timezone, address_line, city, state, created_at`,
          [
            auth.tenantId,
            input.name,
            input.timezone,
            input.addressLine ?? null,
            input.city ?? null,
            input.state ?? null,
          ],
        );
        const unit = toUnit(r.rows[0]!);
        await this.audit.record(c, {
          tenantId: auth.tenantId,
          actorUserId: auth.userId,
          action: 'unit.created',
          targetType: 'unit',
          targetId: unit.id,
          requestId,
        });
        return unit;
      } catch (err) {
        if ((err as { code?: string }).code === '23505')
          throw Errors.conflict('Já existe uma unidade com este nome.');
        throw err;
      }
    });
  }

  async listMembers(auth: AuthContext): Promise<MemberDto[]> {
    return this.db.withContext(ctxOf(auth), async (c) => {
      const r = await c.query(
        `SELECT m.id AS membership_id, u.id AS user_id, u.name, u.email, m.roles, m.status
           FROM memberships m JOIN users u ON u.id = m.user_id
          WHERE m.tenant_id = $1 ORDER BY u.name LIMIT 500`,
        [auth.tenantId],
      );
      return r.rows.map((row) => ({
        membershipId: row.membership_id,
        userId: row.user_id,
        name: row.name,
        email: row.email,
        roles: row.roles,
        status: row.status,
      }));
    });
  }

  async listAudit(auth: AuthContext) {
    return this.db.withContext(ctxOf(auth), async (c) => {
      const r = await c.query(
        `SELECT a.id::text, a.action, a.target_type, a.target_id, a.created_at, u.name AS actor_name
           FROM audit_events a LEFT JOIN users u ON u.id = a.actor_user_id
          WHERE a.tenant_id = $1 ORDER BY a.created_at DESC LIMIT 50`,
        [auth.tenantId],
      );
      return r.rows.map((row) => ({
        id: row.id,
        action: row.action,
        targetType: row.target_type,
        targetId: row.target_id,
        actorName: row.actor_name,
        createdAt: (row.created_at as Date).toISOString(),
      }));
    });
  }
}
