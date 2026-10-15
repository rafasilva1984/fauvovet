import type { INestApplication } from '@nestjs/common';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { SEED } from '../src/db/seed';
import { API, bootApp, loginInTenant } from './helpers';

let app: INestApplication;
beforeAll(async () => {
  app = await bootApp();
});
afterAll(async () => {
  await app.close();
});

const A = SEED.tenantA.id;
const [ana, bruno, carla, davi] = SEED.users;

describe('RBAC — negar por padrão, verificado no backend', () => {
  it('recepção lê unidades mas não membros, auditoria ou tema', async () => {
    const s = await loginInTenant(app, carla.email, A);
    await s.agent.get(`${API}/units`).expect(200);
    await s.agent.get(`${API}/members`).expect(403);
    await s.agent.get(`${API}/audit`).expect(403);
    await s.agent
      .put(`${API}/tenant/theme`)
      .set('x-csrf-token', s.csrf)
      .send({ primary: '#0e5e66', secondary: '#7a4fa3', mode: 'light' })
      .expect(403);
  });

  it('veterinário não cria unidades', async () => {
    const s = await loginInTenant(app, bruno.email, A);
    await s.agent
      .post(`${API}/units`)
      .set('x-csrf-token', s.csrf)
      .send({ name: 'Unidade X' })
      .expect(403);
  });

  it('financeiro não tem permissões clínicas no contexto', async () => {
    const s = await loginInTenant(app, davi.email, A);
    expect(s.me.context!.roles).toEqual(['finance']);
    const me = await s.agent.get(`${API}/auth/me`).expect(200);
    expect(me.body.context.permissions).not.toContain('clinical:read');
    expect(me.body.context.dashboard).toBe('finance');
  });

  it('titular atualiza tema com contraste válido e gera auditoria', async () => {
    const s = await loginInTenant(app, ana.email, A);
    const res = await s.agent
      .put(`${API}/tenant/theme`)
      .set('x-csrf-token', s.csrf)
      .send({ primary: '#1D4E89', secondary: '#7a4fa3', mode: 'dark' })
      .expect(200);
    expect(res.body.primary).toBe('#1d4e89');
    const tenant = await s.agent.get(`${API}/tenant`).expect(200);
    expect(tenant.body.theme).toMatchObject({ primary: '#1d4e89', mode: 'dark' });
    const audit = await s.agent.get(`${API}/audit`).expect(200);
    expect(audit.body.some((e: { action: string }) => e.action === 'tenant.theme_updated')).toBe(
      true,
    );
  });

  it('titular não salva tema sem contraste AA', async () => {
    const s = await loginInTenant(app, ana.email, A);
    const res = await s.agent
      .put(`${API}/tenant/theme`)
      .set('x-csrf-token', s.csrf)
      .send({ primary: '#808080', secondary: '#7a4fa3', mode: 'light' });
    expect(res.status).toBe(422);
  });

  it('titular cria unidade; nome duplicado no mesmo tenant gera 409', async () => {
    const s = await loginInTenant(app, ana.email, A);
    const name = `Aurora — Norte ${Date.now()}`;
    const created = await s.agent
      .post(`${API}/units`)
      .set('x-csrf-token', s.csrf)
      .send({ name, city: 'Cidade Fictícia', state: 'sp' })
      .expect(201);
    expect(created.body.state).toBe('SP');
    await s.agent.post(`${API}/units`).set('x-csrf-token', s.csrf).send({ name }).expect(409);
  });
});
