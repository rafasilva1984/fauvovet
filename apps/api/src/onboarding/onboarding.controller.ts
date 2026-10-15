import { randomUUID } from 'node:crypto';
import { Body, Controller, HttpCode, Inject, Injectable, Post, Req } from '@nestjs/common';
import {
  DEFAULT_THEME,
  OnboardingRequest,
  onboardingRequestSchema,
  onlyDigits,
} from '@fauvovet/contracts';
import { AuditService } from '../audit/audit.service';
import { hashPassword } from '../auth/tokens';
import { CONFIG, Env } from '../config/env';
import { DbService } from '../db/db.service';
import { Public } from '../common/decorators';
import { Errors, parseBody } from '../common/errors';
import { RateLimiter } from '../common/rate-limit';
import type { AppRequest } from '../common/request-context';

export const SIGNUP_LIMITER = Symbol('SIGNUP_LIMITER');

@Injectable()
export class OnboardingService {
  constructor(
    private readonly db: DbService,
    private readonly audit: AuditService,
  ) {}

  /** Cria, numa única transação: usuário titular, tenant, unidade inicial, vínculo tenant_owner e tema padrão. */
  async register(input: OnboardingRequest, requestId: string) {
    const tenantId = randomUUID();
    const userId = randomUUID();
    const passwordHash = await hashPassword(input.password);
    try {
      return await this.db.withContext({ tenantId, userId }, async (c) => {
        await c.query(
          'INSERT INTO users (id, email, name, crmv, password_hash) VALUES ($1, $2, $3, $4, $5)',
          [userId, input.email, input.ownerName, input.ownerCrmv || null, passwordHash],
        );
        await c.query(
          `INSERT INTO tenants (id, trade_name, legal_name, person_type, document_number) VALUES ($1, $2, $3, $4, $5)`,
          [
            tenantId,
            input.tradeName,
            input.legalName || null,
            input.personType,
            input.document ? onlyDigits(input.document) : null,
          ],
        );
        const unit = await c.query(
          'INSERT INTO units (tenant_id, name, timezone) VALUES ($1, $2, $3) RETURNING id',
          [tenantId, input.unitName, input.timezone],
        );
        await c.query(
          `INSERT INTO memberships (tenant_id, user_id, roles) VALUES ($1, $2, ARRAY['tenant_owner'])`,
          [tenantId, userId],
        );
        await c.query(
          'INSERT INTO tenant_themes (tenant_id, primary_color, secondary_color, mode, updated_by) VALUES ($1, $2, $3, $4, $5)',
          [tenantId, DEFAULT_THEME.primary, DEFAULT_THEME.secondary, DEFAULT_THEME.mode, userId],
        );
        await this.audit.record(c, {
          tenantId,
          actorUserId: userId,
          action: 'tenant.created',
          targetType: 'tenant',
          targetId: tenantId,
          metadata: { personType: input.personType, unitId: unit.rows[0].id },
          requestId,
        });
        return { tenantId };
      });
    } catch (err) {
      if ((err as { code?: string; constraint?: string }).constraint === 'users_email_uq') {
        // Mensagem genérica: orienta login/recuperação sem confirmar dados adicionais.
        throw Errors.conflict(
          'Não foi possível concluir o cadastro com este e-mail. Se você já possui conta, faça login.',
        );
      }
      throw err;
    }
  }
}

@Controller('onboarding')
export class OnboardingController {
  constructor(
    private readonly onboarding: OnboardingService,
    @Inject(CONFIG) private readonly config: Env,
    @Inject(SIGNUP_LIMITER) private readonly limiter: RateLimiter,
  ) {}

  @Public()
  @Post()
  @HttpCode(201)
  async register(@Body() body: unknown, @Req() req: AppRequest) {
    if (!this.config.ALLOW_SELF_SIGNUP) throw Errors.signupDisabled();
    const input = parseBody(onboardingRequestSchema, body);
    this.limiter.consume(`ip:${req.ip}`);
    await this.onboarding.register(input, req.requestId);
    return { ok: true };
  }
}
