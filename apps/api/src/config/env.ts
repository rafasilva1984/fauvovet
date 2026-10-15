import { z } from 'zod';

const bool = z.enum(['true', 'false', '1', '0']).transform((v) => v === 'true' || v === '1');

const envSchema = z.object({
  NODE_ENV: z.enum(['development', 'test', 'production']).default('development'),
  BASE_PATH: z
    .string()
    .default('/fauvovet')
    .refine(
      (v) => v === '' || /^\/[a-z0-9][a-z0-9-_/]*[a-z0-9]$/i.test(v),
      'BASE_PATH deve ser vazio ou começar com / e não terminar com /',
    ),
  API_PORT: z.coerce.number().int().positive().default(4000),
  API_HOST: z.string().default('127.0.0.1'),
  PUBLIC_ORIGIN: z.url().default('http://localhost:3000'),
  DATABASE_URL: z.string().min(1, 'DATABASE_URL é obrigatório'),
  DB_POOL_MAX: z.coerce.number().int().positive().default(10),
  SESSION_TTL_HOURS: z.coerce
    .number()
    .positive()
    .max(24 * 30)
    .default(12),
  COOKIE_SECURE: bool.default(false),
  TRUST_PROXY: bool.default(false),
  ALLOW_SELF_SIGNUP: bool.default(false),
  LOGIN_MAX_FAILURES: z.coerce.number().int().positive().default(10),
  LOGIN_LOCK_MINUTES: z.coerce.number().int().positive().default(15),
  LOGIN_RATE_LIMIT_PER_15MIN: z.coerce.number().int().positive().default(20),
  SIGNUP_RATE_LIMIT_PER_HOUR: z.coerce.number().int().positive().default(10),
});

export type Env = z.infer<typeof envSchema>;

export function loadConfig(source: NodeJS.ProcessEnv = process.env): Env {
  const parsed = envSchema.safeParse(source);
  if (!parsed.success) {
    const issues = parsed.error.issues.map((i) => `${i.path.join('.')}: ${i.message}`).join('; ');
    throw new Error(`Configuração inválida: ${issues}`);
  }
  const env = parsed.data;
  if (env.NODE_ENV === 'production' && !env.COOKIE_SECURE) {
    throw new Error('Em produção COOKIE_SECURE deve ser true (HTTPS obrigatório).');
  }
  if (env.NODE_ENV === 'test') assertTestDatabase(env.DATABASE_URL);
  return env;
}

/** Guarda contra rodar testes apontando para banco que não seja local e *_test. */
export function assertTestDatabase(url: string): void {
  const u = new URL(url);
  const db = u.pathname.slice(1);
  if (!db.endsWith('_test'))
    throw new Error(`Testes recusados: banco "${db}" não termina com _test.`);
  if (!['127.0.0.1', 'localhost', '::1', 'postgres'].includes(u.hostname)) {
    throw new Error(`Testes recusados: host "${u.hostname}" não é local.`);
  }
}

export const CONFIG = Symbol('CONFIG');
