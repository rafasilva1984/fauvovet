import { migrate, roleFromUrl } from './migrate';

async function main() {
  const migrationUrl = process.env.MIGRATION_DATABASE_URL;
  const appUrl = process.env.DATABASE_URL;
  if (!migrationUrl || !appUrl)
    throw new Error('Defina MIGRATION_DATABASE_URL e DATABASE_URL (veja .env.example).');
  const target = new URL(migrationUrl);
  if (
    process.env.NODE_ENV !== 'production' &&
    !['127.0.0.1', 'localhost'].includes(target.hostname)
  ) {
    throw new Error(
      `Recusado: NODE_ENV=${process.env.NODE_ENV ?? 'undefined'} com banco não local (${target.hostname}).`,
    );
  }
  const applied = await migrate({ migrationUrl, appRole: roleFromUrl(appUrl) });
  console.log(
    applied.length ? `${applied.length} migração(ões) aplicada(s).` : 'Banco já está atualizado.',
  );
}

main().catch((err) => {
  console.error(`ERRO: ${err.message}`);
  process.exit(1);
});
