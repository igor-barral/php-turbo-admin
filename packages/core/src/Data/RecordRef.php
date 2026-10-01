<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Data;

/** Referência a um registro relacionado: o suficiente para exibir e linkar. */
final readonly class RecordRef
{
    public function __construct(public string|int $id, public string $title)
    {
    }

    /** @return array{id: string|int, title: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'title' => $this->title];
    }
}
