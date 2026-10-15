import { createHash, randomUUID } from 'node:crypto';
import type { NextFunction, Request, Response } from 'express';

export interface AuthContext {
  sessionId: string;
  userId: string;
  userName: string;
  userEmail: string;
  csrfToken: string;
  tenantId: string | null;
  tenantName: string | null;
  membershipId: string | null;
  unitId: string | null;
  roles: string[];
}

export type AppRequest = Request & { requestId: string; auth?: AuthContext };

/** Pseudônimo estável do tenant para logs (não expõe o UUID real). */
export function pseudonymize(id: string | null | undefined): string {
  if (!id) return '-';
  return createHash('sha256').update(id).digest('hex').slice(0, 12);
}

/** Correlation/request ID + log estruturado de acesso (sem query string, corpo ou cookies). */
export function requestContextMiddleware(req: Request, res: Response, next: NextFunction): void {
  const incoming = req.header('x-request-id');
  const requestId = incoming && /^[A-Za-z0-9-]{8,64}$/.test(incoming) ? incoming : randomUUID();
  (req as AppRequest).requestId = requestId;
  res.setHeader('x-request-id', requestId);
  const start = process.hrtime.bigint();
  res.on('finish', () => {
    if (process.env.NODE_ENV === 'test' || process.env.ACCESS_LOG === 'false') return;
    const ms = Number(process.hrtime.bigint() - start) / 1e6;
    const auth = (req as AppRequest).auth;
    console.log(
      JSON.stringify({
        level: 'info',
        msg: 'http',
        requestId,
        method: req.method,
        path: req.path,
        status: res.statusCode,
        ms: Math.round(ms * 10) / 10,
        tenant: pseudonymize(auth?.tenantId),
      }),
    );
  });
  next();
}
