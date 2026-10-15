import { Client } from 'pg';
import { migrate, roleFromUrl } from '../src/db/migrate';
import { seed } from '../src/db/seed';
import { SEED_PASSWORD, testUrls } from './env';

/** Recria o schema do banco *_test do zero, aplica migrações e seed fictício. */
export default async function setup() {
  const { app, migration } = testUrls();
  const c = new Client({ connectionString: migration });
  await c.connect();
  await c.query('DROP SCHEMA IF EXISTS app CASCADE');
  await c.query('DROP SCHEMA IF EXISTS public CASCADE');
  await c.query('CREATE SCHEMA public');
  await c.end();
  await migrate({ migrationUrl: migration, appRole: roleFromUrl(app), log: () => undefined });
  await seed(migration, SEED_PASSWORD, () => undefined);
}
