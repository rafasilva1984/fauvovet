import type { INestApplication } from '@nestjs/common';
import request from 'supertest';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { SEED } from '../src/db/seed';
import { API, bootApp, login } from './helpers';

let app: INestApplication;
beforeAll(async () => {
  app = await bootApp();
});
afterAll(async () => {
  await app.close();
});

const http = () => request(app.getHttpServer());

describe('health', () => {
  it('responde ok com banco disponível', async () => {
    const res = await http().get(`${API}/health`).expect(200);
    expect(res.body).toMatchObject({ status: 'ok', database: 'up' });
    expect(res.headers['x-request-id']).toBeTruthy();
  });
  it('rota inexistente retorna erro padronizado', async () => {
    const res = await http().get(`${API}/nao-existe`).expect(404);
    expect(res.body.error.code).toBe('not_found');
  });
  it('API não responde fora do basePath /fauvovet', async () => {
    await http().get('/api/v1/health').expect(404);
  });
});

describe('login', () => {
  it('rejeita senha incorreta e e-mail inexistente com a mesma mensagem', async () => {
    const a = await http()
      .post(`${API}/auth/login`)
      .send({ email: SEED.users[1].email, password: 'errada-123456' });
    const b = await http()
      .post(`${API}/auth/login`)
      .send({ email: 'ninguem@exemplo.test', password: 'errada-123456' });
    expect(a.status).toBe(401);
    expect(b.status).toBe(401);
    expect(a.body.error.message).toBe(b.body.error.message);
  });

  it('valida entrada', async () => {
    const res = await http().post(`${API}/auth/login`).send({ email: 'nao-e-email' });
    expect(res.status).toBe(422);
    expect(res.body.error.code).toBe('validation_error');
  });

  it('define cookie HttpOnly, SameSite=Lax e Path=/fauvovet, sem expor token no corpo', async () => {
    const res = await http()
      .post(`${API}/auth/login`)
      .send({ email: SEED.users[1].email, password: 'senha-de-teste-ficticia' })
      .expect(200);
    const cookie = String(res.headers['set-cookie']);
    expect(cookie).toMatch(/fv_session=/);
    expect(cookie).toMatch(/HttpOnly/i);
    expect(cookie).toMatch(/SameSite=Lax/i);
    expect(cookie).toMatch(/Path=\/fauvovet/);
    expect(JSON.stringify(res.body)).not.toContain(cookie.split(';')[0]!.split('=')[1]!);
  });

  it('sem cookie, /auth/me retorna 401', async () => {
    await http().get(`${API}/auth/me`).expect(401);
  });

  it('cookie forjado é rejeitado', async () => {
    await http()
      .get(`${API}/auth/me`)
      .set('Cookie', 'fv_session=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA')
      .expect(401);
  });

  it('seleciona contexto automaticamente para vínculo único com unidade única', async () => {
    const s = await login(app, SEED.users[4].email); // Diana — tenant B, 1 unidade
    expect(s.me.context?.tenantId).toBe(SEED.tenantB.id);
    expect(s.me.context?.unitId).toBe(SEED.units[2].id);
  });

  it('com várias unidades, exige escolha explícita', async () => {
    const s = await login(app, SEED.users[1].email); // Bruno — 2 unidades
    expect(s.me.context).toBeNull();
    const res = await s.agent.get(`${API}/units`).expect(409);
    expect(res.body.error.code).toBe('context_required');
  });
});

describe('CSRF e origem', () => {
  it('mutação sem X-CSRF-Token é recusada', async () => {
    const s = await login(app, SEED.users[1].email);
    const m = s.me.memberships[0]!;
    const res = await s.agent.post(`${API}/auth/context`).send({ membershipId: m.membershipId });
    expect(res.status).toBe(403);
    expect(res.body.error.code).toBe('csrf_failed');
  });

  it('mutação com Origin de outro site é recusada mesmo com token', async () => {
    const s = await login(app, SEED.users[1].email);
    const res = await s.agent
      .post(`${API}/auth/context`)
      .set('x-csrf-token', s.csrf)
      .set('Origin', 'https://site-malicioso.exemplo.test')
      .send({ membershipId: s.me.memberships[0]!.membershipId });
    expect(res.status).toBe(403);
  });

  it('token CSRF de outra sessão não é aceito', async () => {
    const a = await login(app, SEED.users[1].email);
    const b = await login(app, SEED.users[1].email);
    const res = await a.agent
      .post(`${API}/auth/context`)
      .set('x-csrf-token', b.csrf)
      .send({ membershipId: a.me.memberships[0]!.membershipId });
    expect(res.status).toBe(403);
  });
});

describe('contexto e logout', () => {
  it('não aceita vínculo de outro usuário', async () => {
    const diana = await login(app, SEED.users[4].email);
    const bruno = await login(app, SEED.users[1].email);
    const res = await bruno.agent
      .post(`${API}/auth/context`)
      .set('x-csrf-token', bruno.csrf)
      .send({ membershipId: diana.me.memberships[0]!.membershipId });
    expect(res.status).toBe(403);
  });

  it('não aceita unidade de outro tenant no próprio vínculo', async () => {
    const bruno = await login(app, SEED.users[1].email);
    const res = await bruno.agent
      .post(`${API}/auth/context`)
      .set('x-csrf-token', bruno.csrf)
      .send({ membershipId: bruno.me.memberships[0]!.membershipId, unitId: SEED.units[2].id });
    expect(res.status).toBe(403);
  });

  it('logout revoga a sessão no servidor', async () => {
    const s = await login(app, SEED.users[1].email);
    await s.agent.get(`${API}/auth/me`).expect(200);
    await s.agent.post(`${API}/auth/logout`).set('x-csrf-token', s.csrf).expect(204);
    await s.agent.get(`${API}/auth/me`).expect(401);
  });
});

describe('onboarding', () => {
  const base = {
    personType: 'PF',
    tradeName: 'Dra. Exemplo Exóticos (fictícia)',
    ownerName: 'Dra. Exemplo (fictícia)',
    password: 'senha-forte-de-teste-1',
    unitName: 'Atendimento domiciliar',
    timezone: 'America/Manaus',
  };

  it('cria tenant, unidade e titular; login entra direto no novo tenant', async () => {
    const email = `onb-${Date.now()}@exemplo.test`;
    await http()
      .post(`${API}/onboarding`)
      .send({ ...base, email })
      .expect(201);
    const s = await login(app, email, base.password);
    expect(s.me.context?.roles).toEqual(['tenant_owner']);
    const units = await s.agent.get(`${API}/units`).expect(200);
    expect(units.body).toHaveLength(1);
    expect(units.body[0].timezone).toBe('America/Manaus');
  });

  it('rejeita e-mail duplicado com mensagem genérica', async () => {
    const res = await http()
      .post(`${API}/onboarding`)
      .send({ ...base, email: SEED.users[0].email });
    expect(res.status).toBe(409);
  });

  it('rejeita CPF inválido e senha curta', async () => {
    const r1 = await http()
      .post(`${API}/onboarding`)
      .send({ ...base, email: 'x1@exemplo.test', document: '123.456.789-00' });
    const r2 = await http()
      .post(`${API}/onboarding`)
      .send({ ...base, email: 'x2@exemplo.test', password: 'curta' });
    expect(r1.status).toBe(422);
    expect(r2.status).toBe(422);
  });

  it('bloqueia conta após falhas consecutivas', async () => {
    const lockApp = await bootApp({ LOGIN_MAX_FAILURES: '3', LOGIN_LOCK_MINUTES: '5' });
    try {
      const email = `lock-${Date.now()}@exemplo.test`;
      await request(lockApp.getHttpServer())
        .post(`${API}/onboarding`)
        .send({ ...base, email })
        .expect(201);
      for (let i = 0; i < 3; i++) {
        await request(lockApp.getHttpServer())
          .post(`${API}/auth/login`)
          .send({ email, password: 'errada-errada' })
          .expect(401);
      }
      const res = await request(lockApp.getHttpServer())
        .post(`${API}/auth/login`)
        .send({ email, password: base.password });
      expect(res.status).toBe(429);
      expect(res.body.error.code).toBe('account_locked');
    } finally {
      await lockApp.close();
    }
  });

  it('respeita ALLOW_SELF_SIGNUP=false', async () => {
    const closed = await bootApp({ ALLOW_SELF_SIGNUP: 'false' });
    try {
      const res = await request(closed.getHttpServer())
        .post(`${API}/onboarding`)
        .send({ ...base, email: 'z@exemplo.test' });
      expect(res.status).toBe(403);
    } finally {
      await closed.close();
    }
  });
});
