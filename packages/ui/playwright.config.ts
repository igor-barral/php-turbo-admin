import { defineConfig, devices } from '@playwright/test';

// E2E contra o playground (Sail): `make up && make seed`, depois `npm run e2e`.
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  retries: 0,
  reporter: 'list',
  use: {
    baseURL: process.env.TURBO_ADMIN_E2E_URL ?? 'http://localhost:8080',
    trace: 'retain-on-failure',
  },
  // TURBO_ADMIN_E2E_CHANNEL=chrome usa o Google Chrome instalado, sem baixar navegadores.
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], channel: process.env.TURBO_ADMIN_E2E_CHANNEL } }],
});
