import { expect, test } from '@playwright/test';
import { USERS, loginInto, removeDemoIfLoaded, trackConsoleErrors } from './helpers';

test('dados de demonstração: carregar, ver na busca/vacinas/casos e remover', async ({ page }) => {
  const errors = trackConsoleErrors(page);
  await loginInto(page, USERS.diana); // clínica B: o teste remove tudo ao final
  await page.goto('configuracoes/dados-de-demonstracao');
  await removeDemoIfLoaded(page); // tentativa anterior pode ter parado no meio
  await page.getByRole('button', { name: 'Carregar demonstração' }).click();
  await expect(page.getByText(/Demonstração carregada: 22 pacientes/).first()).toBeVisible();

  await page.getByRole('link', { name: 'Busca: dermatite atópica confirmada em cães' }).click();
  await expect(page.getByRole('link', { name: /Bento/ }).first()).toBeVisible();
  await page.goto('configuracoes/dados-de-demonstracao');
  await page.getByRole('link', { name: 'Busca clínica: uveíte em aves' }).click();
  await expect(page.getByRole('link', { name: /Sol/ }).first()).toBeVisible();
  await expect(page.locator('.fv-snippet mark').first()).toHaveText(/uveíte/i);
  await page.goto('configuracoes/dados-de-demonstracao');
  await page.getByRole('link', { name: /tonometria/ }).click();
  await expect(page.getByText('Anexo PDF: Laudo oftalmológico').first()).toBeVisible();
  // Campo principal de Pacientes: palavra do prontuário (aspas curvas e "ou" minúsculo, como no celular).
  await page.goto('pacientes');
  await page
    .getByRole('searchbox', { name: /qualquer palavra do prontuário/ })
    .fill('“recusa alimentar” ou “não se alimenta”');
  await page.getByRole('button', { name: 'Buscar', exact: true }).click();
  // Simba e Frajola (demo) + Jabu (seed da clínica B: "Recusa alimentar há 5 dias").
  await expect(page.getByRole('heading', { name: 'No prontuário: 3 pacientes' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Simba' })).toBeVisible();
  await page.goto('exames');
  await expect(page.getByText('Bento · Citologia')).toBeVisible();
  await page.goto('busca?q=coceira');
  await expect(page.getByText('Sinônimos da clínica usados')).toBeVisible();
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
