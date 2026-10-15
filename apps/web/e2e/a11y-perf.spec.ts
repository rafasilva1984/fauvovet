import AxeBuilder from '@axe-core/playwright';
import { mkdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import { USERS, loginInto } from './helpers';

async function axe(page: Page) {
  const r = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
    .analyze();
  const summary = r.violations.map(
    (v) => `${v.id} (${v.impact}): ${v.nodes.length} nó(s) — ${v.help}`,
  );
  expect(summary, summary.join('\n')).toEqual([]);
}

test('acessibilidade WCAG AA (axe): login, cadastro, painel, unidades, aparência', async ({
  page,
}) => {
  await page.goto('login');
  await axe(page);
  await page.goto('cadastro');
  await axe(page);
  await loginInto(page, USERS.ana);
  await axe(page);
  await page.goto('unidades');
  await expect(page.getByRole('main').getByText('Aurora — Centro')).toBeVisible();
  await axe(page);
  await page.goto('configuracoes/aparencia');
  await axe(page);
});

test('modo escuro mantém contraste AA', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.goto('login');
  await axe(page);
  await loginInto(page, USERS.diana);
  await axe(page);
});

test('Web Vitals de laboratório (LCP/CLS) e peso de JS', async ({ page }, testInfo) => {
  const measure = async () =>
    page.evaluate(
      () =>
        new Promise<{ lcp: number; cls: number }>((resolve) => {
          let lcp = 0;
          let cls = 0;
          new PerformanceObserver((l) =>
            l.getEntries().forEach((e) => (lcp = Math.max(lcp, e.startTime))),
          ).observe({
            type: 'largest-contentful-paint',
            buffered: true,
          });
          new PerformanceObserver((l) =>
            l.getEntries().forEach((e) => {
              const s = e as PerformanceEntry & { value: number; hadRecentInput: boolean };
              if (!s.hadRecentInput) cls += s.value;
            }),
          ).observe({ type: 'layout-shift', buffered: true });
          setTimeout(() => resolve({ lcp: Math.round(lcp), cls: +cls.toFixed(4) }), 1500);
        }),
    );
  const jsBytes = async () =>
    page.evaluate(() =>
      performance
        .getEntriesByType('resource')
        .filter((r) => (r as PerformanceResourceTiming).initiatorType === 'script')
        .reduce((a, r) => a + (r as PerformanceResourceTiming).encodedBodySize, 0),
    );

  await page.goto('login');
  const loginVitals = { ...(await measure()), jsKb: Math.round((await jsBytes()) / 1024) };
  await loginInto(page, USERS.ana);
  await page.goto('painel');
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  const painelVitals = { ...(await measure()), jsKb: Math.round((await jsBytes()) / 1024) };

  const out = join(__dirname, '..', '..', '..', '.perf');
  mkdirSync(out, { recursive: true });
  const file = join(out, 'web-vitals.json');
  const all = existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : {};
  all[testInfo.project.name] = {
    date: new Date().toISOString(),
    login: loginVitals,
    painel: painelVitals,
  };
  writeFileSync(file, JSON.stringify(all, null, 2));
  console.log(`[${testInfo.project.name}] login`, loginVitals, 'painel', painelVitals);
  // Sem metas impostas ainda (baseline); apenas regressões grosseiras falham.
  expect(loginVitals.cls).toBeLessThan(0.1);
  expect(painelVitals.cls).toBeLessThan(0.1);
});
