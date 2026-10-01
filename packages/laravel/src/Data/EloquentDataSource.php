<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Data;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
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

/**
 * Persistência via Eloquent: global scopes, casts, mutators, eventos e observers continuam
 * valendo. Colunas recebidas já foram validadas contra o schema pelo core.
 */
final class EloquentDataSource implements DataSource
{
    public function list(EntitySchema $entity, ListQuery $query, QueryPlan $plan): Page
    {
        $model = $this->model($entity);
        $builder = $this->query($model, $plan);

        foreach ($query->filters as $filter) {
            $column = $model->qualifyColumn($filter->field);
            $filter->value === null ? $builder->whereNull($column) : $builder->where($column, $filter->value);
        }

        if ($query->search !== null) {
            $term = '%' . $this->escapeLike($query->search) . '%';
            $builder->where(static function (Builder $where) use ($query, $model, $term): void {
                foreach ($query->searchFields as $field) {
                    $where->orWhereLike($model->qualifyColumn($field), $term);
                }
            });
        }

        $this->applySortAndCursor($builder, $model, $query);

        if (!$query->keyset) {
            $builder->offset($query->offset());
        }

        // Um registro a mais indica se há próxima página, sem COUNT(*).
        $models = $builder->limit($query->limit + 1)->get();
        $hasMore = $models->count() > $query->limit;
        $models = $models->take($query->limit)->values();

        /** @var Model|null $last */
        $last = $models->last();

        return new Page(
            array_values($models->map(fn (Model $m): Record => $this->toRecord($m, $entity, $plan))->all()),
            $hasMore,
            $last === null ? null : new Cursor($this->rawValue($last, $query->sort->field), $this->key($last)),
        );
    }

    public function find(EntitySchema $entity, string $id, QueryPlan $plan): ?Record
    {
        $model = $this->model($entity);

        // Evita erro de SQL (PostgreSQL) ao comparar coluna inteira com texto.
        if (in_array($model->getKeyType(), ['int', 'integer'], true) && preg_match('/^\d{1,18}$/', $id) !== 1) {
            return null;
        }

        $found = $this->query($model, $plan)->whereKey($id)->first();

        return $found instanceof Model ? $this->toRecord($found, $entity, $plan) : null;
    }

    public function create(EntitySchema $entity, array $data): Record
    {
        $model = $this->model($entity)->newInstance();

        $this->guard($model, static function () use ($model, $data): void {
            $model->fill($data)->save();
        });

        return new Record($this->key($model), [], source: $model);
    }

    public function update(EntitySchema $entity, Record $record, array $data): Record
    {
        $model = $this->source($record);

        $this->guard($model, static function () use ($model, $data): void {
            $model->fill($data)->save();
        });

        return new Record($this->key($model), [], source: $model);
    }

    public function delete(EntitySchema $entity, Record $record): void
    {
        $model = $this->source($record);

        $this->guard($model, static function () use ($model): void {
            $model->delete();
        });
    }

    public function lookup(EntitySchema $entity, string $search, array $fields, string $titleField, int $limit): array
    {
        $model = $this->model($entity);
        $builder = $model->newQuery()->select(array_values(array_unique([
            $model->getQualifiedKeyName(),
            $model->qualifyColumn($titleField),
        ])));

        if ($search !== '' && $fields !== []) {
            $term = '%' . $this->escapeLike($search) . '%';
            $builder->where(static function (Builder $where) use ($fields, $model, $term): void {
                foreach ($fields as $field) {
                    $where->orWhereLike($model->qualifyColumn($field), $term);
                }
            });
        }

        return array_values($builder->orderBy($model->qualifyColumn($titleField))
            ->limit($limit)
            ->get()
            ->map(fn (Model $m): RecordRef => new RecordRef($this->key($m), $this->stringValue($m, $titleField)))
            ->all());
    }

    private function model(EntitySchema $entity): Model
    {
        /** @var Model */
        return new ($entity->class)();
    }

    /** @return Builder<Model> */
    private function query(Model $model, QueryPlan $plan): Builder
    {
        $builder = $model->newQuery()->select(array_map($model->qualifyColumn(...), $plan->columns));

        foreach ($plan->relations as $name => $display) {
            $builder->with([$name => static function (Relation $relation) use ($display): void {
                // belongsTo: carrega só a chave e o campo exibido do alvo.
                if ($relation instanceof BelongsTo && $display !== null) {
                    $related = $relation->getRelated();
                    $relation->select(array_values(array_unique([
                        $related->qualifyColumn($relation->getOwnerKeyName()),
                        $related->qualifyColumn($display),
                    ])));
                }
            }]);
        }

        return $builder;
    }

    /** @param Builder<Model> $builder */
    private function applySortAndCursor(Builder $builder, Model $model, ListQuery $query): void
    {
        $direction = $query->sort->direction === SortDirection::Desc ? 'desc' : 'asc';
        $operator = $direction === 'desc' ? '<' : '>';
        $sortColumn = $model->qualifyColumn($query->sort->field);
        $keyColumn = $model->getQualifiedKeyName();
        $sortsByKey = $query->sort->field === $query->primaryKey;
        $cursor = $query->cursor;

        if ($cursor !== null) {
            if ($sortsByKey) {
                $builder->where($keyColumn, $operator, $cursor->id);
            } else {
                // (coluna, pk) > (valor, id), escrito de forma portável entre bancos.
                $builder->where(static function (Builder $where) use ($sortColumn, $keyColumn, $operator, $cursor): void {
                    $where->where($sortColumn, $operator, $cursor->value)
                        ->orWhere(static function (Builder $tie) use ($sortColumn, $keyColumn, $operator, $cursor): void {
                            $tie->where($sortColumn, '=', $cursor->value)->where($keyColumn, $operator, $cursor->id);
                        });
                });
            }
        }

        $builder->orderBy($sortColumn, $direction);

        if (!$sortsByKey) {
            $builder->orderBy($keyColumn, $direction);
        }
    }

    private function toRecord(Model $model, EntitySchema $entity, QueryPlan $plan): Record
    {
        $attributes = [];
        foreach ($plan->columns as $column) {
            if ($entity->hasField($column)) {
                $attributes[$column] = $this->attribute($model, $column);
            }
        }

        $relations = [];
        foreach ($plan->relations as $name => $display) {
            $related = $model->relationLoaded($name) ? $model->getRelation($name) : null;
            $relations[$name] = match (true) {
                $related instanceof Model => $this->ref($related, $display),
                $related instanceof Collection => array_values($related->map(fn (Model $m): RecordRef => $this->ref($m, $display))->all()),
                default => null,
            };
        }

        return new Record($this->key($model), $attributes, $relations, $model);
    }

    /** Valor com casts aplicados; um valor inválido no banco (ex.: enum desconhecido) vira null. */
    private function attribute(Model $model, string $column): mixed
    {
        try {
            return $model->getAttribute($column);
        } catch (\ValueError) {
            return null;
        }
    }

    private function ref(Model $model, ?string $display): RecordRef
    {
        return new RecordRef($this->key($model), $display === null ? (string) $this->key($model) : $this->stringValue($model, $display));
    }

    private function stringValue(Model $model, string $column): string
    {
        $value = $this->attribute($model, $column);

        return match (true) {
            $value instanceof \BackedEnum => (string) $value->value,
            is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => (string) $this->key($model),
        };
    }

    private function key(Model $model): string|int
    {
        $key = $model->getKey();

        return is_int($key) ? $key : (is_scalar($key) ? (string) $key : throw new \LogicException('Chave primária não escalar não é suportada.'));
    }

    /** Valor cru do banco (escalar vindo do PDO), comparável na próxima consulta. */
    private function rawValue(Model $model, string $column): string|int|float|bool|null
    {
        $value = $model->getRawOriginal($column);

        return is_scalar($value) ? $value : null;
    }

    private function source(Record $record): Model
    {
        if (!$record->source instanceof Model) {
            throw new \LogicException('Registro sem model Eloquent de origem.');
        }

        return $record->source;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Executa a escrita numa transação (savepoint, se já houver uma: no PostgreSQL um erro
     * aborta a transação externa inteira) e converte violações de integridade em
     * ConflictException (409), sem expor SQL. Observers rodam dentro da mesma transação.
     */
    private function guard(Model $model, callable $operation): void
    {
        try {
            $model->getConnection()->transaction(static function () use ($operation): void {
                $operation();
            });
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException('Já existe um registro com esses dados.');
        } catch (QueryException $exception) {
            if (str_starts_with((string) $exception->getCode(), '23')) {
                throw new ConflictException('A operação viola uma restrição do banco (o registro pode estar em uso).');
            }

            throw $exception;
        }
    }
}
