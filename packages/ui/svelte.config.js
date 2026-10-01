import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

export default {
  preprocess: vitePreprocess(),
  compilerOptions: {
    // Estilos dos componentes vão para o arquivo CSS da build (compatível com CSP sem 'unsafe-inline').
    css: 'external',
  },
};
