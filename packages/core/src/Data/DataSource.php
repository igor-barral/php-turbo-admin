<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Data;

use PhpTurbo\Admin\Core\Query\ListQuery;
use PhpTurbo\Admin\Core\Schema\EntitySchema;

/**
 * Porta de persistência. A implementação deve passar pelo ORM (scopes, casts, eventos).
 * Nomes de campos recebidos aqui já foram validados contra o schema pelo core.
 */
interface DataSource
{
    public function list(EntitySchema $entity, ListQuery $query, QueryPlan $plan): Page;

    public function find(EntitySchema $entity, string $id, QueryPlan $plan): ?Record;

    /** @param array<string, mixed> $data */
    public function create(EntitySchema $entity, array $data): Record;

    /** @param array<string, mixed> $data */
    public function update(EntitySchema $entity, Record $record, array $data): Record;

    public function delete(EntitySchema $entity, Record $record): void;

    /**
     * Busca curta para autocomplete de relações.
     *
     * @param list<string> $fields campos onde procurar
     *
     * @return list<RecordRef>
     */
    public function lookup(EntitySchema $entity, string $search, array $fields, string $titleField, int $limit): array;
}
