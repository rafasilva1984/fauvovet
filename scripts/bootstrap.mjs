#!/usr/bin/env node
// Prepara o ambiente local de forma idempotente. Nunca sobrescreve um .env existente.
// Uso: pnpm bootstrap                (MySQL local Percona 5.7 sem root — Linux x86_64)
//      pnpm bootstrap --external-db  (usa MySQL/MariaDB já existente conforme DB_* do .env)
import { copyFileSync, existsSync, readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { join } from 'node:path';
import { ROOT, log, phpEnv, run } from './lib.mjs';

const external = process.argv.includes('--external-db');
const version = (cmd, args = ['--version']) => {
  const r = spawnSync(cmd, args, { encoding: 'utf8', shell: process.platform === 'win32', env: phpEnv() });
  return r.status === 0 ? r.stdout.trim().split('\n')[0] : 'não encontrado';
};

log('Diagnóstico do ambiente:');
console.log(
  `  SO: ${process.platform}-${process.arch}${process.env.WSL_DISTRO_NAME ? ` (WSL: ${process.env.WSL_DISTRO_NAME})` : ''}${process.env.MSYSTEM ? ` (Git Bash: ${process.env.MSYSTEM})` : ''}`,
);
console.log(`  node: ${process.version} · pnpm: ${version('pnpm')}`);
console.log(`  php: ${version('php')} · composer: ${version('composer')}`);
console.log(`  git: ${version('git')}`);

const php = spawnSync('php', ['-r', 'echo PHP_VERSION_ID;'], { encoding: 'utf8' });
if (php.status !== 0 || Number(php.stdout) < 80300) {
  console.error('PHP 8.3+ é necessário (a hospedagem usa PHP 8.x). Veja README › Pré-requisitos.');
  process.exit(1);
}

const envFile = join(ROOT, '.env');
const created = !existsSync(envFile);
if (created) {
  copyFileSync(join(ROOT, '.env.example'), envFile);
  log('.env criado a partir de .env.example (valores fictícios de desenvolvimento).');
} else log('.env já existe — mantido sem alterações.');

const steps = [
  ['composer', ['install', '--no-interaction']],
  ['pnpm', ['install', '--frozen-lockfile']],
  ...(/^APP_KEY=base64:/m.test(readFileSync(envFile, 'utf8'))
    ? []
    : [['node', ['scripts/php.mjs', 'artisan', 'key:generate', '--no-interaction']]]),
  ...(external ? [['node', ['scripts/local-mysql.mjs', 'php-ext']]] : [['node', ['scripts/local-mysql.mjs', 'setup']]]),
  ['node', ['scripts/php.mjs', 'artisan', 'migrate', '--force']],
  ['node', ['scripts/php.mjs', 'artisan', 'db:seed', '--force']],
  ['node', ['node_modules/vite/bin/vite.js', 'build']],
];
for (const [cmd, args] of steps) {
  log(`▶ ${cmd} ${args.join(' ')}`);
  const code = await run(cmd, args, { env: phpEnv() });
  if (code !== 0) {
    console.error(`Falhou: ${cmd} ${args.join(' ')} (código ${code}). Veja docs/TESTING.md › Troubleshooting.`);
    process.exit(code);
  }
}
log('Ambiente pronto. Próximo passo: `pnpm dev` e abra http://127.0.0.1:8000/fauvovet');
