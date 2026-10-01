<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Validation;

use PhpTurbo\Admin\Core\Schema\FieldSchema;
use PhpTurbo\Admin\Core\Schema\FieldType;

/**
 * Converte valores vindos do cliente (JSON ou query string) para o tipo do campo,
 * rejeitando o que não corresponde. Usado em filtros e na escrita.
 */
final class ValueCoercer
{
    private const DATETIME_FORMATS = [\DateTimeInterface::ATOM, 'Y-m-d\TH:i:s.vP', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];

    /** @throws InvalidValue */
    public function coerce(FieldSchema $field, mixed $value): mixed
    {
        // Formulários enviam "" para campos vazios: em campos anuláveis não textuais, vira null.
        if ($value === '' && $field->nullable && !$this->isTextual($field->type)) {
            $value = null;
        }

        if ($value === null) {
            if (!$field->nullable) {
                throw new InvalidValue('Campo obrigatório.');
            }

            return null;
        }

        return match ($field->type) {
            FieldType::Integer => $this->integer($value),
            FieldType::Decimal, FieldType::Money => $this->decimal($value),
            FieldType::Boolean => $this->boolean($value),
            FieldType::Date => $this->dateTime($value, ['Y-m-d'], 'Y-m-d', 'Data inválida (use AAAA-MM-DD).'),
            FieldType::DateTime => $this->dateTime($value, self::DATETIME_FORMATS, 'Y-m-d H:i:s', 'Data e hora inválidas.'),
            FieldType::Time => $this->dateTime($value, ['H:i:s', 'H:i'], 'H:i:s', 'Horário inválido (use HH:MM).'),
            FieldType::Enum => $this->enum($field, $value),
            FieldType::Json => $this->json($value),
            FieldType::Email => $this->filtered($value, FILTER_VALIDATE_EMAIL, 'E-mail inválido.'),
            FieldType::Url => $this->url($value),
            FieldType::Uuid => $this->uuid($value),
            default => $this->string($value),
        };
    }

    private function isTextual(FieldType $type): bool
    {
        return in_array($type, [FieldType::String, FieldType::Text, FieldType::Password], true);
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d{1,18}$/', $value) === 1) {
            return (int) $value;
        }

        if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            return (int) $value;
        }

        throw new InvalidValue('Deve ser um número inteiro.');
    }

    /** Decimais trafegam como string para não perder precisão. */
    private function decimal(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return $value;
        }

        throw new InvalidValue('Deve ser um número.');
    }

    private function boolean(mixed $value): bool
    {
        return match (true) {
            is_bool($value) => $value,
            $value === 1, $value === '1', $value === 'true' => true,
            $value === 0, $value === '0', $value === 'false' => false,
            default => throw new InvalidValue('Deve ser verdadeiro ou falso.'),
        };
    }

    /** @param list<string> $formats */
    private function dateTime(mixed $value, array $formats, string $output, string $message): string
    {
        if (is_string($value)) {
            foreach ($formats as $format) {
                $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $value);
                $errors = \DateTimeImmutable::getLastErrors();
                if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                    // Com offset explícito (ISO 8601), converte para o fuso padrão da aplicação.
                    return $parsed->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format($output);
                }
            }
        }

        throw new InvalidValue($message);
    }

    private function enum(FieldSchema $field, mixed $value): string|int
    {
        foreach ($field->options as $option) {
            if ((string) $option['value'] === (is_scalar($value) ? (string) $value : null)) {
                return $option['value'];
            }
        }

        throw new InvalidValue('Opção inválida.');
    }

    /** @return array<mixed> */
    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        throw new InvalidValue('Deve ser um objeto ou lista JSON.');
    }

    private function filtered(mixed $value, int $filter, string $message): string
    {
        $string = $this->string($value);

        if (filter_var($string, $filter) === false) {
            throw new InvalidValue($message);
        }

        return $string;
    }

    private function url(mixed $value): string
    {
        $url = $this->filtered($value, FILTER_VALIDATE_URL, 'URL inválida.');

        // Bloqueia javascript:, data: etc. — a URL pode ser exibida como link na UI.
        if (!in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new InvalidValue('A URL deve começar com http:// ou https://.');
        }

        return $url;
    }

    private function uuid(mixed $value): string
    {
        $string = $this->string($value);

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $string) !== 1) {
            throw new InvalidValue('UUID inválido.');
        }

        return $string;
    }

    private function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new InvalidValue('Deve ser um texto.');
    }
}
