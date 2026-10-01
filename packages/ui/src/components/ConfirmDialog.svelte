<script lang="ts">
  import { t } from '../lib/i18n';

  interface Props {
    title: string;
    message: string;
    confirmLabel: string;
    busy?: boolean;
    onconfirm: () => void;
    oncancel: () => void;
  }

  let { title, message, confirmLabel, busy = false, onconfirm, oncancel }: Props = $props();

  let cancelButton = $state<HTMLButtonElement>();

  $effect(() => {
    cancelButton?.focus();
  });

  function onkeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') oncancel();
  }
</script>

<svelte:window {onkeydown} />

<div class="backdrop">
  <div class="card dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message">
    <h2 id="confirm-title">{title}</h2>
    <p id="confirm-message">{message}</p>
    <div class="actions">
      <button class="btn" bind:this={cancelButton} onclick={oncancel} disabled={busy}>{t.cancel}</button>
      <button class="btn btn-primary danger" onclick={onconfirm} disabled={busy}>{confirmLabel}</button>
    </div>
  </div>
</div>

<style>
  .backdrop { position: fixed; inset: 0; display: grid; place-items: center; padding: 1rem; background: rgb(0 0 0 / 45%); z-index: 40; }
  .dialog { width: min(28rem, 100%); padding: 1.25rem; display: grid; gap: .75rem; }
  .dialog p { margin: 0; }
  .actions { display: flex; justify-content: flex-end; gap: .5rem; }
  .danger { background: var(--danger); border-color: var(--danger); }
</style>
