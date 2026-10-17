import { expect, test } from '@playwright/test';
import { USERS, loginInto, trackConsoleErrors } from './helpers';

test('veterinário emite atestado em PDF a partir do prontuário e anula', async ({ page, request }) => {
  const errors = trackConsoleErrors(page);
  await loginInto(page, USERS.bruno);
  await page.goto('pacientes/01K7FV0000000000000PTA0001'); // Thor
  await page.getByRole('link', { name: 'Emitir' }).first().click();
  await page.getByRole('link', { name: /Atestado de saúde/ }).click();
  const body = page.getByLabel('Texto do documento');
  await expect(body).toHaveValue(/Atesto, para os devidos fins, que examinei .* o animal Thor/);
  await body.fill(
    (await body.inputValue())
      .replace('[finalidade: viagem, hospedagem, banho e tosa…]', 'viagem')
      .replace('[prazo]', '10'),
  );
  await page.getByRole('button', { name: 'Emitir PDF' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('Documento emitido');
  await expect(page.getByText('Válido', { exact: true })).toBeVisible();

  // O PDF é servido só para quem está logado, com cabeçalhos de documento privado.
  const href = await page.getByRole('link', { name: 'Abrir PDF' }).getAttribute('href');
  const res = await page.request.get(href!);
  expect(res.status()).toBe(200);
  expect(res.headers()['content-type']).toBe('application/pdf');
  expect((await res.body()).subarray(0, 4).toString()).toBe('%PDF');
  const anon = await request.get(href!, { maxRedirects: 0 });
  expect(anon.status()).toBe(302); // sem sessão → login

  await page.getByLabel('Motivo').fill('Emitido para teste (fictício)');
  await page.getByRole('button', { name: 'Anular' }).click();
  await expect(page.getByRole('alert')).toContainText('Documento anulado em');
  expect(errors).toEqual([]);
});
