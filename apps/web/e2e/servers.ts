/**
 * Sobe API e web de E2E em processos próprios (grupo de processos encerrado no teardown).
 * Substitui o `webServer` do Playwright porque a pré-checagem de porta dele fica pendurada por minutos
 * no WSL2 com `networkingMode=mirrored` (conexão a porta fechada em 127.0.0.1 não é recusada).
 * Aqui cada sondagem tem timeout de 1 s.
 */
import { spawn, type ChildProcess } from 'node:child_process';
import { join } from 'node:path';

export const E2E = {
  host: process.env.E2E_HOST ?? '127.0.0.1',
  webPort: process.env.E2E_WEB_PORT ?? '3100',
  apiPort: process.env.E2E_API_PORT ?? '4100',
  basePath: process.env.BASE_PATH ?? '/fauvovet',
};
export const WEB_ORIGIN = `http://${E2E.host}:${E2E.webPort}`;

const ROOT = join(__dirname, '..', '..', '..');

async function isUp(url: string): Promise<boolean> {
  try {
    const r = await fetch(url, { signal: AbortSignal.timeout(1000) });
    return r.status < 500;
  } catch {
    return false;
  }
}

async function waitFor(url: string, child: ChildProcess, timeoutMs: number, name: string) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    if (child.exitCode !== null)
      throw new Error(`${name} encerrou com código ${child.exitCode} antes de ficar pronto.`);
    if (await isUp(url)) return;
    await new Promise((r) => setTimeout(r, 300));
  }
  throw new Error(`${name} não respondeu em ${url} após ${timeoutMs / 1000}s.`);
}

function start(name: string, cmd: string, env: Record<string, string>): ChildProcess {
  const child = spawn(cmd, {
    cwd: join(ROOT, 'apps', 'web'),
    shell: true,
    detached: process.platform !== 'win32',
    stdio: ['ignore', 'pipe', 'pipe'],
    env: { ...process.env, ...env },
  });
  const prefix = (d: Buffer) =>
    d
      .toString()
      .split('\n')
      .filter(Boolean)
      .forEach((l) => process.stdout.write(`[${name}] ${l}\n`));
  child.stdout?.on('data', prefix);
  child.stderr?.on('data', prefix);
  return child;
}

export async function startServers(): Promise<ChildProcess[]> {
  const common = {
    E2E_WEB_PORT: E2E.webPort,
    E2E_API_PORT: E2E.apiPort,
    E2E_PUBLIC_ORIGIN: WEB_ORIGIN,
  };
  for (const url of [
    `http://${E2E.host}:${E2E.apiPort}${E2E.basePath}/api/v1/health`,
    `${WEB_ORIGIN}${E2E.basePath}/login`,
  ]) {
    if (await isUp(url))
      throw new Error(`Já existe um servidor em ${url}. Encerre-o antes do E2E.`);
  }
  const api = start(
    'api',
    'node ../../node_modules/typescript/bin/tsc -p ../api/tsconfig.build.json && node ../../scripts/e2e-api.mjs',
    common,
  );
  const web = start(
    'web',
    'node ../../scripts/web-run.mjs build && node ../../scripts/web-run.mjs start',
    {
      ...common,
      NEXT_DIST_DIR: '.next-e2e',
      WEB_PORT: E2E.webPort,
      WEB_HOST: E2E.host,
      API_INTERNAL_URL: `http://${E2E.host}:${E2E.apiPort}`,
    },
  );
  const children = [api, web];
  try {
    await waitFor(
      `http://${E2E.host}:${E2E.apiPort}${E2E.basePath}/api/v1/health`,
      api,
      120_000,
      'API',
    );
    await waitFor(`${WEB_ORIGIN}${E2E.basePath}/login`, web, 180_000, 'Web');
  } catch (err) {
    stopServers(children);
    throw err;
  }
  return children;
}

export function stopServers(children: ChildProcess[]) {
  for (const c of children) {
    if (c.exitCode !== null || !c.pid) continue;
    try {
      if (process.platform === 'win32') c.kill();
      else process.kill(-c.pid, 'SIGTERM'); // grupo inteiro (shell + node + next)
    } catch {
      /* já encerrado */
    }
  }
}
