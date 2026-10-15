import { expect, test } from '@playwright/test';
import { USERS, loginInto, trackConsoleErrors } from './helpers';

test('isolamento na interface: clínica B não vê nada da clínica A', async ({ page }) => {
  await loginInto(page, USERS.diana);
  await page.goto('unidades');
  await expect(page.getByRole('main').getByText('Atendimento domiciliar')).toBeVisible();
  await expect(page.getByText(/Aurora/)).toHaveCount(0);
});

test('ID de unidade de outra clínica na URL responde 404', async ({ page }) => {
  await loginInto(page, USERS.diana);
  const res = await page.goto('unidades/01K7FV000000000000000TNA01'); // unidade da clínica A
  expect(res?.status()).toBe(404);
  await expect(page.getByText('Página não encontrada')).toBeVisible();
});

test('usuária com dois vínculos troca de clínica explicitamente', async ({ page, isMobile }) => {
  await loginInto(page, USERS.eva, 'Clínica Veterinária Aurora (fictícia)');
  await page.goto('unidades');
  await expect(page.getByRole('main').getByText('Aurora — Centro')).toBeVisible();
  if (isMobile) await page.getByRole('button', { name: 'Abrir menu da conta' }).click();
  await page.getByRole('link', { name: 'Trocar clínica/unidade' }).locator('visible=true').click();
  await page.getByRole('button', { name: 'Entrar em Vet Horizonte Domiciliar (fictício)' }).click();
  await page.waitForURL(/\/painel$/);
  await page.goto('unidades');
  await expect(page.getByRole('main').getByText('Atendimento domiciliar')).toBeVisible();
  await expect(page.getByText('Aurora — Centro')).toHaveCount(0);
});

test('titular cria unidade e personaliza a aparência (Alpine sob CSP estrita)', async ({ page }, testInfo) => {
  const errors = trackConsoleErrors(page);
  await loginInto(page, USERS.ana);
  await page.goto('unidades');
  const name = `Aurora — ${testInfo.project.name} ${Date.now() % 100000}`;
  await page.getByRole('textbox', { name: 'Nome', exact: true }).fill(name);
  await page.getByLabel('Cidade').fill('Cidade Fictícia');
  await page.getByLabel('UF').fill('mg');
  await page.getByRole('button', { name: 'Criar unidade' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('criada');
  await expect(page.getByRole('main').getByText(name, { exact: true })).toBeVisible();

  await page.goto('configuracoes/aparencia');
  const primary = page.getByRole('textbox', { name: 'Primária', exact: true });
  await primary.fill('#808080');
  await expect(page.getByText(/abaixo do mínimo/).first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'Salvar aparência' })).toBeDisabled();
  await primary.fill('#1d4e89');
  await expect(page.getByText(/atende WCAG AA/).first()).toBeVisible();
  await page.getByRole('button', { name: 'Salvar aparência' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('Aparência salva');
  await expect
    .poll(() => page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--fv-primary').trim()))
    .toBe('#1d4e89');
  // A cor precisa estar de fato aplicada nos elementos (não só presente no HTML).
  await page.reload();
  await expect(page.getByRole('button', { name: 'Salvar aparência' })).toHaveCSS(
    'background-color',
    'rgb(29, 78, 137)',
  );
  await page.getByRole('button', { name: 'Restaurar padrão FauvoVet' }).click();
  await page.getByRole('button', { name: 'Salvar aparência' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('Aparência salva');
  expect(errors, errors.join('\n')).toEqual([]);
});

test('cadastro de nova clínica entra direto no painel do novo ambiente', async ({ page }, testInfo) => {
  await page.goto('cadastro');
  await page.getByText('Profissional autônomo (CPF)').click();
  await page.getByLabel('Nome da clínica ou nome profissional').fill('Dra. Exóticos Fictícia');
  await page.getByLabel('CPF ou CNPJ (opcional)').fill('529.982.247-25');
  await page.getByLabel('Seu nome').fill('Dra. Fictícia');
  await page.getByLabel('E-mail de acesso').fill(`e2e-${testInfo.project.name}-${Date.now()}@exemplo.test`);
  await page.getByRole('textbox', { name: 'Senha', exact: true }).fill('curta');
  await page.getByLabel('Confirme a senha').fill('curta');
  await page.getByRole('button', { name: 'Criar ambiente' }).click();
  await expect(page.getByText('A senha deve ter ao menos 12 caracteres.')).toBeVisible();
  await page.getByRole('textbox', { name: 'Senha', exact: true }).fill('senha-longa-ficticia-1');
  await page.getByLabel('Confirme a senha').fill('senha-longa-ficticia-1');
  await page.getByRole('button', { name: 'Criar ambiente' }).click();
  await page.waitForURL(/\/painel$/);
  await expect(page.getByText('Dra. Exóticos Fictícia').locator('visible=true').first()).toBeVisible();
});
