#!/usr/bin/env node
// Executa o PHP com as extensões locais do projeto (ver scripts/local-mysql.mjs › php-ext).
// Uso: node scripts/php.mjs artisan migrate   |   node scripts/php.mjs vendor/bin/phpunit
import { spawn } from 'node:child_process';
import { ROOT, phpEnv } from './lib.mjs';

const php = process.env.PHP_BINARY ?? 'php';
const child = spawn(php, process.argv.slice(2), { cwd: ROOT, stdio: 'inherit', env: phpEnv() });
child.on('exit', (code) => process.exit(code ?? 1));
child.on('error', (e) => {
  console.error(`Não foi possível executar "${php}": ${e.message}. Instale PHP 8.3+ (veja README).`);
  process.exit(127);
});
