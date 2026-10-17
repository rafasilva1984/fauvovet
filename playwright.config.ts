import { defineConfig, devices } from '@playwright/test';
import { BASE } from './e2e/global-setup';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  // Local (WSL): 1 nova tentativa só para absorver oscilação de rede da máquina (ERR_NETWORK_CHANGED aborta até
  // páginas em 127.0.0.1). Falha nas duas = reprovado; passar só na 2ª aparece como "flaky" no relatório.
  // CI: sem nova tentativa.
  retries: process.env.CI ? 0 : 1,
  reporter: [['list'], ['html', { open: 'never' }]],
  timeout: 30_000,
  globalSetup: './e2e/global-setup.ts',
  use: {
    baseURL: `${BASE}/`,
    trace: 'retain-on-failure',
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 900 } } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
});
