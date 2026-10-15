import { Client } from 'pg';
import { DEFAULT_THEME } from '@fauvovet/contracts';
import { hashPassword } from '../auth/tokens';

/**
 * Dados 100% FICTÍCIOS para desenvolvimento e testes: dois tenants (A e B) e um usuário com
 * vínculo em ambos. Domínios .test são reservados (RFC 2606) e nunca resolvem na internet.
 * Idempotente: IDs fixos + ON CONFLICT.
 */
export const SEED = {
  tenantA: {
    id: '0a000000-0000-4000-8000-00000000000a',
    name: 'Clínica Veterinária Aurora (fictícia)',
    personType: 'PJ',
    document: '11222333000181',
  },
  tenantB: {
    id: '0b000000-0000-4000-8000-00000000000b',
    name: 'Vet Horizonte Domiciliar (fictício)',
    personType: 'PF',
    document: null,
  },
  units: [
    { id: '0a000000-0000-4000-8000-0000000000a1', tenant: 'tenantA', name: 'Aurora — Centro' },
    { id: '0a000000-0000-4000-8000-0000000000a2', tenant: 'tenantA', name: 'Aurora — Zona Sul' },
    {
      id: '0b000000-0000-4000-8000-0000000000b1',
      tenant: 'tenantB',
      name: 'Atendimento domiciliar',
    },
  ],
  users: [
    {
      id: '0a000000-0000-4000-8000-0000000a0001',
      email: 'ana.titular@aurora.exemplo.test',
      name: 'Ana Titular (fictícia)',
      memberships: [['tenantA', ['tenant_owner', 'veterinarian']]],
    },
    {
      id: '0a000000-0000-4000-8000-0000000a0002',
      email: 'bruno.vet@aurora.exemplo.test',
      name: 'Bruno Veterinário (fictício)',
      memberships: [['tenantA', ['veterinarian']]],
    },
    {
      id: '0a000000-0000-4000-8000-0000000a0003',
      email: 'carla.recepcao@aurora.exemplo.test',
      name: 'Carla Recepção (fictícia)',
      memberships: [['tenantA', ['receptionist']]],
    },
    {
      id: '0a000000-0000-4000-8000-0000000a0004',
      email: 'davi.financeiro@aurora.exemplo.test',
      name: 'Davi Financeiro (fictício)',
      memberships: [['tenantA', ['finance']]],
    },
    {
      id: '0b000000-0000-4000-8000-0000000b0001',
      email: 'diana.titular@horizonte.exemplo.test',
      name: 'Diana Titular (fictícia)',
      memberships: [['tenantB', ['tenant_owner', 'veterinarian']]],
    },
    {
      id: '0c000000-0000-4000-8000-0000000c0001',
      email: 'eva.plantonista@exemplo.test',
      name: 'Eva Plantonista (fictícia)',
      memberships: [
        ['tenantA', ['veterinarian']],
        ['tenantB', ['veterinarian']],
      ],
    },
  ],
} as const;

export async function seed(
  migrationUrl: string,
  password: string,
  log: (m: string) => void = console.log,
): Promise<void> {
  const client = new Client({ connectionString: migrationUrl, application_name: 'fauvovet-seed' });
  await client.connect();
  try {
    await client.query('BEGIN');
    for (const key of ['tenantA', 'tenantB'] as const) {
      const t = SEED[key];
      await client.query(
        `INSERT INTO tenants (id, trade_name, person_type, document_number) VALUES ($1, $2, $3, $4)
         ON CONFLICT (id) DO UPDATE SET trade_name = EXCLUDED.trade_name`,
        [t.id, t.name, t.personType, t.document],
      );
      await client.query(
        `INSERT INTO tenant_themes (tenant_id, primary_color, secondary_color, mode) VALUES ($1, $2, $3, 'system')
         ON CONFLICT (tenant_id) DO NOTHING`,
        [
          t.id,
          key === 'tenantA' ? DEFAULT_THEME.primary : '#7c2d12',
          key === 'tenantA' ? DEFAULT_THEME.secondary : '#1e40af',
        ],
      );
    }
    for (const u of SEED.units) {
      await client.query(
        `INSERT INTO units (id, tenant_id, name) VALUES ($1, $2, $3) ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name`,
        [u.id, SEED[u.tenant].id, u.name],
      );
    }
    const passwordHash = await hashPassword(password);
    for (const u of SEED.users) {
      await client.query(
        `INSERT INTO users (id, email, name, password_hash) VALUES ($1, $2, $3, $4)
         ON CONFLICT (id) DO UPDATE SET password_hash = EXCLUDED.password_hash, failed_login_count = 0, locked_until = NULL`,
        [u.id, u.email, u.name, passwordHash],
      );
      for (const [tenantKey, roles] of u.memberships) {
        await client.query(
          `INSERT INTO memberships (tenant_id, user_id, roles) VALUES ($1, $2, $3)
           ON CONFLICT (tenant_id, user_id) DO UPDATE SET roles = EXCLUDED.roles, status = 'active'`,
          [SEED[tenantKey].id, u.id, roles],
        );
      }
    }
    await client.query('COMMIT');
    log(
      `Seed fictício aplicado: 2 tenants, ${SEED.units.length} unidades, ${SEED.users.length} usuários.`,
    );
  } catch (err) {
    await client.query('ROLLBACK');
    throw err;
  } finally {
    await client.end();
  }
}
