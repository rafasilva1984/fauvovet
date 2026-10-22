import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { USERS, loginInto, trackConsoleErrors } from './helpers';

test('veterinário cadastra medicamento, monta receita com dose pelo peso e emite sobre o receituário', async ({
  page,
}) => {
  const errors = trackConsoleErrors(page);
  const name = `Cefalexina e2e ${Date.now()}`;
  await loginInto(page, USERS.bruno);

  await page.goto('configuracoes/medicamentos/novo');
  await page.getByLabel('Nome (como sai na receita)').fill(name);
  await page.getByLabel('Classe').fill('Cefalosporinas');
  await page.getByLabel('Forma').selectOption('tablet');
  await page.getByLabel('Concentração (mg por unidade ou por mL)').fill('500');
  await page.getByLabel('Dose usual (mg/kg)').fill('20');
  await page.getByLabel('Faixa mínima (mg/kg)').fill('15');
  await page.getByLabel('Faixa máxima (mg/kg)').fill('30');
  await page.getByLabel('Via', { exact: true }).selectOption('Oral');
  await page.getByLabel('Frequência').selectOption('A cada 12 horas');
  await page.getByLabel('Duração (dias)').fill('10');
  await page.getByRole('button', { name: 'Salvar' }).click();
  await expect(page.getByRole('main').getByRole('status').filter({ hasText: 'Medicamento “' })).toContainText(name);

  await page.goto('pacientes/01K7FV0000000000000PTA0001#documentos'); // Thor, 28,15 kg
  await page.getByRole('link', { name: 'Nova receita' }).first().click();
  await expect(page.getByText('Alergias registradas:')).toBeVisible();
  await page
    .getByLabel('Do catálogo')
    .first()
    .selectOption({ label: `${name} — comprimido 500 mg` });
  await page.getByRole('button', { name: 'Calcular e revisar' }).click();
  // 20 mg/kg × 28,15 kg = 563 mg → 1,126 comprimido → arredonda para 1 ¼ (625 mg) = 22,2 mg/kg efetivos.
  await expect(page.getByTestId('calc-0')).toContainText('1 ¼ comprimido (625 mg)');
  await expect(page.getByTestId('calc-0')).toContainText('dose efetiva 22,2 mg/kg');
  await expect(page.getByTestId('rx-preview')).toContainText('a cada 12 horas, por 10 dias.');
  const a11y = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
  expect(a11y.violations.map((v) => `${v.id}: ${v.help}`)).toEqual([]);
  // Celular: nenhum campo da receita pode passar da borda da tela.
  const clipped = await page.evaluate(() =>
    [...document.querySelectorAll('main *')]
      .filter((el) => !el.closest('.sr-only, [hidden]') && el.getBoundingClientRect().right > innerWidth + 1)
      .map((el) => el.tagName),
  );
  expect(clipped).toEqual([]);

  await page.getByRole('button', { name: 'Emitir receita' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('Receita emitida');
  await expect(page.getByTestId('rx-items')).toContainText(name);
  await expect(page.getByTestId('rx-items')).toContainText('20 mg/kg × 28,15 kg');
  const href = await page.getByRole('link', { name: 'Abrir PDF' }).getAttribute('href');
  const res = await page.request.get(href!);
  expect(res.headers()['content-type']).toBe('application/pdf');

  // Anula para não acumular "em uso" entre execuções.
  await page.getByLabel('Motivo').fill('Teste e2e (fictício)');
  await page.getByRole('button', { name: 'Anular' }).click();
  await expect(page.getByRole('alert')).toContainText('Documento anulado em');
  expect(errors).toEqual([]);
});
