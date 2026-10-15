#!/usr/bin/env node
// Valida os guias HTML de mudança (CLAUDE.md §17.3):
//  - todo docs/changes/AAAA-MM-DD-slug.html é bem formado (estrutura básica) e tem as 10 seções obrigatórias;
//  - não contém TODO pendente (exceto o TEMPLATE) nem referências a scripts/CSS remotos;
//  - docs/PROGRESS.md referencia o guia mais recente;
//  - se há código de implementação (apps/ ou packages/), existe ao menos um guia.
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { ROOT } from './lib.mjs';

const CHANGES = join(ROOT, 'docs', 'changes');
const REQUIRED_SECTIONS = [
  /<h2[^>]*>\s*1\.\s/,
  /<h2[^>]*>\s*2\.\s/,
  /<h2[^>]*>\s*3\.\s/,
  /<h2[^>]*>\s*4\.\s/,
  /<h2[^>]*>\s*5\.\s/,
  /<h2[^>]*>\s*6\.\s/,
  /<h2[^>]*>\s*7\.\s/,
  /<h2[^>]*>\s*8\.\s/,
  /<h2[^>]*>\s*9\.\s/,
  /<h2[^>]*>\s*10\.\s/,
];
const VOID = new Set([
  'meta',
  'link',
  'br',
  'hr',
  'img',
  'input',
  'source',
  'col',
  'wbr',
  'area',
  'base',
  'embed',
  'track',
]);

const errors = [];
const err = (f, m) => errors.push(`${f}: ${m}`);

function checkWellFormed(file, html) {
  if (!/^<!doctype html>/i.test(html.trimStart())) err(file, 'falta <!doctype html>');
  if (!/<html[^>]*\blang="pt-BR"/.test(html)) err(file, 'falta <html lang="pt-BR">');
  if (!/<meta[^>]+charset="utf-8"/i.test(html)) err(file, 'falta <meta charset="utf-8">');
  if (!/<meta[^>]+name="viewport"/i.test(html)) err(file, 'falta meta viewport (responsividade)');
  if (!/<title>[^<]+<\/title>/.test(html)) err(file, 'falta <title>');
  // Balanceamento de tags (ignora conteúdo de <pre>/<code> escapado, comentários, scripts e styles).
  const stripped = html
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/<style[\s\S]*?<\/style>/gi, '')
    .replace(/<script[\s\S]*?<\/script>/gi, '');
  const stack = [];
  const re = /<\/?([a-zA-Z][a-zA-Z0-9-]*)\b[^>]*?(\/?)>/g;
  let m;
  while ((m = re.exec(stripped))) {
    const tag = m[1].toLowerCase();
    if (tag === '!doctype' || VOID.has(tag) || m[2] === '/') continue;
    if (m[0].startsWith('</')) {
      const top = stack.pop();
      if (top !== tag) {
        err(file, `tag desbalanceada: esperado </${top ?? '?'}>, encontrado </${tag}>`);
        return;
      }
    } else stack.push(tag);
  }
  if (stack.length) err(file, `tags não fechadas: ${stack.join(', ')}`);
}

if (!existsSync(CHANGES)) {
  console.error('docs/changes não existe');
  process.exit(1);
}
const all = readdirSync(CHANGES).filter((f) => f.endsWith('.html'));
if (!all.includes('TEMPLATE.html')) err('docs/changes', 'TEMPLATE.html ausente');
const guides = all.filter((f) => f !== 'TEMPLATE.html');

for (const f of all) {
  const rel = `docs/changes/${f}`;
  if (f !== 'TEMPLATE.html' && !/^\d{4}-\d{2}-\d{2}-[a-z0-9-]+\.html$/.test(f))
    err(rel, 'nome deve seguir AAAA-MM-DD-slug.html');
  const html = readFileSync(join(CHANGES, f), 'utf8');
  checkWellFormed(rel, html);
  REQUIRED_SECTIONS.forEach((re, i) => {
    if (!re.test(html)) err(rel, `seção obrigatória ${i + 1} ausente (<h2>${i + 1}. ...)`);
  });
  if (/<script[^>]+src=["']https?:/i.test(html) || /<link[^>]+href=["']https?:/i.test(html))
    err(rel, 'não usar scripts/CSS remotos (deve funcionar offline)');
  if (f !== 'TEMPLATE.html') {
    if (/class="todo"|\bTODO\b/.test(html)) err(rel, 'contém TODO pendente — preencha o guia');
    for (const s of ['PASS', 'Rollback', 'Commit'])
      if (!new RegExp(s, 'i').test(html)) err(rel, `não menciona "${s}"`);
  }
}

const hasImplementation = ['apps', 'packages'].some(
  (d) => existsSync(join(ROOT, d)) && readdirSync(join(ROOT, d)).length > 0,
);
if (hasImplementation && guides.length === 0)
  err('docs/changes', 'há implementação mas nenhum guia HTML de mudança');

const progressPath = join(ROOT, 'docs', 'PROGRESS.md');
if (!existsSync(progressPath)) err('docs/PROGRESS.md', 'ausente');
else if (guides.length) {
  const progress = readFileSync(progressPath, 'utf8');
  const latest = [...guides].sort().at(-1);
  if (!progress.includes(`changes/${latest}`))
    err('docs/PROGRESS.md', `não referencia o guia mais recente (changes/${latest})`);
  for (const g of guides)
    if (!progress.includes(g)) err('docs/PROGRESS.md', `não lista o guia ${g} no histórico`);
}

if (errors.length) {
  console.error(errors.map((e) => `✖ ${e}`).join('\n'));
  console.error(`\ndocs:validate falhou com ${errors.length} problema(s).`);
  process.exit(1);
}
console.log(
  `✔ docs:validate: ${guides.length} guia(s) de mudança + TEMPLATE válidos; PROGRESS.md atualizado.`,
);
