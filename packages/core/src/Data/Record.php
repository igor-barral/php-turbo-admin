<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Data;

/**
 * Registro devolvido pelo DataSource. `attributes` traz valores brutos (já com casts do ORM);
 * o core filtra e normaliza antes de serializar. `source` é o objeto original do ORM, usado
 * pelo adapter (autorização, escrita) e nunca serializado.
 */
final readonly class Record
{
    /**
     * @param array<string, mixed>                          $attributes
     * @param array<string, RecordRef|list<RecordRef>|null> $relations
     */
    public function __construct(
        public string|int $id,
        public array $attributes,
        public array $relations = [],
        public mixed $source = null,
    ) {
    }
}
