import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

function files(dir: string): string[] {
  return readdirSync(dir, { withFileTypes: true }).flatMap((entry) =>
    entry.isDirectory() ? files(join(dir, entry.name)) : [join(dir, entry.name)],
  );
}

// Regra de segurança da arquitetura (§9.4): nada de HTML cru, nem APIs que executam strings.
it.each([
  ['{@html', /\{@html/],
  ['innerHTML', /\.innerHTML\s*=/],
  ['eval', /\beval\(|new Function\(/],
  ['localStorage token', /localStorage\.setItem\([^)]*token/i],
])('does not use %s in the UI source', (_name, pattern) => {
  const offenders = files(join(__dirname, '../src')).filter((file) => pattern.test(readFileSync(file, 'utf8')));

  expect(offenders).toEqual([]);
});
