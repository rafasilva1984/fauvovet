/** Cliente HTTP da API (mesma origem, cookie HttpOnly + cabeçalho CSRF). */
const BASE = `${process.env.NEXT_PUBLIC_BASE_PATH ?? ''}/api/v1`;

let csrfToken: string | null = null;
export function setCsrfToken(token: string | null) {
  csrfToken = token;
}

export class ApiRequestError extends Error {
  constructor(
    readonly status: number,
    readonly code: string,
    message: string,
    readonly details?: { path: string; message: string }[],
  ) {
    super(message);
  }
  fieldErrors(): Record<string, string> {
    const out: Record<string, string> = {};
    for (const d of this.details ?? []) if (!out[d.path]) out[d.path] = d.message;
    return out;
  }
}

export async function api<T>(
  path: string,
  opts: { method?: string; body?: unknown } = {},
): Promise<T> {
  const method = opts.method ?? 'GET';
  const headers: Record<string, string> = { accept: 'application/json' };
  if (opts.body !== undefined) headers['content-type'] = 'application/json';
  if (method !== 'GET' && csrfToken) headers['x-csrf-token'] = csrfToken;
  let res: Response;
  try {
    res = await fetch(`${BASE}${path}`, {
      method,
      headers,
      credentials: 'same-origin',
      cache: 'no-store',
      body: opts.body === undefined ? undefined : JSON.stringify(opts.body),
    });
  } catch {
    throw new ApiRequestError(
      0,
      'network',
      'Sem conexão com o servidor. Verifique sua internet e tente novamente.',
    );
  }
  if (res.status === 204) return undefined as T;
  const data = await res.json().catch(() => null);
  if (!res.ok) {
    const e = data?.error;
    throw new ApiRequestError(
      res.status,
      e?.code ?? 'http_error',
      e?.message ?? `Erro ${res.status}.`,
      e?.details,
    );
  }
  return data as T;
}
