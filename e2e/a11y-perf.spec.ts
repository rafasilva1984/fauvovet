import AxeBuilder from '@axe-core/playwright';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import { USERS, loginInto, logout } from './helpers';

async function axe(page: Page) {
  const r = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  const summary = r.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.length} nó(s) — ${v.help}`);
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
