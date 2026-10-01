import type { Field } from './types';
import { t } from './i18n';

const dateFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeZone: 'UTC' });
const dateTimeFormat = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
const numberFormat = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 10 });

/** Texto exibido para um valor (sempre texto puro: a UI nunca injeta HTML). */
export function formatValue(field: Field, value: unknown): string {
  if (value === null || value === undefined || value === '') return t.empty;

  switch (field.type) {
    case 'boolean':
      return value ? t.yes : t.no;
    case 'enum':
      return field.options.find((o) => String(o.value) === String(value))?.label ?? String(value);
    case 'date':
      return formatDate(String(value), dateFormat, `${value}T00:00:00Z`);
    case 'datetime':
      return formatDate(String(value), dateTimeFormat, String(value));
    case 'integer':
    case 'decimal':
    case 'money': {
      const number = Number(value);
      return Number.isFinite(number) ? numberFormat.format(number) : String(value);
    }
    case 'json':
      return JSON.stringify(value);
    default:
      return String(value);
  }
}

function formatDate(raw: string, format: Intl.DateTimeFormat, iso: string): string {
  const date = new Date(iso);

  return Number.isNaN(date.getTime()) ? raw : format.format(date);
}

/** Encurta textos longos para a listagem. */
export function truncate(text: string, max = 80): string {
  return text.length > max ? text.slice(0, max - 1) + '…' : text;
}
