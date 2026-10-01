<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Metadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use PhpTurbo\Admin\Core\Schema\RelationType;

/**
 * Descobre relações pelos métodos públicos com tipo de retorno de relação declarado.
 *
 * Só esses métodos são invocados, numa instância vazia do model: construir a relação
 * não consulta o banco. Métodos sem tipo de retorno não são executados — devem ser
 * declarados em configureAdmin().
 *
 * @internal
 */
final class RelationInspector
{
    /**
     * Ordem importa: subclasses antes das classes-base (MorphTo estende BelongsTo etc.).
     *
     * @var array<class-string<Relation<Model, Model, mixed>>, RelationType>
     */
    private const TYPES = [
        MorphTo::class => RelationType::MorphTo,
        BelongsTo::class => RelationType::BelongsTo,
        MorphToMany::class => RelationType::MorphToMany,
        BelongsToMany::class => RelationType::BelongsToMany,
        HasOneThrough::class => RelationType::HasOneThrough,
        HasManyThrough::class => RelationType::HasManyThrough,
        MorphOne::class => RelationType::MorphOne,
        MorphMany::class => RelationType::MorphMany,
        HasOne::class => RelationType::HasOne,
        HasMany::class => RelationType::HasMany,
    ];

    /**
     * @return list<array{name: string, type: RelationType, target: class-string|null, foreignKey: string|null}>
     */
    public function inspect(Model $model): array
    {
        $relations = [];
        $frameworkMethods = $this->frameworkTraitMethods($model);

        foreach ((new \ReflectionClass($model))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$this->isCandidate($method, $frameworkMethods)) {
                continue;
            }

            try {
                $relation = $method->invoke($model);
            } catch (\Throwable) {
                continue;
            }

            if (!$relation instanceof Relation) {
                continue;
            }

            $type = $this->typeOf($relation);
            if ($type === null) {
                continue;
            }

            $relations[] = [
                'name' => $method->getName(),
                'type' => $type,
                'target' => $type === RelationType::MorphTo ? null : $relation->getRelated()::class,
                'foreignKey' => $this->foreignKeyOf($relation),
            ];
        }

        return $relations;
    }

    /** @param array<string, true> $frameworkMethods */
    private function isCandidate(\ReflectionMethod $method, array $frameworkMethods): bool
    {
        if ($method->isStatic() || $method->isAbstract() || $method->getNumberOfRequiredParameters() > 0) {
            return false;
        }

        // Relações vindas de traits do framework (ex.: Notifiable::notifications) ficam de fora.
        if (isset($frameworkMethods[$method->getName()]) || $method->getDeclaringClass()->getName() === Model::class) {
            return false;
        }

        $returnType = $method->getReturnType();

        return $returnType instanceof \ReflectionNamedType
            && !$returnType->isBuiltin()
            && is_a($returnType->getName(), Relation::class, true);
    }

    /** @return array<string, true> */
    private function frameworkTraitMethods(Model $model): array
    {
        $methods = [];
        foreach (class_uses_recursive($model) as $trait) {
            if (str_starts_with($trait, 'Illuminate\\')) {
                foreach (get_class_methods($trait) as $name) {
                    $methods[$name] = true;
                }
            }
        }

        return $methods;
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private function typeOf(Relation $relation): ?RelationType
    {
        foreach (self::TYPES as $class => $type) {
            if ($relation instanceof $class) {
                return $type;
            }
        }

        return null;
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private function foreignKeyOf(Relation $relation): ?string
    {
        return match (true) {
            $relation instanceof BelongsTo => $relation->getForeignKeyName(),
            $relation instanceof HasOneOrMany => $relation->getForeignKeyName(),
            default => null,
        };
    }
}
