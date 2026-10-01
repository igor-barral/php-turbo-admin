<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Http;

use PhpTurbo\Admin\Core\Auth\Ability;
use PhpTurbo\Admin\Core\Auth\Authorizer;
use PhpTurbo\Admin\Core\Schema\EntitySchema;
use PhpTurbo\Admin\Core\Schema\FieldSchema;
use PhpTurbo\Admin\Core\Schema\RelationSchema;
use PhpTurbo\Admin\Core\Schema\RelationType;

/**
 * Versão pública do schema para a UI. Comparado ao cache interno, omite:
 * classes PHP, regras de validação do framework (podem citar tabelas) e relações
 * para entidades que o usuário não pode ver.
 *
 * @internal
 */
final readonly class SchemaPresenter
{
    public const VERSION = 1;

    public function __construct(private Authorizer $authorizer)
    {
    }

    /** @return array<string, mixed> */
    public function present(EntityVisibility $visibility, mixed $actor): array
    {
        return [
            'version' => self::VERSION,
            'entities' => array_map(
                fn (EntitySchema $entity): array => $this->entity($entity, $visibility, $actor),
                $visibility->visibleEntities(),
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function entity(EntitySchema $entity, EntityVisibility $visibility, mixed $actor): array
    {
        $relations = [];
        foreach ($entity->relations as $relation) {
            $presented = $this->relation($relation, $visibility);
            if ($presented !== null) {
                $relations[] = $presented;
            }
        }

        return [
            'key' => $entity->key,
            'label' => $entity->label,
            'labelPlural' => $entity->labelPlural,
            'primaryKey' => $entity->primaryKey,
            'titleField' => $entity->titleField,
            'sort' => $entity->sort?->toArray(),
            'searchable' => array_filter($entity->fields, static fn (FieldSchema $f): bool => $f->searchable) !== [],
            'fields' => array_values(array_map(static function (FieldSchema $field): array {
                $array = $field->toArray();
                unset($array['rules']);

                return $array;
            }, $entity->fields)),
            'relations' => $relations,
            'actions' => array_values(array_map(static fn ($a): array => $a->toArray(), $entity->actions)),
            'abilities' => ['create' => $this->authorizer->can($actor, Ability::Create, $entity)],
        ];
    }

    /** @return array<string, mixed>|null */
    private function relation(RelationSchema $relation, EntityVisibility $visibility): ?array
    {
        $target = $visibility->target($relation->target);

        if ($target === null && $relation->type !== RelationType::MorphTo) {
            return null;
        }

        return [
            'name' => $relation->name,
            'label' => $relation->label,
            'type' => $relation->type->value,
            'target' => $target?->key,
            'foreignKey' => $relation->foreignKey,
            // Escrever um belongsTo exige buscar opções no alvo (lookup).
            'writable' => $relation->writable && $target !== null && $relation->type === RelationType::BelongsTo,
        ];
    }
}
