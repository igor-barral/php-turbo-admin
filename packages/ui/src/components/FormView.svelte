<script lang="ts">
  import { ApiError, type Api } from '../lib/api';
  import { fromInput, InvalidInput, toInput, type InputValue } from '../lib/form';
  import { t } from '../lib/i18n';
  import { toasts } from '../lib/toasts.svelte';
  import { href, navigate } from '../lib/router';
  import type { Entity, Field, RecordData, Ref, Relation } from '../lib/types';
  import FieldInput from './FieldInput.svelte';
  import RelationSelect from './RelationSelect.svelte';

  interface Props {
    api: Api;
    entity: Entity;
    entities: Entity[];
    /** Ausente = criação. */
    id?: string;
  }

  let { api, entity, id }: Props = $props();

  const mode = $derived(id === undefined ? 'create' : 'update');
  const fields = $derived(entity.fields.filter((f) => f.writable && f.visibility.includes(mode)));
  // belongsTo graváveis aparecem como seletor e escrevem na coluna da FK.
  const relations = $derived(
    entity.relations.filter((r) => r.writable && r.foreignKey && entity.fields.some((f) => f.name === r.foreignKey && f.writable)),
  );

  let record = $state<RecordData | null>(null);
  let values = $state<Record<string, InputValue>>({});
  let initial: Record<string, InputValue> = {};
  let errors = $state<Record<string, string[]>>({});
  let saving = $state(false);
  let loadError = $state<string | null>(null);

  const allFields = $derived(entity.fields);

  $effect(() => {
    if (id === undefined) {
      reset(null);
      return;
    }
    api.get(entity.key, id).then(
      (response) => reset(response.data),
      (e: unknown) => (loadError = e instanceof ApiError ? e.title : String(e)),
    );
  });

  function reset(data: RecordData | null): void {
    record = data;
    const next: Record<string, InputValue> = {};
    for (const field of allFields) {
      next[field.name] = toInput(field, data?.attributes[field.name] ?? defaultValue(field));
    }
    values = next;
    initial = { ...next };
  }

  function defaultValue(field: Field): unknown {
    return field.type === 'enum' && !field.nullable ? (field.options[0]?.value ?? null) : null;
  }

  function fieldOf(name: string | null): Field | undefined {
    return entity.fields.find((f) => f.name === name);
  }

  function initialRef(relation: Relation): Ref | null {
    const value = record?.relations[relation.name];
    return value && !Array.isArray(value) ? value : null;
  }

  function payload(): Record<string, unknown> {
    const names = [...fields.map((f) => f.name), ...relations.map((r) => r.foreignKey as string)];
    const data: Record<string, unknown> = {};
    const clientErrors: Record<string, string[]> = {};

    for (const name of names) {
      const field = fieldOf(name);
      const value = values[name];
      if (!field || value === undefined) continue;
      // Edição envia só o que mudou; criação omite campos vazios não obrigatórios.
      if (mode === 'update' && value === initial[name]) continue;
      if (mode === 'create' && value === '' && !field.required) continue;
      try {
        data[name] = fromInput(field, value);
      } catch (e) {
        if (e instanceof InvalidInput) clientErrors[name] = [e.message];
        else throw e;
      }
    }

    if (Object.keys(clientErrors).length > 0) {
      errors = clientErrors;
      throw new InvalidInput(t.fixErrors);
    }

    return data;
  }

  async function submit(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    errors = {};
    saving = true;

    try {
      const data = payload();
      const response = id === undefined ? await api.create(entity.key, data) : await api.update(entity.key, id, data);
      toasts.push('success', t.saved);
      navigate({ name: 'show', entity: entity.key, id: String(response.data.id) });
    } catch (e) {
      if (e instanceof ApiError && e.status === 422) {
        errors = e.errors;
        toasts.push('error', t.fixErrors);
      } else {
        toasts.push('error', e instanceof ApiError ? (e.detail ?? e.title) : e instanceof Error ? e.message : String(e));
      }
    } finally {
      saving = false;
    }
  }

  const cancelHref = $derived(
    id === undefined
      ? href({ name: 'list', entity: entity.key, query: new URLSearchParams() })
      : href({ name: 'show', entity: entity.key, id }),
  );
</script>

<section>
  <div class="page-header">
    <h1>{id === undefined ? `${t.create}: ${entity.label}` : `${t.edit}: ${record?.title ?? entity.label}`}</h1>
  </div>

  {#if loadError}
    <p class="card error" role="alert">{loadError}</p>
  {:else if id !== undefined && !record}
    <p class="muted">{t.loading}</p>
  {:else}
    <form class="card form" onsubmit={submit} novalidate>
      {#each relations as relation (relation.name)}
        {@const fk = fieldOf(relation.foreignKey)}
        {#if fk}
          <RelationSelect
            {api}
            {relation}
            bind:value={() => String(values[fk.name] ?? ''), (v) => (values[fk.name] = v)}
            initial={initialRef(relation)}
            nullable={fk.nullable}
            required={mode === 'create' && fk.required}
            errors={errors[fk.name] ?? []}
          />
        {/if}
      {/each}

      {#each fields as field (field.name)}
        <FieldInput {field} bind:value={() => values[field.name] ?? '', (v) => (values[field.name] = v)} errors={errors[field.name] ?? []} required={mode === 'create' && field.required} />
      {/each}

      {#each Object.entries(errors).filter(([name]) => !fieldOf(name)) as [name, messages] (name)}
        <p class="error" role="alert">{name}: {messages.join(' ')}</p>
      {/each}

      <div class="actions">
        <a class="btn" href={cancelHref}>{t.cancel}</a>
        <button class="btn btn-primary" type="submit" disabled={saving}>{t.save}</button>
      </div>
    </form>
  {/if}
</section>

<style>
  .form { display: grid; gap: 1rem; padding: 1.25rem; max-width: 46rem; }
  .actions { display: flex; justify-content: flex-end; gap: .5rem; }
  .error { margin: 0; padding: .75rem 1rem; color: var(--danger); }
</style>
