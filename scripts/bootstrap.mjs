#!/usr/bin/env node
// Prepara o ambiente local de forma idempotente. Nunca sobrescreve um .env existente.
// Uso: pnpm bootstrap            (PostgreSQL embutido, sem Docker/root)
//      pnpm bootstrap --external-db   (usa PostgreSQL já existente, ex.: Docker ou serviço do sistema)
import { copyFileSync, existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { join } from 'node:path';
import { ROOT, log, run } from './lib.mjs';

const useExternalDb = process.argv.includes('--external-db');

function version(cmd, args = ['--version']) {
  const r = spawnSync(cmd, args, { encoding: 'utf8', shell: process.platform === 'win32' });
  return r.status === 0 ? r.stdout.trim().split('\n')[0] : 'não encontrado';
}

log('Diagnóstico do ambiente:');
console.log(
  `  SO: ${process.platform}-${process.arch}${process.env.WSL_DISTRO_NAME ? ` (WSL: ${process.env.WSL_DISTRO_NAME})` : ''}${process.env.MSYSTEM ? ` (Git Bash: ${process.env.MSYSTEM})` : ''}`,
);
console.log(`  node: ${process.version}`);
console.log(`  pnpm: ${version('pnpm')}`);
console.log(`  git: ${version('git')}`);
console.log(`  docker: ${version('docker')}`);

const [major] = process.versions.node.split('.').map(Number);
if (major < 24) {
  console.error('Node.js 24+ é necessário (veja mise.toml / .nvmrc).');
  process.exit(1);
}

const envFile = join(ROOT, '.env');
if (!existsSync(envFile)) {
  copyFileSync(join(ROOT, '.env.example'), envFile);
  log('.env criado a partir de .env.example (valores fictícios de desenvolvimento).');
} else {
  log('.env já existe — mantido sem alterações.');
}

const steps = [
  ['pnpm', ['install', '--frozen-lockfile']],
  ['pnpm', ['--filter', '@fauvovet/contracts', 'build']],
  ...(useExternalDb ? [] : [['node', ['scripts/db-local.mjs', 'setup']]]),
  ['pnpm', ['--filter', '@fauvovet/api', 'build']],
  ['pnpm', ['db:migrate']],
  ['pnpm', ['db:seed']],
];
for (const [cmd, args] of steps) {
  log(`▶ ${cmd} ${args.join(' ')}`);
  const code = await run(cmd, args);
  if (code !== 0) {
    console.error(
      `Falhou: ${cmd} ${args.join(' ')} (código ${code}). Veja docs/TESTING.md › Troubleshooting.`,
    );
    process.exit(code);
  }
}
log('Ambiente pronto. Próximo passo: `pnpm dev` e abra http://localhost:3000/fauvovet');
