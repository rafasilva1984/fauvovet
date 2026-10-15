/**
 * Sobe o servidor de E2E (banco *_test recriado + PHP em /fauvovet) e o derruba no teardown.
 * Não usa o `webServer` do Playwright: no WSL2 com rede "mirrored" a pré-checagem de porta fechada em
 * 127.0.0.1 trava minutos; aqui cada sondagem tem timeout de 1 s (docs/TESTING.md).
 */
import { spawn, spawnSync } from 'node:child_process';

export const E2E_PORT = process.env.E2E_PORT ?? '8100';
export const BASE = `http://127.0.0.1:${E2E_PORT}/fauvovet`;
const ROOT = process.cwd(); // o Playwright roda a partir da raiz do repositório

async function up(url: string) {
  try {
    const r = await fetch(url, { signal: AbortSignal.timeout(1000), redirect: 'manual' });
    return r.status < 500;
  } catch {
    return false;
  }
}

export default async function globalSetup() {
  if (await up(`${BASE}/login`)) throw new Error(`Já existe um servidor em ${BASE}. Encerre-o antes do E2E.`);
  const build = spawnSync(process.execPath, ['node_modules/vite/bin/vite.js', 'build'], {
    cwd: ROOT,
    stdio: 'inherit',
  });
  if (build.status !== 0) throw new Error('vite build falhou');
  const server = spawn(process.execPath, ['scripts/e2e-server.mjs'], {
    cwd: ROOT,
    detached: process.platform !== 'win32',
    stdio: 'inherit',
    env: { ...process.env, E2E_PORT },
  });
  const deadline = Date.now() + 120_000;
  while (!(await up(`${BASE}/login`))) {
    if (server.exitCode !== null) throw new Error(`Servidor de E2E encerrou (código ${server.exitCode}).`);
    if (Date.now() > deadline) throw new Error('Servidor de E2E não respondeu em 120 s.');
    await new Promise((r) => setTimeout(r, 300));
  }
  return () => {
    try {
      if (server.pid) process.kill(process.platform === 'win32' ? server.pid : -server.pid, 'SIGTERM');
    } catch {
      /* já encerrado */
    }
  };
}
