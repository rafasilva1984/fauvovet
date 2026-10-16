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
  await page.getByLabel('Qual espécie / tipo (opcional)').fill('Calopsita');
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
