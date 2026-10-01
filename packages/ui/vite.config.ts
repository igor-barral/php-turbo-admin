import { svelte } from '@sveltejs/vite-plugin-svelte';
import { defineConfig } from 'vitest/config';

// Em `npm run dev`, a API e a config vêm do playground (Sail em :8080). Cookies de
// sessão/XSRF de localhost valem para qualquer porta, então o login do playground funciona.
const backend = process.env.TURBO_ADMIN_BACKEND ?? 'http://localhost:8080';

export default defineConfig({
  plugins: [svelte()],
  base: './',
  build: {
    // A build vai para dentro do adapter: quem instala o pacote não precisa de Node.
    outDir: '../laravel/dist',
    emptyOutDir: true,
    target: 'es2022',
    modulePreload: { polyfill: false },
    sourcemap: false,
  },
  server: {
    proxy: {
      '/config.json': { target: backend, rewrite: () => '/admin/config.json' },
      '/admin/api': backend,
    },
  },
  resolve: process.env.VITEST ? { conditions: ['browser'] } : undefined,
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['tests/**/*.test.ts'],
    setupFiles: ['tests/setup.ts'],
    coverage: {
      provider: 'v8',
      include: ['src/**/*.{ts,svelte}'],
      exclude: ['src/main.ts', 'src/**/*.d.ts'],
      thresholds: { lines: 90, statements: 90, functions: 90, branches: 85 },
    },
  },
});
