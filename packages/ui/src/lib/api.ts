import type { ListResponse, RecordData, Ref, RuntimeConfig, Schema } from './types';

/** Erro da API no formato Problem Details (RFC 9457). */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly title: string,
    public readonly detail: string | null = null,
    public readonly errors: Record<string, string[]> = {},
  ) {
    super(detail ?? title);
  }

  /** Sessão expirada (401) ou token CSRF inválido (419): só recarregar resolve. */
  get needsReload(): boolean {
    return this.status === 401 || this.status === 419;
  }
}

type Query = Record<string, string | undefined>;

export interface Api {
  schema(): Promise<Schema>;
  list(entity: string, query?: Query): Promise<ListResponse>;
  get(entity: string, id: string): Promise<{ data: RecordData }>;
  create(entity: string, data: Record<string, unknown>): Promise<{ data: RecordData }>;
  update(entity: string, id: string, data: Record<string, unknown>): Promise<{ data: RecordData }>;
  remove(entity: string, id: string): Promise<void>;
  lookup(entity: string, q: string): Promise<{ data: Ref[] }>;
}

/** Lê o cookie XSRF-TOKEN que o Laravel envia; o header X-XSRF-TOKEN satisfaz o CSRF. */
export function xsrfToken(cookies: string = document.cookie): string | null {
  const match = cookies.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));

  return match ? decodeURIComponent(match.slice('XSRF-TOKEN='.length)) : null;
}

export function createApi(base: string, fetcher: typeof fetch = (...args) => fetch(...args)): Api {
  const root = new URL(base.replace(/\/+$/, '') + '/', document.baseURI);
  const crossOrigin = root.origin !== window.location.origin;

  async function request<T>(method: string, path: string, query: Query = {}, body?: unknown): Promise<T> {
    const url = new URL(path, root);
    for (const [name, value] of Object.entries(query)) {
      if (value !== undefined && value !== '') url.searchParams.set(name, value);
    }

    const headers: Record<string, string> = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const token = xsrfToken();
    if (token) headers['X-XSRF-TOKEN'] = token;
    if (body !== undefined) headers['Content-Type'] = 'application/json';

    const response = await fetcher(url, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      credentials: crossOrigin ? 'include' : 'same-origin',
    });

    if (response.status === 204) return undefined as T;

    const payload: unknown = await response.json().catch(() => null);

    if (!response.ok) {
      const problem = (payload ?? {}) as { title?: string; detail?: string; errors?: Record<string, string[]> };
      throw new ApiError(response.status, problem.title ?? 'Erro inesperado', problem.detail ?? null, problem.errors ?? {});
    }

    return payload as T;
  }

  const entity = (key: string, id?: string) =>
    `entities/${encodeURIComponent(key)}${id === undefined ? '' : '/' + encodeURIComponent(id)}`;

  return {
    schema: () => request('GET', 'schema'),
    list: (key, query = {}) => request('GET', entity(key), query),
    get: (key, id) => request('GET', entity(key, id)),
    create: (key, data) => request('POST', entity(key), {}, data),
    update: (key, id, data) => request('PATCH', entity(key, id), {}, data),
    remove: (key, id) => request('DELETE', entity(key, id)),
    lookup: (key, q) => request('GET', entity(key) + '/lookup', { q }),
  };
}

/** Logout via POST com o token CSRF (um link GET permitiria deslogar o usuário por CSRF). */
export async function logout(url: string, fetcher: typeof fetch = (...args) => fetch(...args)): Promise<void> {
  const token = xsrfToken();
  await fetcher(new URL(url, document.baseURI), {
    method: 'POST',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(token ? { 'X-XSRF-TOKEN': token } : {}) },
    credentials: 'same-origin',
  }).catch(() => undefined);
  window.location.reload();
}

export async function loadConfig(fetcher: typeof fetch = (...args) => fetch(...args)): Promise<RuntimeConfig> {
  const response = await fetcher(new URL('config.json', document.baseURI), {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });

  if (!response.ok) throw new ApiError(response.status, 'Não foi possível carregar a configuração');

  return (await response.json()) as RuntimeConfig;
}
