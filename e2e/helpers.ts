import { expect, type Page } from '@playwright/test';

export const PASSWORD = process.env.E2E_PASSWORD ?? 'fauvovet-e2e-2026';

export const USERS = {
  ana: 'ana.titular@aurora.exemplo.test',
  bruno: 'bruno.vet@aurora.exemplo.test',
  carla: 'carla.recepcao@aurora.exemplo.test',
  diana: 'diana.titular@horizonte.exemplo.test',
  eva: 'eva.plantonista@exemplo.test',
};

/** Coleta erros de console (inclui violações de CSP) para falhar o teste se aparecerem. */
/**
 * Oscilação de rede do PRÓPRIO computador (ex.: WSL/Wi-Fi/VPN reconectando): o Chrome cancela downloads em
 * andamento com ERR_NETWORK_CHANGED. Não é defeito do sistema — só este erro é ignorado; CSP, JS e 404 continuam
 * reprovando o teste.
 */
const ENVIRONMENT_NOISE = /net::ERR_NETWORK_CHANGED/;

export function trackConsoleErrors(page: Page): string[] {
  const errors: string[] = [];
  page.on('console', (m) => {
    if (m.type() === 'error' && !ENVIRONMENT_NOISE.test(m.text())) errors.push(m.text());
  });
  page.on('pageerror', (e) => errors.push(e.message));
  return errors;
}

export async function login(page: Page, email: string, password = PASSWORD) {
  await page.goto('login');
  await page.getByLabel('E-mail').fill(email);
  await page.getByRole('textbox', { name: 'Senha', exact: true }).fill(password);
  await page.getByRole('button', { name: 'Entrar', exact: true }).click();
}

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
  await expect(page.getByRole('heading', { level: 1 })).toContainText(/Bom dia|Boa tarde|Boa noite/);
}

export async function logout(page: Page, isMobile: boolean) {
  if (isMobile) await page.getByRole('button', { name: 'Abrir menu da conta' }).click();
  await page.getByRole('button', { name: 'Sair' }).locator('visible=true').click();
  await page.waitForURL(/\/login$/);
}

/** Na tela de dados de demonstração: remove a demonstração se uma tentativa anterior a deixou carregada. */
export async function removeDemoIfLoaded(page: Page) {
  const confirm = page.getByRole('checkbox', { name: /Entendo que/ });
  if (await confirm.isEnabled()) {
    await confirm.check();
    await page.getByRole('button', { name: 'Remover demonstração' }).click();
    await expect(page.getByRole('button', { name: 'Carregar demonstração' })).toBeVisible();
  }
}
