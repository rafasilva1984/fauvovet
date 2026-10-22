#!/usr/bin/env node
// Copia o pacote de deploy mais recente (.deploy/fauvovet-<versão>) para uma pasta do Windows onde o FileZilla
// enxerga com facilidade. Padrão no WSL: C:\fauvovet-deploy (fora do OneDrive, para o .env não ir para a nuvem).
// Uso: pnpm deploy:copiar [--primeira-instalacao]
//   --primeira-instalacao  cria também app-fauvovet/.env a partir do modelo, para você preencher no Bloco de Notas.
// Outra pasta: DEPLOY_COPY_DIR=/caminho pnpm deploy:copiar
import { cpSync, copyFileSync, existsSync, readFileSync, readdirSync, rmSync, statSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join, relative } from 'node:path';
import { ROOT, log } from './lib.mjs';

const firstInstall = process.argv.includes('--primeira-instalacao');
const deployDir = join(ROOT, '.deploy');
const packages = existsSync(deployDir)
  ? readdirSync(deployDir)
      .filter((n) => n.startsWith('fauvovet-') && statSync(join(deployDir, n)).isDirectory())
      .map((n) => ({ n, t: statSync(join(deployDir, n)).mtimeMs }))
      .sort((a, b) => b.t - a.t)
  : [];
if (!packages.length) {
  console.error('Nenhum pacote em .deploy/. Rode antes: pnpm deploy:package');
  process.exit(1);
}
const source = join(deployDir, packages[0].n);
if (packages[0].n.endsWith('-modificado')) {
  console.error(
    `O pacote mais recente (${packages[0].n}) tem alterações não commitadas. Faça commit e rode pnpm deploy:package de novo.`,
  );
  process.exit(1);
}
// Pacote de um commit anterior = reenviar a versão que já está no servidor (aconteceu em 21/10). Recusa.
const head = execFileSync('git', ['rev-parse', '--short=7', 'HEAD'], { cwd: ROOT, encoding: 'utf8' }).trim();
if (packages[0].n !== `fauvovet-${head}` && !process.argv.includes('--pacote-antigo')) {
  console.error(
    `O pacote mais recente (${packages[0].n}) NÃO é do commit atual (${head}). Rode antes: pnpm deploy:package\n` +
      '(para copiar mesmo assim um pacote antigo, ex.: rollback, use: pnpm deploy:copiar --pacote-antigo)',
  );
  process.exit(1);
}

const target = process.env.DEPLOY_COPY_DIR ?? (existsSync('/mnt/c') ? '/mnt/c/fauvovet-deploy' : null);
if (!target) {
  console.error(`Fora do WSL: use o pacote direto em ${source} ou defina DEPLOY_COPY_DIR.`);
  process.exit(1);
}
if (existsSync(target)) {
  const marker = join(target, 'LEIA-ME.txt');
  if (!existsSync(marker) || !readFileSync(marker, 'utf8').startsWith('FauvoVet')) {
    console.error(`${target} já existe e não parece ser um pacote do FauvoVet. Nada foi apagado; escolha outra pasta.`);
    process.exit(1);
  }
  if (existsSync(join(target, 'app-fauvovet', '.env'))) {
    console.error(
      `${target} ainda contém um .env (com senhas). Se ele já foi enviado ao servidor, apague a pasta ${target} pelo Explorador de Arquivos e rode de novo.`,
    );
    process.exit(1);
  }
  rmSync(target, { recursive: true, force: true });
}
cpSync(source, target, { recursive: true });
if (firstInstall)
  copyFileSync(join(target, 'app-fauvovet', '.env.production.example'), join(target, 'app-fauvovet', '.env'));

const win = target.startsWith('/mnt/')
  ? `${target[5].toUpperCase()}:\\${target.slice(7).replaceAll('/', '\\')}`
  : target;
log(`Pacote ${packages[0].n} copiado de ${relative(ROOT, source)} para ${win}`);
if (firstInstall)
  log(
    `Arquivo para preencher: ${win === target ? join(target, 'app-fauvovet', '.env') : `${win}\\app-fauvovet\\.env`}`,
  );
