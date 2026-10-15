#!/usr/bin/env node
// Desenvolvimento da API: compila uma vez, depois tsc --watch + node --watch (reinicia ao recompilar).
import { spawn, spawnSync } from 'node:child_process';
import { join } from 'node:path';
import { ROOT } from './lib.mjs';

const apiDir = join(ROOT, 'apps', 'api');
const shell = process.platform === 'win32';
const tsc = join(ROOT, 'node_modules', '.bin', shell ? 'tsc.cmd' : 'tsc');
const first = spawnSync(tsc, ['-p', 'tsconfig.build.json'], {
  cwd: apiDir,
  stdio: 'inherit',
  shell,
});
if (first.status !== 0) process.exit(first.status ?? 1);
const children = [
  spawn(tsc, ['-p', 'tsconfig.build.json', '--watch', '--preserveWatchOutput'], {
    cwd: apiDir,
    stdio: 'inherit',
    shell,
  }),
  spawn(
    process.execPath,
    ['--enable-source-maps', '--env-file-if-exists=../../.env', '--watch', 'dist/main.js'],
    { cwd: apiDir, stdio: 'inherit' },
  ),
];
const stop = () => children.forEach((c) => c.kill());
process.on('SIGINT', stop);
process.on('SIGTERM', stop);
