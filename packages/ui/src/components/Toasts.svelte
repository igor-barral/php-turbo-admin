<script lang="ts">
  import { toasts } from '../lib/toasts.svelte';
</script>

<div class="toasts" aria-live="polite">
  {#each toasts.items as toast (toast.id)}
    <div class="toast card {toast.kind}" role={toast.kind === 'error' ? 'alert' : 'status'}>
      <span>{toast.message}</span>
      <button class="btn btn-sm" aria-label="Fechar" onclick={() => toasts.dismiss(toast.id)}>×</button>
    </div>
  {/each}
</div>

<style>
  .toasts { position: fixed; right: 1rem; bottom: 1rem; display: grid; gap: .5rem; z-index: 50; max-width: min(24rem, calc(100vw - 2rem)); }
  .toast { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .6rem .8rem; border-left: 4px solid var(--success); }
  .toast.error { border-left-color: var(--danger); }
</style>
