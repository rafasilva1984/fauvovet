import AxeBuilder from '@axe-core/playwright';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import { USERS, loginInto, logout } from './helpers';

async function axe(page: Page) {
  // Mede o estado FINAL da tela: com animação de entrada, o axe calcularia o contraste no meio do "fade".
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.waitForTimeout(50);
  // Celular: nenhum elemento visível pode passar da borda da tela (o overflow-x: clip esconderia o defeito).
  const clipped = await page.evaluate(() =>
    innerWidth > 600
      ? []
      : [...document.querySelectorAll('body *')]
          .filter((el) => {
            if (el.closest('.sr-only, [aria-hidden="true"], [x-cloak], [hidden], .pointer-events-none')) return false;
            const r = el.getBoundingClientRect();
            const st = getComputedStyle(el);
            return r.width > 0 && r.height > 0 && st.visibility !== 'hidden' && r.right > innerWidth + 1;
          })
          .slice(0, 5)
          .map(
            (el) =>
              `${el.tagName.toLowerCase()} "${(el.textContent ?? '').trim().slice(0, 40)}" right=${Math.round(el.getBoundingClientRect().right)}`,
          ),
  );
  expect(clipped, `conteúdo além da borda em ${page.url()}`).toEqual([]);
  const r = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  // Lista cada elemento reprovado (seletor + dados do contraste) para facilitar a correção.
  const summary = r.violations.map(
    (v) =>
      `${v.id} (${v.impact}): ${v.nodes.length} nó(s) — ${v.help}\n` +
      v.nodes
        .map((n) => {
          const d = (n.any[0]?.data ?? {}) as { fgColor?: string; bgColor?: string; contrastRatio?: number };
          return `   • ${n.target.join(' ')}${d.contrastRatio ? ` [${d.fgColor} sobre ${d.bgColor} = ${d.contrastRatio}]` : ''}`;
        })
        .join('\n'),
  );
  expect(summary, summary.join('\n')).toEqual([]);
}

test('acessibilidade WCAG AA (axe): login, cadastro, contexto, painel, unidades, aparência, equipe', async ({
  page,
}) => {
  await page.goto('login');
  await axe(page);
  await page.goto('cadastro');
  await axe(page);
  await loginInto(page, USERS.ana);
  await axe(page);
  for (const path of ['unidades', 'configuracoes/aparencia', 'equipe', 'contexto']) {
    await page.goto(path);
    await axe(page);
  }
});

test('acessibilidade WCAG AA (axe): convite, edição de membro e aceite de convite', async ({
  page,
  isMobile,
}, testInfo) => {
  await loginInto(page, USERS.ana);
  await page.goto('equipe');
  await page
    .getByLabel('E-mail da pessoa')
    .fill(`a11y-${testInfo.project.name}-${Date.now() % 1_000_000}@exemplo.test`);
  await page.getByRole('checkbox', { name: 'Estoque' }).check();
  await page.getByRole('button', { name: 'Gerar link de convite' }).click();
  await axe(page); // painel do link + lista de convites pendentes
  const link = await page.getByLabel('Link de convite').inputValue();
  await page.getByRole('link', { name: /Editar acesso de Carla/ }).click();
  await axe(page);
  await logout(page, isMobile);
  await page.goto(link);
  await axe(page);
});

test('acessibilidade WCAG AA (axe): pacientes e responsáveis', async ({ page }) => {
  await loginInto(page, USERS.ana);
  for (const path of [
    'pacientes',
    'pacientes?q=rafael',
    'pacientes/novo',
    'pacientes/01K7FV0000000000000PTA0002?vincular=marina',
    'pacientes/01K7FV0000000000000PTA0002/editar',
    'responsaveis',
    'responsaveis/novo',
    'responsaveis/01K7FV0000000000000GRDA001',
  ]) {
    await page.goto(path);
    await axe(page);
  }
});

test('acessibilidade WCAG AA (axe): agenda', async ({ page }) => {
  await loginInto(page, USERS.ana);
  for (const path of [
    'agenda',
    'agenda?visao=semana',
    'agenda/novo?buscar=thor',
    'agenda/novo?paciente=01K7FV0000000000000PTA0003',
    'agenda/01K7FV0000000000000APA0002',
    'configuracoes/tipos-de-atendimento',
  ]) {
    await page.goto(path);
    await axe(page);
  }
});

test('acessibilidade WCAG AA (axe): prontuário', async ({ page }) => {
  await loginInto(page, USERS.bruno);
  await page.goto('pacientes/01K7FV0000000000000PTA0001'); // ficha com alertas, peso e linha do tempo
  await axe(page);
  await page.goto('atendimentos/01K7FV0000000000000ENA0002'); // finalizado (leitura + adendos)
  await axe(page);
  await page.goto('pacientes/01K7FV0000000000000PTA0003');
  await page.getByRole('button', { name: 'Novo atendimento' }).click();
  await expect(page.getByLabel('Queixa principal')).toBeVisible(); // rascunho (formulário)
  await axe(page);
});

test('acessibilidade WCAG AA (axe): documentos e perfil', async ({ page }) => {
  await loginInto(page, USERS.ana);
  for (const path of [
    'pacientes?diagnostico=dermatite&idade_min=1', // filtros avançados abertos + evidência
    'vacinas',
    'configuracoes/vacinas',
    'configuracoes/vacinas/nova',
    'pacientes/01K7FV0000000000000PTA0001/documentos/novo',
    'configuracoes/modelos-de-documento',
    'configuracoes/modelos-de-documento/novo',
    'perfil',
  ]) {
    await page.goto(path);
    await axe(page);
  }
  await page.goto('pacientes/01K7FV0000000000000PTA0001/documentos/novo');
  await page.getByRole('link', { name: /Orientações pós-consulta/ }).click();
  await axe(page);
  await page.getByRole('button', { name: 'Emitir PDF' }).click();
  await expect(page.getByText('Válido', { exact: true })).toBeVisible();
  await axe(page);
});

test('modo escuro mantém contraste AA', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.goto('login');
  await axe(page);
  await loginInto(page, USERS.diana);
  await axe(page);
});

test('Web Vitals de laboratório (LCP/CLS) e peso de JS/CSS', async ({ page }, testInfo) => {
  const measure = () =>
    page.evaluate(
      () =>
        new Promise<{ lcp: number; cls: number; kb: number }>((resolve) => {
          let lcp = 0;
          let cls = 0;
          new PerformanceObserver((l) => l.getEntries().forEach((e) => (lcp = Math.max(lcp, e.startTime)))).observe({
            type: 'largest-contentful-paint',
            buffered: true,
          });
          new PerformanceObserver((l) =>
            l.getEntries().forEach((e) => {
              const s = e as PerformanceEntry & { value: number; hadRecentInput: boolean };
              if (!s.hadRecentInput) cls += s.value;
            }),
          ).observe({ type: 'layout-shift', buffered: true });
          setTimeout(() => {
            const kb = performance
              .getEntriesByType('resource')
              .reduce((a, r) => a + (r as PerformanceResourceTiming).encodedBodySize, 0);
            resolve({ lcp: Math.round(lcp), cls: +cls.toFixed(4), kb: Math.round(kb / 1024) });
          }, 1200);
        }),
    );
  await page.goto('login');
  const loginVitals = await measure();
  await loginInto(page, USERS.ana);
  await page.goto('painel');
  const painelVitals = await measure();
  const out = join(process.cwd(), '.perf');
  mkdirSync(out, { recursive: true });
  const file = join(out, 'web-vitals.json');
  const all = existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : {};
  all[testInfo.project.name] = { date: new Date().toISOString(), login: loginVitals, painel: painelVitals };
  writeFileSync(file, JSON.stringify(all, null, 2));
  console.log(`[${testInfo.project.name}] login`, loginVitals, 'painel', painelVitals);
  expect(loginVitals.cls).toBeLessThan(0.1);
  expect(painelVitals.cls).toBeLessThan(0.1);
});
