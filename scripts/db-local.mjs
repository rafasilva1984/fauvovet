#!/usr/bin/env node
// PostgreSQL local SEM root/Docker, usando os binários do pacote embedded-postgres.
// Uso: node scripts/db-local.mjs <start|stop|status|setup>
// Dados ficam em .data/pg (ignorado pelo Git). Escuta somente em 127.0.0.1.
import { existsSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { createRequire } from 'node:module';
import { join, dirname } from 'node:path';
import { spawnSync } from 'node:child_process';
import pg from 'pg';
import { ROOT, loadEnv, log } from './lib.mjs';

loadEnv();
const require = createRequire(import.meta.url);
const PORT = Number(process.env.LOCAL_PG_PORT ?? 54329);
const SUPERPASS = process.env.LOCAL_PG_SUPERUSER_PASSWORD ?? 'dev-only-superuser-change-me';
const DATA_DIR = join(ROOT, '.data', 'pg');
const LOG_FILE = join(ROOT, '.data', 'pg.log');

function binDir() {
  const platformPkg = {
    'linux-x64': '@embedded-postgres/linux-x64',
    'linux-arm64': '@embedded-postgres/linux-arm64',
    'darwin-x64': '@embedded-postgres/darwin-x64',
    'darwin-arm64': '@embedded-postgres/darwin-arm64',
    'win32-x64': '@embedded-postgres/windows-x64',
  }[`${process.platform}-${process.arch}`];
  if (!platformPkg)
    throw new Error(
      `Plataforma sem binários embutidos: ${process.platform}-${process.arch}. Use Docker (infra/docker).`,
    );
  // O pacote de plataforma é dependência opcional de embedded-postgres; resolve a partir dele.
  const fromEmbedded = createRequire(require.resolve('embedded-postgres'));
  const entry = fromEmbedded.resolve(platformPkg);
  let dir = dirname(entry);
  while (!existsSync(join(dir, 'native', 'bin')) && dirname(dir) !== dir) dir = dirname(dir);
  return join(dir, 'native', 'bin');
}

const exe = (name) => join(binDir(), process.platform === 'win32' ? `${name}.exe` : name);

function pgCtl(args) {
  return spawnSync(exe('pg_ctl'), ['-D', DATA_DIR, ...args], { encoding: 'utf8' });
}

function isRunning() {
  if (!existsSync(DATA_DIR)) return false;
  return pgCtl(['status']).status === 0;
}

function init() {
  if (existsSync(join(DATA_DIR, 'PG_VERSION'))) return;
  const initdb = exe('initdb');
  mkdirSync(DATA_DIR, { recursive: true });
  const pwfile = join(ROOT, '.data', '.pwfile');
  writeFileSync(pwfile, SUPERPASS, { mode: 0o600 });
  log(`Inicializando cluster em ${DATA_DIR}`);
  const r = spawnSync(
    initdb,
    [
      '-D',
      DATA_DIR,
      '-U',
      'postgres',
      '--auth=scram-sha-256',
      `--pwfile=${pwfile}`,
      '-E',
      'UTF8',
      '--locale=C.UTF-8',
    ],
    { encoding: 'utf8' },
  );
  rmSync(pwfile, { force: true });
  if (r.status !== 0) {
    console.error(r.stdout, r.stderr);
    throw new Error('initdb falhou');
  }
}

function start() {
  init();
  if (isRunning()) {
    log(`PostgreSQL local já está em execução (porta ${PORT}).`);
    return;
  }
  const r = pgCtl([
    '-l',
    LOG_FILE,
    '-w',
    '-t',
    '30',
    '-o',
    `-p ${PORT} -c listen_addresses=127.0.0.1`,
    'start',
  ]);
  if (r.status !== 0) {
    console.error(r.stdout, r.stderr);
    throw new Error(`Falha ao iniciar PostgreSQL. Veja ${LOG_FILE}`);
  }
  log(`PostgreSQL local iniciado em 127.0.0.1:${PORT}`);
}

function stop() {
  if (!isRunning()) return log('PostgreSQL local não está em execução.');
  pgCtl(['-w', 'stop', '-m', 'fast']);
  log('PostgreSQL local parado.');
}

function parse(url, label) {
  if (!url) throw new Error(`${label} não definido. Rode \`pnpm bootstrap\` para criar o .env.`);
  const u = new URL(url);
  return {
    user: decodeURIComponent(u.username),
    password: decodeURIComponent(u.password),
    db: u.pathname.slice(1),
    host: u.hostname,
    port: Number(u.port),
  };
}

async function setup() {
  start();
  const app = parse(process.env.DATABASE_URL, 'DATABASE_URL');
  const mig = parse(process.env.MIGRATION_DATABASE_URL, 'MIGRATION_DATABASE_URL');
  const testApp = parse(process.env.TEST_DATABASE_URL, 'TEST_DATABASE_URL');
  if (!['127.0.0.1', 'localhost'].includes(app.host))
    throw new Error('db:setup só opera em banco local (127.0.0.1/localhost).');
  if (!testApp.db.endsWith('_test'))
    throw new Error('TEST_DATABASE_URL deve apontar para banco terminado em _test.');
  const client = new pg.Client({
    host: '127.0.0.1',
    port: PORT,
    user: 'postgres',
    password: SUPERPASS,
    database: 'postgres',
  });
  await client.connect();
  const ident = (s) => client.escapeIdentifier(s);
  const lit = (s) => client.escapeLiteral(s);
  async function ensureRole(name, password, extra) {
    const exists = (await client.query('select 1 from pg_roles where rolname = $1', [name]))
      .rowCount;
    const verb = exists ? 'ALTER' : 'CREATE';
    await client.query(`${verb} ROLE ${ident(name)} WITH LOGIN PASSWORD ${lit(password)} ${extra}`);
  }
  // Papel de migração: dono do schema, sem superuser. Papel da aplicação: sem BYPASSRLS.
  await ensureRole(mig.user, mig.password, 'NOSUPERUSER NOCREATEROLE NOBYPASSRLS');
  await ensureRole(app.user, app.password, 'NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS');
  for (const db of [app.db, testApp.db]) {
    const exists = (await client.query('select 1 from pg_database where datname = $1', [db]))
      .rowCount;
    if (!exists) {
      await client.query(
        `CREATE DATABASE ${ident(db)} OWNER ${ident(mig.user)} ENCODING 'UTF8' TEMPLATE template0`,
      );
      log(`Banco ${db} criado.`);
    }
    await client.query(`REVOKE ALL ON DATABASE ${ident(db)} FROM PUBLIC`);
    await client.query(`GRANT CONNECT ON DATABASE ${ident(db)} TO ${ident(app.user)}`);
    const c2 = new pg.Client({
      host: '127.0.0.1',
      port: PORT,
      user: 'postgres',
      password: SUPERPASS,
      database: db,
    });
    await c2.connect();
    await c2.query(`ALTER SCHEMA public OWNER TO ${ident(mig.user)}`);
    await c2.query('REVOKE CREATE ON SCHEMA public FROM PUBLIC');
    await c2.end();
  }
  await client.end();
  log(`Papéis ${mig.user}/${app.user} e bancos ${app.db}/${testApp.db} prontos.`);
}

const cmd = process.argv[2];
try {
  if (cmd === 'start') start();
  else if (cmd === 'stop') stop();
  else if (cmd === 'status') {
    const running = isRunning();
    log(running ? `PostgreSQL local em execução (porta ${PORT}).` : 'PostgreSQL local parado.');
    process.exitCode = running ? 0 : 3;
  } else if (cmd === 'setup') await setup();
  else {
    console.error('Uso: node scripts/db-local.mjs <start|stop|status|setup>');
    process.exitCode = 2;
  }
} catch (err) {
  console.error(`[fauvovet] ERRO: ${err.message}`);
  process.exitCode = 1;
}
