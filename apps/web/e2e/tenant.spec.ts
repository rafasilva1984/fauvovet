import { expect, test } from '@playwright/test';
import { USERS, loginInto } from './helpers';

test('isolamento na interface: tenant B não vê nada do tenant A', async ({ page }) => {
  await loginInto(page, USERS.diana);
  await page.goto('unidades');
  await expect(page.getByRole('main').getByText('Atendimento domiciliar')).toBeVisible();
  await expect(page.getByRole('main').getByText(/Aurora/)).toHaveCount(0);
});

test('usuário com dois vínculos troca de clínica explicitamente', async ({ page, isMobile }) => {
  await loginInto(page, USERS.eva, 'Clínica Veterinária Aurora (fictícia)');
  await page.goto('unidades');
  await expect(page.getByRole('main').getByText('Aurora — Centro')).toBeVisible();
  if (isMobile) await page.getByRole('button', { name: 'Abrir menu da conta' }).click();
  await page.getByRole('link', { name: 'Trocar clínica/unidade' }).locator('visible=true').click();
  await page.getByRole('button', { name: 'Entrar em Vet Horizonte Domiciliar (fictício)' }).click();
  await page.waitForURL(/\/painel$/);
  await page.goto('unidades');
  await expect(page.getByRole('main').getByText('Atendimento domiciliar')).toBeVisible();
  await expect(page.getByRole('main').getByText('Aurora — Centro')).toHaveCount(0);
});

test('titular cria unidade e personaliza a aparência', async ({ page }, testInfo) => {
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
  const primary = page.getByLabel('Primária', { exact: true });
  await primary.fill('#808080');
  await expect(page.getByText(/abaixo do mínimo/).first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'Salvar aparência' })).toBeDisabled();
  await primary.fill('#1d4e89');
  await page.getByRole('button', { name: 'Salvar aparência' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('Aparência salva');
  await page.reload();
  await page.waitForURL(/aparencia$/);
  await expect
    .poll(() =>
      page.evaluate(() =>
        getComputedStyle(document.documentElement).getPropertyValue('--fv-primary').trim(),
      ),
    )
    .toBe('#1d4e89');
  // Restaura o padrão para não afetar outros testes
  await page.getByRole('button', { name: 'Restaurar padrão FauvoVet' }).click();
  await page.getByRole('button', { name: 'Salvar aparência' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('Aparência salva');
});

test('cadastro de nova clínica entra direto no painel do novo ambiente', async ({
  page,
}, testInfo) => {
  await page.goto('cadastro');
  await page.getByText('Profissional autônomo (CPF)').click();
  await page.getByLabel('Nome profissional').fill('Dra. Exóticos Fictícia');
  await page.getByLabel('CPF (opcional)').fill('529.982.247-25');
  await page.getByLabel('Seu nome').fill('Dra. Fictícia');
  await page
    .getByLabel('E-mail de acesso')
    .fill(`e2e-${testInfo.project.name}-${Date.now()}@exemplo.test`);
  await page.getByLabel('Senha').fill('curta');
  await page.getByRole('button', { name: 'Criar ambiente' }).click();
  await expect(page.getByText('A senha deve ter ao menos 12 caracteres.')).toBeVisible();
  await page.getByLabel('Senha').fill('senha-longa-ficticia-1');
  await page.getByRole('button', { name: 'Criar ambiente' }).click();
  await page.waitForURL(/\/painel$/);
  await expect(
    page.getByText('Dra. Exóticos Fictícia').locator('visible=true').first(),
  ).toBeVisible();
});
