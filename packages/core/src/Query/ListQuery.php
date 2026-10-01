<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Query;

use PhpTurbo\Admin\Core\Schema\Sort;

/**
 * Consulta de listagem já validada contra o schema. Campos aqui são sempre nomes
 * conhecidos — o DataSource pode usá-los como colunas com segurança.
 */
final readonly class ListQuery
{
    /**
     * @param list<Filter> $filters
     * @param list<string> $searchFields
     */
    public function __construct(
        public Sort $sort,
        public string $primaryKey,
        public int $limit,
        public bool $keyset,
        public ?Cursor $cursor = null,
        public int $page = 1,
        public array $filters = [],
        public ?string $search = null,
        public array $searchFields = [],
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
