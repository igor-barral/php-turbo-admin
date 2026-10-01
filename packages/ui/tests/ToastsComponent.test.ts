import { render, screen } from '@testing-library/svelte';
import userEvent from '@testing-library/user-event';
import Toasts from '../src/components/Toasts.svelte';
import { toasts } from '../src/lib/toasts.svelte';
import { flush } from './fixtures';

it('renders toasts with the right roles and dismisses them', async () => {
  render(Toasts);
  toasts.push('success', 'Salvo', 0);
  toasts.push('error', 'Falhou', 0);
  await flush();

  expect(screen.getByRole('status')).toHaveTextContent('Salvo');
  expect(screen.getByRole('alert')).toHaveTextContent('Falhou');

  for (const button of screen.getAllByRole('button', { name: 'Fechar' })) await userEvent.click(button);
  expect(toasts.items).toEqual([]);
});
