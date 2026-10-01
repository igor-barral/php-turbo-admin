<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Schema;

/**
 * @phpstan-type RelationArray array{
 *     name: string, label: string, type: string, target: class-string|null, foreignKey: string|null,
 *     displayField: string|null, eager: bool, writable: bool
 * }
 */
final readonly class RelationSchema
{
    /**
     * @param class-string|null $target Classe relacionada (null em morphTo)
     */
    public function __construct(
        public string $name,
        public string $label,
        public RelationType $type,
        public ?string $target,
        public ?string $foreignKey,
        public ?string $displayField,
        public bool $eager,
        public bool $writable,
    ) {
    }

    /** @return RelationArray */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type->value,
            'target' => $this->target,
            'foreignKey' => $this->foreignKey,
            'displayField' => $this->displayField,
            'eager' => $this->eager,
            'writable' => $this->writable,
        ];
    }

    /** @param RelationArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            label: $data['label'],
            type: RelationType::from($data['type']),
            target: $data['target'],
            foreignKey: $data['foreignKey'],
            displayField: $data['displayField'],
            eager: $data['eager'],
            writable: $data['writable'],
        );
    }
}
