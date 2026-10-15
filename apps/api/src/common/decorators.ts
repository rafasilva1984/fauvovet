import { createParamDecorator, ExecutionContext, SetMetadata } from '@nestjs/common';
import type { Permission } from '@fauvovet/contracts';
import type { AppRequest, AuthContext } from './request-context';

export const IS_PUBLIC = 'fv:public';
export const REQUIRED_PERMISSION = 'fv:permission';
export const REQUIRES_CONTEXT = 'fv:requires-context';

/** Rota sem sessão (login, onboarding, health). */
export const Public = () => SetMetadata(IS_PUBLIC, true);
/** Exige tenant ativo e a permissão indicada (implica contexto). */
export const RequirePermission = (permission: Permission) =>
  SetMetadata(REQUIRED_PERMISSION, permission);

export const Auth = createParamDecorator((_data: unknown, ctx: ExecutionContext): AuthContext => {
  const req = ctx.switchToHttp().getRequest<AppRequest>();
  if (!req.auth) throw new Error('Auth ausente — rota deveria estar protegida pelo SessionGuard.');
  return req.auth;
});

export const RequestId = createParamDecorator(
  (_data: unknown, ctx: ExecutionContext): string =>
    ctx.switchToHttp().getRequest<AppRequest>().requestId,
);
