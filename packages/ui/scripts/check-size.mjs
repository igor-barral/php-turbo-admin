// Orçamento de tamanho da build: falha se o JS/CSS (gzip) passar do limite.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { gzipSync } from 'node:zlib';

const BUDGET = { js: 45 * 1024, css: 8 * 1024 };
const dir = new URL('../../laravel/dist/assets/', import.meta.url).pathname;
const totals = { js: 0, css: 0 };

for (const file of readdirSync(dir)) {
  const ext = file.split('.').pop();
  if (ext in totals && statSync(join(dir, file)).isFile()) {
    totals[ext] += gzipSync(readFileSync(join(dir, file))).length;
  }
}

let failed = false;
for (const [ext, size] of Object.entries(totals)) {
  const ok = size <= BUDGET[ext];
  failed ||= !ok;
  console.log(`${ok ? '✓' : '✗'} ${ext}: ${(size / 1024).toFixed(1)} KB gzip (limite ${BUDGET[ext] / 1024} KB)`);
}

process.exit(failed ? 1 : 0);
