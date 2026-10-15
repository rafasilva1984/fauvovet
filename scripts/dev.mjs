#!/usr/bin/env node
// Sobe PostgreSQL local (se embutido), API e web em paralelo. Ctrl+C encerra tudo.
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { ROOT, loadEnv, log, run } from './lib.mjs';

loadEnv();
const shell = process.platform === 'win32';
if (!process.argv.includes('--external-db')) {
  const code = await run('node', ['scripts/db-local.mjs', 'start']);
  if (code !== 0) process.exit(code);
}
await run('pnpm', ['--filter', '@fauvovet/contracts', 'build']);
const base = process.env.BASE_PATH ?? '/fauvovet';
const procs = [
  [
    'api',
    spawn('pnpm', ['--filter', '@fauvovet/api', 'dev'], { cwd: ROOT, stdio: 'inherit', shell }),
  ],
];
if (existsSync(join(ROOT, 'apps', 'web', 'package.json'))) {
  procs.push([
    'web',
    spawn('pnpm', ['--filter', '@fauvovet/web', 'dev'], { cwd: ROOT, stdio: 'inherit', shell }),
  ]);
}
log(
  `Web: http://localhost:${process.env.WEB_PORT ?? 3000}${base}  ·  API: http://127.0.0.1:${process.env.API_PORT ?? 4000}${base}/api/v1/health`,
);
const stop = () => procs.forEach(([, p]) => p.kill('SIGINT'));
process.on('SIGINT', stop);
process.on('SIGTERM', stop);
for (const [name, p] of procs) p.on('exit', (code) => log(`${name} encerrado (código ${code})`));
