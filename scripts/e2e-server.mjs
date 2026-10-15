#!/usr/bin/env node
// Servidor de E2E: recria o banco *_test com seed fictício e serve a aplicação em /fauvovet (PHP embutido).
// Uso interno do Playwright (e2e/global-setup.ts). Recusa qualquer banco que não termine com _test.
import { spawnSync } from 'node:child_process';
import { ROOT, loadEnv, log, phpEnv } from './lib.mjs';
import { startPhpServer } from './dev-server.mjs';

loadEnv();
const port = Number(process.env.E2E_PORT ?? 8100);
const db = process.env.DB_TEST_DATABASE ?? 'fauvovet_test';
if (!/_test$/.test(db)) throw new Error(`E2E recusado: banco "${db}" não termina com _test.`);

export const E2E_ENV = {
  APP_ENV: 'local',
  APP_DEBUG: 'false',
  APP_URL: `http://127.0.0.1:${port}/fauvovet`,
  DB_DATABASE: db,
  SESSION_DRIVER: 'database',
  SESSION_SECURE_COOKIE: 'false',
  // Sem persistência de rate limit entre requisições do E2E (o limite é coberto pelos testes PHP).
  CACHE_STORE: 'array',
  SEED_PASSWORD: process.env.E2E_PASSWORD ?? 'fauvovet-e2e-2026',
  FAUVOVET_ALLOW_SELF_SIGNUP: 'true',
  DEPLOY_TOOLS_ENABLED: 'false',
  LOG_LEVEL: 'warning',
};

const fresh = spawnSync(process.env.PHP_BINARY ?? 'php', ['artisan', 'migrate:fresh', '--seed', '--force'], {
  cwd: ROOT,
  env: phpEnv(E2E_ENV),
  encoding: 'utf8',
});
if (fresh.status !== 0) {
  console.error(fresh.stdout, fresh.stderr);
  process.exit(1);
}
log(`Banco ${db} recriado para E2E. Servindo em http://127.0.0.1:${port}/fauvovet`);
const child = startPhpServer({ port, env: E2E_ENV, stdio: 'ignore' });
for (const sig of ['SIGINT', 'SIGTERM']) process.on(sig, () => (child.kill(sig), process.exit(0)));
child.on('exit', (code) => process.exit(code ?? 1));
