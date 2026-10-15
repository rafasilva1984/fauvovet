import type { INestApplication } from '@nestjs/common';
import request from 'supertest';
import type TestAgent from 'supertest/lib/agent';
import { createApp } from '../src/app.factory';
import { SEED_PASSWORD, testConfig } from './env';

export const API = '/fauvovet/api/v1';

export async function bootApp(overrides: Record<string, string> = {}): Promise<INestApplication> {
  const app = await createApp(testConfig(overrides));
  await app.init();
  return app;
}

export interface Session {
  agent: TestAgent;
  csrf: string;
  me: {
    user: { id: string };
    memberships: { membershipId: string; tenantId: string; units: { id: string }[] }[];
    context: { tenantId: string; unitId: string | null; roles: string[] } | null;
  };
}

export async function login(
  app: INestApplication,
  email: string,
  password = SEED_PASSWORD,
): Promise<Session> {
  const agent = request.agent(app.getHttpServer());
  const res = await agent.post(`${API}/auth/login`).send({ email, password });
  if (res.status !== 200)
    throw new Error(`login falhou para ${email}: ${res.status} ${JSON.stringify(res.body)}`);
  return { agent, csrf: res.body.csrfToken, me: res.body };
}

/** Login + seleção do primeiro vínculo/unidade do tenant indicado. */
export async function loginInTenant(
  app: INestApplication,
  email: string,
  tenantId: string,
): Promise<Session> {
  const s = await login(app, email);
  const m = s.me.memberships.find((x) => x.tenantId === tenantId);
  if (!m) throw new Error(`${email} sem vínculo com ${tenantId}`);
  const res = await s.agent
    .post(`${API}/auth/context`)
    .set('x-csrf-token', s.csrf)
    .send({ membershipId: m.membershipId, unitId: m.units[0]?.id });
  if (res.status !== 200)
    throw new Error(`context falhou: ${res.status} ${JSON.stringify(res.body)}`);
  return { ...s, me: res.body };
}
