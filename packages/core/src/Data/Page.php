<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Data;

use PhpTurbo\Admin\Core\Query\Cursor;

final readonly class Page
{
    /**
     * @param list<Record> $records
     * @param Cursor|null  $next    posição após o último registro (paginação por cursor)
     */
    public function __construct(
        public array $records,
        public bool $hasMore,
        public ?Cursor $next = null,
    ) {
    }
}
