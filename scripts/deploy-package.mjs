#!/usr/bin/env node
// Monta o pacote de deploy para hospedagem SEM SSH (envio por FileZilla via SFTP/FTPS):
//   .deploy/fauvovet-<versão>/app-fauvovet/        → raiz do FTP (FORA de public_html)
//   .deploy/fauvovet-<versão>/public_html/fauvovet/ → dentro de public_html
// Só entram arquivos do repositório não ignorados pelo .gitignore (nunca .env, dados locais, testes ou docs)
// + vendor sem dev + build.
import { createHash } from 'node:crypto';
import {
  cpSync,
  existsSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  rmSync,
  statSync,
  writeFileSync,
  copyFileSync,
} from 'node:fs';
import { execFileSync, spawnSync } from 'node:child_process';
import { dirname, join, relative } from 'node:path';
import { ROOT, log, phpEnv } from './lib.mjs';

const sh = (cmd, args, opts = {}) => {
  const r = spawnSync(cmd, args, { cwd: ROOT, stdio: 'inherit', ...opts });
  if (r.status !== 0) throw new Error(`Falhou: ${cmd} ${args.join(' ')}`);
};
const git = (...args) => execFileSync('git', args, { cwd: ROOT, encoding: 'utf8' }).trim();

const sha = git('rev-parse', '--short', 'HEAD');
const dirty = git('status', '--porcelain') !== '';
const version = `${sha}${dirty ? '-modificado' : ''}`;
const out = join(ROOT, '.deploy', `fauvovet-${version}`);
const app = join(out, 'app-fauvovet');
const pub = join(out, 'public_html', 'fauvovet');
if (dirty)
  log(
    'AVISO: há alterações não commitadas; o pacote será marcado como "-modificado". Para publicar, faça commit antes.',
  );

rmSync(out, { recursive: true, force: true });
mkdirSync(app, { recursive: true });
mkdirSync(pub, { recursive: true });

// 1) Código da aplicação (somente arquivos rastreados pelo Git, em pastas de runtime)
const RUNTIME = [
  /^app\//,
  /^bootstrap\/(app|providers)\.php$/,
  /^config\//,
  /^database\/migrations\//,
  /^lang\//,
  /^resources\/views\//,
  /^routes\//,
  /^artisan$/,
  /^composer\.(json|lock)$/,
];
const files = git('ls-files', '--cached', '--others', '--exclude-standard')
  .split('\n')
  .filter((f) => RUNTIME.some((re) => re.test(f)));
for (const f of files) {
  mkdirSync(dirname(join(app, f)), { recursive: true });
  copyFileSync(join(ROOT, f), join(app, f));
}
for (const d of [
  'bootstrap/cache',
  'storage/app/private',
  'storage/framework/cache/data',
  'storage/framework/sessions',
  'storage/framework/views',
  'storage/logs',
]) {
  mkdirSync(join(app, d), { recursive: true });
  writeFileSync(join(app, d, '.gitignore'), '*\n!.gitignore\n');
}
copyFileSync(join(ROOT, 'deploy', 'app.htaccess'), join(app, '.htaccess'));
copyFileSync(join(ROOT, 'deploy', 'env.production.example'), join(app, '.env.production.example'));

// 2) Dependências PHP de produção (sem pacotes de desenvolvimento), autoload otimizado
sh(
  'composer',
  [
    'install',
    '--no-dev',
    '--optimize-autoloader',
    '--classmap-authoritative',
    '--no-scripts',
    '--no-interaction',
    `--working-dir=${app}`,
  ],
  { env: phpEnv() },
);
sh(process.env.PHP_BINARY ?? 'php', ['artisan', 'package:discover'], {
  cwd: app,
  env: phpEnv({ APP_ENV: 'production', APP_KEY: 'base64:' + Buffer.alloc(32).toString('base64') }),
});
rmSync(join(app, 'storage', 'logs', 'laravel.log'), { force: true });

// 3) Pasta pública: front controller de produção + assets compilados
sh(process.execPath, ['node_modules/vite/bin/vite.js', 'build']);
copyFileSync(join(ROOT, 'deploy', 'public', 'index.php'), join(pub, 'index.php'));
copyFileSync(join(ROOT, 'deploy', 'public', '.htaccess'), join(pub, '.htaccess'));
copyFileSync(join(ROOT, 'deploy', 'public', '.user.ini'), join(pub, '.user.ini'));
cpSync(join(ROOT, 'public', 'build'), join(pub, 'build'), { recursive: true });
for (const f of ['favicon.ico', 'robots.txt'])
  if (existsSync(join(ROOT, 'public', f))) copyFileSync(join(ROOT, 'public', f), join(pub, f));

// 4) Verificações de segurança do pacote
const all = [];
const walk = (d) =>
  readdirSync(d).forEach((n) => (statSync(join(d, n)).isDirectory() ? walk(join(d, n)) : all.push(join(d, n))));
walk(out);
const forbidden = all.filter((f) =>
  /(^|\/)\.env$|\.(pem|key|sql|dump)$|(^|\/)(tests|e2e|node_modules|\.git)\//.test(relative(out, f)),
);
if (forbidden.length) throw new Error(`Arquivos proibidos no pacote:\n${forbidden.join('\n')}`);
const phpInPublic = all.filter((f) => f.startsWith(pub) && f.endsWith('.php') && !f.endsWith('/index.php'));
if (phpInPublic.length) throw new Error(`PHP indevido na pasta pública: ${phpInPublic.join(', ')}`);

// 5) Manifesto com SHA-256 (para conferir o que foi enviado)
const manifest = all
  .map((f) => `${createHash('sha256').update(readFileSync(f)).digest('hex')}  ${relative(out, f)}`)
  .sort((a, b) => a.slice(66).localeCompare(b.slice(66)));
writeFileSync(join(out, 'MANIFEST.sha256'), manifest.join('\n') + '\n');
writeFileSync(
  join(out, 'LEIA-ME.txt'),
  [
    `FauvoVet ${version} — pacote gerado em ${new Date().toISOString()}`,
    '',
    'Envio pelo FileZilla (SFTP ou FTPS), conforme docs/DEPLOY_LOCAWEB.md:',
    '  app-fauvovet/          -> raiz do FTP (ao lado de public_html/, NUNCA dentro dela)',
    '  public_html/fauvovet/  -> public_html/fauvovet/',
    '',
    'Primeira instalação: crie app-fauvovet/.env NO SERVIDOR a partir de .env.production.example.',
    'Atualização: não sobrescreva o .env nem a pasta storage/ do servidor.',
    `Arquivos: ${all.length + 2} · conferência: MANIFEST.sha256`,
  ].join('\n') + '\n',
);
log(`Pacote pronto: ${relative(ROOT, out)} (${all.length} arquivos).`);
