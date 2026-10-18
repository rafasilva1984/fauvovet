import { expect, test } from '@playwright/test';
import { join } from 'node:path';
import { USERS, loginInto, trackConsoleErrors } from './helpers';

test('fundos de documento: enviar papel timbrado, prévia, escolher fundo na emissão e remover', async ({ page }) => {
  const errors = trackConsoleErrors(page);
  await loginInto(page, USERS.ana);
  await page.goto('configuracoes/fundos-de-documento');
  await expect(page.getByRole('heading', { level: 1, name: 'Papel timbrado e receituário' })).toBeVisible();
  // PDF fictício com tabela comprimida (como o Word exporta).
  await page
    .getByLabel('Enviar papel timbrado')
    .setInputFiles(join(process.cwd(), 'tests/fixtures/timbrado-ficticio-xref-comprimida.pdf'));
  await page.locator('button[type=submit]', { hasText: 'Enviar papel timbrado' }).click();
  await expect(page.getByText(/Papel timbrado enviado: já é o fundo padrão/)).toBeVisible();
  await expect(page.getByText('timbrado-ficticio-xref-comprimida.pdf')).toBeVisible();
  const preview = await page.request.get(
    (await page.getByRole('link', { name: 'Ver prévia (PDF) do papel timbrado' }).getAttribute('href')) ?? '',
  );
  expect(preview.headers()['content-type']).toBe('application/pdf');

  await page.goto('pacientes/01K7FV0000000000000PTA0001/documentos/novo');
  await page.getByRole('link', { name: /Orientações pós-consulta/ }).click();
  await expect(page.getByRole('combobox', { name: 'Fundo' })).toHaveValue('auto');
  await page.getByRole('combobox', { name: 'Fundo' }).selectOption('letterhead');
  await page.getByRole('button', { name: 'Emitir PDF' }).click();
  await expect(page.getByText('Documento emitido.', { exact: false })).toBeVisible();

  // Limpa para os demais testes: tira o timbre de uso (os documentos emitidos não mudam).
  await page.goto('configuracoes/fundos-de-documento');
  await page.getByText('Parar de usar o papel timbrado').click();
  await page.getByRole('button', { name: 'Remover do uso' }).click();
  await expect(page.getByText(/removido do uso/)).toBeVisible();
  expect(errors).toEqual([]);
});
