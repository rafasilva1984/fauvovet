#!/usr/bin/env node
// Gate obrigatório pré-push/pré-deploy (CLAUDE.md §15.2). Uso: pnpm verify [--e2e]
import { createConnection } from 'node:net';
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { ROOT, log, phpEnv, run } from './lib.mjs';

// Lê só DB_HOST/DB_PORT do .env, sem injetar o .env no ambiente dos subprocessos
// (os testes precisam usar o banco *_test definido em phpunit.xml).
const envText = existsSync(join(ROOT, '.env')) ? readFileSync(join(ROOT, '.env'), 'utf8') : '';
const envVar = (k, d) => envText.match(new RegExp(`^${k}=(.*)$`, 'm'))?.[1]?.trim() || d;
const args = new Set(process.argv.slice(2));

function dbReachable() {
  return new Promise((res) => {
    const s = createConnection({
      host: process.env.DB_HOST ?? '127.0.0.1',
      port: Number(process.env.DB_PORT ?? 33079),
    });
    s.setTimeout(1000, () => (s.destroy(), res(false)));
    s.on('connect', () => (s.destroy(), res(true)));
    s.on('error', () => res(false));
  });
}

const php = (...a) => ['node', ['scripts/php.mjs', ...a]];
const steps = [
  ['Formatação PHP (pint)', php('vendor/bin/pint', '--test')],
  ['Formatação web/docs (prettier)', ['pnpm', ['exec', 'prettier', '--check', '.']]],
  ['Análise estática (PHPStan nível 6)', php('vendor/bin/phpstan', 'analyse', '--memory-limit=1G', '--no-progress')],
  ['Testes PHP (unidade + integração + isolamento, MySQL real)', php('artisan', 'test', '--compact'), { db: true }],
  ['Auditoria de dependências (composer audit)', ['composer', ['audit', '--no-dev', '--locked']]],
  ['Documentação HTML + checkpoint', ['node', ['scripts/docs-validate.mjs']]],
  ['Varredura de segredos', ['node', ['scripts/secrets-scan.mjs']]],
  ['Build do frontend (vite)', ['node', ['node_modules/vite/bin/vite.js', 'build']]],
  [
    'E2E (Playwright desktop + celular, servidor em /fauvovet)',
    ['pnpm', ['exec', 'playwright', 'test']],
    { e2e: true, db: true },
  ],
];

const results = [];
let failed = false;
const hasDb = await dbReachable();
for (const [name, [cmd, cmdArgs], opts = {}] of steps) {
  if (failed) {
    results.push([name, 'NOT RUN', 'etapa anterior falhou']);
    continue;
  }
  if (opts.e2e && !args.has('--e2e')) {
    results.push([name, 'NOT RUN', 'use pnpm verify --e2e']);
    continue;
  }
  if (opts.db && !hasDb) {
    results.push([name, 'FAIL', 'MySQL local inacessível — rode `pnpm db:start`']);
    failed = true;
    continue;
  }
  log(`▶ ${name}`);
  const t = Date.now();
  const code = await run(cmd, cmdArgs, { cwd: ROOT, env: phpEnv() });
  results.push([name, code === 0 ? 'PASS' : 'FAIL', `${((Date.now() - t) / 1000).toFixed(1)}s`]);
  if (code !== 0) failed = true;
}
console.log('\n================ pnpm verify ================');
for (const [name, status, note] of results) console.log(`${status.padEnd(8)} ${name}  (${note})`);
console.log('=============================================');
process.exit(failed ? 1 : 0);
