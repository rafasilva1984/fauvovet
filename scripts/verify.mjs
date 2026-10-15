#!/usr/bin/env node
// Gate obrigatório pré-push/pré-deploy (CLAUDE.md §15.2). Uso: pnpm verify [--e2e] [--skip-db]
// Executa em sequência e para na primeira falha, com resumo PASS/FAIL/NOT RUN.
import { ROOT, loadEnv, log, run } from './lib.mjs';
import { createConnection } from 'node:net';

loadEnv();
const args = new Set(process.argv.slice(2));
const pnpm = 'pnpm';

function dbReachable() {
  const url = process.env.TEST_DATABASE_URL;
  if (!url) return Promise.resolve(false);
  const u = new URL(url);
  return new Promise((res) => {
    const s = createConnection({ host: u.hostname, port: Number(u.port || 5432) });
    s.setTimeout(1500);
    s.on('connect', () => (s.destroy(), res(true)));
    s.on('error', () => res(false));
    s.on('timeout', () => (s.destroy(), res(false)));
  });
}

const steps = [
  ['Contratos (build)', [pnpm, ['--filter', '@fauvovet/contracts', 'build']]],
  ['Formatação (prettier)', [pnpm, ['format:check']]],
  ['Lint (eslint)', [pnpm, ['lint']]],
  ['Tipos (tsc)', [pnpm, ['typecheck']]],
  ['Testes unitários', [pnpm, ['test']]],
  [
    'Integração + isolamento multi-tenant (PostgreSQL real)',
    [pnpm, ['test:integration']],
    { db: true },
  ],
  ['Documentação HTML + checkpoint', [pnpm, ['docs:validate']]],
  ['Varredura de segredos', [pnpm, ['security:secrets']]],
  ['Build de produção', [pnpm, ['build']]],
  ['E2E (Playwright desktop + mobile)', [pnpm, ['test:e2e']], { e2e: true, db: true }],
];

const results = [];
let failed = false;
const hasDb = args.has('--skip-db') ? false : await dbReachable();
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
    results.push([
      name,
      'FAIL',
      'PostgreSQL de teste inacessível — rode `pnpm db:start` (ou Docker)',
    ]);
    failed = true;
    continue;
  }
  log(`▶ ${name}`);
  const t = Date.now();
  const code = await run(cmd, cmdArgs, { cwd: ROOT });
  const secs = ((Date.now() - t) / 1000).toFixed(1);
  results.push([name, code === 0 ? 'PASS' : 'FAIL', `${secs}s`]);
  if (code !== 0) failed = true;
}

console.log('\n================ pnpm verify ================');
for (const [name, status, note] of results) console.log(`${status.padEnd(8)} ${name}  (${note})`);
console.log('=============================================');
process.exit(failed ? 1 : 0);
