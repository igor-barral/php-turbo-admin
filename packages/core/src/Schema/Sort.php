<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Schema;

final readonly class Sort
{
    public function __construct(
        public string $field,
        public SortDirection $direction = SortDirection::Asc,
    ) {
    }

    /** @return array{field: string, direction: string} */
    public function toArray(): array
    {
        return ['field' => $this->field, 'direction' => $this->direction->value];
    }

    /** @param array{field: string, direction: string} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['field'], SortDirection::from($data['direction']));
    }
}
