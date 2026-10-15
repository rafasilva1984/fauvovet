// Utilitários portáveis usados pelos scripts da raiz.
import { existsSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { delimiter, dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
export const DATA = join(ROOT, '.data');
export const PHP_CONF_D = join(DATA, 'php', 'conf.d');

/** Carrega .env da raiz (se existir) sem sobrescrever variáveis já definidas. */
export function loadEnv() {
  const file = join(ROOT, '.env');
  if (existsSync(file)) process.loadEnvFile(file);
}

export function log(msg) {
  console.log(`[fauvovet] ${msg}`);
}

/**
 * Ambiente para executar o PHP local. Se `pnpm setup:local` extraiu extensões (ex.: pdo_mysql) em
 * .data/php/conf.d, elas são acrescentadas ao diretório de .ini padrão (prefixo vazio = mantém o padrão).
 */
export function phpEnv(extra = {}) {
  const env = { ...process.env, ...extra };
  if (existsSync(PHP_CONF_D)) {
    const current = process.env.PHP_INI_SCAN_DIR;
    env.PHP_INI_SCAN_DIR = current ? `${current}${delimiter}${PHP_CONF_D}` : `${delimiter}${PHP_CONF_D}`;
  }
  return env;
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
