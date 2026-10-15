/**
 * Benchmark reproduzível (baseline) das rotas críticas da fundação, contra o banco de DESENVOLVIMENTO
 * com seed fictício. Mede latência fim a fim em processo (HTTP local, sem rede externa).
 * Uso: pnpm perf:check  (requer pnpm db:start, db:migrate, db:seed e build da API)
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import os from 'node:os';
import { createApp, apiPrefix } from '../app.factory';
import { loadConfig } from '../config/env';

const ITER = Number(process.env.PERF_ITERATIONS ?? 200);

function pct(sorted: number[], p: number) {
  return sorted[Math.min(sorted.length - 1, Math.ceil((p / 100) * sorted.length) - 1)]!;
}

async function main() {
  if (process.env.NODE_ENV === 'production') throw new Error('perf:check recusado em produção.');
  process.env.ACCESS_LOG = 'false'; // mede a aplicação sem o custo de log de acesso no stdout
  const config = loadConfig({ ...process.env, LOGIN_RATE_LIMIT_PER_15MIN: '100000' });
  if (!['127.0.0.1', 'localhost'].includes(new URL(config.DATABASE_URL).hostname))
    throw new Error('perf:check só roda em banco local.');
  const app = await createApp({ ...config, NODE_ENV: 'test' });
  await app.listen(0, '127.0.0.1');
  const addr = app.getHttpServer().address() as { port: number };
  const base = `http://127.0.0.1:${addr.port}/${apiPrefix(config)}`;
  const password = process.env.SEED_PASSWORD!;

  const login = await fetch(`${base}/auth/login`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ email: 'diana.titular@horizonte.exemplo.test', password }),
  });
  if (login.status !== 200) throw new Error(`login falhou (${login.status}). Rode pnpm db:seed.`);
  const cookie = login.headers.get('set-cookie')!.split(';')[0]!;

  const scenarios: Record<string, () => Promise<Response>> = {
    'GET /health': () => fetch(`${base}/health`),
    'GET /auth/me': () => fetch(`${base}/auth/me`, { headers: { cookie } }),
    'GET /units': () => fetch(`${base}/units`, { headers: { cookie } }),
    'GET /tenant': () => fetch(`${base}/tenant`, { headers: { cookie } }),
  };
  const report: Record<string, unknown> = {
    date: new Date().toISOString(),
    iterations: ITER,
    host: {
      platform: `${process.platform}-${process.arch}`,
      cpus: os.cpus().length,
      cpuModel: os.cpus()[0]?.model,
      memGb: Math.round(os.totalmem() / 1e9),
      node: process.version,
    },
    results: {},
  };
  for (const [name, fn] of Object.entries(scenarios)) {
    for (let i = 0; i < 20; i++) await (await fn()).arrayBuffer(); // aquecimento
    const times: number[] = [];
    for (let i = 0; i < ITER; i++) {
      const t = performance.now();
      const r = await fn();
      await r.arrayBuffer();
      if (r.status !== 200) throw new Error(`${name} → HTTP ${r.status}`);
      times.push(performance.now() - t);
    }
    times.sort((a, b) => a - b);
    const res = {
      p50: +pct(times, 50).toFixed(2),
      p95: +pct(times, 95).toFixed(2),
      p99: +pct(times, 99).toFixed(2),
      max: +times.at(-1)!.toFixed(2),
    };
    (report.results as Record<string, unknown>)[name] = res;
    console.log(
      `${name.padEnd(16)} p50=${res.p50}ms p95=${res.p95}ms p99=${res.p99}ms max=${res.max}ms`,
    );
  }
  const rss = Math.round(process.memoryUsage().rss / 1e6);
  report.rssMb = rss;
  console.log(`RSS do processo: ${rss} MB`);
  const out = join(__dirname, '..', '..', '..', '..', '.perf');
  mkdirSync(out, { recursive: true });
  writeFileSync(join(out, 'api-baseline.json'), JSON.stringify(report, null, 2));
  console.log(
    'Relatório: .perf/api-baseline.json (não versionado). Sem limites impostos até consolidar baseline.',
  );
  await app.close();
}

main().catch((err) => {
  console.error(`ERRO: ${err.message}`);
  process.exit(1);
});
