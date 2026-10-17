import { expect, test } from '@playwright/test';
import { USERS, loginInto, logout, trackConsoleErrors } from './helpers';

test('busca avançada: veterinário filtra por diagnóstico e abre o atendimento de origem', async ({
  page,
  isMobile,
}) => {
  const errors = trackConsoleErrors(page);
  await loginInto(page, USERS.bruno);
  await page.goto('pacientes');
  await page.getByText('Filtros avançados').click();
  await page.getByRole('combobox', { name: 'Sexo' }).selectOption({ label: 'Macho' });
  await page.getByRole('textbox', { name: 'Diagnóstico ou código' }).fill('dermatite atopica');
  await page.getByRole('combobox', { name: 'Situação' }).selectOption({ label: 'Somente confirmado' });
  await page.getByRole('button', { name: 'Aplicar filtros' }).click();

  await expect(page.getByText('1 paciente', { exact: true })).toBeVisible();
  const evidence = page.getByRole('list', { name: 'Diagnósticos encontrados para Thor' });
  await expect(evidence.getByText('Confirmado')).toBeVisible();
  await expect(evidence.getByText('Suspeita')).toHaveCount(0); // suspeita não se mistura com confirmado
  await evidence.getByRole('link', { name: /Dermatite atópica/ }).click();
  await expect(page).toHaveURL(/atendimentos\/01K7FV0000000000000ENA0002$/);
  expect(errors).toEqual([]);
  await logout(page, isMobile);

  // Recepção: filtros de cadastro sim, prontuário não.
  await loginInto(page, USERS.carla);
  await page.goto('pacientes?raca=persa');
  await expect(page.getByText('1 paciente', { exact: true })).toBeVisible();
  await expect(page.getByRole('textbox', { name: 'Diagnóstico ou código' })).toHaveCount(0);
});
