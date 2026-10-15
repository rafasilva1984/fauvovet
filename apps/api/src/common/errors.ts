import {
  ArgumentsHost,
  Catch,
  ExceptionFilter,
  HttpException,
  HttpStatus,
  Logger,
} from '@nestjs/common';
import type { Request, Response } from 'express';
import { ZodType } from 'zod';

export class AppError extends HttpException {
  constructor(
    status: number,
    readonly code: string,
    message: string,
    readonly details?: unknown,
  ) {
    super({ code, message, details }, status);
  }
}

export const Errors = {
  validation: (details: unknown) =>
    new AppError(HttpStatus.UNPROCESSABLE_ENTITY, 'validation_error', 'Dados inválidos.', details),
  unauthenticated: () =>
    new AppError(HttpStatus.UNAUTHORIZED, 'unauthenticated', 'Sessão inválida ou expirada.'),
  invalidCredentials: () =>
    new AppError(HttpStatus.UNAUTHORIZED, 'invalid_credentials', 'E-mail ou senha incorretos.'),
  locked: () =>
    new AppError(
      HttpStatus.TOO_MANY_REQUESTS,
      'account_locked',
      'Muitas tentativas. Aguarde alguns minutos e tente novamente.',
    ),
  rateLimited: () =>
    new AppError(
      HttpStatus.TOO_MANY_REQUESTS,
      'rate_limited',
      'Muitas requisições. Tente novamente em instantes.',
    ),
  csrf: () =>
    new AppError(
      HttpStatus.FORBIDDEN,
      'csrf_failed',
      'Falha de verificação de segurança (CSRF). Recarregue a página.',
    ),
  noContext: () =>
    new AppError(
      HttpStatus.CONFLICT,
      'context_required',
      'Selecione uma clínica/unidade para continuar.',
    ),
  forbidden: () =>
    new AppError(HttpStatus.FORBIDDEN, 'forbidden', 'Você não tem permissão para esta ação.'),
  notFound: () => new AppError(HttpStatus.NOT_FOUND, 'not_found', 'Recurso não encontrado.'),
  conflict: (message: string) => new AppError(HttpStatus.CONFLICT, 'conflict', message),
  signupDisabled: () =>
    new AppError(
      HttpStatus.FORBIDDEN,
      'signup_disabled',
      'Cadastro público desabilitado nesta instalação.',
    ),
};

export function parseBody<T>(schema: ZodType<T>, body: unknown): T {
  const r = schema.safeParse(body);
  if (!r.success) {
    throw Errors.validation(
      r.error.issues.map((i) => ({ path: i.path.join('.'), message: i.message })),
    );
  }
  return r.data;
}

/** Formato de erro padronizado: { error: { code, message, details?, requestId } }. Nunca expõe stack. */
@Catch()
export class AllExceptionsFilter implements ExceptionFilter {
  private readonly logger = new Logger('HTTP');

  catch(exception: unknown, host: ArgumentsHost): void {
    const ctx = host.switchToHttp();
    const res = ctx.getResponse<Response>();
    const req = ctx.getRequest<Request>();
    const requestId = (req as Request & { requestId?: string }).requestId;
    if (exception instanceof AppError) {
      const body = exception.getResponse() as { code: string; message: string; details?: unknown };
      res.status(exception.getStatus()).json({ error: { ...body, requestId } });
      return;
    }
    if (exception instanceof HttpException) {
      const payload = exception.getResponse();
      if (typeof payload === 'object' && payload !== null && 'status' in payload) {
        res.status(exception.getStatus()).json(payload); // ex.: healthcheck degradado
        return;
      }
      const status = exception.getStatus();
      const code =
        status === 404 ? 'not_found' : status === 413 ? 'payload_too_large' : 'http_error';
      const message = status === 404 ? 'Rota não encontrada.' : exception.message;
      res.status(status).json({ error: { code, message, requestId } });
      return;
    }
    // Erro inesperado: registrar apenas tipo/mensagem (sem corpo da requisição ou dados clínicos).
    this.logger.error(
      `requestId=${requestId} ${(exception as Error)?.name}: ${(exception as Error)?.message}`,
    );
    res.status(500).json({
      error: { code: 'internal_error', message: 'Erro interno. Tente novamente.', requestId },
    });
  }
}
