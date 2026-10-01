<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Http;

use PhpTurbo\Admin\Core\Data\QueryPlan;
use PhpTurbo\Admin\Core\Data\Record;
use PhpTurbo\Admin\Core\Data\RecordRef;
use PhpTurbo\Admin\Core\Schema\EntitySchema;
use PhpTurbo\Admin\Core\Schema\FieldType;

/**
 * Serializa registros: só campos legíveis do schema, valores normalizados para JSON.
 * Qualquer atributo extra que o DataSource devolva é descartado.
 */
final class RecordSerializer
{
    /**
     * @param array<string, bool>|null $abilities
     *
     * @return array<string, mixed>
     */
    public function serialize(EntitySchema $entity, Record $record, QueryPlan $plan, ?array $abilities = null): array
    {
        $attributes = [];
        foreach ($record->attributes as $name => $value) {
            $field = $entity->fields[$name] ?? null;
            if ($field !== null && $field->readable) {
                $attributes[$name] = $this->normalize($field->type, $value);
            }
        }

        $relations = [];
        foreach (array_keys($plan->relations) as $name) {
            $value = $record->relations[$name] ?? null;
            $relations[$name] = match (true) {
                $value instanceof RecordRef => $value->toArray(),
                is_array($value) => array_map(static fn (RecordRef $ref): array => $ref->toArray(), $value),
                default => null,
            };
        }

        $serialized = [
            'id' => $record->id,
            'title' => $this->title($entity, $attributes, $record->id),
            // Objetos JSON mesmo quando vazios ({} e não []).
            'attributes' => (object) $attributes,
            'relations' => (object) $relations,
        ];

        if ($abilities !== null) {
            $serialized['abilities'] = $abilities;
        }

        return $serialized;
    }

    public function normalize(FieldType $type, mixed $value): mixed
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value), is_string($value) => $value,
            is_float($value) => is_finite($value) ? $value : null,
            $value instanceof \DateTimeInterface => $value->format(match ($type) {
                FieldType::Date => 'Y-m-d',
                FieldType::Time => 'H:i:s',
                default => \DateTimeInterface::ATOM,
            }),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            $value instanceof \JsonSerializable => $value->jsonSerialize(),
            is_array($value) => $value,
            $value instanceof \Stringable => (string) $value,
            // Objetos desconhecidos não são expostos (poderiam vazar estado interno).
            default => null,
        };
    }

    /** @param array<string, mixed> $attributes */
    private function title(EntitySchema $entity, array $attributes, string|int $id): string
    {
        $value = $entity->titleField === null ? null : ($attributes[$entity->titleField] ?? null);

        return is_scalar($value) && $value !== '' ? (string) $value : (string) $id;
    }
}
