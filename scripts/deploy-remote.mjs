#!/usr/bin/env node
// Chama as ferramentas de deploy do servidor (sem SSH). O token vem da variável de ambiente
// FAUVOVET_DEPLOY_TOKEN da sua sessão de terminal (nunca de arquivo).
// Uso: FAUVOVET_DEPLOY_TOKEN=... pnpm deploy:remote https://dradeboragakdino.com.br/fauvovet diagnostico|migrar|otimizar
const [url, action] = process.argv.slice(2);
const token = process.env.FAUVOVET_DEPLOY_TOKEN;
const actions = { diagnostico: 'GET', migrar: 'POST', otimizar: 'POST' };
if (!url || !actions[action] || !token) {
  console.error('Uso: FAUVOVET_DEPLOY_TOKEN=<token> pnpm deploy:remote <URL base> <diagnostico|migrar|otimizar>');
  process.exit(2);
}
if (!url.startsWith('https://') && !/^http:\/\/(127\.0\.0\.1|localhost)/.test(url)) {
  console.error(
    'AVISO: URL sem HTTPS — o token trafegaria sem criptografia. Ative o certificado antes (ou use só em teste local).',
  );
  if (!process.env.FAUVOVET_ALLOW_HTTP) process.exit(2);
}
const res = await fetch(`${url.replace(/\/$/, '')}/_deploy/${action}`, {
  method: actions[action],
  headers: { 'X-Deploy-Token': token, accept: 'application/json' },
  signal: AbortSignal.timeout(120000),
});
const text = await res.text();
console.log(`HTTP ${res.status}`);
try {
  console.log(JSON.stringify(JSON.parse(text), null, 2));
} catch {
  console.log(
    res.status === 404
      ? 'Não encontrado: ferramentas desligadas (DEPLOY_TOOLS_ENABLED=false) ou token errado.'
      : text.slice(0, 500),
  );
}
process.exit(res.ok ? 0 : 1);
