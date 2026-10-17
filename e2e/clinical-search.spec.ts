import { expect, test } from '@playwright/test';
import { USERS, loginInto, trackConsoleErrors } from './helpers';

test('busca clínica: termo sem acento, trecho destacado e abre o atendimento de origem', async ({ page }) => {
  const errors = trackConsoleErrors(page);
  await loginInto(page, USERS.bruno);
  await page.goto('busca');
  await page.getByRole('searchbox', { name: 'O que procurar no prontuário' }).fill('dermatite atopica');
  await page.getByRole('button', { name: 'Buscar' }).click();
  await expect(page.getByText(/1 paciente/)).toBeVisible();
  const results = page.getByRole('list', { name: 'Trechos encontrados para Thor' });
  await expect(results.locator('mark').first()).toHaveText(/Dermatite/i);
  await results.getByRole('link', { name: /Confirmado.*Diagnóstico/ }).click();
  await expect(page).toHaveURL(/atendimentos\/01K7FV0000000000000ENA0002$/);

  await page.goto('busca?q=penicilina+NÃO+prurido');
  await expect(page.getByText('Nada encontrado no prontuário')).toBeVisible();
  expect(errors).toEqual([]);
});
