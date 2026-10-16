#!/usr/bin/env node
// Benchmark reproduzível (baseline) das rotas críticas, servidas em /fauvovet pelo PHP embutido com
// opcache, sobre o banco *_test recriado com o seed fictício. Relatório em .perf/web-baseline.json.
// Limitação: o servidor embutido não é o PHP-FPM/Apache da hospedagem — use como referência relativa.
import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import os from 'node:os';
import { join } from 'node:path';
import { ROOT, loadEnv, log, phpEnv } from './lib.mjs';

loadEnv();
const PORT = 8300;
const base = `http://127.0.0.1:${PORT}/fauvovet`;
const ITER = Number(process.env.PERF_ITERATIONS ?? 150);
const server = spawn(process.execPath, ['scripts/e2e-server.mjs'], {
  cwd: ROOT,
  detached: true,
  stdio: 'ignore',
  env: { ...process.env, E2E_PORT: String(PORT), FAUVOVET_OPCACHE: '1' },
});
const stop = () => {
  try {
    process.kill(-server.pid, 'SIGTERM');
  } catch {
    /* encerrado */
  }
};
const up = async () => {
  try {
    return (await fetch(`${base}/login`, { signal: AbortSignal.timeout(1000) })).ok;
  } catch {
    return false;
  }
};
try {
  const deadline = Date.now() + 90_000;
  while (!(await up())) {
    if (Date.now() > deadline) throw new Error('servidor não subiu');
    await new Promise((r) => setTimeout(r, 300));
  }
  // PERF_VOLUME=N: acrescenta N pacientes sintéticos na clínica B (banco *_test) para medir busca com volume.
  const volume = Number(process.env.PERF_VOLUME ?? 0);
  if (volume > 0) {
    const r = spawnSync(process.env.PHP_BINARY ?? 'php', ['artisan', 'db:seed', '--class=VolumeSeeder', '--force'], {
      cwd: ROOT,
      env: phpEnv({
        APP_ENV: 'local',
        DB_DATABASE: process.env.DB_TEST_DATABASE ?? 'fauvovet_test',
        PERF_VOLUME: String(volume),
      }),
      encoding: 'utf8',
    });
    if (r.status !== 0) throw new Error(`volume sintético falhou: ${r.stderr || r.stdout}`);
    log(r.stdout.trim());
  }
  const page = await fetch(`${base}/login`);
  let cookie = (page.headers.getSetCookie() ?? []).map((c) => c.split(';')[0]).join('; ');
  const token = (await page.text()).match(/name="_token" value="([^"]+)"/)[1];
  const login = await fetch(`${base}/login`, {
    method: 'POST',
    redirect: 'manual',
    headers: { cookie, 'content-type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      _token: token,
      email: 'diana.titular@horizonte.exemplo.test',
      password: process.env.E2E_PASSWORD ?? 'fauvovet-e2e-2026',
    }),
  });
  cookie = (login.headers.getSetCookie() ?? []).map((c) => c.split(';')[0]).join('; ') || cookie;
  const routes = {
    'GET /login (anônimo)': ['/login', false],
    'GET /painel': ['/painel', true],
    'GET /unidades': ['/unidades', true],
    'GET /equipe': ['/equipe', true],
    'GET /pacientes': ['/pacientes', true],
    'GET /pacientes?q=nome': ['/pacientes?q=souza', true],
    'GET /pacientes?q=fone': ['/pacientes?q=98765', true],
    'GET /responsaveis?q=nome': ['/responsaveis?q=lima', true],
  };
  const report = {
    date: new Date().toISOString(),
    iterations: ITER,
    host: {
      platform: `${process.platform}-${process.arch}`,
      cpus: os.cpus().length,
      cpu: os.cpus()[0]?.model,
      memGb: Math.round(os.totalmem() / 1e9),
    },
    server: 'php -S (opcache), MySQL/Percona 5.7 local',
    volume: { syntheticPatients: volume },
    results: {},
  };
  const pct = (a, p) => a[Math.min(a.length - 1, Math.ceil((p / 100) * a.length) - 1)];
  for (const [name, [path, auth]] of Object.entries(routes)) {
    const opts = { redirect: 'manual', headers: auth ? { cookie } : {} };
    for (let i = 0; i < 15; i++) await (await fetch(base + path, opts)).arrayBuffer();
    const t = [];
    for (let i = 0; i < ITER; i++) {
      const s = performance.now();
      const r = await fetch(base + path, opts);
      await r.arrayBuffer();
      if (r.status !== 200) throw new Error(`${name} → HTTP ${r.status}`);
      t.push(performance.now() - s);
    }
    t.sort((a, b) => a - b);
    const res = { p50: +pct(t, 50).toFixed(1), p95: +pct(t, 95).toFixed(1), p99: +pct(t, 99).toFixed(1) };
    report.results[name] = res;
    console.log(`${name.padEnd(26)} p50=${res.p50}ms p95=${res.p95}ms p99=${res.p99}ms`);
  }
  mkdirSync(join(ROOT, '.perf'), { recursive: true });
  writeFileSync(join(ROOT, '.perf', 'web-baseline.json'), JSON.stringify(report, null, 2));
  log('Relatório: .perf/web-baseline.json (não versionado). Sem limites impostos até consolidar baseline.');
} catch (e) {
  console.error(`ERRO: ${e.message}`);
  process.exitCode = 1;
} finally {
  stop();
}
