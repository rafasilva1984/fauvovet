import { expect, test } from '@playwright/test';
import { USERS, login, loginInto, logout, trackConsoleErrors } from './helpers';

test('rota protegida sem sessão redireciona para o login', async ({ page }) => {
  await page.goto('painel');
  await page.waitForURL(/\/login$/);
  await expect(page.getByRole('heading', { name: 'Entrar' })).toBeVisible();
});

test('senha incorreta mostra erro genérico', async ({ page }) => {
  await login(page, USERS.bruno, 'senha-errada-123');
  await expect(page.getByText('E-mail ou senha incorretos.')).toBeVisible();
});

test('cookie de sessão: HttpOnly, SameSite=Lax e restrito a /fauvovet; sem cookie legível por JS', async ({
  page,
  context,
}) => {
  await page.goto('login');
  const cookies = await context.cookies();
  const session = cookies.find((c) => c.name === 'fauvovet_session');
  expect(session).toBeDefined();
  expect(session!.httpOnly).toBe(true);
  expect(session!.sameSite).toBe('Lax');
  expect(session!.path).toBe('/fauvovet');
  expect(cookies.find((c) => c.name === 'XSRF-TOKEN')).toBeUndefined();
});

test('POST sem token CSRF é recusado (419)', async ({ page }) => {
  await page.goto('login');
  const res = await page.request.post('login', { form: { email: USERS.ana, password: 'x' } });
  expect(res.status()).toBe(419);
});

test('veterinário escolhe unidade e vê navegação conforme o papel, sem erros de CSP', async ({ page, isMobile }) => {
  const errors = trackConsoleErrors(page);
  await login(page, USERS.bruno);
  await page.waitForURL(/\/contexto$/);
  await page.getByLabel('Aurora — Zona Sul').check();
  await page.getByRole('button', { name: /Entrar em Clínica Veterinária Aurora/ }).click();
  await page.waitForURL(/\/painel$/);
  const nav = page.getByRole('navigation', { name: 'Principal' }).locator('visible=true');
  await expect(nav.getByRole('link', { name: 'Painel' })).toBeVisible();
  await expect(nav.getByRole('link', { name: 'Unidades' })).toBeVisible();
  await expect(nav.getByRole('link', { name: 'Equipe' })).toHaveCount(0);
  await expect(nav.getByRole('link', { name: 'Aparência' })).toHaveCount(0);
  if (!isMobile) await expect(page.getByText('Aurora — Zona Sul').first()).toBeVisible();
  expect(errors, errors.join('\n')).toEqual([]);
});

test('logout encerra a sessão no servidor', async ({ page, isMobile }) => {
  await loginInto(page, USERS.carla);
  await logout(page, isMobile);
  await page.goto('painel');
  await page.waitForURL(/\/login$/);
});
