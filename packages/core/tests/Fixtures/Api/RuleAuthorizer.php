<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Tests\Fixtures\Api;

use PhpTurbo\Admin\Core\Auth\Ability;
use PhpTurbo\Admin\Core\Auth\Authorizer;
use PhpTurbo\Admin\Core\Data\Record;
use PhpTurbo\Admin\Core\Schema\EntitySchema;

/** Nega tudo, exceto o que for liberado explicitamente ("entidade.habilidade" ou "*"). */
final class RuleAuthorizer implements Authorizer
{
    /** @var list<array{mixed, string, string, mixed}> */
    public array $checks = [];

    /** @param list<string> $allowed */
    public function __construct(public array $allowed = ['*'], private readonly ?\Closure $recordRule = null)
    {
    }

    public function can(mixed $actor, Ability $ability, EntitySchema $entity, ?Record $record = null): bool
    {
        $this->checks[] = [$actor, $ability->value, $entity->key, $record?->id];

        if ($record !== null && $this->recordRule !== null && !($this->recordRule)($ability, $record)) {
            return false;
        }

        return in_array('*', $this->allowed, true)
            || in_array($entity->key . '.*', $this->allowed, true)
            || in_array($entity->key . '.' . $ability->value, $this->allowed, true);
    }
}
