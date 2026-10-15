#!/usr/bin/env node
// Varredura simples de segredos/PII nos arquivos rastreados ou preparados no Git.
// Não substitui ferramentas dedicadas (ex.: gitleaks na CI), mas roda localmente sem dependências.
import { execFileSync } from 'node:child_process';
import { readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { ROOT } from './lib.mjs';

const files = execFileSync('git', ['ls-files', '--cached', '--others', '--exclude-standard'], {
  cwd: ROOT,
  encoding: 'utf8',
})
  .split('\n')
  .filter(Boolean)
  .filter((f) => !/(^|\/)pnpm-lock\.yaml$/.test(f));

const RULES = [
  { name: 'Chave privada', re: /-----BEGIN (RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----/ },
  { name: 'Token GitHub', re: /\b(ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{30,}\b/ },
  { name: 'Chave AWS', re: /\bAKIA[0-9A-Z]{16}\b/ },
  { name: 'Chave Anthropic/OpenAI', re: /\bsk-(ant-)?[A-Za-z0-9_-]{30,}\b/ },
  { name: 'Token Slack', re: /\bxox[baprs]-[A-Za-z0-9-]{10,}\b/ },
  {
    name: 'URL com senha não-dev',
    re: /\b[a-z]+:\/\/[^\s:@/]+:(?!dev-only-)[^\s@/]{6,}@(?!127\.0\.0\.1|localhost|postgres[:/])[^\s/]+/i,
  },
];
const FORBIDDEN_FILES = [
  /(^|\/)\.env$/,
  /(^|\/)\.env\.(?!example$)[^/]+$/,
  /\.(pem|key|p12|pfx|dump|backup)$/i,
];

let problems = 0;
for (const f of files) {
  if (FORBIDDEN_FILES.some((re) => re.test(f))) {
    console.error(`✖ arquivo proibido no repositório: ${f}`);
    problems++;
    continue;
  }
  const full = join(ROOT, f);
  let st;
  try {
    st = statSync(full);
  } catch {
    continue; // removido no working tree
  }
  if (st.size > 2_000_000) continue;
  const text = readFileSync(full, 'utf8');
  if (text.includes('\u0000')) continue; // binário
  text.split('\n').forEach((line, i) => {
    for (const rule of RULES) {
      if (rule.re.test(line)) {
        console.error(`✖ ${rule.name}: ${f}:${i + 1}`);
        problems++;
      }
    }
  });
}
if (problems) {
  console.error(`\n${problems} problema(s) encontrado(s). Remova os segredos antes de commitar.`);
  process.exit(1);
}
console.log(
  `✔ Varredura de segredos: ${files.length} arquivos verificados, nenhum segredo encontrado.`,
);
