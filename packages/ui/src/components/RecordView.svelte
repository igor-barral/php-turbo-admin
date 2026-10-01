<script lang="ts">
  import { ApiError, type Api } from '../lib/api';
  import { t } from '../lib/i18n';
  import { toasts } from '../lib/toasts.svelte';
  import { href, navigate } from '../lib/router';
  import type { Entity, RecordData, Ref } from '../lib/types';
  import FieldValue from './FieldValue.svelte';
  import ConfirmDialog from './ConfirmDialog.svelte';

  interface Props {
    api: Api;
    entity: Entity;
    entities: Entity[];
    id: string;
  }

  let { api, entity, entities, id }: Props = $props();

  let record = $state<RecordData | null>(null);
  let error = $state<string | null>(null);
  let confirming = $state(false);
  let deleting = $state(false);

  const fields = $derived(entity.fields.filter((f) => f.visibility.includes('detail')));

  $effect(() => {
    api.get(entity.key, id).then(
      (response) => (record = response.data),
      (e: unknown) => (error = e instanceof ApiError ? e.title : String(e)),
    );
  });

  function targetOf(name: string): Entity | undefined {
    const relation = entity.relations.find((r) => r.name === name);
    return entities.find((e) => e.key === relation?.target);
  }

  function refs(value: Ref | Ref[] | null | undefined): Ref[] {
    if (!value) return [];
    return Array.isArray(value) ? value : [value];
  }

  async function remove(): Promise<void> {
    deleting = true;
    try {
      await api.remove(entity.key, id);
      toasts.push('success', t.deleted);
      navigate({ name: 'list', entity: entity.key, query: new URLSearchParams() });
    } catch (e) {
      toasts.push('error', e instanceof ApiError ? (e.detail ?? e.title) : String(e));
      deleting = false;
      confirming = false;
    }
  }
</script>

<section>
  <div class="page-header">
    <div>
      <a class="muted back" href={href({ name: 'list', entity: entity.key, query: new URLSearchParams() })}>← {entity.labelPlural}</a>
      <h1>{record?.title ?? entity.label}</h1>
    </div>
    {#if record}
      <div class="actions">
        {#if record.abilities?.update}
          <a class="btn" href={href({ name: 'edit', entity: entity.key, id })}>{t.edit}</a>
        {/if}
        {#if record.abilities?.delete}
          <button class="btn btn-danger" onclick={() => (confirming = true)}>{t.delete}</button>
        {/if}
      </div>
    {/if}
  </div>

  {#if error}
    <p class="card error" role="alert">{error}</p>
  {:else if !record}
    <p class="muted">{t.loading}</p>
  {:else}
    <dl class="card details">
      {#each fields as field (field.name)}
        <div class="row">
          <dt>{field.label}</dt>
          <dd><FieldValue {field} value={record.attributes[field.name]} /></dd>
        </div>
      {/each}
      {#each Object.entries(record.relations) as [name, value] (name)}
        {@const relation = entity.relations.find((r) => r.name === name)}
        {@const target = targetOf(name)}
        <div class="row">
          <dt>{relation?.label ?? name}</dt>
          <dd>
            {#each refs(value) as ref (ref.id)}
              {#if target}
                <a href={href({ name: 'show', entity: target.key, id: String(ref.id) })}>{ref.title}</a>
              {:else}
                <span>{ref.title}</span>
              {/if}
            {:else}
              <span class="muted">{t.empty}</span>
            {/each}
          </dd>
        </div>
      {/each}
    </dl>
  {/if}
</section>

{#if confirming && record}
  <ConfirmDialog
    title={t.confirmDeleteTitle}
    message={t.confirmDelete(record.title)}
    confirmLabel={t.delete}
    busy={deleting}
    onconfirm={remove}
    oncancel={() => (confirming = false)}
  />
{/if}

<style>
  .back { text-decoration: none; font-size: .9rem; }
  .actions { display: flex; gap: .5rem; }
  .details { margin: 0; }
  .row { display: grid; grid-template-columns: minmax(8rem, 14rem) 1fr; gap: 1rem; padding: .7rem 1rem; border-bottom: 1px solid var(--border); }
  .row:last-child { border-bottom: 0; }
  dt { color: var(--muted); font-weight: 500; }
  dd { margin: 0; min-width: 0; overflow-wrap: anywhere; }
  .error { padding: .75rem 1rem; color: var(--danger); }
  @media (max-width: 600px) { .row { grid-template-columns: 1fr; gap: .2rem; } }
</style>
