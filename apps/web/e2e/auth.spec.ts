import { expect, test } from '@playwright/test';
import { USERS, login, loginInto } from './helpers';

test('rota protegida sem sessão redireciona para o login', async ({ page }) => {
  await page.goto('painel');
  await page.waitForURL(/\/login$/);
  await expect(page.getByRole('heading', { name: 'Entrar' })).toBeVisible();
});

test('senha incorreta mostra erro sem revelar se o e-mail existe', async ({ page }) => {
  await login(page, USERS.bruno, 'senha-errada-123');
  await expect(page.getByRole('main').getByRole('alert')).toHaveText('E-mail ou senha incorretos.');
});

test('veterinário escolhe unidade e vê navegação conforme papel', async ({ page, isMobile }) => {
  await login(page, USERS.bruno);
  await page.waitForURL(/\/contexto$/);
  await page.getByLabel('Aurora — Zona Sul').check();
  await page.getByRole('button', { name: /Entrar em Clínica Veterinária Aurora/ }).click();
  await page.waitForURL(/\/painel$/);
  const nav = page.getByRole('navigation', { name: 'Principal' }).locator('visible=true');
  await expect(nav.getByRole('link', { name: 'Painel' })).toBeVisible();
  await expect(nav.getByRole('link', { name: 'Unidades' })).toBeVisible();
  // Veterinário não tem permissão de equipe/aparência: itens não aparecem (backend também nega)
  await expect(nav.getByRole('link', { name: 'Equipe' })).toHaveCount(0);
  await expect(nav.getByRole('link', { name: 'Aparência' })).toHaveCount(0);
  if (!isMobile) await expect(page.getByText('Aurora — Zona Sul').first()).toBeVisible();
});

test('logout encerra a sessão no servidor', async ({ page, isMobile }) => {
  await loginInto(page, USERS.carla);
  if (isMobile) await page.getByRole('button', { name: 'Abrir menu da conta' }).click();
  await page.getByRole('button', { name: 'Sair' }).locator('visible=true').click();
  await page.waitForURL(/\/login$/);
  await page.goto('painel');
  await page.waitForURL(/\/login$/);
});
