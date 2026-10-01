<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Schema;

use PhpTurbo\Admin\Core\Exception\UnknownFieldException;
use PhpTurbo\Admin\Core\Exception\UnknownRelationException;

/**
 * Descrição final e imutável de uma entidade. É o único formato que o core e a UI conhecem.
 *
 * @phpstan-import-type FieldArray from FieldSchema
 * @phpstan-import-type RelationArray from RelationSchema
 * @phpstan-import-type ActionArray from ActionSchema
 *
 * @phpstan-type EntityArray array{
 *     key: string, class: class-string, label: string, labelPlural: string, primaryKey: string,
 *     titleField: string|null, sort: array{field: string, direction: string}|null,
 *     fields: list<FieldArray>, relations: list<RelationArray>, actions: list<ActionArray>
 * }
 */
final readonly class EntitySchema
{
    /** @var array<string, FieldSchema> */
    public array $fields;

    /** @var array<string, RelationSchema> */
    public array $relations;

    /** @var array<string, ActionSchema> */
    public array $actions;

    /**
     * @param class-string           $class
     * @param list<FieldSchema>    $fields
     * @param list<RelationSchema> $relations
     * @param list<ActionSchema>   $actions
     */
    public function __construct(
        public string $key,
        public string $class,
        public string $label,
        public string $labelPlural,
        public string $primaryKey,
        public ?string $titleField,
        array $fields,
        array $relations = [],
        array $actions = [],
        public ?Sort $sort = null,
    ) {
        $this->fields = self::indexByName($fields);
        $this->relations = self::indexByName($relations);
        $this->actions = self::indexByName($actions);
    }

    public function hasField(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    public function field(string $name): FieldSchema
    {
        return $this->fields[$name] ?? throw UnknownFieldException::on($this->class, $name, array_keys($this->fields));
    }

    public function hasRelation(string $name): bool
    {
        return isset($this->relations[$name]);
    }

    public function relation(string $name): RelationSchema
    {
        return $this->relations[$name]
            ?? throw UnknownRelationException::on($this->class, $name, array_keys($this->relations));
    }

    /** @return list<FieldSchema> */
    public function fieldsVisibleOn(Visibility $visibility): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (FieldSchema $field): bool => $field->isVisibleOn($visibility),
        ));
    }

    /** @return EntityArray */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'class' => $this->class,
            'label' => $this->label,
            'labelPlural' => $this->labelPlural,
            'primaryKey' => $this->primaryKey,
            'titleField' => $this->titleField,
            'sort' => $this->sort?->toArray(),
            'fields' => array_values(array_map(static fn (FieldSchema $f): array => $f->toArray(), $this->fields)),
            'relations' => array_values(array_map(
                static fn (RelationSchema $r): array => $r->toArray(),
                $this->relations,
            )),
            'actions' => array_values(array_map(static fn (ActionSchema $a): array => $a->toArray(), $this->actions)),
        ];
    }

    /** @param EntityArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            class: $data['class'],
            label: $data['label'],
            labelPlural: $data['labelPlural'],
            primaryKey: $data['primaryKey'],
            titleField: $data['titleField'],
            fields: array_map(FieldSchema::fromArray(...), $data['fields']),
            relations: array_map(RelationSchema::fromArray(...), $data['relations']),
            actions: array_map(ActionSchema::fromArray(...), $data['actions']),
            sort: $data['sort'] === null ? null : Sort::fromArray($data['sort']),
        );
    }

    /**
     * @template T of FieldSchema|RelationSchema|ActionSchema
     *
     * @param list<T> $items
     *
     * @return array<string, T>
     */
    private static function indexByName(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $indexed[$item->name] = $item;
        }

        return $indexed;
    }
}
