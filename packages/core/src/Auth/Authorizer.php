<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Auth;

use PhpTurbo\Admin\Core\Data\Record;
use PhpTurbo\Admin\Core\Schema\EntitySchema;

/**
 * Porta de autorização. Sem regra registrada para a entidade, a implementação deve negar.
 */
interface Authorizer
{
    /**
     * @param mixed $actor usuário autenticado no formato do framework (opaco para o core)
     */
    public function can(mixed $actor, Ability $ability, EntitySchema $entity, ?Record $record = null): bool;
}
