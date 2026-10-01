<script lang="ts">
  import type { InputValue } from '../lib/form';
  import { t } from '../lib/i18n';
  import type { Field } from '../lib/types';

  interface Props {
    field: Field;
    value: InputValue;
    errors?: string[];
    required?: boolean;
  }

  let { field, value = $bindable(), errors = [], required = false }: Props = $props();

  const id = $derived(`field-${field.name}`);
  const invalid = $derived(errors.length > 0);
  const textType = $derived(
    ({ email: 'email', url: 'url', password: 'password', integer: 'number', decimal: 'number', money: 'number', date: 'date', time: 'time', datetime: 'datetime-local' } as Record<string, string>)[field.type] ?? 'text',
  );
</script>

<div class="field">
  {#if field.type === 'boolean'}
    <label class="checkbox">
      <input {id} type="checkbox" checked={value === true} onchange={(e) => (value = e.currentTarget.checked)} aria-invalid={invalid} />
      {field.label}
    </label>
  {:else}
    <label for={id}>
      {field.label}
      {#if required}<span class="muted">({t.required})</span>{/if}
    </label>

    {#if field.type === 'enum'}
      <select {id} class="input" bind:value aria-invalid={invalid} {required}>
        {#if field.nullable || value === ''}<option value="">{t.empty}</option>{/if}
        {#each field.options as option (option.value)}
          <option value={String(option.value)}>{option.label}</option>
        {/each}
      </select>
    {:else if field.type === 'text' || field.type === 'json'}
      <textarea {id} class="input" class:mono={field.type === 'json'} bind:value aria-invalid={invalid} {required}></textarea>
    {:else}
      <input
        {id}
        class="input"
        type={textType}
        step={field.type === 'integer' ? '1' : field.type === 'decimal' || field.type === 'money' ? 'any' : undefined}
        autocomplete={field.type === 'password' ? 'new-password' : undefined}
        bind:value
        aria-invalid={invalid}
        {required}
      />
    {/if}
  {/if}

  {#each errors as message (message)}
    <p class="error">{message}</p>
  {/each}
</div>

<style>
  .field { display: grid; gap: .3rem; }
  label { font-weight: 500; }
  .checkbox { display: inline-flex; align-items: center; gap: .5rem; }
  .mono { font-family: var(--mono); font-size: .85rem; }
  .error { margin: 0; color: var(--danger); font-size: .85rem; }
</style>
