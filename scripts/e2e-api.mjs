#!/usr/bin/env node
// Servidor de API para E2E: recria o banco *_test, aplica migrações e seed fictício e sobe a API
// compilada (apps/api/dist) com NODE_ENV=test (guardas de banco local *_test ativas).
import { createRequire } from 'node:module';
import { join } from 'node:path';
import { spawn } from 'node:child_process';
import pg from 'pg';
import { ROOT, loadEnv, log } from './lib.mjs';

loadEnv();
const require = createRequire(import.meta.url);
const dist = join(ROOT, 'apps', 'api', 'dist');
const { migrate, roleFromUrl } = require(join(dist, 'db', 'migrate.js'));
const { seed } = require(join(dist, 'db', 'seed.js'));
const { assertTestDatabase } = require(join(dist, 'config', 'env.js'));

const appUrl = process.env.TEST_DATABASE_URL;
const migrationUrl = process.env.TEST_MIGRATION_DATABASE_URL;
assertTestDatabase(appUrl);
assertTestDatabase(migrationUrl);

const c = new pg.Client({ connectionString: migrationUrl });
await c.connect();
await c.query('DROP SCHEMA IF EXISTS app CASCADE');
await c.query('DROP SCHEMA IF EXISTS public CASCADE');
await c.query('CREATE SCHEMA public');
await c.end();
await migrate({ migrationUrl, appRole: roleFromUrl(appUrl), log: () => undefined });
await seed(migrationUrl, process.env.E2E_PASSWORD ?? 'fauvovet-e2e-2026', () => undefined);
log('Banco de teste recriado para E2E.');

const port = process.env.E2E_API_PORT ?? '4100';
const child = spawn(process.execPath, [join(dist, 'main.js')], {
  stdio: 'inherit',
  env: {
    ...process.env,
    NODE_ENV: 'test',
    DATABASE_URL: appUrl,
    API_PORT: port,
    PUBLIC_ORIGIN:
      process.env.E2E_PUBLIC_ORIGIN ?? `http://127.0.0.1:${process.env.E2E_WEB_PORT ?? '3100'}`,
    ALLOW_SELF_SIGNUP: 'true',
    LOGIN_RATE_LIMIT_PER_15MIN: '1000',
    SIGNUP_RATE_LIMIT_PER_HOUR: '1000',
  },
});
for (const sig of ['SIGINT', 'SIGTERM']) process.on(sig, () => child.kill(sig));
child.on('exit', (code) => process.exit(code ?? 1));
