import { expect, test } from '@playwright/test';
import { USERS, loginInto, logout, trackConsoleErrors } from './helpers';

test('recepção agenda, registra chegada e o veterinário atende e conclui', async ({ page, isMobile }) => {
  const errors = trackConsoleErrors(page);
  await loginInto(page, USERS.carla);
  await page
    .getByRole('navigation', { name: 'Principal' })
    .locator('visible=true')
    .getByRole('link', { name: 'Agenda' })
    .click();
  await expect(page.getByRole('heading', { level: 1, name: 'Agenda' })).toBeVisible();

  await page.getByRole('link', { name: 'Novo agendamento' }).click();
  await page.getByLabel('Buscar paciente, responsável ou telefone').fill('Kiwi');
  await page.getByRole('button', { name: 'Buscar' }).click();
  await page.getByRole('link', { name: 'Escolher Kiwi' }).click();
  // Sem profissional definido → não disputa horário com o seed nem com o outro projeto do E2E.
  await page.getByRole('combobox', { name: 'Tipo de atendimento' }).selectOption({ label: 'Vacinação (15 min)' });
  await page.getByLabel('Hora').fill(isMobile ? '07:15' : '07:00');
  await page.getByLabel('Duração (min)').fill('15');
  await page.getByRole('button', { name: 'Agendar', exact: true }).click();

  await expect(page.getByRole('main').getByRole('status')).toContainText('Agendado para');
  const card = page.getByRole('listitem').filter({ hasText: isMobile ? '07:15–07:30' : '07:00–07:15' });
  await expect(card.getByRole('link', { name: 'Kiwi' })).toBeVisible();
  await card.getByRole('button', { name: 'Registrar chegada — Kiwi' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('Aguardando: Kiwi');
  await expect(page.getByRole('heading', { name: /Fila de espera agora/ })).toBeVisible();
  // Recepção não inicia o atendimento.
  await expect(page.getByRole('button', { name: 'Iniciar atendimento — Kiwi' })).toHaveCount(0);
  expect(errors).toEqual([]);
  await logout(page, isMobile);

  await loginInto(page, USERS.bruno);
  await page.goto('agenda');
  const card2 = page.getByRole('listitem').filter({ hasText: isMobile ? '07:15–07:30' : '07:00–07:15' });
  await card2.getByRole('button', { name: 'Iniciar atendimento — Kiwi' }).click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('Em atendimento: Kiwi');
  await page
    .getByRole('listitem')
    .filter({ hasText: isMobile ? '07:15–07:30' : '07:00–07:15' })
    .getByRole('button', { name: 'Concluir — Kiwi' })
    .click();
  await expect(page.getByRole('main').getByRole('status')).toContainText('Concluído: Kiwi');
});

test('conflito de horário mostra mensagem clara e semana lista os dias', async ({ page }) => {
  await loginInto(page, USERS.ana);
  await page.goto('agenda/novo?paciente=01K7FV0000000000000PTA0003');
  await page.getByRole('combobox', { name: 'Profissional' }).selectOption({ label: 'Bruno Veterinário (fictício)' });
  await page.getByLabel('Hora').fill('09:10'); // seed: Thor com Bruno às 09:00–09:30 de hoje
  await page.getByRole('button', { name: 'Agendar', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('O profissional já tem Thor das 09:00 às 09:30');

  await page.goto('agenda?visao=semana');
  await expect(page.getByRole('heading', { name: /^Semana de/ })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Semana', exact: true })).toHaveAttribute('aria-current', 'page');
});

test('clínica B não vê a agenda da clínica A', async ({ page }) => {
  await loginInto(page, USERS.diana);
  await page.goto('agenda');
  await expect(page.getByRole('main').getByRole('link', { name: 'Jabu' })).toBeVisible();
  await expect(page.getByText('Thor', { exact: true })).toHaveCount(0);
  const res = await page.goto('agenda/01K7FV0000000000000APA0001');
  expect(res?.status()).toBe(404);
});
