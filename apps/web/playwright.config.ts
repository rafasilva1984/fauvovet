import { defineConfig, devices } from '@playwright/test';
import { E2E, WEB_ORIGIN } from './e2e/servers';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  timeout: 30_000,
  // Servidores próprios (API :4100 com banco *_test recriado; web de produção :3100). Ver e2e/servers.ts.
  globalSetup: './e2e/global-setup.ts',
  use: {
    baseURL: `${WEB_ORIGIN}${E2E.basePath}/`,
    trace: 'retain-on-failure',
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
  },
  projects: [
    {
      name: 'desktop',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 900 } },
    },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
});
