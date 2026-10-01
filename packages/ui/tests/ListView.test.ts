import { render, screen, within } from '@testing-library/svelte';
import userEvent from '@testing-library/user-event';
import ListView from '../src/components/ListView.svelte';
import { ApiError } from '../src/lib/api';
import { fakeApi, flush, listResponse, posts, record, users } from './fixtures';

const params = (init: Record<string, string> = {}) => new URLSearchParams(init);

it('renders index columns, relation titles and row actions', async () => {
  const api = fakeApi();
  render(ListView, { api, entity: posts, query: params() });

  expect(await screen.findByText('Post 2')).toBeInTheDocument();
  const headers = screen.getAllByRole('columnheader').map((h) => h.textContent?.trim());
  expect(headers).toEqual(['Id ▼', 'Título', 'Status', 'Featured', 'Created_at', 'Autor', 'Ações']);
  expect(screen.getAllByText('Ana')).toHaveLength(2);
  expect(screen.getAllByText('Rascunho').length).toBeGreaterThan(0);
  expect(api.list).toHaveBeenCalledWith('posts', {});
  expect(screen.getAllByRole('link', { name: 'Editar' })[0]).toHaveAttribute('href', '#/posts/2/edit');
  expect(screen.getByRole('link', { name: 'Novo' })).toHaveAttribute('href', '#/posts/new');
});

it('hides create and edit when not allowed', async () => {
  const api = fakeApi({ list: vi.fn(async () => listResponse([record(1, { abilities: { update: false, delete: false } })])) });
  render(ListView, { api, entity: { ...posts, abilities: { create: false } }, query: params() });

  await screen.findByText('Post 1');
  expect(screen.queryByRole('link', { name: 'Novo' })).toBeNull();
  expect(screen.queryByRole('link', { name: 'Editar' })).toBeNull();
});

it('shows the empty state', async () => {
  render(ListView, { api: fakeApi({ list: vi.fn(async () => listResponse([])) }), entity: users, query: params() });

  expect(await screen.findByText('Nenhum registro encontrado.')).toBeInTheDocument();
  expect(screen.queryByRole('searchbox')).toBeNull();
});

it('toggles sorting through the url', async () => {
  render(ListView, { api: fakeApi(), entity: posts, query: params({ sort: 'title', cursor: 'abc' }) });

  const title = await screen.findByRole('columnheader', { name: /Título/ });
  expect(title).toHaveAttribute('aria-sort', 'ascending');
  expect(screen.getByRole('columnheader', { name: /^Id/ })).toHaveAttribute('aria-sort', 'none');

  await userEvent.click(within(title).getByRole('button'));
  expect(window.location.hash).toBe('#/posts?sort=-title');
});

it('marks the default sort as descending', async () => {
  render(ListView, { api: fakeApi(), entity: posts, query: params() });

  expect(await screen.findByRole('columnheader', { name: /^Id/ })).toHaveAttribute('aria-sort', 'descending');
});

it('debounces search into the url', async () => {
  vi.useFakeTimers({ shouldAdvanceTime: true });
  render(ListView, { api: fakeApi(), entity: posts, query: params({ q: 'old' }) });

  const input = screen.getByRole('searchbox');
  expect(input).toHaveValue('old');
  await userEvent.clear(input);
  await userEvent.type(input, ' novo ');
  vi.advanceTimersByTime(350);

  expect(window.location.hash).toBe('#/posts?q=novo');
  vi.useRealTimers();
});

it('filters by enum and boolean fields', async () => {
  render(ListView, { api: fakeApi(), entity: posts, query: params({ 'filter[featured]': '1' }) });

  expect(screen.getByRole('combobox', { name: 'Featured' })).toHaveValue('1');
  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Status' }), 'published');

  expect(decodeURIComponent(window.location.hash)).toBe('#/posts?filter[featured]=1&filter[status]=published');

  await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Featured' }), '');
  expect(decodeURIComponent(window.location.hash)).toBe('#/posts');
});

it('navigates cursor pages forward and back', async () => {
  const api = fakeApi({ list: vi.fn(async () => listResponse([record(5)], { hasMore: true, nextCursor: 'c2' })) });
  const view = render(ListView, { api, entity: posts, query: params() });

  await screen.findByText('Post 5');
  expect(screen.getByRole('button', { name: 'Anterior' })).toBeDisabled();
  await userEvent.click(screen.getByRole('button', { name: 'Próxima' }));
  expect(window.location.hash).toBe('#/posts?cursor=c2');

  await view.rerender({ api, entity: posts, query: params({ cursor: 'c2' }) });
  await flush();
  expect(screen.getByRole('button', { name: 'Anterior' })).toBeEnabled();
  await userEvent.click(screen.getByRole('button', { name: 'Anterior' }));
  expect(window.location.hash).toBe('#/posts');
});

it('navigates page based pagination', async () => {
  const api = fakeApi({ list: vi.fn(async () => listResponse([record(5)], { pagination: 'page', page: 2, hasMore: true })) });
  render(ListView, { api, entity: posts, query: params({ page: '2' }) });

  expect(await screen.findByText('Página 2')).toBeInTheDocument();
  await userEvent.click(screen.getByRole('button', { name: 'Próxima' }));
  expect(window.location.hash).toBe('#/posts?page=3');
  await userEvent.click(screen.getByRole('button', { name: 'Anterior' }));
  expect(window.location.hash).toBe('#/posts?page=1');
});

it('shows api errors', async () => {
  const api = fakeApi({ list: vi.fn(async () => Promise.reject(new ApiError(400, 'Requisição inválida', 'Não é possível ordenar por "x".'))) });
  render(ListView, { api, entity: posts, query: params({ sort: 'x' }) });

  expect(await screen.findByRole('alert')).toHaveTextContent('Não é possível ordenar por "x".');
});

it('shows unexpected errors', async () => {
  render(ListView, { api: fakeApi({ list: vi.fn(async () => Promise.reject(new Error('rede'))) }), entity: posts, query: params() });

  expect(await screen.findByRole('alert')).toHaveTextContent('rede');
});

it('ignores out of order responses', async () => {
  let resolveSlow: (value: unknown) => void = () => {};
  const slow = new Promise((resolve) => (resolveSlow = resolve));
  const list = vi.fn()
    .mockImplementationOnce(() => slow)
    .mockImplementationOnce(async () => listResponse([record(9)]));
  const api = fakeApi({ list });
  const view = render(ListView, { api, entity: posts, query: params() });

  await view.rerender({ api, entity: posts, query: params({ q: 'x' }) });
  expect(await screen.findByText('Post 9')).toBeInTheDocument();

  resolveSlow(listResponse([record(1)]));
  await flush();
  expect(screen.queryByText('Post 1')).toBeNull();
});

it('joins to-many relation titles and handles missing ones', async () => {
  const api = fakeApi({
    list: vi.fn(async () => listResponse([
      record(1, { relations: { author: [{ id: 1, title: 'Ana' }, { id: 2, title: 'Bia' }] } }),
      record(2, { relations: {} }),
    ])),
  });
  render(ListView, { api, entity: posts, query: params() });

  expect(await screen.findByText('Ana, Bia')).toBeInTheDocument();
});
