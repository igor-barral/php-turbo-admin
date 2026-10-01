<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Metadata;

final class Priority
{
    /** Introspecção do banco: colunas, tipos, índices, FKs. */
    public const DATABASE = 100;

    /** Convenções do ORM: casts, hidden, fillable, relações. */
    public const ORM = 200;

    /** configureAdmin() do usuário. */
    public const USER = 300;
}
