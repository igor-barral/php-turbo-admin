<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Tests\Fixtures\Api;

use PhpTurbo\Admin\Core\Data\ConflictException;
use PhpTurbo\Admin\Core\Data\DataSource;
use PhpTurbo\Admin\Core\Data\Page;
use PhpTurbo\Admin\Core\Data\QueryPlan;
use PhpTurbo\Admin\Core\Data\Record;
use PhpTurbo\Admin\Core\Data\RecordRef;
use PhpTurbo\Admin\Core\Query\Cursor;
use PhpTurbo\Admin\Core\Query\ListQuery;
use PhpTurbo\Admin\Core\Schema\EntitySchema;
use PhpTurbo\Admin\Core\Schema\SortDirection;

/** DataSource em memória que registra as chamadas, para testar o kernel isoladamente. */
final class InMemoryDataSource implements DataSource
{
    /** @var array<string, array<int, array<string, mixed>>> entidade => id => linha */
    public array $rows = [];

    /** @var list<array{string, mixed}> */
    public array $calls = [];

    /** @var array<string, array<int, array<string, RecordRef|list<RecordRef>|null>>> */
    public array $relations = [];

    public ?string $conflictOn = null;

    /** @param array<string, mixed> $row */
    public function seed(string $entity, array $row): void
    {
        $this->rows[$entity][(int) $row['id']] = $row;
    }

    public function list(EntitySchema $entity, ListQuery $query, QueryPlan $plan): Page
    {
        $this->calls[] = ['list', [$query, $plan]];
        $rows = array_values($this->rows[$entity->key] ?? []);

        foreach ($query->filters as $filter) {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => ($r[$filter->field] ?? null) === $filter->value));
        }

        if ($query->search !== null) {
            $rows = array_values(array_filter($rows, static function (array $r) use ($query): bool {
                foreach ($query->searchFields as $field) {
                    if (stripos((string) ($r[$field] ?? ''), (string) $query->search) !== false) {
                        return true;
                    }
                }

                return false;
            }));
        }

        $field = $query->sort->field;
        $desc = $query->sort->direction === SortDirection::Desc;
        usort($rows, static function (array $a, array $b) use ($field, $desc): int {
            $cmp = [$a[$field], $a['id']] <=> [$b[$field], $b['id']];

            return $desc ? -$cmp : $cmp;
        });

        if ($query->cursor !== null) {
            $cursor = $query->cursor;
            $rows = array_values(array_filter($rows, static function (array $r) use ($field, $desc, $cursor): bool {
                $cmp = [$r[$field], $r['id']] <=> [$cursor->value, $cursor->id];

                return $desc ? $cmp < 0 : $cmp > 0;
            }));
        }

        $slice = array_slice($rows, $query->keyset ? 0 : $query->offset(), $query->limit + 1);
        $hasMore = count($slice) > $query->limit;
        $slice = array_slice($slice, 0, $query->limit);
        $last = $slice === [] ? null : $slice[array_key_last($slice)];

        return new Page(
            array_map(fn (array $r): Record => $this->record($entity, $r, $plan), $slice),
            $hasMore,
            $last === null ? null : new Cursor($last[$field], $last['id']),
        );
    }

    public function find(EntitySchema $entity, string $id, QueryPlan $plan): ?Record
    {
        $this->calls[] = ['find', [$id, $plan]];
        $row = $this->rows[$entity->key][(int) $id] ?? null;

        return $row === null ? null : $this->record($entity, $row, $plan);
    }

    public function create(EntitySchema $entity, array $data): Record
    {
        $this->calls[] = ['create', $data];
        $this->guardConflict($entity);
        $id = ($this->rows[$entity->key] ?? []) === [] ? 1 : max(array_keys($this->rows[$entity->key])) + 1;
        $this->rows[$entity->key][$id] = ['id' => $id] + $data;

        return new Record($id, $this->rows[$entity->key][$id]);
    }

    public function update(EntitySchema $entity, Record $record, array $data): Record
    {
        $this->calls[] = ['update', $data];
        $this->guardConflict($entity);
        $this->rows[$entity->key][(int) $record->id] = $data + $this->rows[$entity->key][(int) $record->id];

        return new Record($record->id, $this->rows[$entity->key][(int) $record->id]);
    }

    public function delete(EntitySchema $entity, Record $record): void
    {
        $this->calls[] = ['delete', $record->id];
        unset($this->rows[$entity->key][(int) $record->id]);
    }

    public function lookup(EntitySchema $entity, string $search, array $fields, string $titleField, int $limit): array
    {
        $this->calls[] = ['lookup', [$search, $fields, $titleField, $limit]];
        $refs = [];
        foreach ($this->rows[$entity->key] ?? [] as $row) {
            if ($search === '' || stripos((string) $row[$titleField], $search) !== false) {
                $refs[] = new RecordRef($row['id'], (string) $row[$titleField]);
            }
        }

        return $refs;
    }

    /** @return list<string> */
    public function callNames(): array
    {
        return array_map(static fn (array $call): string => $call[0], $this->calls);
    }

    /** @param array<string, mixed> $row */
    private function record(EntitySchema $entity, array $row, QueryPlan $plan): Record
    {
        // Devolve todas as colunas (inclusive não planejadas) para provar que o serializer filtra.
        return new Record($row['id'], $row, $this->relations[$entity->key][(int) $row['id']] ?? [], source: $row);
    }

    private function guardConflict(EntitySchema $entity): void
    {
        if ($this->conflictOn === $entity->key) {
            throw new ConflictException('Já existe um registro com esses dados.');
        }
    }
}
