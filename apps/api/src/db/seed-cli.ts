import { seed, SEED } from './seed';

async function main() {
  if (process.env.NODE_ENV === 'production') throw new Error('Seed recusado em produção.');
  const url = process.env.MIGRATION_DATABASE_URL;
  if (!url) throw new Error('Defina MIGRATION_DATABASE_URL (veja .env.example).');
  const host = new URL(url).hostname;
  if (!['127.0.0.1', 'localhost'].includes(host))
    throw new Error(`Seed recusado: banco não local (${host}).`);
  const password = process.env.SEED_PASSWORD;
  if (!password || password.length < 12)
    throw new Error('Defina SEED_PASSWORD (mínimo 12 caracteres) no .env.');
  await seed(url, password);
  console.log('Usuários fictícios (senha = SEED_PASSWORD do .env):');
  for (const u of SEED.users) console.log(`  - ${u.email}`);
}

main().catch((err) => {
  console.error(`ERRO: ${err.message}`);
  process.exit(1);
});
