import { render, screen } from '@testing-library/svelte';
import userEvent from '@testing-library/user-event';
import RecordView from '../src/components/RecordView.svelte';
import { ApiError } from '../src/lib/api';
import { toasts } from '../src/lib/toasts.svelte';
import { fakeApi, flush, posts, record, schema } from './fixtures';

afterEach(() => {
  toasts.items.splice(0);
});

it('shows detail fields and links relations to visible entities', async () => {
  const api = fakeApi({
    get: vi.fn(async () => ({ data: record(3, { relations: { author: { id: 1, title: 'Ana' }, owner: { id: 9, title: '9' } } }) })),
  });
  render(RecordView, { api, entity: posts, entities: schema.entities, id: '3' });

  expect(await screen.findByRole('heading', { name: 'Post 3' })).toBeInTheDocument();
  expect(screen.getByText('Texto')).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Ana' })).toHaveAttribute('href', '#/users/1');
  expect(screen.getByText('9').tagName).toBe('SPAN');
  expect(screen.getByRole('link', { name: 'Editar' })).toHaveAttribute('href', '#/posts/3/edit');
  expect(screen.queryByText('user_id')).toBeNull();
});

it('renders empty to-many relations', async () => {
  const api = fakeApi({ get: vi.fn(async () => ({ data: record(3, { relations: { author: null, tags: [] } }) })) });
  render(RecordView, { api, entity: posts, entities: schema.entities, id: '3' });

  expect(await screen.findByText('tags')).toBeInTheDocument();
  expect(screen.getAllByText('—').length).toBeGreaterThanOrEqual(2);
});

it('deletes after confirmation', async () => {
  const api = fakeApi();
  render(RecordView, { api, entity: posts, entities: schema.entities, id: '3' });

  await userEvent.click(await screen.findByRole('button', { name: 'Excluir' }));
  const dialog = screen.getByRole('alertdialog');
  expect(dialog).toHaveTextContent('Tem certeza de que deseja excluir “Post 3”?');
  expect(screen.getByRole('button', { name: 'Cancelar' })).toHaveFocus();

  await userEvent.click(screen.getAllByRole('button', { name: 'Excluir' }).at(-1)!);
  await flush();

  expect(api.remove).toHaveBeenCalledWith('posts', '3');
  expect(window.location.hash).toBe('#/posts');
  expect(toasts.items.at(-1)?.message).toBe('Registro excluído.');
});

it('cancels deletion with Escape', async () => {
  render(RecordView, { api: fakeApi(), entity: posts, entities: schema.entities, id: '3' });

  await userEvent.click(await screen.findByRole('button', { name: 'Excluir' }));
  await userEvent.keyboard('{Escape}');

  expect(screen.queryByRole('alertdialog')).toBeNull();
});

it('reports delete conflicts and keeps the page', async () => {
  const api = fakeApi({ remove: vi.fn(async () => Promise.reject(new ApiError(409, 'Conflito', 'O registro está em uso.'))) });
  render(RecordView, { api, entity: posts, entities: schema.entities, id: '3' });

  await userEvent.click(await screen.findByRole('button', { name: 'Excluir' }));
  await userEvent.click(screen.getAllByRole('button', { name: 'Excluir' }).at(-1)!);
  await flush();

  expect(toasts.items.at(-1)).toMatchObject({ kind: 'error', message: 'O registro está em uso.' });
  expect(screen.queryByRole('alertdialog')).toBeNull();
});

it('reports unexpected delete errors', async () => {
  const api = fakeApi({ remove: vi.fn(async () => Promise.reject(new Error('rede'))) });
  render(RecordView, { api, entity: posts, entities: schema.entities, id: '3' });

  await userEvent.click(await screen.findByRole('button', { name: 'Excluir' }));
  await userEvent.click(screen.getAllByRole('button', { name: 'Excluir' }).at(-1)!);
  await flush();

  expect(toasts.items.at(-1)?.message).toBe('Error: rede');
});

it('hides actions without abilities', async () => {
  const api = fakeApi({ get: vi.fn(async () => ({ data: record(3, { abilities: { update: false, delete: false } }) })) });
  render(RecordView, { api, entity: posts, entities: schema.entities, id: '3' });

  await screen.findByRole('heading', { name: 'Post 3' });
  expect(screen.queryByRole('button', { name: 'Excluir' })).toBeNull();
  expect(screen.queryByRole('link', { name: 'Editar' })).toBeNull();
});

it('shows load errors', async () => {
  render(RecordView, {
    api: fakeApi({ get: vi.fn(async () => Promise.reject(new ApiError(404, 'Recurso não encontrado'))) }),
    entity: posts,
    entities: schema.entities,
    id: '99',
  });

  expect(await screen.findByRole('alert')).toHaveTextContent('Recurso não encontrado');
});

it('shows unexpected load errors', async () => {
  render(RecordView, { api: fakeApi({ get: vi.fn(async () => Promise.reject(new Error('x'))) }), entity: posts, entities: schema.entities, id: '1' });

  expect(await screen.findByRole('alert')).toHaveTextContent('Error: x');
});
