import { render, screen } from '@testing-library/svelte';
import userEvent from '@testing-library/user-event';
import FieldInput from '../src/components/FieldInput.svelte';
import FieldValue from '../src/components/FieldValue.svelte';
import { field } from './fixtures';

describe('FieldValue', () => {
  it('renders json blocks, badges and multiline text', () => {
    const { container, unmount } = render(FieldValue, { field: field('m', { type: 'json' }), value: { a: 1 } });
    expect(container.querySelector('pre')?.textContent).toBe('{\n  "a": 1\n}');
    unmount();

    render(FieldValue, { field: field('b', { type: 'boolean' }), value: true });
    expect(screen.getByText('Sim')).toHaveClass('on');
  });

  it('links only safe urls and emails', () => {
    const { unmount } = render(FieldValue, { field: field('site', { type: 'url' }), value: 'https://example.com' });
    expect(screen.getByRole('link')).toHaveAttribute('href', 'https://example.com');
    expect(screen.getByRole('link')).toHaveAttribute('rel', 'noopener noreferrer');
    unmount();

    const second = render(FieldValue, { field: field('site', { type: 'url' }), value: 'javascript:alert(1)' });
    expect(screen.queryByRole('link')).toBeNull();
    second.unmount();

    render(FieldValue, { field: field('email', { type: 'email' }), value: 'a@b.com', compact: true });
    expect(screen.getByRole('link')).toHaveAttribute('href', 'mailto:a@b.com');
  });

  it('truncates in compact mode with a title', () => {
    render(FieldValue, { field: field('t'), value: 'x'.repeat(120), compact: true });

    expect(screen.getByTitle('x'.repeat(120)).textContent).toHaveLength(80);
  });

  it('renders full text and compact json', () => {
    const { container, unmount } = render(FieldValue, { field: field('t', { type: 'text' }), value: 'linha 1\nlinha 2' });
    expect(container.querySelector('.multiline')?.textContent).toBe('linha 1\nlinha 2');
    unmount();

    render(FieldValue, { field: field('j', { type: 'json' }), value: { a: 1 }, compact: true });
    expect(screen.getByText('{"a":1}')).toBeInTheDocument();
  });

  it('never interprets values as html', () => {
    const { container } = render(FieldValue, { field: field('t'), value: '<img src=x onerror=alert(1)>' });

    expect(container.querySelector('img')).toBeNull();
    expect(container.textContent).toContain('<img src=x onerror=alert(1)>');
  });
});

describe('FieldInput', () => {
  it.each([
    ['email', 'email'],
    ['url', 'url'],
    ['password', 'password'],
    ['integer', 'number'],
    ['decimal', 'number'],
    ['date', 'date'],
    ['time', 'time'],
    ['datetime', 'datetime-local'],
    ['uuid', 'text'],
  ] as const)('uses the right input for %s', (type, inputType) => {
    const { container } = render(FieldInput, { field: field('x', { type }), value: '' });

    expect(container.querySelector('input')).toHaveAttribute('type', inputType);
  });

  it('sets steps and autocomplete hints', () => {
    const { container, unmount } = render(FieldInput, { field: field('x', { type: 'decimal' }), value: '' });
    expect(container.querySelector('input')).toHaveAttribute('step', 'any');
    unmount();

    const second = render(FieldInput, { field: field('x', { type: 'integer' }), value: '' });
    expect(second.container.querySelector('input')).toHaveAttribute('step', '1');
    second.unmount();

    render(FieldInput, { field: field('x', { type: 'password' }), value: '' });
    expect(document.querySelector('input')).toHaveAttribute('autocomplete', 'new-password');
  });

  it('renders selects with an empty option when nullable', () => {
    render(FieldInput, { field: field('s', { type: 'enum', nullable: true, options: [{ value: 'a', label: 'A' }] }), value: '' });

    expect(screen.getAllByRole('option').map((o) => o.textContent)).toEqual(['—', 'A']);
  });

  it('renders text areas and checkboxes', async () => {
    const { unmount } = render(FieldInput, { field: field('t', { type: 'text' }), value: 'oi', errors: ['Muito curto.'] });
    expect(screen.getByRole('textbox')).toHaveValue('oi');
    expect(screen.getByRole('textbox')).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByText('Muito curto.')).toBeInTheDocument();
    unmount();

    render(FieldInput, { field: field('b', { type: 'boolean' }), value: false });
    await userEvent.click(screen.getByRole('checkbox'));
    expect(screen.getByRole('checkbox')).toBeChecked();
  });
});
