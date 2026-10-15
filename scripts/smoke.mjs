#!/usr/bin/env node
// Smoke não destrutivo: health, página de login, e (opcional) login/me/logout com usuário FICTÍCIO.
// Uso: SMOKE_URL=http://localhost:3000/fauvovet pnpm smoke [--api-only]
//      SMOKE_EMAIL=... SMOKE_PASSWORD=... para testar login (nunca usar contas reais com dados clínicos).
import { loadEnv, log } from './lib.mjs';

loadEnv();
const base = (
  process.env.SMOKE_URL ??
  `http://localhost:${process.env.WEB_PORT ?? 3000}${process.env.BASE_PATH ?? '/fauvovet'}`
).replace(/\/$/, '');
const api = `${base}/api/v1`;
const checks = [];
async function check(name, fn) {
  const t = Date.now();
  try {
    await fn();
    checks.push(['PASS', name, `${Date.now() - t}ms`]);
  } catch (e) {
    checks.push(['FAIL', name, e.message]);
  }
}
const expectStatus = (r, s) => {
  if (r.status !== s) throw new Error(`HTTP ${r.status} (esperado ${s})`);
};

await check('API health', async () => {
  const r = await fetch(`${api}/health`);
  expectStatus(r, 200);
  const b = await r.json();
  if (b.database !== 'up') throw new Error('banco indisponível');
});
if (!process.argv.includes('--api-only'))
  await check('Página de login', async () => {
    const r = await fetch(`${base}/login`);
    expectStatus(r, 200);
    if (!(await r.text()).includes('FauvoVet')) throw new Error('conteúdo inesperado');
  });
await check('Rota protegida sem sessão → 401', async () => {
  expectStatus(await fetch(`${api}/auth/me`), 401);
});
const email =
  process.env.SMOKE_EMAIL ??
  (process.env.NODE_ENV !== 'production' ? 'carla.recepcao@aurora.exemplo.test' : undefined);
const password =
  process.env.SMOKE_PASSWORD ??
  (process.env.NODE_ENV !== 'production' ? process.env.SEED_PASSWORD : undefined);
if (email && password) {
  await check('Login + me + logout (usuário fictício)', async () => {
    const r = await fetch(`${api}/auth/login`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ email, password }),
    });
    expectStatus(r, 200);
    const cookie = r.headers.get('set-cookie')?.split(';')[0];
    const { csrfToken } = await r.json();
    expectStatus(await fetch(`${api}/auth/me`, { headers: { cookie } }), 200);
    expectStatus(
      await fetch(`${api}/auth/logout`, {
        method: 'POST',
        headers: { cookie, 'x-csrf-token': csrfToken },
      }),
      204,
    );
    expectStatus(await fetch(`${api}/auth/me`, { headers: { cookie } }), 401);
  });
}
for (const [s, n, d] of checks) console.log(`${s.padEnd(5)} ${n} (${d})`);
log(`smoke em ${base}`);
process.exit(checks.some(([s]) => s === 'FAIL') ? 1 : 0);
