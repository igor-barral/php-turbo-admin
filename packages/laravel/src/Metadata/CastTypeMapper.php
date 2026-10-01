<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Metadata;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Support\Str;
use PhpTurbo\Admin\Core\Schema\FieldType;

/**
 * Converte casts do Eloquent em tipos semânticos. Retorna null para casts sem equivalente
 * (o tipo vindo do banco é mantido).
 *
 * @internal
 */
final class CastTypeMapper
{
    public function map(string $cast): ?FieldType
    {
        if (enum_exists($cast)) {
            return FieldType::Enum;
        }

        $normalized = strtolower($cast);
        $base = explode(':', $normalized, 2)[0];

        return match (true) {
            in_array($base, ['int', 'integer'], true) => FieldType::Integer,
            in_array($base, ['real', 'float', 'double', 'decimal'], true) => FieldType::Decimal,
            in_array($base, ['bool', 'boolean'], true) => FieldType::Boolean,
            in_array($base, ['date', 'immutable_date'], true) => FieldType::Date,
            in_array($base, ['datetime', 'immutable_datetime', 'timestamp'], true) => FieldType::DateTime,
            in_array($base, ['array', 'json', 'object', 'collection'], true),
            in_array($normalized, ['encrypted:array', 'encrypted:collection', 'encrypted:object'], true),
            $this->isJsonCastClass($cast) => FieldType::Json,
            $base === 'hashed' => FieldType::Password,
            default => null,
        };
    }

    /**
     * Opções de um enum PHP (BackedEnum) usado como cast.
     *
     * @return list<array{value: string|int, label: string}>
     */
    public function enumOptions(string $cast): array
    {
        if (!is_subclass_of($cast, \BackedEnum::class)) {
            return [];
        }

        return array_map(
            static fn (\BackedEnum $case): array => ['value' => $case->value, 'label' => Str::headline($case->name)],
            $cast::cases(),
        );
    }

    private function isJsonCastClass(string $cast): bool
    {
        $class = explode(':', $cast, 2)[0];

        return in_array($class, [AsArrayObject::class, AsCollection::class, AsEncryptedArrayObject::class, AsEncryptedCollection::class], true);
    }
}
