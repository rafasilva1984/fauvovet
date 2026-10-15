import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiRequestError, api, setCsrfToken } from './api';

afterEach(() => {
  vi.unstubAllGlobals();
  setCsrfToken(null);
});

function stubFetch(status: number, body: unknown) {
  const fn = vi.fn(
    async () => new Response(body === undefined ? null : JSON.stringify(body), { status }),
  );
  vi.stubGlobal('fetch', fn);
  return fn;
}

describe('cliente da API', () => {
  it('envia X-CSRF-Token apenas em métodos mutáveis', async () => {
    setCsrfToken('tok');
    const f = stubFetch(200, { ok: true });
    await api('/units');
    await api('/units', { method: 'POST', body: { name: 'x' } });
    const [, getInit] = f.mock.calls[0] as unknown as [string, RequestInit];
    const [, postInit] = f.mock.calls[1] as unknown as [string, RequestInit];
    expect((getInit.headers as Record<string, string>)['x-csrf-token']).toBeUndefined();
    expect((postInit.headers as Record<string, string>)['x-csrf-token']).toBe('tok');
    expect(postInit.credentials).toBe('same-origin');
  });

  it('converte erro padronizado da API em ApiRequestError com erros por campo', async () => {
    stubFetch(422, {
      error: {
        code: 'validation_error',
        message: 'Dados inválidos.',
        details: [{ path: 'name', message: 'Obrigatório' }],
      },
    });
    const err = await api<never>('/units', { method: 'POST', body: {} }).catch(
      (e: ApiRequestError) => e,
    );
    expect(err).toBeInstanceOf(ApiRequestError);
    expect(err.status).toBe(422);
    expect(err.fieldErrors()).toEqual({ name: 'Obrigatório' });
  });

  it('falha de rede vira mensagem amigável', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => Promise.reject(new TypeError('fetch failed'))),
    );
    const err = await api<never>('/auth/me').catch((e: ApiRequestError) => e);
    expect(err.code).toBe('network');
    expect(err.message).toMatch(/Sem conexão/);
  });

  it('204 retorna undefined', async () => {
    stubFetch(204, undefined);
    await expect(api('/auth/logout', { method: 'POST' })).resolves.toBeUndefined();
  });
});
