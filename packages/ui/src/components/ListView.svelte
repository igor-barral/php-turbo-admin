<script lang="ts">
  import { ApiError, type Api } from '../lib/api';
  import { t } from '../lib/i18n';
  import { href, navigate } from '../lib/router';
  import type { Entity, ListResponse, Ref } from '../lib/types';
  import FieldValue from './FieldValue.svelte';

  interface Props {
    api: Api;
    entity: Entity;
    query: URLSearchParams;
  }

  let { api, entity, query }: Props = $props();

  let result = $state<ListResponse | null>(null);
  let error = $state<string | null>(null);
  let loading = $state(false);
  // Cursores já visitados, para o botão "Anterior" na paginação por cursor.
  let history = $state<string[]>([]);
  // Derivado gravável: acompanha a URL, mas pode ser editado enquanto o usuário digita.
  let search = $derived(query.get('q') ?? '');

  const columns = $derived(entity.fields.filter((f) => f.visibility.includes('index')));
  const relationColumns = $derived(entity.relations.filter((r) => r.type === 'belongsTo' && r.target !== null));
  const filters = $derived(entity.fields.filter((f) => f.filterable && (f.type === 'enum' || f.type === 'boolean')));
  const sort = $derived(
    query.get('sort') ?? (entity.sort ? (entity.sort.direction === 'desc' ? '-' : '') + entity.sort.field : ''),
  );

  let requestId = 0;

  $effect(() => {
    void load(query);
  });

  async function load(params: URLSearchParams): Promise<void> {
    const current = ++requestId;
    loading = true;
    error = null;

    try {
      const response = await api.list(entity.key, Object.fromEntries(params));
      // Descarta respostas fora de ordem (o usuário mudou o filtro antes de terminar).
      if (current === requestId) result = response;
    } catch (e) {
      if (current === requestId) error = e instanceof ApiError ? (e.detail ?? e.title) : String(e);
    } finally {
      if (current === requestId) loading = false;
    }
  }

  function go(changes: Record<string, string | null>, keepPosition = false): void {
    const next = new URLSearchParams(query);
    for (const [name, value] of Object.entries(changes)) {
      if (value === null || value === '') next.delete(name);
      else next.set(name, value);
    }
    if (!keepPosition) {
      next.delete('cursor');
      next.delete('page');
      history = [];
    }
    navigate({ name: 'list', entity: entity.key, query: next });
  }

  function toggleSort(field: string): void {
    go({ sort: sort === field ? '-' + field : field });
  }

  function ariaSort(field: string): 'ascending' | 'descending' | 'none' {
    if (sort === field) return 'ascending';
    if (sort === '-' + field) return 'descending';
    return 'none';
  }

  let debounce: ReturnType<typeof setTimeout> | undefined;
  function onSearch(): void {
    clearTimeout(debounce);
    debounce = setTimeout(() => go({ q: search.trim() }), 300);
  }

  function nextPage(): void {
    if (!result) return;
    if (result.meta.pagination === 'cursor' && result.meta.nextCursor) {
      history = [...history, query.get('cursor') ?? ''];
      go({ cursor: result.meta.nextCursor }, true);
    } else if (result.meta.pagination === 'page') {
      go({ page: String((result.meta.page ?? 1) + 1) }, true);
    }
  }

  function previousPage(): void {
    if (!result) return;
    if (result.meta.pagination === 'cursor') {
      const previous = history.at(-1) ?? '';
      history = history.slice(0, -1);
      go({ cursor: previous || null }, true);
    } else {
      go({ page: String(Math.max(1, (result.meta.page ?? 1) - 1)) }, true);
    }
  }

  const hasPrevious = $derived(
    result?.meta.pagination === 'cursor' ? query.has('cursor') : (result?.meta.page ?? 1) > 1,
  );

  function refTitle(value: Ref | Ref[] | null | undefined): string {
    if (!value) return t.empty;
    return Array.isArray(value) ? value.map((r) => r.title).join(', ') : value.title;
  }
</script>

<section aria-labelledby="list-title">
  <div class="page-header">
    <h1 id="list-title">{entity.labelPlural}</h1>
    {#if entity.abilities.create}
      <a class="btn btn-primary" href={href({ name: 'create', entity: entity.key })}>{t.create}</a>
    {/if}
  </div>

  <div class="toolbar">
    {#if entity.searchable}
      <label class="search">
        <span class="sr-only">{t.search}</span>
        <input class="input" type="search" placeholder={t.search} bind:value={search} oninput={onSearch} />
      </label>
    {/if}
    {#each filters as field (field.name)}
      <label class="filter">
        <span class="muted">{field.label}</span>
        <select
          class="input"
          value={query.get(`filter[${field.name}]`) ?? ''}
          onchange={(e) => go({ [`filter[${field.name}]`]: e.currentTarget.value })}
        >
          <option value="">{t.filterAll}</option>
          {#if field.type === 'boolean'}
            <option value="1">{t.yes}</option>
            <option value="0">{t.no}</option>
          {:else}
            {#each field.options as option (option.value)}
              <option value={String(option.value)}>{option.label}</option>
            {/each}
          {/if}
        </select>
      </label>
    {/each}
  </div>

  {#if error}
    <p class="card error" role="alert">{error}</p>
  {/if}

  <div class="card table-wrap" aria-busy={loading}>
    <table>
      <thead>
        <tr>
          {#each columns as field (field.name)}
            <th scope="col" aria-sort={field.sortable ? ariaSort(field.name) : undefined}>
              {#if field.sortable}
                <button class="sort" title={t.sortBy(field.label)} onclick={() => toggleSort(field.name)}>
                  {field.label}
                  <span aria-hidden="true">{ariaSort(field.name) === 'ascending' ? '▲' : ariaSort(field.name) === 'descending' ? '▼' : ''}</span>
                </button>
              {:else}
                {field.label}
              {/if}
            </th>
          {/each}
          {#each relationColumns as relation (relation.name)}
            <th scope="col">{relation.label}</th>
          {/each}
          <th scope="col"><span class="sr-only">{t.actions}</span></th>
        </tr>
      </thead>
      <tbody>
        {#if result}
          {#each result.data as record (record.id)}
            <tr>
              {#each columns as field (field.name)}
                <td><FieldValue {field} value={record.attributes[field.name]} compact /></td>
              {/each}
              {#each relationColumns as relation (relation.name)}
                <td>{refTitle(record.relations[relation.name])}</td>
              {/each}
              <td class="row-actions">
                <a class="btn btn-sm" href={href({ name: 'show', entity: entity.key, id: String(record.id) })}>{t.view}</a>
                {#if record.abilities?.update}
                  <a class="btn btn-sm" href={href({ name: 'edit', entity: entity.key, id: String(record.id) })}>{t.edit}</a>
                {/if}
              </td>
            </tr>
          {:else}
            <tr><td class="empty-state" colspan={columns.length + relationColumns.length + 1}>{t.noRecords}</td></tr>
          {/each}
        {:else}
          <tr><td class="empty-state" colspan={columns.length + relationColumns.length + 1}>{t.loading}</td></tr>
        {/if}
      </tbody>
    </table>
  </div>

  {#if result && (hasPrevious || result.meta.hasMore)}
    <nav class="pagination" aria-label="Paginação">
      <button class="btn" disabled={!hasPrevious || loading} onclick={previousPage}>{t.previous}</button>
      {#if result.meta.pagination === 'page'}<span class="muted">{t.page} {result.meta.page}</span>{/if}
      <button class="btn" disabled={!result.meta.hasMore || loading} onclick={nextPage}>{t.next}</button>
    </nav>
  {/if}
</section>

<style>
  .toolbar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; margin-bottom: 1rem; }
  .search { flex: 1 1 16rem; max-width: 24rem; }
  .filter { display: grid; gap: .2rem; font-size: .85rem; min-width: 10rem; }
  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: .6rem .8rem; text-align: left; border-bottom: 1px solid var(--border); vertical-align: top; }
  th { font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; color: var(--muted); white-space: nowrap; }
  tbody tr:last-child td { border-bottom: 0; }
  tbody tr:hover { background: var(--surface-2); }
  .sort { all: unset; cursor: pointer; display: inline-flex; gap: .3rem; }
  .sort:focus-visible { outline: 2px solid var(--focus); }
  .row-actions { text-align: right; white-space: nowrap; }
  .row-actions .btn + .btn { margin-left: .3rem; }
  .pagination { display: flex; justify-content: flex-end; align-items: center; gap: .75rem; margin-top: 1rem; }
  .error { padding: .75rem 1rem; color: var(--danger); margin-bottom: 1rem; }
</style>
