<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Http;

use PhpTurbo\Admin\Core\Data\QueryPlan;
use PhpTurbo\Admin\Core\Schema\EntitySchema;
use PhpTurbo\Admin\Core\Schema\RelationSchema;
use PhpTurbo\Admin\Core\Schema\RelationType;
use PhpTurbo\Admin\Core\Schema\Sort;
use PhpTurbo\Admin\Core\Schema\Visibility;

/** @internal */
final class QueryPlanner
{
    /** Listagem: colunas visíveis no índice + belongsTo marcados como eager. */
    public function forIndex(EntitySchema $entity, Sort $sort, EntityVisibility $visibility): QueryPlan
    {
        $relations = array_filter(
            $entity->relations,
            static fn (RelationSchema $r): bool => $r->eager && $r->type === RelationType::BelongsTo,
        );

        return $this->plan($entity, Visibility::Index, $relations, $visibility, [$sort->field]);
    }

    /** Detalhe: todos os campos legíveis + relações de registro único. */
    public function forDetail(EntitySchema $entity, EntityVisibility $visibility): QueryPlan
    {
        $relations = array_filter($entity->relations, static fn (RelationSchema $r): bool => $r->type->isSingle());

        return $this->plan($entity, Visibility::Detail, $relations, $visibility);
    }

    /**
     * @param array<string, RelationSchema> $relations
     * @param list<string>                  $extra
     */
    private function plan(
        EntitySchema $entity,
        Visibility $screen,
        array $relations,
        EntityVisibility $visibility,
        array $extra = [],
    ): QueryPlan {
        $columns = [$entity->primaryKey => true];

        if ($entity->titleField !== null) {
            $columns[$entity->titleField] = true;
        }

        foreach ($extra as $column) {
            $columns[$column] = true;
        }

        foreach ($entity->fieldsVisibleOn($screen) as $field) {
            $columns[$field->name] = true;
        }

        $planned = [];
        foreach ($relations as $relation) {
            // morphTo pode apontar para qualquer entidade: só exibe a chave.
            $target = $relation->type === RelationType::MorphTo ? null : $visibility->target($relation->target);

            if ($target === null && $relation->type !== RelationType::MorphTo) {
                continue;
            }

            if ($relation->foreignKey !== null && $entity->hasField($relation->foreignKey)) {
                $columns[$relation->foreignKey] = true;
            }

            $planned[$relation->name] = $target === null ? null : $this->displayField($relation, $target);
        }

        return new QueryPlan(array_keys($columns), $planned);
    }

    /** O campo exibido precisa ser legível no schema do alvo — senão, usa a chave. */
    private function displayField(RelationSchema $relation, EntitySchema $target): ?string
    {
        $candidate = $relation->displayField ?? $target->titleField;

        return $candidate !== null && $target->hasField($candidate) && $target->field($candidate)->readable
            ? $candidate
            : null;
    }
}
