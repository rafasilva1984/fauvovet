import { expect, test } from '@playwright/test';
import { USERS, loginInto, logout, trackConsoleErrors } from './helpers';

test('veterinário registra vacina e a recepção vê os vencimentos', async ({ page, isMobile }, testInfo) => {
  const errors = trackConsoleErrors(page);
  const lot = `LOTE-${testInfo.project.name}-${Date.now() % 100000}`;
  await loginInto(page, USERS.bruno);
  await page.goto('pacientes/01K7FV0000000000000PTA0002#vacinas'); // Mia (gata)
  await expect(page.getByRole('heading', { name: 'Vacinação' })).toBeVisible();
  await page.getByRole('combobox', { name: 'Vacina' }).selectOption({ label: 'Polivalente felina (V4/V5)' });
  await page.getByRole('textbox', { name: 'Lote', exact: true }).fill(lot);
  await page.getByRole('combobox', { name: 'Local de aplicação' }).selectOption({ index: 1 });
  await page.getByRole('button', { name: 'Registrar vacina' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText(
    'Vacina registrada: Polivalente felina (V4/V5)',
  );
  await expect(page.getByText(/Dose \d em .* próxima/).first()).toBeVisible();

  const res = await page.request.get(`${page.url().split('#')[0]}/vacinas/carteira`);
  expect(res.headers()['content-type']).toBe('application/pdf');
  expect(errors).toEqual([]);
  await logout(page, isMobile);

  await loginInto(page, USERS.carla);
  await page.goto('vacinas');
  await expect(page.getByRole('heading', { level: 1, name: 'Vacinas a vencer' })).toBeVisible();
  await expect(page.getByText('Thor · Polivalente canina (V8/V10)')).toBeVisible(); // seed: reforço atrasado
  await expect(page.getByText(/Atrasada há \d+ dias/).first()).toBeVisible();
});
