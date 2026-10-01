import { parseRoute, type Route } from './router';

/** Rota atual, reativa às mudanças de hash. */
export function createRouter() {
  let current = $state<Route>(parseRoute(window.location.hash));

  const update = () => {
    current = parseRoute(window.location.hash);
  };

  window.addEventListener('hashchange', update);

  return {
    get current() {
      return current;
    },
    destroy() {
      window.removeEventListener('hashchange', update);
    },
  };
}
