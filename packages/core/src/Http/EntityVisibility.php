<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Http;

use PhpTurbo\Admin\Core\Auth\Ability;
use PhpTurbo\Admin\Core\Auth\Authorizer;
use PhpTurbo\Admin\Core\Registry\SchemaRegistry;
use PhpTurbo\Admin\Core\Schema\EntitySchema;

/**
 * Quais entidades o usuário atual pode ver (viewAny), calculado uma vez por requisição.
 * Também decide se dados de uma relação podem aparecer: só se o alvo for visível.
 *
 * @internal
 */
final class EntityVisibility
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function __construct(
        private readonly SchemaRegistry $registry,
        private readonly Authorizer $authorizer,
        private readonly mixed $actor,
    ) {
    }

    public function canSee(EntitySchema $entity): bool
    {
        return $this->cache[$entity->key] ??= $this->authorizer->can($this->actor, Ability::ViewAny, $entity);
    }

    /** Schema do alvo de uma relação, se registrado e visível. */
    public function target(?string $class): ?EntitySchema
    {
        if ($class === null || !$this->registry->hasClass($class)) {
            return null;
        }

        $target = $this->registry->forClass($class);

        return $this->canSee($target) ? $target : null;
    }

    /** @return list<EntitySchema> */
    public function visibleEntities(): array
    {
        return array_values(array_filter($this->registry->all(), $this->canSee(...)));
    }
}
