import { describe, expect, it } from 'vitest';
import { assertTestDatabase, loadConfig } from './env';

const base = { DATABASE_URL: 'postgres://u:p@127.0.0.1:5432/fauvovet_dev' };

describe('configuração', () => {
  it('recusa testes contra banco que não termina com _test', () => {
    expect(() => assertTestDatabase('postgres://u:p@127.0.0.1/fauvovet_dev')).toThrow(/_test/);
    expect(() => assertTestDatabase('postgres://u:p@db.producao.exemplo.test/x_test')).toThrow(
      /local/,
    );
    expect(() => assertTestDatabase('postgres://u:p@127.0.0.1/fauvovet_test')).not.toThrow();
  });
  it('exige cookie Secure em produção', () => {
    expect(() => loadConfig({ ...base, NODE_ENV: 'production', COOKIE_SECURE: 'false' })).toThrow(
      /COOKIE_SECURE/,
    );
  });
  it('valida BASE_PATH', () => {
    expect(loadConfig({ ...base, BASE_PATH: '/fauvovet' }).BASE_PATH).toBe('/fauvovet');
    expect(loadConfig({ ...base, BASE_PATH: '' }).BASE_PATH).toBe('');
    expect(() => loadConfig({ ...base, BASE_PATH: 'fauvovet/' })).toThrow();
  });
  it('cadastro público desabilitado por padrão', () => {
    expect(loadConfig(base).ALLOW_SELF_SIGNUP).toBe(false);
  });
});
