<script lang="ts">
  import { formatValue, truncate } from '../lib/format';
  import type { Field } from '../lib/types';

  interface Props {
    field: Field;
    value: unknown;
    compact?: boolean;
  }

  let { field, value, compact = false }: Props = $props();

  const text = $derived(formatValue(field, value));
  // Só http(s) vira link (o servidor também valida o esquema na escrita).
  const link = $derived(
    field.type === 'url' && typeof value === 'string' && /^https?:\/\//i.test(value)
      ? value
      : field.type === 'email' && typeof value === 'string' && value !== ''
        ? `mailto:${value}`
        : null,
  );
</script>

{#if field.type === 'json' && !compact && value !== null && value !== undefined}
  <pre class="json">{JSON.stringify(value, null, 2)}</pre>
{:else if field.type === 'boolean'}
  <span class="badge" class:on={value === true}>{text}</span>
{:else if link}
  <a href={link} rel="noopener noreferrer" target={field.type === 'url' ? '_blank' : undefined}>{compact ? truncate(text) : text}</a>
{:else if field.type === 'text' && !compact}
  <span class="multiline">{text}</span>
{:else}
  <span title={compact && text.length > 80 ? text : undefined}>{compact ? truncate(text) : text}</span>
{/if}

<style>
  .json { margin: 0; padding: .6rem; background: var(--surface-2); border-radius: var(--radius); font: .85rem/1.4 var(--mono); overflow-x: auto; }
  .multiline { white-space: pre-wrap; }
  .badge { display: inline-block; padding: .05rem .5rem; border-radius: 999px; font-size: .8rem; background: var(--surface-2); color: var(--muted); }
  .badge.on { color: var(--success); }
</style>
