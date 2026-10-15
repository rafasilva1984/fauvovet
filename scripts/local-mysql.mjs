#!/usr/bin/env node
// MySQL local de DESENVOLVIMENTO/TESTE sem root nem Docker (Linux x86_64).
// Usa Percona Server 5.7 (mesma família do banco gerenciado da Locaweb), baixado com checksum
// verificado para .data/tools, dados em .data/mysql, escutando apenas em 127.0.0.1.
//
// Uso: node scripts/local-mysql.mjs <setup|start|stop|status|php-ext>
//   setup   = php-ext + download + inicialização + bancos/usuário do .env (idempotente)
//   php-ext = em Debian/Ubuntu sem pdo_mysql, extrai a extensão do pacote oficial (apt-get download)
//
// Alternativa: se você já tem MySQL/MariaDB/Percona (pacote do sistema, XAMPP, Docker), ajuste DB_* no .env
// e use apenas `pnpm db:migrate`. Defina MYSQL_BASEDIR para usar outra instalação com este script.
import { createHash } from 'node:crypto';
import {
  createReadStream,
  createWriteStream,
  existsSync,
  mkdirSync,
  readdirSync,
  rmSync,
  symlinkSync,
  writeFileSync,
} from 'node:fs';
import { spawn, spawnSync } from 'node:child_process';
import { connect } from 'node:net';
import { join } from 'node:path';
import { pipeline } from 'node:stream/promises';
import { DATA, PHP_CONF_D, ROOT, loadEnv, log, phpEnv } from './lib.mjs';

loadEnv();
const PERCONA = {
  version: '5.7.44-48',
  file: 'Percona-Server-5.7.44-48-Linux.x86_64.glibc2.17-minimal.tar.gz',
  url: 'https://downloads.percona.com/downloads/Percona-Server-5.7/Percona-Server-5.7.44-48/binary/tarball/Percona-Server-5.7.44-48-Linux.x86_64.glibc2.17-minimal.tar.gz',
  sha256: '2aec67e7d23cf0cf54ebc2603d38359982967536bec5750842665641feec10d0',
};
const TOOLS = join(DATA, 'tools');
const LIBS = join(TOOLS, 'lib');
const DATADIR = join(DATA, 'mysql');
const SOCKET = join(DATA, 'mysql.sock');
const PIDFILE = join(DATA, 'mysql.pid');
const LOGFILE = join(DATA, 'mysql.log');
const HOST = '127.0.0.1';
const PORT = Number(process.env.DB_PORT ?? 33079);

const baseDir = () => process.env.MYSQL_BASEDIR ?? join(TOOLS, PERCONA.file.replace(/\.tar\.gz$/, ''));
const bin = (name) => join(baseDir(), 'bin', name);
const libEnv = () => ({
  ...process.env,
  LD_LIBRARY_PATH: [LIBS, process.env.LD_LIBRARY_PATH].filter(Boolean).join(':'),
});

function aptDownload(pkgs, into) {
  mkdirSync(into, { recursive: true });
  const r = spawnSync('apt-get', ['download', ...pkgs], { cwd: into, encoding: 'utf8' });
  if (r.status !== 0) return false;
  for (const f of readdirSync(into).filter((x) => x.endsWith('.deb'))) {
    spawnSync('dpkg', ['-x', f, 'x'], { cwd: into });
  }
  return true;
}

function phpExt() {
  const has = spawnSync('php', ['-r', 'exit(extension_loaded("pdo_mysql") ? 0 : 1);'], { env: phpEnv() });
  if (has.error) throw new Error('PHP não encontrado no PATH. Instale PHP 8.3+ (veja README).');
  if (has.status === 0) return log('PHP já possui pdo_mysql.');
  const ver = spawnSync('php', ['-r', 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'], {
    encoding: 'utf8',
  }).stdout.trim();
  const apiDir = spawnSync('php', ['-r', 'echo basename(ini_get("extension_dir"));'], {
    encoding: 'utf8',
  }).stdout.trim();
  const dir = join(TOOLS, 'php-ext');
  log(`pdo_mysql ausente; extraindo php${ver}-mysql do repositório do sistema (sem instalar, sem root)...`);
  if (!aptDownload([`php${ver}-mysql`], dir)) {
    throw new Error(
      `Não foi possível baixar php${ver}-mysql. Instale a extensão pdo_mysql pelo gerenciador do sistema.`,
    );
  }
  const ext = join(dir, 'x', 'usr', 'lib', 'php', apiDir);
  mkdirSync(PHP_CONF_D, { recursive: true });
  writeFileSync(
    join(PHP_CONF_D, '90-fauvovet-mysql.ini'),
    `extension=${join(ext, 'mysqlnd.so')}\nextension=${join(ext, 'pdo_mysql.so')}\n`,
  );
  const ok = spawnSync('php', ['-r', 'exit(extension_loaded("pdo_mysql") ? 0 : 1);'], { env: phpEnv() });
  if (ok.status !== 0) throw new Error('pdo_mysql extraído mas não carregou (versão do PHP diferente do pacote?).');
  log('pdo_mysql disponível para os scripts do projeto (via .data/php/conf.d).');
}

async function sha256(file) {
  const h = createHash('sha256');
  await pipeline(createReadStream(file), h);
  return h.digest('hex');
}

async function ensureServer() {
  if (process.env.MYSQL_BASEDIR) return;
  if (existsSync(bin('mysqld'))) return;
  if (process.platform !== 'linux' || process.arch !== 'x64') {
    throw new Error(
      'Download automático só para Linux x86_64. Instale MySQL 5.7+/MariaDB e defina MYSQL_BASEDIR ou use DB_* do .env.',
    );
  }
  mkdirSync(TOOLS, { recursive: true });
  const tgz = join(TOOLS, PERCONA.file);
  if (!existsSync(tgz) || (await sha256(tgz)) !== PERCONA.sha256) {
    log(`Baixando Percona Server ${PERCONA.version} (~86 MB)...`);
    const res = await fetch(PERCONA.url);
    if (!res.ok) throw new Error(`Download falhou: HTTP ${res.status}`);
    await pipeline(res.body, createWriteStream(tgz));
  }
  const sum = await sha256(tgz);
  if (sum !== PERCONA.sha256) {
    rmSync(tgz, { force: true });
    throw new Error(`Checksum inválido do pacote Percona (${sum}). Download descartado.`);
  }
  log('Checksum SHA-256 conferido. Extraindo...');
  const r = spawnSync('tar', ['xzf', tgz, '-C', TOOLS]);
  if (r.status !== 0) throw new Error('Falha ao extrair o pacote.');
}

function ensureLibaio() {
  const ldd = spawnSync('ldd', [bin('mysqld')], { encoding: 'utf8', env: libEnv() });
  if (!/libaio\.so\.1 => not found/.test(ldd.stdout ?? '')) return;
  log('libaio ausente; extraindo do repositório do sistema (sem root)...');
  const dir = join(TOOLS, 'libaio');
  if (!aptDownload(['libaio1t64'], dir) && !aptDownload(['libaio1'], dir)) {
    throw new Error('libaio não encontrada. Instale libaio1/libaio1t64 pelo gerenciador do sistema.');
  }
  const libdir = join(dir, 'x', 'usr', 'lib', 'x86_64-linux-gnu');
  const candidate = readdirSync(libdir).find((f) => /^libaio\.so\.1(t64)?$/.test(f));
  mkdirSync(LIBS, { recursive: true });
  rmSync(join(LIBS, 'libaio.so.1'), { force: true });
  symlinkSync(join(libdir, candidate), join(LIBS, 'libaio.so.1'));
}

/** Teste de porta com timeout curto (no WSL "mirrored" porta fechada não é recusada; ver docs/TESTING.md). */
function portOpen() {
  return new Promise((resolve) => {
    const s = connect({ host: HOST, port: PORT });
    const done = (v) => (s.destroy(), resolve(v));
    s.setTimeout(800, () => done(false));
    s.on('connect', () => done(true));
    s.on('error', () => done(false));
  });
}

const commonArgs = () => [`--basedir=${baseDir()}`, `--datadir=${DATADIR}`];

function initialize() {
  if (existsSync(join(DATADIR, 'mysql'))) return;
  mkdirSync(DATADIR, { recursive: true });
  log('Inicializando diretório de dados...');
  const r = spawnSync(bin('mysqld'), ['--no-defaults', '--initialize-insecure', ...commonArgs()], {
    encoding: 'utf8',
    env: libEnv(),
  });
  if (r.status !== 0) throw new Error(`Inicialização falhou:\n${r.stderr}`);
}

async function start() {
  initialize();
  if (await portOpen()) return log(`MySQL local já em execução (${HOST}:${PORT}).`);
  const child = spawn(
    bin('mysqld'),
    [
      '--no-defaults',
      ...commonArgs(),
      `--port=${PORT}`,
      `--bind-address=${HOST}`,
      `--socket=${SOCKET}`,
      `--pid-file=${PIDFILE}`,
      `--log-error=${LOGFILE}`,
      '--character-set-server=utf8mb4',
      '--collation-server=utf8mb4_unicode_ci',
      '--sql-mode=ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION',
      '--innodb-buffer-pool-size=128M',
      '--performance-schema=OFF',
    ],
    { detached: true, stdio: 'ignore', env: libEnv() },
  );
  child.unref();
  for (let i = 0; i < 60; i++) {
    if (await portOpen()) return log(`MySQL local iniciado em ${HOST}:${PORT} (dados em .data/mysql).`);
    await new Promise((r) => setTimeout(r, 500));
  }
  throw new Error(`MySQL não respondeu. Veja ${LOGFILE}`);
}

function stop() {
  if (!existsSync(SOCKET)) return log('MySQL local não está em execução.');
  spawnSync(bin('mysqladmin'), [`--socket=${SOCKET}`, '-uroot', 'shutdown'], { env: libEnv() });
  log('MySQL local parado.');
}

function sql(statements) {
  const r = spawnSync(bin('mysql'), [`--socket=${SOCKET}`, '-uroot', '--batch'], {
    input: statements,
    encoding: 'utf8',
    env: libEnv(),
  });
  if (r.status !== 0) throw new Error(r.stderr);
  return r.stdout;
}

async function setup() {
  phpExt();
  await ensureServer();
  ensureLibaio();
  await start();
  const user = process.env.DB_USERNAME ?? 'fauvovet';
  const pass = process.env.DB_PASSWORD ?? '';
  const dbs = [process.env.DB_DATABASE ?? 'fauvovet_dev', process.env.DB_TEST_DATABASE ?? 'fauvovet_test'];
  if (!/^[a-z0-9_]+$/.test(user) || dbs.some((d) => !/^[a-z0-9_]+$/.test(d)))
    throw new Error('Nome de usuário/banco inválido no .env.');
  if (!dbs[1].endsWith('_test')) throw new Error('DB_TEST_DATABASE deve terminar com _test.');
  const q = (s) => `'${s.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
  sql(
    [
      ...dbs.map((d) => `CREATE DATABASE IF NOT EXISTS \`${d}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`),
      `CREATE USER IF NOT EXISTS ${q(user)}@'127.0.0.1' IDENTIFIED BY ${q(pass)};`,
      `ALTER USER ${q(user)}@'127.0.0.1' IDENTIFIED BY ${q(pass)};`,
      // Mesmo escopo de privilégios esperado na hospedagem: só os bancos do projeto, sem SUPER/FILE/GRANT.
      ...dbs.map((d) => `GRANT ALL PRIVILEGES ON \`${d}\`.* TO ${q(user)}@'127.0.0.1';`),
      'FLUSH PRIVILEGES;',
    ].join('\n'),
  );
  log(`Bancos ${dbs.join(', ')} e usuário ${user}@127.0.0.1 prontos.`);
}

const cmd = process.argv[2];
try {
  if (cmd === 'setup') await setup();
  else if (cmd === 'start') await start();
  else if (cmd === 'stop') stop();
  else if (cmd === 'php-ext') phpExt();
  else if (cmd === 'status') {
    const up = await portOpen();
    log(up ? `MySQL local em execução (${HOST}:${PORT}).` : 'MySQL local parado.');
    process.exitCode = up ? 0 : 3;
  } else {
    console.error('Uso: node scripts/local-mysql.mjs <setup|start|stop|status|php-ext>');
    process.exitCode = 2;
  }
} catch (e) {
  console.error(`[fauvovet] ERRO: ${e.message}`);
  process.exitCode = 1;
}
