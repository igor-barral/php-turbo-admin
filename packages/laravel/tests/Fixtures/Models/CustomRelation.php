<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Relação de terceiros sem equivalente no core: deve ser ignorada.
 *
 * @extends Relation<Tag, Post, null>
 */
class CustomRelation extends Relation
{
    public function addConstraints(): void
    {
    }

    public function addEagerConstraints(array $models): void
    {
    }

    public function initRelation(array $models, $relation): array
    {
        return $models;
    }

    public function match(array $models, Collection $results, $relation): array
    {
        return $models;
    }

    public function getResults(): mixed
    {
        return null;
    }
}
