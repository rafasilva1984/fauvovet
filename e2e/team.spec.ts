import { expect, test } from '@playwright/test';
import { USERS, loginInto, logout, trackConsoleErrors } from './helpers';

test('titular convida por link e a pessoa cria a conta e entra na clínica', async ({ page, isMobile }, testInfo) => {
  const errors = trackConsoleErrors(page);
  const email = `convite-${testInfo.project.name}-${Date.now() % 1_000_000}@exemplo.test`.toLowerCase();

  await loginInto(page, USERS.ana);
  await page.goto('equipe');
  await page.getByLabel('E-mail da pessoa').fill(email);
  await page.getByRole('checkbox', { name: 'Recepção' }).check();
  await page.getByRole('button', { name: 'Gerar link de convite' }).click();

  await expect(page.getByRole('heading', { name: `Link de convite para ${email}` })).toBeVisible();
  const link = await page.getByLabel('Link de convite').inputValue();
  expect(link).toMatch(/\/fauvovet\/convite\/[A-Za-z0-9_-]{43}$/);
  await page.getByRole('button', { name: 'Copiar link' }).click();
  await expect(page.getByRole('main').getByText(email).first()).toBeVisible();
  await logout(page, isMobile);

  await page.goto(link);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Clínica Veterinária Aurora (fictícia)');
  await page.getByLabel('Seu nome').fill('Pessoa Convidada (fictícia)');
  await page.getByRole('textbox', { name: 'Crie uma senha', exact: true }).fill('senha-e2e-ficticia');
  await page.getByRole('textbox', { name: 'Confirme a senha', exact: true }).fill('senha-e2e-ficticia');
  await page.getByRole('button', { name: 'Criar conta e entrar na equipe' }).click();
  await page.waitForURL(/\/painel$/);
  await expect(page.getByRole('main').getByRole('status')).toContainText('Convite aceito');

  // Nenhum erro de console/CSP no fluxo (o 404 abaixo é intencional e geraria um aviso de recurso).
  expect(errors).toEqual([]);

  // O link é de uso único.
  await logout(page, isMobile);
  const again = await page.goto(link);
  expect(again?.status()).toBe(404);
  await expect(page.getByRole('heading', { name: 'Convite indisponível' })).toBeVisible();
});

test('titular desativa e reativa um membro', async ({ page, isMobile }) => {
  // Altera um registro compartilhado do seed: roda só em um projeto para não disputar com o outro em paralelo.
  test.skip(isMobile, 'executado apenas no desktop');
  await loginInto(page, USERS.ana);
  await page.goto('equipe');
  await page.getByRole('link', { name: /Editar acesso de Davi Financeiro/ }).click();
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Davi Financeiro');
  await page.getByRole('radio', { name: /Desativado/ }).check();
  await page.getByRole('button', { name: 'Salvar acesso' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('atualizado');
  await expect(page.getByRole('main').getByText('Desativado')).toBeVisible();

  await page.getByRole('link', { name: /Editar acesso de Davi Financeiro/ }).click();
  await page.getByRole('radio', { name: /Ativo/ }).check();
  await page.getByRole('button', { name: 'Salvar acesso' }).click();
  await expect(page.getByRole('main').getByText('Desativado')).toHaveCount(0);
});
