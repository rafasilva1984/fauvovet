import { expect, type Page } from '@playwright/test';

export const PASSWORD = process.env.E2E_PASSWORD ?? 'fauvovet-e2e-2026';

export const USERS = {
  ana: 'ana.titular@aurora.exemplo.test',
  bruno: 'bruno.vet@aurora.exemplo.test',
  carla: 'carla.recepcao@aurora.exemplo.test',
  diana: 'diana.titular@horizonte.exemplo.test',
  eva: 'eva.plantonista@exemplo.test',
};

export async function login(page: Page, email: string, password = PASSWORD) {
  await page.goto('login');
  await page.getByLabel('E-mail').fill(email);
  await page.getByRole('textbox', { name: 'Senha', exact: true }).fill(password);
  await page.getByRole('button', { name: 'Entrar' }).click();
}

/** Login e, se necessário, escolha do primeiro vínculo/unidade do tenant indicado. */
export async function loginInto(page: Page, email: string, tenantName?: string) {
  await login(page, email);
  await page.waitForURL(/\/(painel|contexto)$/);
  if (page.url().endsWith('/contexto')) {
    const button = tenantName
      ? page.getByRole('button', { name: `Entrar em ${tenantName}` })
      : page.getByRole('button', { name: /^Entrar em / }).first();
    await button.click();
  }
  await page.waitForURL(/\/painel$/);
  await expect(page.getByRole('heading', { level: 1 })).toContainText(
    /Bom dia|Boa tarde|Boa noite/,
  );
}
