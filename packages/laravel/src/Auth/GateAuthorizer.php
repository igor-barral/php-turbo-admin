<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Auth;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use PhpTurbo\Admin\Core\Auth\Ability;
use PhpTurbo\Admin\Core\Auth\Authorizer;
use PhpTurbo\Admin\Core\Data\Record;
use PhpTurbo\Admin\Core\Schema\EntitySchema;

/**
 * Autorização via Gate/Policies do app. Sem Policy para o model, o Gate nega — que é
 * exatamente o comportamento "negar por padrão" exigido pelo core.
 */
final readonly class GateAuthorizer implements Authorizer
{
    public function __construct(private Gate $gate)
    {
    }

    public function can(mixed $actor, Ability $ability, EntitySchema $entity, ?Record $record = null): bool
    {
        $subject = $record?->source instanceof Model ? $record->source : $entity->class;

        return $this->gate
            ->forUser($actor instanceof Authenticatable ? $actor : null)
            ->allows($ability->value, [$subject]);
    }
}
