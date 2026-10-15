/**
 * Suíte obrigatória de isolamento multi-tenant (CLAUDE.md §5.3, §16.2 item 3).
 * Prova em duas camadas: HTTP (API) e banco (RLS com o papel real da aplicação).
 */
import type { INestApplication } from '@nestjs/common';
import { Client, Pool } from 'pg';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { SEED } from '../../src/db/seed';
import { testUrls } from '../env';
import { API, bootApp, loginInTenant } from '../helpers';

const A = SEED.tenantA.id;
const B = SEED.tenantB.id;
const unitsA = SEED.units.filter((u) => u.tenant === 'tenantA').map((u) => u.id);
const unitB = SEED.units.find((u) => u.tenant === 'tenantB')!.id;
const [ana, , , , diana, eva] = SEED.users;

let app: INestApplication;
beforeAll(async () => {
  app = await bootApp();
});
afterAll(async () => {
  await app.close();
});

describe('isolamento via API', () => {
  it('tenant A lista somente unidades de A', async () => {
    const s = await loginInTenant(app, ana.email, A);
    const res = await s.agent.get(`${API}/units`).expect(200);
    const ids = res.body.map((u: { id: string }) => u.id);
    expect(ids).toEqual(expect.arrayContaining(unitsA));
    expect(ids).not.toContain(unitB);
  });

  it('ID previsível de unidade de B retorna 404 para A (sem revelar existência)', async () => {
    const s = await loginInTenant(app, ana.email, A);
    await s.agent.get(`${API}/units/${unitB}`).expect(404);
    await s.agent.get(`${API}/units/${unitsA[0]}`).expect(200);
    await s.agent.get(`${API}/units/nao-e-uuid`).expect(404);
  });

  it('tenant_id manipulado em corpo, query e cabeçalho é ignorado', async () => {
    const s = await loginInTenant(app, ana.email, A);
    const created = await s.agent
      .post(`${API}/units?tenantId=${B}`)
      .set('x-csrf-token', s.csrf)
      .set('x-tenant-id', B)
      .send({ name: `Tentativa cruzada ${Date.now()}`, tenantId: B, tenant_id: B })
      .expect(201);
    // A unidade foi criada no tenant da sessão (A), não em B.
    const diana_ = await loginInTenant(app, diana.email, B);
    await diana_.agent.get(`${API}/units/${created.body.id}`).expect(404);
    await s.agent.get(`${API}/units/${created.body.id}`).expect(200);
    const listB = await s.agent.get(`${API}/units?tenantId=${B}`).set('x-tenant-id', B).expect(200);
    expect(listB.body.map((u: { id: string }) => u.id)).not.toContain(unitB);
  });

  it('membros e auditoria de A não incluem dados de B', async () => {
    const s = await loginInTenant(app, ana.email, A);
    const members = await s.agent.get(`${API}/members`).expect(200);
    const emails = members.body.map((m: { email: string }) => m.email);
    expect(emails).toContain(ana.email);
    expect(emails).not.toContain(diana.email);
    const tB = await loginInTenant(app, diana.email, B);
    await tB.agent
      .put(`${API}/tenant/theme`)
      .set('x-csrf-token', tB.csrf)
      .send({ primary: '#7c2d12', secondary: '#1e40af', mode: 'light' })
      .expect(200);
    const auditA = await s.agent.get(`${API}/audit`).expect(200);
    expect(auditA.body.every((e: { targetId: string | null }) => e.targetId !== B)).toBe(true);
  });

  it('usuário com vínculo em A e B vê apenas o tenant ativo e troca de forma explícita', async () => {
    const s = await loginInTenant(app, eva.email, A);
    const inA = await s.agent.get(`${API}/units`).expect(200);
    expect(inA.body.map((u: { id: string }) => u.id)).not.toContain(unitB);
    const mB = s.me.memberships.find((m) => m.tenantId === B)!;
    const switched = await s.agent
      .post(`${API}/auth/context`)
      .set('x-csrf-token', s.csrf)
      .send({ membershipId: mB.membershipId });
    expect(switched.body.context.tenantId).toBe(B);
    const inB = await s.agent.get(`${API}/units`).expect(200);
    expect(inB.body.map((u: { id: string }) => u.id)).toEqual([unitB]);
    const tenant = await s.agent.get(`${API}/tenant`).expect(200);
    expect(tenant.body.id).toBe(B);
  });
});

describe('isolamento no banco (RLS com papel da aplicação)', () => {
  let pool: Pool;
  beforeAll(() => {
    pool = new Pool({ connectionString: testUrls().app, max: 1 });
  });
  afterAll(async () => {
    await pool.end();
  });

  async function inTx<T>(
    tenantId: string | null,
    userId: string | null,
    fn: (c: import('pg').PoolClient) => Promise<T>,
  ) {
    const c = await pool.connect();
    try {
      await c.query('BEGIN');
      await c.query(
        "SELECT set_config('app.tenant_id', $1, true), set_config('app.user_id', $2, true)",
        [tenantId ?? '', userId ?? ''],
      );
      return await fn(c);
    } finally {
      await c.query('ROLLBACK');
      c.release();
    }
  }

  it('papel da aplicação não é superuser, não tem BYPASSRLS e não é dono das tabelas', async () => {
    const r = await pool.query(
      `SELECT rolsuper, rolbypassrls,
              (SELECT count(*)::int FROM pg_tables WHERE schemaname = 'public' AND tableowner = current_user) AS owned
         FROM pg_roles WHERE rolname = current_user`,
    );
    expect(r.rows[0]).toEqual({ rolsuper: false, rolbypassrls: false, owned: 0 });
  });

  it('todas as tabelas com tenant_id ou dados de identidade têm RLS habilitada', async () => {
    const r = await pool.query(
      `SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public' AND c.relkind = 'r' AND c.relname <> 'schema_migrations' AND NOT c.relrowsecurity`,
    );
    expect(r.rows).toEqual([]);
  });

  it('sem contexto, nenhuma linha é visível', async () => {
    await inTx(null, null, async (c) => {
      for (const t of [
        'tenants',
        'units',
        'users',
        'memberships',
        'membership_units',
        'user_sessions',
        'tenant_themes',
        'audit_events',
      ]) {
        const r = await c.query(`SELECT count(*)::int AS n FROM ${t}`);
        expect({ t, n: r.rows[0].n }).toEqual({ t, n: 0 });
      }
    });
  });

  it('com contexto A, consulta direta por tenant_id de B retorna vazio', async () => {
    await inTx(A, null, async (c) => {
      expect((await c.query('SELECT * FROM units WHERE tenant_id = $1', [B])).rowCount).toBe(0);
      expect((await c.query('SELECT * FROM tenants WHERE id = $1', [B])).rowCount).toBe(0);
      expect((await c.query('SELECT * FROM memberships WHERE tenant_id = $1', [B])).rowCount).toBe(
        0,
      );
      expect(
        (await c.query('SELECT count(*)::int AS n FROM units')).rows[0].n,
      ).toBeGreaterThanOrEqual(2);
    });
  });

  it('com contexto A, INSERT com tenant_id de B é bloqueado por WITH CHECK', async () => {
    await expect(
      inTx(A, null, (c) =>
        c.query('INSERT INTO units (tenant_id, name) VALUES ($1, $2)', [B, 'Invasão']),
      ),
    ).rejects.toThrow(/row-level security/);
  });

  it('com contexto A, UPDATE em linha de B não afeta nada e mover linha para B é bloqueado', async () => {
    await inTx(A, null, async (c) => {
      const r = await c.query("UPDATE units SET name = 'x' WHERE id = $1", [unitB]);
      expect(r.rowCount).toBe(0);
    });
    await expect(
      inTx(A, null, (c) =>
        c.query('UPDATE units SET tenant_id = $1 WHERE id = $2', [B, unitsA[0]]),
      ),
    ).rejects.toThrow(/row-level security|violates/);
  });

  it('FK composta impede vincular unidade de B a vínculo de A', async () => {
    await expect(
      inTx(A, null, async (c) => {
        const m = await c.query('SELECT id FROM memberships WHERE tenant_id = $1 LIMIT 1', [A]);
        await c.query(
          'INSERT INTO membership_units (tenant_id, membership_id, unit_id) VALUES ($1, $2, $3)',
          [A, m.rows[0].id, unitB],
        );
      }),
    ).rejects.toThrow(/foreign key/);
  });

  it('contexto é local à transação: não vaza para a próxima requisição no mesmo pool', async () => {
    await inTx(A, null, async (c) => {
      expect((await c.query('SELECT count(*)::int AS n FROM units')).rows[0].n).toBeGreaterThan(0);
    });
    const r = await pool.query(
      "SELECT current_setting('app.tenant_id', true) AS t, (SELECT count(*)::int FROM units) AS n",
    );
    expect(r.rows[0].t ?? '').toBe('');
    expect(r.rows[0].n).toBe(0);
  });

  it('auditoria é append-only mesmo para o dono das tabelas', async () => {
    const c = new Client({ connectionString: testUrls().migration });
    await c.connect();
    try {
      await expect(c.query('DELETE FROM audit_events')).rejects.toThrow(/append-only/);
    } finally {
      await c.end();
    }
    await expect(pool.query('DELETE FROM audit_events')).rejects.toThrow(/permission denied/);
  });

  it('funções de autenticação não expõem dados sem credencial correspondente', async () => {
    const r1 = await pool.query(
      "SELECT * FROM app.auth_resolve_session(decode(repeat('00', 32), 'hex'))",
    );
    expect(r1.rowCount).toBe(0);
    const r2 = await inTx(null, null, (c) => c.query('SELECT * FROM app.my_memberships()'));
    expect(r2.rowCount).toBe(0);
    const r3 = await inTx(null, eva.id, (c) =>
      c.query('SELECT tenant_id FROM app.my_memberships()'),
    );
    expect(r3.rows.map((x) => x.tenant_id).sort()).toEqual([A, B].sort());
  });
});
