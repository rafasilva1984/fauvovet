#!/usr/bin/env node
// Desenvolvimento: MySQL local + build do frontend em modo watch + PHP servindo em /fauvovet.
// (Mesmo modelo da produção: assets compilados, sem servidor Vite; CSP estrita funciona igual.)
import { spawn } from 'node:child_process';
import { ROOT, loadEnv, log, run } from './lib.mjs';
import { startPhpServer } from './dev-server.mjs';

loadEnv();
if (!process.argv.includes('--external-db')) {
  const code = await run('node', ['scripts/local-mysql.mjs', 'start']);
  if (code !== 0) process.exit(code);
}
const port = Number(process.env.DEV_PORT ?? 8000);
const procs = [
  spawn(process.execPath, ['node_modules/vite/bin/vite.js', 'build', '--watch'], { cwd: ROOT, stdio: 'inherit' }),
  startPhpServer({ port }),
];
log(`Aplicação: http://127.0.0.1:${port}/fauvovet  (usuários fictícios: README › Começando)`);
const stop = () => procs.forEach((p) => p.kill('SIGTERM'));
process.on('SIGINT', stop);
process.on('SIGTERM', stop);
