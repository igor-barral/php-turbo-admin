<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Schema;

/**
 * Descrição final e imutável de um campo, já com as regras de segurança aplicadas.
 *
 * @phpstan-type FieldArray array{
 *     name: string, label: string, type: string, nullable: bool, readable: bool, writable: bool,
 *     searchable: bool, sortable: bool, filterable: bool, visibility: list<string>, widget: string|null,
 *     rules: list<string>, options: list<array{value: string|int, label: string}>, required: bool
 * }
 */
final readonly class FieldSchema
{
    /**
     * @param list<Visibility>                           $visibility
     * @param list<string>                               $rules
     * @param list<array{value: string|int, label: string}> $options
     */
    public function __construct(
        public string $name,
        public string $label,
        public FieldType $type,
        public bool $nullable,
        public bool $readable,
        public bool $writable,
        public bool $searchable,
        public bool $sortable,
        public bool $filterable,
        public array $visibility,
        public ?string $widget = null,
        public array $rules = [],
        public array $options = [],
        public bool $required = false,
    ) {
    }

    public function isVisibleOn(Visibility $visibility): bool
    {
        return in_array($visibility, $this->visibility, true);
    }

    /** @return FieldArray */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type->value,
            'nullable' => $this->nullable,
            'readable' => $this->readable,
            'writable' => $this->writable,
            'searchable' => $this->searchable,
            'sortable' => $this->sortable,
            'filterable' => $this->filterable,
            'visibility' => array_map(static fn (Visibility $v): string => $v->value, $this->visibility),
            'widget' => $this->widget,
            'rules' => $this->rules,
            'options' => $this->options,
            'required' => $this->required,
        ];
    }

    /** @param FieldArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            label: $data['label'],
            type: FieldType::from($data['type']),
            nullable: $data['nullable'],
            readable: $data['readable'],
            writable: $data['writable'],
            searchable: $data['searchable'],
            sortable: $data['sortable'],
            filterable: $data['filterable'],
            visibility: array_map(static fn (string $v): Visibility => Visibility::from($v), $data['visibility']),
            widget: $data['widget'],
            rules: $data['rules'],
            options: $data['options'],
            required: $data['required'],
        );
    }
}
