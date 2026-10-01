import { ApiError, createApi, loadConfig, logout, xsrfToken } from '../src/lib/api';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

describe('xsrfToken', () => {
  it('reads and decodes the XSRF-TOKEN cookie', () => {
    expect(xsrfToken('a=1; XSRF-TOKEN=abc%3D%3D; b=2')).toBe('abc==');
    expect(xsrfToken('a=1')).toBeNull();
  });
});

describe('createApi', () => {
  it('builds urls, query strings and headers', async () => {
    document.cookie = 'XSRF-TOKEN=tok%2B1';
    const fetcher = vi.fn(async () => jsonResponse({ data: [] }));
    const api = createApi('/admin/api/v1/', fetcher);

    await api.list('posts', { sort: '-id', 'filter[status]': 'draft', q: '', cursor: undefined });

    const [url, init] = fetcher.mock.calls[0] as unknown as [URL, RequestInit];
    expect(url.pathname).toBe('/admin/api/v1/entities/posts');
    expect(url.searchParams.get('sort')).toBe('-id');
    expect(url.searchParams.get('filter[status]')).toBe('draft');
    expect(url.searchParams.has('q')).toBe(false);
    expect(init.method).toBe('GET');
    expect(init.credentials).toBe('same-origin');
    expect(init.headers).toEqual({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': 'tok+1' });
  });

  it('sends json bodies and encodes path segments', async () => {
    const fetcher = vi.fn(async () => jsonResponse({ data: { id: 1 } }));
    const api = createApi('/api', fetcher);

    await api.update('posts', 'a/b', { title: 'x' });
    await api.create('posts', { title: 'y' });
    await api.get('posts', '7');
    await api.lookup('users', 'an');
    await api.schema();

    const calls = fetcher.mock.calls as unknown as [URL, RequestInit][];
    expect(calls.map(([u, i]) => `${i.method} ${u.pathname}${u.search}`)).toEqual([
      'PATCH /api/entities/posts/a%2Fb',
      'POST /api/entities/posts',
      'GET /api/entities/posts/7',
      'GET /api/entities/users/lookup?q=an',
      'GET /api/schema',
    ]);
    expect(calls[0]?.[1].body).toBe('{"title":"x"}');
    expect((calls[0]?.[1].headers as Record<string, string>)['Content-Type']).toBe('application/json');
  });

  it('returns undefined for 204 responses', async () => {
    const api = createApi('/api', vi.fn(async () => new Response(null, { status: 204 })));

    await expect(api.remove('posts', '1')).resolves.toBeUndefined();
  });

  it('turns problem details into ApiError', async () => {
    const api = createApi('/api', vi.fn(async () => jsonResponse({ title: 'Dados inválidos', status: 422, errors: { title: ['Obrigatório'] } }, 422)));

    const error = await api.create('posts', {}).catch((e: unknown) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({ status: 422, title: 'Dados inválidos', detail: null, errors: { title: ['Obrigatório'] }, needsReload: false });
  });

  it('handles non json errors', async () => {
    const api = createApi('/api', vi.fn(async () => new Response('<html>', { status: 500 })));

    await expect(api.schema()).rejects.toMatchObject({ status: 500, title: 'Erro inesperado' });
  });

  it('flags responses that need a reload', () => {
    expect(new ApiError(401, 'x').needsReload).toBe(true);
    expect(new ApiError(419, 'x').needsReload).toBe(true);
    expect(new ApiError(403, 'x').needsReload).toBe(false);
    expect(new ApiError(400, 'Título', 'Detalhe').message).toBe('Detalhe');
  });

  it('includes credentials for cross origin apis', async () => {
    const fetcher = vi.fn(async () => jsonResponse({}));

    await createApi('https://api.example.com/admin/api/v1', fetcher).schema();

    expect((fetcher.mock.calls[0] as unknown as [URL, RequestInit])[1].credentials).toBe('include');
  });
});

describe('loadConfig', () => {
  it('loads config.json relative to the document base', async () => {
    const fetcher = vi.fn(async () => jsonResponse({ apiBase: '/admin/api/v1' }));

    await expect(loadConfig(fetcher)).resolves.toEqual({ apiBase: '/admin/api/v1' });
    expect((fetcher.mock.calls[0] as unknown as [URL])[0].pathname).toBe('/config.json');
  });

  it('fails clearly when the config is unavailable', async () => {
    await expect(loadConfig(vi.fn(async () => new Response('', { status: 401 })))).rejects.toMatchObject({ status: 401 });
  });
});

describe('logout', () => {
  it('posts with the csrf token and reloads', async () => {
    document.cookie = 'XSRF-TOKEN=abc';
    const reload = vi.fn();
    const original = window.location;
    Object.defineProperty(window, 'location', { configurable: true, value: { ...original, reload, href: original.href } });
    const fetcher = vi.fn(async () => new Response(null, { status: 204 }));

    await logout('/logout', fetcher);
    await logout('/logout', vi.fn(async () => Promise.reject(new Error('offline'))));

    const [url, init] = fetcher.mock.calls[0] as unknown as [URL, RequestInit];
    expect(url.pathname).toBe('/logout');
    expect(init.method).toBe('POST');
    expect((init.headers as Record<string, string>)['X-XSRF-TOKEN']).toBe('abc');
    expect(reload).toHaveBeenCalledTimes(2);
    Object.defineProperty(window, 'location', { configurable: true, value: original });
  });

  it('omits the token header without the cookie', async () => {
    const original = window.location;
    Object.defineProperty(window, 'location', { configurable: true, value: { ...original, reload: vi.fn(), href: original.href } });
    const fetcher = vi.fn(async () => new Response(null, { status: 204 }));

    await logout('/logout', fetcher);

    expect((fetcher.mock.calls[0] as unknown as [URL, RequestInit])[1].headers).not.toHaveProperty('X-XSRF-TOKEN');
    Object.defineProperty(window, 'location', { configurable: true, value: original });
  });
});
