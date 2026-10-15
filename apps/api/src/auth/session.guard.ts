import { CanActivate, ExecutionContext, Inject, Injectable } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { Permission, hasPermission } from '@fauvovet/contracts';
import { CONFIG, Env } from '../config/env';
import { IS_PUBLIC, REQUIRED_PERMISSION } from '../common/decorators';
import { Errors } from '../common/errors';
import type { AppRequest } from '../common/request-context';
import { AuthService } from './auth.service';
import { SESSION_COOKIE, safeEqual } from './tokens';

const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

/**
 * Guard global (negar por padrão):
 *  1. Rotas não marcadas com @Public() exigem sessão válida (cookie HttpOnly).
 *  2. Métodos mutáveis exigem cabeçalho X-CSRF-Token igual ao token da sessão e Origin confiável.
 *  3. @RequirePermission() exige contexto de tenant ativo e permissão do papel no vínculo.
 */
@Injectable()
export class SessionGuard implements CanActivate {
  constructor(
    private readonly reflector: Reflector,
    private readonly auth: AuthService,
    @Inject(CONFIG) private readonly config: Env,
  ) {}

  async canActivate(ctx: ExecutionContext): Promise<boolean> {
    const req = ctx.switchToHttp().getRequest<AppRequest>();
    const targets = [ctx.getHandler(), ctx.getClass()];
    const unsafe = !SAFE_METHODS.has(req.method);

    if (unsafe) this.checkOrigin(req);

    if (this.reflector.getAllAndOverride<boolean>(IS_PUBLIC, targets)) return true;

    const token: unknown = req.cookies?.[SESSION_COOKIE];
    if (typeof token !== 'string' || token.length < 20 || token.length > 100)
      throw Errors.unauthenticated();
    const auth = await this.auth.resolveSession(token);
    if (!auth) throw Errors.unauthenticated();
    req.auth = auth;

    if (unsafe) {
      const header = req.header('x-csrf-token');
      if (!header || !safeEqual(header, auth.csrfToken)) throw Errors.csrf();
    }

    const permission = this.reflector.getAllAndOverride<Permission | undefined>(
      REQUIRED_PERMISSION,
      targets,
    );
    if (permission) {
      if (!auth.tenantId) throw Errors.noContext();
      if (!hasPermission(auth.roles, permission)) throw Errors.forbidden();
    }
    return true;
  }

  /** Requisições mutáveis com Origin de outro site são recusadas (defesa adicional ao SameSite). */
  private checkOrigin(req: AppRequest): void {
    const origin = req.header('origin');
    if (!origin) return; // clientes não-navegador (CLI/testes) não enviam Origin; CSRF token continua exigido
    if (origin !== new URL(this.config.PUBLIC_ORIGIN).origin) throw Errors.csrf();
  }
}
