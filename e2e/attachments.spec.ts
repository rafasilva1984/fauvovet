import { expect, test } from '@playwright/test';
import { USERS, loginInto, trackConsoleErrors } from './helpers';

// PNG 1×1 válido e PDF mínimo (fictícios).
const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
  'base64',
);
const PDF = Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');

test('veterinário anexa imagem e PDF ao prontuário, vê a miniatura e remove', async ({ page }, testInfo) => {
  const errors = trackConsoleErrors(page);
  const tag = `${testInfo.project.name}-${Date.now() % 100000}`;
  await loginInto(page, USERS.bruno);
  await page.goto('pacientes/01K7FV0000000000000PTA0002#anexos'); // Mia

  await page.getByLabel('Arquivo').setInputFiles({ name: 'raio-x.png', mimeType: 'image/png', buffer: PNG });
  await page.getByRole('combobox', { name: 'Categoria' }).selectOption('image');
  await page.getByLabel('Título', { exact: true }).fill(`Raio-X ${tag}`);
  await page.getByRole('button', { name: 'Enviar anexo' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('Anexo enviado');
  const thumb = page.getByRole('img', { name: `Miniatura de Raio-X ${tag}` });
  await expect(thumb).toBeVisible();
  await thumb.scrollIntoViewIfNeeded(); // miniatura com loading="lazy": no celular pode estar fora da tela
  await expect.poll(() => thumb.evaluate((img: HTMLImageElement) => img.naturalWidth)).toBe(1); // carregou de verdade

  await page.getByLabel('Arquivo').setInputFiles({ name: 'hemograma.pdf', mimeType: 'application/pdf', buffer: PDF });
  await page.getByLabel('Título', { exact: true }).fill(`Hemograma ${tag}`);
  await page.getByRole('button', { name: 'Enviar anexo' }).click();
  await expect(page.getByRole('link', { name: `Baixar Hemograma ${tag}` })).toBeVisible();
  const download = page.waitForEvent('download');
  await page.getByRole('link', { name: `Baixar Hemograma ${tag}` }).click();
  expect((await download).suggestedFilename()).toBe('hemograma.pdf');

  // Arquivo disfarçado é recusado pelo conteúdo.
  await page
    .getByLabel('Arquivo')
    .setInputFiles({ name: 'exame.pdf', mimeType: 'application/pdf', buffer: Buffer.from('<?php echo 1;') });
  await page.getByRole('button', { name: 'Enviar anexo' }).click();
  await expect(page.getByText('Tipo de arquivo não aceito').first()).toBeVisible();

  await page.getByText(`Remover da ficha Raio-X ${tag}`).click();
  await page.getByLabel(`Motivo da remoção de Raio-X ${tag}`).fill('Enviado em duplicidade (fictício)');
  await page.getByRole('button', { name: 'Remover' }).first().click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('removido');
  await expect(page.getByRole('img', { name: `Miniatura de Raio-X ${tag}` })).toHaveCount(0);
  expect(errors).toEqual([]);
});
