import { expect, test } from '@playwright/test';
import { USERS, loginInto, trackConsoleErrors } from './helpers';

test('veterinário registra atendimento com diagnóstico, finaliza e adiciona adendo', async ({ page }, testInfo) => {
  const errors = trackConsoleErrors(page);
  const complaint = `Tosse seca há uma semana (fictício ${testInfo.project.name}).`;
  await loginInto(page, USERS.bruno);
  await page.goto('pacientes/01K7FV0000000000000PTA0002'); // Mia
  await expect(page.getByRole('heading', { name: 'Prontuário' })).toBeVisible();
  await page.getByRole('button', { name: 'Novo atendimento' }).click();
  await expect(page.getByText('Rascunho', { exact: true }).first()).toBeVisible();

  await page.getByLabel('Queixa principal').fill(complaint);
  await page.getByLabel('Temperatura (°C)').fill('39,4');
  await page.getByRole('combobox', { name: 'Mucosas' }).selectOption('Hiperêmicas');
  await page.getByLabel('Descrição 1').fill('Traqueobronquite infecciosa');
  await page.getByRole('combobox', { name: 'Situação' }).first().selectOption('confirmed');
  await page.getByLabel('Descrição 2').fill('Corpo estranho');
  await page.getByRole('combobox', { name: 'Situação' }).nth(1).selectOption('ruled_out');
  await page.getByRole('button', { name: 'Salvar rascunho' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('Rascunho salvo');
  await expect(page.getByLabel('Descrição 1')).toHaveValue('Traqueobronquite infecciosa');

  // Sem a confirmação, não finaliza.
  await page.getByRole('button', { name: 'Finalizar e assinar' }).click();
  await expect(page.getByRole('alert').first()).toContainText('Confirme que revisou');
  await page.getByLabel('Revisei o registro e confirmo as informações como responsável técnico.').check();
  await page.getByRole('button', { name: 'Finalizar e assinar' }).click();
  await expect(page.getByText(/Finalizado e assinado por Bruno/)).toBeVisible();
  await expect(page.getByRole('button', { name: 'Salvar rascunho' })).toHaveCount(0);

  await page.getByLabel('Novo adendo').fill('Tutor informou contato com outro cão tossindo (fictício).');
  await page.getByRole('button', { name: 'Registrar adendo' }).click();
  await expect(page.getByText('Tutor informou contato com outro cão tossindo (fictício).')).toBeVisible();

  // Linha do tempo na ficha mostra o atendimento e os diagnósticos com a situação.
  await page.getByRole('link', { name: 'Ficha do paciente' }).click();
  await expect(page.getByText(complaint)).toBeVisible();
  await expect(page.getByText('Confirmado: Traqueobronquite infecciosa').first()).toBeVisible();
  await expect(page.getByText('Descartado: Corpo estranho').first()).toBeVisible();
  expect(errors).toEqual([]);
});

test('recepção não vê prontuário nem alertas clínicos', async ({ page }) => {
  await loginInto(page, USERS.carla);
  await page.goto('pacientes/01K7FV0000000000000PTA0001'); // Thor (tem alerta e diagnósticos no seed)
  await expect(page.getByRole('heading', { name: 'Thor', level: 1 })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Prontuário' })).toHaveCount(0);
  await expect(page.getByText('Alergia a penicilina')).toHaveCount(0);
  const res = await page.goto('atendimentos/01K7FV0000000000000ENA0002');
  expect(res?.status()).toBe(403);
});
