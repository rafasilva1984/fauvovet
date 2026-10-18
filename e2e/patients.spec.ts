import { expect, test } from '@playwright/test';
import { USERS, loginInto, trackConsoleErrors } from './helpers';

test('recepção cadastra paciente exótico com responsável novo, busca e vincula co-responsável', async ({
  page,
}, testInfo) => {
  const errors = trackConsoleErrors(page);
  const suffix = `${testInfo.project.name}-${Date.now() % 1_000_000}`;
  const petName = `Lua ${suffix}`;
  const tutorName = `Tutora Teste ${suffix} (fictícia)`;

  await loginInto(page, USERS.carla);
  await page.goto('pacientes/novo');
  await page.getByLabel('Nome do paciente').fill(petName);
  await page.getByRole('combobox', { name: 'Espécie', exact: true }).selectOption('bird');
  await page.getByLabel('Qual espécie / tipo ').fill('Calopsita');
  await page.getByLabel('Sexo').selectOption('female');
  await page.getByLabel('ou idade: anos').fill('2');
  await page.getByLabel('Nome completo').fill(tutorName);
  await page.getByLabel('Celular / telefone').fill(`(11) 9${String(Date.now()).slice(-8)}`);
  await page.getByRole('button', { name: 'Salvar paciente' }).click();

  await expect(page.getByRole('main').getByRole('status')).toContainText('cadastrado');
  await expect(page.getByRole('heading', { level: 1, name: petName })).toBeVisible();
  await expect(page.getByRole('main').getByText('Ave — Calopsita').first()).toBeVisible();
  await expect(
    page
      .getByRole('main')
      .getByText(/~2 anos/)
      .first(),
  ).toBeVisible(); // etiqueta do cabeçalho + ficha

  // Vincula um co-responsável já cadastrado (seed fictício).
  await page.getByLabel('Buscar responsável já cadastrado').fill('Rafael');
  await page.getByRole('button', { name: 'Buscar' }).click();
  await page.getByRole('button', { name: 'Vincular Rafael Lima (fictício)' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('vinculado');
  await expect(page.getByRole('link', { name: 'Rafael Lima (fictício)' })).toBeVisible();

  // Busca pela lista encontra o paciente pelo nome do responsável.
  await page.goto('pacientes');
  await page.getByLabel('Buscar por paciente, responsável, telefone ou microchip').fill(tutorName);
  await page.getByRole('button', { name: 'Buscar' }).click();
  await expect(page.getByRole('main').getByText(petName)).toBeVisible();
  expect(errors).toEqual([]);
});

test('clínica B não vê pacientes da clínica A na interface', async ({ page }) => {
  await loginInto(page, USERS.diana);
  await page.goto('pacientes');
  await expect(page.getByRole('main').getByText('Jabu', { exact: true })).toBeVisible();
  await expect(page.getByText('Thor', { exact: true })).toHaveCount(0);
  const res = await page.goto('pacientes/01K7FV0000000000000PTA0001'); // Thor, clínica A
  expect(res?.status()).toBe(404);
});

test('data de nascimento preenche a idade e CEP preenche o endereço', async ({ page }) => {
  const errors = trackConsoleErrors(page);
  await loginInto(page, USERS.carla);
  await page.goto('pacientes/novo');
  // Data calculada NO NAVEGADOR (fuso America/Sao_Paulo do Playwright): calcular no Node (UTC na CI) faz o dia
  // divergir entre 21h e 0h e a idade sair com 1 mês a menos.
  const birthIso = await page.evaluate(() => {
    const d = new Date();
    d.setDate(1); // evita estouro de mês (ex.: 31 → mês sem 31)
    d.setFullYear(d.getFullYear() - 3);
    d.setMonth(d.getMonth() - 2);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-01`;
  });
  await page.getByLabel('Data de nascimento').fill(birthIso);
  await expect(page.getByLabel('ou idade: anos')).toHaveValue('3');
  await expect(page.getByLabel('meses', { exact: true })).toHaveValue('2');
  // Digitar a idade limpa a data (vira estimada no servidor).
  await page.getByLabel('ou idade: anos').fill('5');
  await expect(page.getByLabel('Data de nascimento')).toHaveValue('');

  // CEP: a resposta do FauvoVet (/cep/…) é simulada aqui; o servidor real consultaria o ViaCEP.
  await page.route('**/cep/01001000', (route) =>
    route.fulfill({
      json: { postal_code: '01001000', street: 'Praça da Sé', district: 'Sé', city: 'São Paulo', state: 'SP' },
    }),
  );
  await page.goto('responsaveis/novo');
  await page.getByLabel('CEP').fill('01001000');
  await expect(page.getByLabel('Rua / logradouro')).toHaveValue('Praça da Sé');
  await expect(page.getByLabel('Bairro')).toHaveValue('Sé');
  await expect(page.getByLabel('Cidade')).toHaveValue('São Paulo');
  await expect(page.getByRole('combobox', { name: 'UF' })).toHaveValue('SP');
  await expect(page.getByText('Endereço encontrado. Informe o número.')).toBeVisible();
  await expect(page.getByLabel('Número')).toBeFocused();
  await expect(page.getByLabel('CEP')).toHaveValue('01001-000');
  expect(errors).toEqual([]);
});
