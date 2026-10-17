// Inicia o PHP embutido servindo a aplicação em /fauvovet (usado por `pnpm dev` e pelo E2E).
import { existsSync, mkdirSync, rmSync, symlinkSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { join } from 'node:path';
import { DATA, ROOT, phpEnv } from './lib.mjs';

export function startPhpServer({ host = '127.0.0.1', port = 8000, env = {}, stdio = 'inherit' } = {}) {
  const docroot = join(DATA, 'devroot');
  mkdirSync(docroot, { recursive: true });
  const link = join(docroot, 'fauvovet');
  if (!existsSync(link)) {
    rmSync(link, { force: true });
    symlinkSync(join(ROOT, 'public'), link, 'junction');
  }
  // FAUVOVET_OPCACHE=1 liga o opcache no servidor embutido (mais próximo do PHP-FPM de produção).
  const ini = [
    // Mesmos limites de upload da produção (deploy/public/.user.ini).
    '-d',
    'upload_max_filesize=16M',
    '-d',
    'post_max_size=20M',
    ...(process.env.FAUVOVET_OPCACHE === '1'
      ? ['-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=0']
      : []),
  ];
  return spawn(
    process.env.PHP_BINARY ?? 'php',
    [...ini, '-S', `${host}:${port}`, '-t', docroot, join(ROOT, 'scripts', 'dev-router.php')],
    {
      cwd: ROOT,
      stdio,
      env: phpEnv({ PHP_CLI_SERVER_WORKERS: '4', ...env }),
    },
  );
}
