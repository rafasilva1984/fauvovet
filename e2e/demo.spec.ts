import { expect, test } from '@playwright/test';
import { USERS, loginInto, trackConsoleErrors } from './helpers';

test('dados de demonstração: carregar, ver na busca/vacinas/casos e remover', async ({ page }) => {
  const errors = trackConsoleErrors(page);
  await loginInto(page, USERS.diana); // clínica B: o teste remove tudo ao final
  await page.goto('configuracoes/dados-de-demonstracao');
  await page.getByRole('button', { name: 'Carregar demonstração' }).click();
  await expect(page.getByText(/Demonstração carregada: 22 pacientes/).first()).toBeVisible();

  await page.getByRole('link', { name: 'Busca: dermatite atópica confirmada em cães' }).click();
  await expect(page.getByRole('link', { name: /Bento/ }).first()).toBeVisible();
  await page.goto('configuracoes/dados-de-demonstracao');
  await page.getByRole('link', { name: 'Busca clínica: uveíte em aves' }).click();
  await expect(page.getByRole('link', { name: /Sol/ }).first()).toBeVisible();
  await expect(page.locator('.fv-snippet mark').first()).toHaveText(/uveíte/i);
  await page.goto('vacinas');
  await expect(page.getByText(/Atrasada/).first()).toBeVisible();
  await page.goto('casos');
  await page.getByRole('link', { name: /Doença renal crônica em gatos idosos/ }).click();
  await expect(page.getByText('Encerrado').first()).toBeVisible();

  await page.goto('configuracoes/dados-de-demonstracao');
  await page.getByRole('checkbox', { name: /Entendo que/ }).check();
  await page.getByRole('button', { name: 'Remover demonstração' }).click();
  await expect(page.getByText(/Demonstração removida: 22 pacientes/)).toBeVisible();
  await page.goto('pacientes?q=Bento');
  await expect(page.getByText('Nenhum paciente encontrado')).toBeVisible();
  expect(errors).toEqual([]);
});
