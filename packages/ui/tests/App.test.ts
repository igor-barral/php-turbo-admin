import { render, screen } from '@testing-library/svelte';
import userEvent from '@testing-library/user-event';
import App from '../src/App.svelte';
import { ApiError } from '../src/lib/api';
import type { RuntimeConfig } from '../src/lib/types';
import { fakeApi, flush } from './fixtures';

const config: RuntimeConfig = { apiBase: '/admin/api/v1', appName: 'Painel', user: { name: 'Igor' }, logoutUrl: '/logout' };

async function go(hash: string): Promise<void> {
  window.location.hash = hash;
  window.dispatchEvent(new HashChangeEvent('hashchange'));
  await flush();
}

it('boots into the dashboard with navigation', async () => {
  render(App, { config, api: fakeApi() });

  expect(screen.getByText('Carregando…')).toBeInTheDocument();
  expect(await screen.findByRole('heading', { name: 'Início' })).toBeInTheDocument();
  expect(document.title).toBe('Painel');
  expect(screen.getByText('Igor')).toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Sair' })).toBeInTheDocument();
  expect(screen.getByRole('navigation', { name: 'Entidades' })).toHaveTextContent('PostsUsuários');
  expect(screen.getAllByRole('link', { name: /Posts/ })[1]).toHaveAttribute('href', '#/posts');
});

it('routes to list, detail, create, edit and not found', async () => {
  const api = fakeApi();
  render(App, { config: { ...config, logoutUrl: null, user: { name: null } }, api });
  await screen.findByRole('heading', { name: 'Início' });

  await go('#/posts');
  expect(await screen.findByRole('heading', { name: 'Posts' })).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Posts', current: 'page' })).toBeInTheDocument();

  await go('#/posts/3');
  expect(await screen.findByRole('heading', { name: 'Post 3' })).toBeInTheDocument();

  await go('#/posts/new');
  expect(await screen.findByRole('heading', { name: 'Novo: Post' })).toBeInTheDocument();

  await go('#/posts/3/edit');
  expect(await screen.findByRole('heading', { name: 'Editar: Post 3' })).toBeInTheDocument();

  await go('#/nope');
  expect(screen.getByText('Página não encontrada.')).toBeInTheDocument();

  await go('#/posts/1/x');
  expect(screen.getByText('Página não encontrada.')).toBeInTheDocument();
});

it('toggles the mobile menu and closes it on navigation', async () => {
  render(App, { config, api: fakeApi() });
  const toggle = await screen.findByRole('button', { name: 'Menu' });

  await userEvent.click(toggle);
  expect(toggle).toHaveAttribute('aria-expanded', 'true');

  await go('#/users');
  expect(toggle).toHaveAttribute('aria-expanded', 'false');
});

it('tells the user when no entity is available', async () => {
  render(App, { config, api: fakeApi({ schema: vi.fn(async () => ({ version: 1, entities: [] })) }) });

  expect(await screen.findByText('Nenhuma entidade disponível para o seu usuário.')).toBeInTheDocument();
});

it('asks to reload when the session expired', async () => {
  render(App, { config, api: fakeApi({ schema: vi.fn(async () => Promise.reject(new ApiError(401, 'Autenticação necessária'))) }) });

  expect(await screen.findByRole('heading', { name: 'Sua sessão expirou.' })).toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Recarregar' })).toBeInTheDocument();
});

it('shows other boot errors', async () => {
  render(App, { config, api: fakeApi({ schema: vi.fn(async () => Promise.reject(new ApiError(500, 'Erro inesperado', 'Tente mais tarde.'))) }) });

  expect(await screen.findByRole('heading', { name: 'Erro inesperado' })).toBeInTheDocument();
  expect(screen.getByText('Tente mais tarde.')).toBeInTheDocument();
});

it('wraps unknown boot failures', async () => {
  render(App, { config, api: fakeApi({ schema: vi.fn(async () => Promise.reject(new TypeError('Failed to fetch'))) }) });

  expect(await screen.findByRole('heading', { name: 'TypeError: Failed to fetch' })).toBeInTheDocument();
});

it('loads config and creates the api by itself', async () => {
  const fetchMock = vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
    const url = String(input);
    const body = url.endsWith('config.json') ? config : { version: 1, entities: [] };
    return new Response(JSON.stringify(body), { status: 200 });
  });

  render(App);

  expect(await screen.findByText('Nenhuma entidade disponível para o seu usuário.')).toBeInTheDocument();
  expect(fetchMock.mock.calls.map(([u]) => new URL(String(u)).pathname)).toEqual(['/config.json', '/admin/api/v1/schema']);
  fetchMock.mockRestore();
});
