#!/usr/bin/env node
// Executa o Next.js com as variáveis do .env da raiz (BASE_PATH, WEB_PORT, API_INTERNAL_URL).
// Uso (via pnpm --filter @fauvovet/web): dev | build | start
import { spawn } from 'node:child_process';
import { join } from 'node:path';
import { ROOT, loadEnv } from './lib.mjs';

loadEnv();
const mode = process.argv[2] ?? 'dev';
const webDir = join(ROOT, 'apps', 'web');
const port = process.env.WEB_PORT ?? '3000';
const args =
  mode === 'build'
    ? ['build']
    : [mode, '--port', port, '--hostname', process.env.WEB_HOST ?? 'localhost'];
const nextBin = join(webDir, 'node_modules', 'next', 'dist', 'bin', 'next');
// NODE_ENV do .env (development) não deve vazar para build/start: o Next exige production nesses modos.
const env = { ...process.env, NODE_ENV: mode === 'dev' ? 'development' : 'production' };
const child = spawn(process.execPath, [nextBin, ...args], { cwd: webDir, stdio: 'inherit', env });
const forward = (sig) => child.kill(sig);
process.on('SIGINT', forward);
process.on('SIGTERM', forward);
child.on('exit', (code) => process.exit(code ?? 1));
