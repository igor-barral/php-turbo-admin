import { fromInput, InvalidInput, toInput } from '../src/lib/form';
import { formatValue, truncate } from '../src/lib/format';
import { field } from './fixtures';

describe('formatValue', () => {
  const enumField = field('status', { type: 'enum', options: [{ value: 'draft', label: 'Rascunho' }, { value: 2, label: 'Dois' }] });

  it.each([
    [field('x'), null, '—'],
    [field('x'), '', '—'],
    [field('x'), undefined, '—'],
    [field('x', { type: 'boolean' }), true, 'Sim'],
    [field('x', { type: 'boolean' }), false, 'Não'],
    [enumField, 'draft', 'Rascunho'],
    [enumField, '2', 'Dois'],
    [enumField, 'other', 'other'],
    [field('x', { type: 'date' }), '2026-03-04', '04/03/2026'],
    [field('x', { type: 'date' }), 'invalid', 'invalid'],
    [field('x', { type: 'integer' }), 1234567, '1.234.567'],
    [field('x', { type: 'decimal' }), '10.50', '10,5'],
    [field('x', { type: 'money' }), 'abc', 'abc'],
    [field('x', { type: 'json' }), { a: 1 }, '{"a":1}'],
    [field('x'), 42, '42'],
  ])('formats %o / %o', (f, value, expected) => {
    expect(formatValue(f, value)).toBe(expected);
  });

  it('formats datetimes in the local zone', () => {
    expect(formatValue(field('x', { type: 'datetime' }), '2026-03-04T10:00:00+00:00')).toMatch(/^04\/03\/2026/);
  });

  it('truncates long text', () => {
    expect(truncate('a'.repeat(100), 10)).toBe('a'.repeat(9) + '…');
    expect(truncate('curto')).toBe('curto');
  });
});

describe('toInput / fromInput', () => {
  it('converts booleans', () => {
    const f = field('x', { type: 'boolean' });
    expect(toInput(f, true)).toBe(true);
    expect(toInput(f, null)).toBe(false);
    expect(fromInput(f, true)).toBe(true);
  });

  it('converts empty values', () => {
    expect(toInput(field('x'), null)).toBe('');
    expect(fromInput(field('x', { type: 'integer', nullable: true }), '')).toBeNull();
    expect(fromInput(field('x', { type: 'string', nullable: true }), '')).toBe('');
    expect(fromInput(field('x', { type: 'text', nullable: true }), '')).toBe('');
  });

  it('converts numbers', () => {
    const f = field('x', { type: 'integer' });
    expect(fromInput(f, '42')).toBe(42);
    expect(fromInput(f, '4.2')).toBe('4.2');
    expect(fromInput(f, ' ')).toBe(' ');
    expect(toInput(f, 42)).toBe('42');
  });

  it('converts json and rejects invalid json', () => {
    const f = field('x', { type: 'json' });
    expect(toInput(f, { a: 1 })).toBe('{\n  "a": 1\n}');
    expect(fromInput(f, '{"a":1}')).toEqual({ a: 1 });
    expect(() => fromInput(f, '{')).toThrow(InvalidInput);
  });

  it('converts datetimes between ISO and local inputs', () => {
    const f = field('x', { type: 'datetime' });
    const local = toInput(f, '2026-03-04T10:30:00+00:00') as string;

    expect(local).toMatch(/^2026-03-04T\d{2}:30$/);
    expect(fromInput(f, local)).toBe('2026-03-04T10:30:00.000Z');
    expect(toInput(f, 'invalid')).toBe('');
    expect(fromInput(f, 'invalid')).toBe('invalid');
  });

  it('converts times and enums', () => {
    expect(toInput(field('x', { type: 'time' }), '08:15:00')).toBe('08:15');
    const e = field('x', { type: 'enum', options: [{ value: 2, label: 'Dois' }] });
    expect(fromInput(e, '2')).toBe(2);
    expect(fromInput(e, '3')).toBe('3');
  });
});
