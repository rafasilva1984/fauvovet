#!/usr/bin/env node
// Smoke NÃO destrutivo contra um ambiente (local ou produção). Não cria nem altera dados.
// Uso: SMOKE_URL=https://dradeboragaldino.com.br/fauvovet pnpm smoke
//      SMOKE_EMAIL=... SMOKE_PASSWORD=... para testar também login/logout (conta sem dados clínicos reais).
const base = (process.env.SMOKE_URL ?? 'http://127.0.0.1:8000/fauvovet').replace(/\/$/, '');
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
const get = (path, opts = {}) =>
  fetch(`${base}${path}`, { redirect: 'manual', signal: AbortSignal.timeout(15000), ...opts });
const cookieOf = (res) => (res.headers.getSetCookie?.() ?? []).map((c) => c.split(';')[0]).join('; ');

await check('Página de login (200, CSP com nonce, X-Frame-Options)', async () => {
  const r = await get('/login');
  if (r.status !== 200) throw new Error(`HTTP ${r.status}`);
  if (!/script-src 'self' 'nonce-/.test(r.headers.get('content-security-policy') ?? '')) throw new Error('CSP ausente');
  if (r.headers.get('x-frame-options') !== 'DENY') throw new Error('X-Frame-Options ausente');
  const cookie = (r.headers.getSetCookie?.() ?? []).find((c) => c.startsWith('fauvovet_session='));
  if (!cookie || !/httponly/i.test(cookie) || !/path=\/fauvovet/i.test(cookie))
    throw new Error('cookie de sessão sem HttpOnly/Path');
  if (base.startsWith('https://') && !/secure/i.test(cookie)) throw new Error('cookie sem Secure em HTTPS');
});
await check('Rota protegida sem sessão → redireciona para login', async () => {
  const r = await get('/painel');
  if (r.status !== 302 || !(r.headers.get('location') ?? '').endsWith('/login')) throw new Error(`HTTP ${r.status}`);
});
await check('Arquivos sensíveis não são servidos (.env, composer.json)', async () => {
  for (const p of ['/.env', '/../app-fauvovet/.env', '/composer.json', '/index.php.bak']) {
    const r = await get(p);
    if (r.status === 200 && (await r.text()).includes('APP_KEY')) throw new Error(`exposto: ${p}`);
  }
});
await check('Ferramentas de deploy não respondem sem token', async () => {
  const r = await get('/_deploy/diagnostico');
  if (r.status !== 404) throw new Error(`HTTP ${r.status}`);
});
await check('Health (/up)', async () => {
  const r = await get('/up');
  if (r.status !== 200) throw new Error(`HTTP ${r.status}`);
});
if (process.env.SMOKE_EMAIL && process.env.SMOKE_PASSWORD) {
  await check('Login + painel + logout', async () => {
    const page = await get('/login');
    let cookie = cookieOf(page);
    const token = (await page.text()).match(/name="_token" value="([^"]+)"/)?.[1];
    const body = new URLSearchParams({
      _token: token ?? '',
      email: process.env.SMOKE_EMAIL,
      password: process.env.SMOKE_PASSWORD,
    });
    const login = await get('/login', {
      method: 'POST',
      body,
      headers: { cookie, 'content-type': 'application/x-www-form-urlencoded' },
    });
    if (login.status !== 302 || (login.headers.get('location') ?? '').endsWith('/login'))
      throw new Error('login recusado');
    cookie = cookieOf(login) || cookie;
    const target = login.headers.get('location');
    const next = await fetch(target, { redirect: 'manual', headers: { cookie } });
    if (next.status !== 200) throw new Error(`HTTP ${next.status} em ${target}`);
    const csrf = (await next.text()).match(/name="_token" value="([^"]+)"/)?.[1] ?? '';
    const out = await get('/sair', {
      method: 'POST',
      body: new URLSearchParams({ _token: csrf }),
      headers: { cookie, 'content-type': 'application/x-www-form-urlencoded' },
    });
    if (out.status !== 302) throw new Error(`logout HTTP ${out.status}`);
  });
}
for (const [s, n, d] of checks) console.log(`${s.padEnd(5)} ${n} (${d})`);
console.log(`[fauvovet] smoke em ${base}`);
process.exit(checks.some(([s]) => s === 'FAIL') ? 1 : 0);
