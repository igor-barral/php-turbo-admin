<script lang="ts">
  import { ApiError, type Api } from '../lib/api';
  import { t } from '../lib/i18n';
  import type { Ref, Relation } from '../lib/types';

  interface Props {
    api: Api;
    relation: Relation;
    value: string;
    initial: Ref | null;
    nullable: boolean;
    required?: boolean;
    errors?: string[];
  }

  let { api, relation, value = $bindable(), initial, nullable, required = false, errors = [] }: Props = $props();

  let selected = $derived<Ref | null>(initial);
  let term = $state('');
  let options = $state<Ref[]>([]);
  let open = $state(false);
  let failure = $state<string | null>(null);

  const id = $derived(`relation-${relation.name}`);
  let debounce: ReturnType<typeof setTimeout> | undefined;

  async function search(): Promise<void> {
    if (!relation.target) return;
    try {
      options = (await api.lookup(relation.target, term.trim())).data;
      failure = null;
      open = true;
    } catch (e) {
      failure = e instanceof ApiError ? (e.detail ?? e.title) : String(e);
    }
  }

  function onInput(): void {
    clearTimeout(debounce);
    debounce = setTimeout(search, 250);
  }

  function choose(option: Ref): void {
    selected = option;
    value = String(option.id);
    open = false;
    term = '';
  }

  function clear(): void {
    selected = null;
    value = '';
  }

  function onkeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && open && options[0]) {
      event.preventDefault();
      choose(options[0]);
    } else if (event.key === 'Escape') {
      open = false;
    }
  }
</script>

<div class="field">
  <label for={id}>
    {relation.label}
    {#if required}<span class="muted">({t.required})</span>{/if}
  </label>

  {#if selected}
    <div class="selected">
      <span>{selected.title}</span>
      {#if nullable}<button type="button" class="btn btn-sm" onclick={clear}>{t.clear}</button>{/if}
    </div>
  {/if}

  <div class="combo">
    <input
      {id}
      class="input"
      type="search"
      role="combobox"
      aria-expanded={open}
      aria-controls="{id}-options"
      aria-autocomplete="list"
      aria-invalid={errors.length > 0}
      placeholder={t.search}
      bind:value={term}
      oninput={onInput}
      onfocus={search}
      {onkeydown}
    />
    {#if open}
      <ul id="{id}-options" class="card options" role="listbox">
        {#each options as option (option.id)}
          <li role="option" aria-selected={String(option.id) === value}>
            <button type="button" onclick={() => choose(option)}>{option.title}</button>
          </li>
        {:else}
          <li class="muted none">{t.noOptions}</li>
        {/each}
      </ul>
    {/if}
  </div>

  {#if failure}<p class="error">{failure}</p>{/if}
  {#each errors as message (message)}
    <p class="error">{message}</p>
  {/each}
</div>

<style>
  .field { display: grid; gap: .3rem; }
  label { font-weight: 500; }
  .selected { display: flex; align-items: center; gap: .5rem; }
  .combo { position: relative; }
  .options { position: absolute; left: 0; right: 0; top: calc(100% + .25rem); margin: 0; padding: .25rem; list-style: none; max-height: 14rem; overflow-y: auto; z-index: 20; }
  .options button { all: unset; display: block; width: 100%; padding: .4rem .6rem; border-radius: 6px; cursor: pointer; box-sizing: border-box; }
  .options button:hover, .options button:focus-visible { background: var(--surface-2); }
  .none { padding: .4rem .6rem; }
  .error { margin: 0; color: var(--danger); font-size: .85rem; }
</style>
