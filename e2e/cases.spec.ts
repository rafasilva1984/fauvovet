import { expect, test } from '@playwright/test';
import { USERS, loginInto, trackConsoleErrors } from './helpers';

test('casos científicos: série a partir da busca, consentimento e exportação anonimizada', async ({
  page,
}, testInfo) => {
  const errors = trackConsoleErrors(page);
  const title = `Dermatite em cães ${testInfo.project.name} ${Date.now() % 100000}`;
  await loginInto(page, USERS.bruno);
  await page.goto('pacientes?diagnostico=dermatite&situacao=confirmed');
  await page.getByRole('link', { name: 'Criar série de casos' }).click();
  await expect(page.getByRole('heading', { level: 1, name: 'Nova série de casos' })).toBeVisible();
  await page.getByRole('textbox', { name: 'Título' }).fill(title);
  await page.getByRole('textbox', { name: 'Pergunta clínica ou objetivo' }).fill('Evolução após 30 dias?');
  await page.getByRole('button', { name: /Criar série com 1 caso/ }).click();

  await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();
  await expect(page.getByText('Série de casos criada com 1 caso.')).toBeVisible();
  const entry = page.getByRole('listitem').filter({ hasText: 'Caso 01' });
  await entry.getByRole('combobox', { name: 'Desfecho' }).selectOption({ label: 'Melhora' });
  await entry.getByRole('combobox', { name: 'Consentimento do tutor' }).selectOption({ label: 'Autorizado' });
  await entry.getByRole('textbox', { name: 'Observações do caso' }).fill('Thor respondeu bem ao tratamento.');
  await entry.getByRole('button', { name: 'Salvar Caso 01' }).click();
  await expect(page.getByText('Consentimento: Autorizado')).toBeVisible();

  const csv = await (await page.request.get(`${page.url().split('#')[0]}/exportar.csv`)).text();
  expect(csv).toContain('[nome omitido] respondeu bem');
  expect(csv).toContain('Dermatite atópica (confirmado)');
  expect(csv).not.toContain('Thor');
  expect(errors).toEqual([]);
});
