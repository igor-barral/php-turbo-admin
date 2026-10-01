import { render, screen } from '@testing-library/svelte';
import userEvent from '@testing-library/user-event';
import FormView from '../src/components/FormView.svelte';
import { ApiError } from '../src/lib/api';
import { toasts } from '../src/lib/toasts.svelte';
import { fakeApi, flush, posts, record, schema } from './fixtures';

afterEach(() => {
  toasts.items.splice(0);
});

it('creates a record with typed values and a relation', async () => {
  vi.useFakeTimers({ shouldAdvanceTime: true });
  const api = fakeApi();
  render(FormView, { api, entity: posts, entities: schema.entities });

  expect(screen.getByRole('heading', { name: 'Novo: Post' })).toBeInTheDocument();
  expect(screen.queryByLabelText(/^Id/)).toBeNull();
  expect(screen.getByLabelText(/Título/)).toBeRequired();

  await userEvent.type(screen.getByLabelText(/Título/), 'Olá');
  await userEvent.selectOptions(screen.getByLabelText('Status'), 'published');
  await userEvent.click(screen.getByLabelText('Featured'));
  await userEvent.clear(screen.getByLabelText('Metadata'));
  await userEvent.type(screen.getByLabelText('Metadata'), '{{"tags":[[1]}');

  await userEvent.type(screen.getByRole('combobox', { name: 'Autor' }), 'an');
  vi.advanceTimersByTime(300);
  await userEvent.click(await screen.findByRole('button', { name: 'Bruno' }));
  expect(screen.getByText('Bruno')).toBeInTheDocument();

  await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));
  await flush();

  expect(api.lookup).toHaveBeenCalledWith('users', 'an');
  expect(api.create).toHaveBeenCalledWith('posts', {
    title: 'Olá',
    status: 'published',
    featured: true,
    metadata: { tags: [1] },
    user_id: 2,
  });
  expect(window.location.hash).toBe('#/posts/10');
  expect(toasts.items.at(-1)?.message).toBe('Registro salvo.');
  vi.useRealTimers();
});

it('edits sending only changed fields', async () => {
  const api = fakeApi();
  render(FormView, { api, entity: posts, entities: schema.entities, id: '4' });

  const title = await screen.findByLabelText(/Título/);
  expect(screen.getByRole('heading', { name: 'Editar: Post 4' })).toBeInTheDocument();
  expect(screen.getByText('Ana')).toBeInTheDocument();
  await userEvent.clear(title);
  await userEvent.type(title, 'Novo título');
  await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));
  await flush();

  expect(api.update).toHaveBeenCalledWith('posts', '4', { title: 'Novo título' });
});

it('clears a nullable relation', async () => {
  const api = fakeApi();
  render(FormView, { api, entity: posts, entities: schema.entities, id: '4' });

  await userEvent.click(await screen.findByRole('button', { name: 'Limpar' }));
  await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));
  await flush();

  expect(api.update).toHaveBeenCalledWith('posts', '4', { user_id: null });
});

it('shows server validation errors per field', async () => {
  const api = fakeApi({
    create: vi.fn(async () => Promise.reject(new ApiError(422, 'Dados inválidos', null, { title: ['Campo obrigatório.'], user_id: ['Autor inválido.'], other: ['Erro geral.'] }))),
  });
  render(FormView, { api, entity: posts, entities: schema.entities });

  await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));
  await flush();

  expect(screen.getByText('Campo obrigatório.')).toBeInTheDocument();
  expect(screen.getByText('Autor inválido.')).toBeInTheDocument();
  expect(screen.getByRole('alert')).toHaveTextContent('other: Erro geral.');
  expect(screen.getByLabelText(/Título/)).toHaveAttribute('aria-invalid', 'true');
  expect(toasts.items.at(-1)?.message).toBe('Corrija os campos destacados.');
});

it('validates json on the client', async () => {
  const api = fakeApi();
  render(FormView, { api, entity: posts, entities: schema.entities });

  await userEvent.type(screen.getByLabelText('Metadata'), '{{');
  await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));
  await flush();

  expect(api.create).not.toHaveBeenCalled();
  expect(screen.getByText('JSON inválido.')).toBeInTheDocument();
});

it('reports other errors', async () => {
  const api = fakeApi({ create: vi.fn(async () => Promise.reject(new ApiError(403, 'Ação não permitida'))) });
  render(FormView, { api, entity: posts, entities: schema.entities });

  await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));
  await flush();
  expect(toasts.items.at(-1)?.message).toBe('Ação não permitida');

  api.create.mockImplementationOnce(async () => Promise.reject('falha'));
  await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));
  await flush();
  expect(toasts.items.at(-1)?.message).toBe('falha');
});

it('shows load errors when editing', async () => {
  render(FormView, {
    api: fakeApi({ get: vi.fn(async () => Promise.reject(new ApiError(404, 'Recurso não encontrado'))) }),
    entity: posts,
    entities: schema.entities,
    id: '9',
  });

  expect(await screen.findByRole('alert')).toHaveTextContent('Recurso não encontrado');
});

it('shows unexpected load errors when editing', async () => {
  render(FormView, { api: fakeApi({ get: vi.fn(async () => Promise.reject(new Error('x'))) }), entity: posts, entities: schema.entities, id: '9' });

  expect(await screen.findByRole('alert')).toHaveTextContent('Error: x');
});

it('links cancel to the right place', async () => {
  const { unmount } = render(FormView, { api: fakeApi(), entity: posts, entities: schema.entities });
  expect(screen.getByRole('link', { name: 'Cancelar' })).toHaveAttribute('href', '#/posts');
  unmount();

  render(FormView, { api: fakeApi(), entity: posts, entities: schema.entities, id: '4' });
  expect(await screen.findByRole('link', { name: 'Cancelar' })).toHaveAttribute('href', '#/posts/4');
});

it('handles relation lookups that fail or return nothing', async () => {
  vi.useFakeTimers({ shouldAdvanceTime: true });
  const lookup = vi.fn()
    .mockImplementationOnce(async () => ({ data: [] }))
    .mockImplementationOnce(async () => Promise.reject(new ApiError(404, 'Recurso não encontrado')))
    .mockImplementationOnce(async () => Promise.reject(new Error('rede')));
  render(FormView, { api: fakeApi({ lookup }), entity: posts, entities: schema.entities });
  const combo = screen.getByRole('combobox', { name: 'Autor' });

  await userEvent.click(combo);
  expect(await screen.findByText('Nenhuma opção encontrada.')).toBeInTheDocument();

  await userEvent.type(combo, 'x');
  vi.advanceTimersByTime(300);
  expect(await screen.findByText('Recurso não encontrado')).toBeInTheDocument();

  await userEvent.type(combo, 'y');
  vi.advanceTimersByTime(300);
  expect(await screen.findByText('Error: rede')).toBeInTheDocument();
  vi.useRealTimers();
});

it('picks the first option with Enter and closes with Escape', async () => {
  const api = fakeApi();
  render(FormView, { api, entity: posts, entities: schema.entities });
  const combo = screen.getByRole('combobox', { name: 'Autor' });

  await userEvent.click(combo);
  await screen.findByRole('listbox');
  await userEvent.keyboard('{Escape}');
  expect(screen.queryByRole('listbox')).toBeNull();

  await userEvent.click(screen.getByLabelText(/Título/));
  await userEvent.click(combo);
  await screen.findByRole('listbox');
  await userEvent.keyboard('{Enter}');
  expect(combo).toHaveAttribute('aria-expanded', 'false');
  expect(screen.getByText('Ana')).toBeInTheDocument();
});

it('defaults required enums to their first option', async () => {
  render(FormView, { api: fakeApi(), entity: posts, entities: schema.entities });

  expect(screen.getByLabelText('Status')).toHaveValue('draft');
});

it('omits relations whose foreign key is not writable', () => {
  const entity = { ...posts, fields: posts.fields.map((f) => (f.name === 'user_id' ? { ...f, writable: false } : f)) };
  render(FormView, { api: fakeApi(), entity, entities: schema.entities });

  expect(screen.queryByRole('combobox', { name: 'Autor' })).toBeNull();
});

it('uses records without relations', async () => {
  const api = fakeApi({ get: vi.fn(async () => ({ data: record(4, { relations: { author: [{ id: 1, title: 'x' }] } }) })) });
  render(FormView, { api, entity: posts, entities: schema.entities, id: '4' });

  await screen.findByLabelText(/Título/);
  expect(screen.queryByRole('button', { name: 'Limpar' })).toBeNull();
});
