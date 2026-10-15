// Utilitários portáveis (WSL, Git Bash, Linux, macOS) usados pelos scripts da raiz.
import { existsSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');

/** Carrega .env da raiz (se existir) sem sobrescrever variáveis já definidas. */
export function loadEnv() {
  const file = join(ROOT, '.env');
  if (existsSync(file)) process.loadEnvFile(file);
}

export function log(msg) {
  console.log(`[fauvovet] ${msg}`);
}

/** Executa um comando herdando stdio; resolve com o código de saída. */
export function run(cmd, args, opts = {}) {
  return new Promise((resolvePromise) => {
    const child = spawn(cmd, args, {
      cwd: ROOT,
      stdio: 'inherit',
      shell: process.platform === 'win32',
      ...opts,
    });
    child.on('exit', (code) => resolvePromise(code ?? 1));
    child.on('error', () => resolvePromise(127));
  });
}
