import { vi } from 'vitest';
import type { Api } from '../src/lib/api';
import type { Entity, Field, ListResponse, RecordData, Schema } from '../src/lib/types';

export function field(name: string, overrides: Partial<Field> = {}): Field {
  return {
    name,
    label: name.charAt(0).toUpperCase() + name.slice(1),
    type: 'string',
    nullable: false,
    readable: true,
    writable: true,
    required: false,
    searchable: false,
    sortable: false,
    filterable: false,
    visibility: ['index', 'detail', 'create', 'update'],
    widget: null,
    options: [],
    ...overrides,
  };
}

export const posts: Entity = {
  key: 'posts',
  label: 'Post',
  labelPlural: 'Posts',
  primaryKey: 'id',
  titleField: 'title',
  sort: { field: 'id', direction: 'desc' },
  searchable: true,
  fields: [
    field('id', { type: 'integer', writable: false, sortable: true, visibility: ['index', 'detail'] }),
    field('title', { label: 'Título', required: true, searchable: true, sortable: true }),
    field('status', {
      type: 'enum',
      filterable: true,
      options: [
        { value: 'draft', label: 'Rascunho' },
        { value: 'published', label: 'Publicado' },
      ],
    }),
    field('featured', { type: 'boolean', filterable: true }),
    field('body', { type: 'text', nullable: true, visibility: ['detail', 'create', 'update'] }),
    field('metadata', { type: 'json', nullable: true, visibility: ['detail', 'create', 'update'] }),
    field('user_id', { type: 'integer', nullable: true, filterable: true, visibility: [] }),
    field('created_at', { type: 'datetime', nullable: true, writable: false, visibility: ['index', 'detail'] }),
  ],
  relations: [
    { name: 'author', label: 'Autor', type: 'belongsTo', target: 'users', foreignKey: 'user_id', writable: true },
    { name: 'owner', label: 'Dono', type: 'morphTo', target: null, foreignKey: null, writable: false },
  ],
  abilities: { create: true },
};

export const users: Entity = {
  key: 'users',
  label: 'Usuário',
  labelPlural: 'Usuários',
  primaryKey: 'id',
  titleField: 'name',
  sort: null,
  searchable: false,
  fields: [field('id', { type: 'integer', writable: false, visibility: ['index', 'detail'] }), field('name')],
  relations: [],
  abilities: { create: false },
};

export const schema: Schema = { version: 1, entities: [posts, users] };

export function record(id: number, overrides: Partial<RecordData> = {}): RecordData {
  return {
    id,
    title: `Post ${id}`,
    attributes: {
      id,
      title: `Post ${id}`,
      status: 'draft',
      featured: false,
      body: 'Texto',
      metadata: { a: 1 },
      user_id: 1,
      created_at: '2026-01-02T10:00:00+00:00',
    },
    relations: { author: { id: 1, title: 'Ana' } },
    abilities: { update: true, delete: true },
    ...overrides,
  };
}

export function listResponse(records: RecordData[], meta: Partial<ListResponse['meta']> = {}): ListResponse {
  return {
    data: records,
    meta: { pagination: 'cursor', limit: 25, page: null, hasMore: false, nextCursor: null, sort: { field: 'id', direction: 'desc' }, ...meta },
  };
}

export function fakeApi(overrides: Partial<Api> = {}): { [K in keyof Api]: ReturnType<typeof vi.fn> & Api[K] } {
  return {
    schema: vi.fn(async () => schema),
    list: vi.fn(async () => listResponse([record(2), record(1)])),
    get: vi.fn(async (_e: string, id: string) => ({ data: record(Number(id)) })),
    create: vi.fn(async () => ({ data: record(10) })),
    update: vi.fn(async (_e: string, id: string) => ({ data: record(Number(id)) })),
    remove: vi.fn(async () => undefined),
    lookup: vi.fn(async () => ({ data: [{ id: 1, title: 'Ana' }, { id: 2, title: 'Bruno' }] })),
    ...overrides,
  } as never;
}

/** Aguarda microtasks e o próximo tick de render. */
export const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
