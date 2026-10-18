import { expect, test } from '@playwright/test';
import { USERS, loginInto, trackConsoleErrors } from './helpers';

test('exames: pedir na ficha, registrar resultado e revisar', async ({ page }, testInfo) => {
  const errors = trackConsoleErrors(page);
  const marker = `MARCADOR${testInfo.project.name.toUpperCase()}${Date.now() % 100000}`;
  await loginInto(page, USERS.bruno);
  await page.goto('pacientes/01K7FV0000000000000PTA0002'); // Mia
  await page.getByRole('combobox', { name: 'Pedir exame' }).selectOption({ label: 'Urinálise' });
  await page.getByRole('button', { name: 'Solicitar exame' }).click();
  await expect(page.getByRole('heading', { level: 1, name: 'Urinálise' })).toBeVisible();
  await expect(page.getByText('Solicitado', { exact: true }).first()).toBeVisible();

  await page.getByRole('combobox', { name: 'Avançar para' }).selectOption({ label: 'Coletado' });
  await page.getByRole('button', { name: 'Atualizar situação' }).click();
  await expect(page.getByText('Situação: Coletado.')).toBeVisible();

  await page.getByRole('textbox', { name: 'Resultado (texto)' }).fill(`Densidade 1.045; ${marker} (fictício).`);
  await page.getByRole('button', { name: 'Registrar resultado' }).click();
  await expect(page.getByText('Resultado registrado.')).toBeVisible();

  await page.getByRole('textbox', { name: 'Interpretação' }).fill('Sem alterações relevantes (fictício).');
  await page.getByRole('checkbox', { name: /Confirmo a revisão/ }).check();
  await page.getByRole('button', { name: 'Revisar e assinar' }).click();
  await expect(page.getByText(/Revisado e assinado por Bruno/)).toBeVisible();

  await page.goto(`busca?q=${marker}`);
  await expect(page.getByText(/Exame: Urinálise — Revisado/).first()).toBeVisible();
  expect(errors).toEqual([]);
});

test('sinônimos e pesquisa salva na busca clínica', async ({ page }, testInfo) => {
  const errors = trackConsoleErrors(page);
  const word = `inapetenciax${testInfo.project.name}${Date.now() % 100000}`;
  await loginInto(page, USERS.bruno);
  await page.goto('configuracoes/sinonimos');
  await page.getByRole('textbox', { name: 'Expressões equivalentes' }).fill(`${word}\nprurido`);
  await page.getByRole('button', { name: 'Criar grupo' }).click();
  await expect(page.getByText('Grupo de sinônimos criado.')).toBeVisible();

  await page.goto(`busca?q=${word}`);
  await expect(page.getByText('Sinônimos da clínica usados')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Thor' })).toBeVisible();
  await page.getByText('Salvar esta pesquisa').click();
  await page.getByRole('textbox', { name: 'Nome da pesquisa' }).fill(`Prurido ${testInfo.project.name}`);
  await page.getByRole('button', { name: 'Salvar', exact: true }).click();
  await expect(page.getByRole('link', { name: `Prurido ${testInfo.project.name}` })).toBeVisible();
  expect(errors).toEqual([]);
});

test('exames estruturados: faixa no catálogo, valores com situação e gráfico', async ({ page }) => {
  const errors = trackConsoleErrors(page);
  await loginInto(page, USERS.ana);
  await page.goto('configuracoes/exames');
  await page.getByText('Bioquímica renal (ureia e creatinina)').first().click();
  await page.getByRole('link', { name: /Parâmetros e faixas de Bioquímica renal/ }).click();
  await expect(page.getByRole('heading', { level: 1, name: /Parâmetros — Bioquímica renal/ })).toBeVisible();
  const card = page.locator('form', { has: page.getByRole('button', { name: 'Salvar faixa de Creatinina' }) });
  await card.getByRole('combobox', { name: 'Espécie' }).selectOption({ label: 'Gato' });
  await card.getByRole('textbox', { name: 'Mínimo' }).fill('0,8');
  await card.getByRole('textbox', { name: 'Máximo' }).fill('1,8');
  await card.getByRole('button', { name: 'Salvar faixa de Creatinina' }).click();
  await expect(page.getByText('Faixa de “Creatinina” salva.')).toBeVisible();

  await page.goto('pacientes/01K7FV0000000000000PTA0002'); // Mia (gata)
  await page
    .getByRole('combobox', { name: 'Pedir exame' })
    .selectOption({ label: 'Bioquímica renal (ureia e creatinina)' });
  await page.getByRole('button', { name: 'Solicitar exame' }).click();
  await expect(page.getByRole('heading', { level: 2, name: 'Valores do exame' })).toBeVisible();
  const row = page.locator('li', { hasText: 'Creatinina' });
  await expect(row.getByRole('textbox', { name: 'Ref. mín.' })).toHaveValue('0,8'); // faixa da espécie veio do catálogo
  await row.getByRole('textbox', { name: 'Resultado' }).fill('2,4');
  await page.getByRole('button', { name: 'Salvar valores' }).click();
  await expect(page.getByText(/Valores registrados/)).toBeVisible();
  await expect(page.getByText('Acima da faixa').first()).toBeVisible();
  await expect(page.getByText('1 fora da faixa')).toBeVisible();
  expect(errors).toEqual([]);
});
