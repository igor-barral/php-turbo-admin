import type { Field } from './types';

export type InputValue = string | boolean;

const pad = (n: number) => String(n).padStart(2, '0');

/** Valor da API → valor do controle de formulário. */
export function toInput(field: Field, value: unknown): InputValue {
  if (field.type === 'boolean') return value === true;
  if (value === null || value === undefined) return '';

  switch (field.type) {
    case 'json':
      return JSON.stringify(value, null, 2);
    case 'datetime': {
      // ISO 8601 com offset → horário local do navegador para <input type="datetime-local">.
      const date = new Date(String(value));
      if (Number.isNaN(date.getTime())) return '';
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }
    case 'time':
      return String(value).slice(0, 5);
    default:
      return String(value);
  }
}

export class InvalidInput extends Error {}

/** Valor do controle → payload da API. Validação final é sempre do servidor. */
export function fromInput(field: Field, value: InputValue): unknown {
  if (typeof value === 'boolean') return value;
  if (value === '' && field.nullable && field.type !== 'string' && field.type !== 'text') return null;

  switch (field.type) {
    case 'integer':
      return value.trim() !== '' && Number.isInteger(Number(value)) ? Number(value) : value;
    case 'json':
      try {
        return JSON.parse(value);
      } catch {
        throw new InvalidInput('JSON inválido.');
      }
    case 'datetime': {
      // Envia em UTC com offset: o servidor converte para o fuso da aplicação.
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? value : date.toISOString();
    }
    case 'enum': {
      const option = field.options.find((o) => String(o.value) === value);
      return option ? option.value : value;
    }
    default:
      return value;
  }
}
