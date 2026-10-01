// Contrato da API /v1 (espelha packages/core/src/Http). Mantenha em sincronia com o core.

export type FieldType =
  | 'string' | 'text' | 'integer' | 'decimal' | 'money' | 'boolean' | 'date' | 'datetime'
  | 'time' | 'enum' | 'json' | 'uuid' | 'email' | 'url' | 'password' | 'file';

export type Visibility = 'index' | 'detail' | 'create' | 'update';

export interface FieldOption {
  value: string | number;
  label: string;
}

export interface Field {
  name: string;
  label: string;
  type: FieldType;
  nullable: boolean;
  readable: boolean;
  writable: boolean;
  required: boolean;
  searchable: boolean;
  sortable: boolean;
  filterable: boolean;
  visibility: Visibility[];
  widget: string | null;
  options: FieldOption[];
}

export interface Relation {
  name: string;
  label: string;
  type: string;
  target: string | null;
  foreignKey: string | null;
  writable: boolean;
}

export interface Entity {
  key: string;
  label: string;
  labelPlural: string;
  primaryKey: string;
  titleField: string | null;
  sort: { field: string; direction: 'asc' | 'desc' } | null;
  searchable: boolean;
  fields: Field[];
  relations: Relation[];
  abilities: { create: boolean };
}

export interface Schema {
  version: number;
  entities: Entity[];
}

export interface Ref {
  id: string | number;
  title: string;
}

export interface RecordData {
  id: string | number;
  title: string;
  attributes: Record<string, unknown>;
  relations: Record<string, Ref | Ref[] | null>;
  abilities?: { update: boolean; delete: boolean };
}

export interface ListMeta {
  pagination: 'cursor' | 'page';
  limit: number;
  page: number | null;
  hasMore: boolean;
  nextCursor: string | null;
  sort: { field: string; direction: 'asc' | 'desc' };
}

export interface ListResponse {
  data: RecordData[];
  meta: ListMeta;
}

export interface RuntimeConfig {
  apiBase: string;
  appName: string;
  user: { name: string | null };
  logoutUrl: string | null;
}
