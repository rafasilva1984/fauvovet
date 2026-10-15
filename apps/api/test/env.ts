import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { assertTestDatabase, loadConfig, type Env } from '../src/config/env';

const envFile = join(__dirname, '..', '..', '..', '.env');
if (existsSync(envFile)) process.loadEnvFile(envFile);

export function testUrls() {
  const app = process.env.TEST_DATABASE_URL;
  const migration = process.env.TEST_MIGRATION_DATABASE_URL;
  if (!app || !migration)
    throw new Error('Defina TEST_DATABASE_URL e TEST_MIGRATION_DATABASE_URL (veja .env.example).');
  assertTestDatabase(app);
  assertTestDatabase(migration);
  return { app, migration };
}

export function testConfig(overrides: Record<string, string> = {}): Env {
  return loadConfig({
    NODE_ENV: 'test',
    BASE_PATH: '/fauvovet',
    PUBLIC_ORIGIN: 'http://localhost:3000',
    DATABASE_URL: testUrls().app,
    DB_POOL_MAX: '4',
    COOKIE_SECURE: 'false',
    ALLOW_SELF_SIGNUP: 'true',
    LOGIN_RATE_LIMIT_PER_15MIN: '10000',
    SIGNUP_RATE_LIMIT_PER_HOUR: '10000',
    ...overrides,
  });
}

export const SEED_PASSWORD = 'senha-de-teste-ficticia';
