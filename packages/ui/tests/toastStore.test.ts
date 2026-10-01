import { toasts } from '../src/lib/toasts.svelte';

it('pushes and auto-dismisses toasts', () => {
  vi.useFakeTimers();

  toasts.push('success', 'Salvo');
  toasts.push('error', 'Falhou', 0);
  expect(toasts.items.map((t) => t.message)).toEqual(['Salvo', 'Falhou']);

  vi.advanceTimersByTime(4000);
  expect(toasts.items.map((t) => t.message)).toEqual(['Falhou']);

  toasts.dismiss(toasts.items[0]!.id);
  toasts.dismiss(999);
  expect(toasts.items).toEqual([]);

  vi.useRealTimers();
});
