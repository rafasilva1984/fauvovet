import { createHash } from 'node:crypto';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { Client } from 'pg';

export const MIGRATIONS_DIR = join(__dirname, '..', '..', 'migrations');

export interface MigrateOptions {
  migrationUrl: string;
  appRole: string;
  log?: (msg: string) => void;
}

const ROLE_RE = /^[a-z_][a-z0-9_]{0,62}$/;

/** Aplica migrações SQL versionadas, em ordem, cada uma em sua transação, com checksum. */
export async function migrate({
  migrationUrl,
  appRole,
  log = console.log,
}: MigrateOptions): Promise<string[]> {
  if (!ROLE_RE.test(appRole)) throw new Error(`Nome de papel inválido: ${appRole}`);
  const client = new Client({
    connectionString: migrationUrl,
    application_name: 'fauvovet-migrate',
  });
  await client.connect();
  const applied: string[] = [];
  try {
    await client.query(`CREATE TABLE IF NOT EXISTS schema_migrations (
      version text PRIMARY KEY, checksum text NOT NULL, applied_at timestamptz NOT NULL DEFAULT now())`);
    await client.query('SELECT pg_advisory_lock(7342001)');
    const done = new Map<string, string>(
      (await client.query('SELECT version, checksum FROM schema_migrations')).rows.map((r) => [
        r.version,
        r.checksum,
      ]),
    );
    const files = readdirSync(MIGRATIONS_DIR)
      .filter((f) => /^\d{4}_[a-z0-9_]+\.sql$/.test(f))
      .sort();
    for (const file of files) {
      const raw = readFileSync(join(MIGRATIONS_DIR, file), 'utf8');
      const checksum = createHash('sha256').update(raw).digest('hex');
      const version = file.replace(/\.sql$/, '');
      const previous = done.get(version);
      if (previous) {
        if (previous !== checksum)
          throw new Error(
            `Migração ${version} foi alterada após aplicada (checksum diverge). Crie nova migração.`,
          );
        continue;
      }
      const sql = raw.replaceAll('{{APP_ROLE}}', `"${appRole}"`);
      await client.query('BEGIN');
      try {
        await client.query(sql);
        await client.query('INSERT INTO schema_migrations (version, checksum) VALUES ($1, $2)', [
          version,
          checksum,
        ]);
        await client.query('COMMIT');
      } catch (err) {
        await client.query('ROLLBACK');
        throw new Error(`Falha na migração ${version}: ${(err as Error).message}`, { cause: err });
      }
      applied.push(version);
      log(`migração aplicada: ${version}`);
    }
    await client.query('SELECT pg_advisory_unlock(7342001)');
  } finally {
    await client.end();
  }
  return applied;
}

export function roleFromUrl(url: string): string {
  return decodeURIComponent(new URL(url).username);
}
