<script lang="ts">
  import { onDestroy } from 'svelte';
  import { ApiError, createApi, loadConfig, logout, type Api } from './lib/api';
  import { t } from './lib/i18n';
  import { href } from './lib/router';
  import { createRouter } from './lib/router.svelte';
  import type { Entity, RuntimeConfig, Schema } from './lib/types';
  import ListView from './components/ListView.svelte';
  import RecordView from './components/RecordView.svelte';
  import FormView from './components/FormView.svelte';
  import Toasts from './components/Toasts.svelte';

  interface Props {
    /** Injetáveis para testes; em produção vêm de config.json. */
    config?: RuntimeConfig;
    api?: Api;
  }

  let { config: initialConfig, api: initialApi }: Props = $props();

  const router = createRouter();
  onDestroy(router.destroy);

  let config = $state<RuntimeConfig | null>(null);
  let api = $state<Api | null>(null);
  let schema = $state<Schema | null>(null);
  let failure = $state<ApiError | null>(null);
  let menuOpen = $state(false);

  async function boot(): Promise<void> {
    try {
      config = initialConfig ?? (await loadConfig());
      api = initialApi ?? createApi(config.apiBase);
      schema = await api.schema();
      document.title = config.appName;
    } catch (error) {
      failure = error instanceof ApiError ? error : new ApiError(0, String(error));
    }
  }

  boot();

  const route = $derived(router.current);
  const entityKey = $derived('entity' in route ? route.entity : null);
  const entity = $derived<Entity | undefined>(schema?.entities.find((e) => e.key === entityKey));

  $effect(() => {
    // Fecha o menu móvel ao navegar.
    void route;
    menuOpen = false;
  });
</script>

{#if failure}
  <main class="center">
    <div class="card message" role="alert">
      <h1>{failure.needsReload ? t.sessionExpired : failure.title}</h1>
      {#if failure.detail}<p class="muted">{failure.detail}</p>{/if}
      <button class="btn btn-primary" onclick={() => window.location.reload()}>{t.reload}</button>
    </div>
  </main>
{:else if !config || !api || !schema}
  <main class="center"><p class="muted" aria-live="polite">{t.loading}</p></main>
{:else}
  <div class="shell">
    <header class="topbar">
      <button class="btn btn-sm menu-toggle" aria-expanded={menuOpen} aria-controls="sidebar" onclick={() => (menuOpen = !menuOpen)}>
        {t.menu}
      </button>
      <a class="brand" href="#/">{config.appName}</a>
      <div class="user">
        {#if config.user.name}<span class="muted">{config.user.name}</span>{/if}
        {#if config.logoutUrl}
          {@const url = config.logoutUrl}
          <button class="btn btn-sm" onclick={() => logout(url)}>{t.logout}</button>
        {/if}
      </div>
    </header>

    <nav id="sidebar" class="sidebar" class:open={menuOpen} aria-label="Entidades">
      <ul>
        {#each schema.entities as item (item.key)}
          <li>
            <a
              href={href({ name: 'list', entity: item.key, query: new URLSearchParams() })}
              aria-current={item.key === entityKey ? 'page' : undefined}
            >{item.labelPlural}</a>
          </li>
        {/each}
      </ul>
    </nav>

    <main class="content">
      {#if route.name === 'home'}
        <h1>{t.dashboard}</h1>
        {#if schema.entities.length === 0}
          <p class="empty-state">{t.noEntities}</p>
        {:else}
          <p class="muted">{t.dashboardHint}</p>
          <div class="grid">
            {#each schema.entities as item (item.key)}
              <a class="card tile" href={href({ name: 'list', entity: item.key, query: new URLSearchParams() })}>
                <strong>{item.labelPlural}</strong>
                <span class="muted">{item.fields.length} campos</span>
              </a>
            {/each}
          </div>
        {/if}
      {:else if !entity}
        <p class="empty-state">{t.notFound}</p>
      {:else if route.name === 'list'}
        {#key entity.key}
          <ListView {api} {entity} query={route.query} />
        {/key}
      {:else if route.name === 'show'}
        {#key route.id}
          <RecordView {api} {entity} entities={schema.entities} id={route.id} />
        {/key}
      {:else if route.name === 'create'}
        <FormView {api} {entity} entities={schema.entities} />
      {:else if route.name === 'edit'}
        {#key route.id}
          <FormView {api} {entity} entities={schema.entities} id={route.id} />
        {/key}
      {/if}
    </main>
  </div>
{/if}

<Toasts />

<style>
  .center { display: grid; place-items: center; min-height: 100vh; padding: 1rem; }
  .message { padding: 1.5rem; max-width: 28rem; text-align: center; display: grid; gap: .75rem; justify-items: center; }

  .shell {
    display: grid;
    grid-template-columns: 15rem 1fr;
    grid-template-rows: auto 1fr;
    grid-template-areas: 'top top' 'side main';
    min-height: 100vh;
  }

  .topbar {
    grid-area: top;
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: .65rem 1.25rem;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
  }
  .brand { font-weight: 700; color: var(--text); text-decoration: none; }
  .user { margin-left: auto; display: flex; align-items: center; gap: .75rem; }
  .menu-toggle { display: none; }

  .sidebar { grid-area: side; padding: 1rem .75rem; border-right: 1px solid var(--border); background: var(--surface); }
  .sidebar ul { list-style: none; margin: 0; padding: 0; display: grid; gap: .15rem; }
  .sidebar a { display: block; padding: .45rem .7rem; border-radius: var(--radius); color: var(--text); text-decoration: none; }
  .sidebar a:hover { background: var(--surface-2); }
  .sidebar a[aria-current='page'] { background: var(--surface-2); color: var(--primary); font-weight: 600; }

  .content { grid-area: main; padding: 1.5rem; min-width: 0; }

  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); gap: .75rem; margin-top: 1rem; }
  .tile { display: grid; gap: .25rem; padding: 1rem; color: var(--text); text-decoration: none; }
  .tile:hover { border-color: var(--primary); }

  @media (max-width: 760px) {
    .shell { grid-template-columns: 1fr; grid-template-areas: 'top' 'main'; }
    .menu-toggle { display: inline-flex; }
    .sidebar { display: none; position: fixed; inset: 3.2rem 0 auto 0; z-index: 10; border-bottom: 1px solid var(--border); }
    .sidebar.open { display: block; }
    .content { padding: 1rem; }
  }
</style>
