<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Schema;

/**
 * @phpstan-type ActionArray array{name: string, label: string, bulk: bool, destructive: bool}
 */
final readonly class ActionSchema
{
    public function __construct(
        public string $name,
        public string $label,
        public bool $bulk = false,
        public bool $destructive = false,
    ) {
    }

    /** @return ActionArray */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'bulk' => $this->bulk,
            'destructive' => $this->destructive,
        ];
    }

    /** @param ActionArray $data */
    public static function fromArray(array $data): self
    {
        return new self($data['name'], $data['label'], $data['bulk'], $data['destructive']);
    }
}
