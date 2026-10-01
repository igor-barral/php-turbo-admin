<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Auth;

/** Nomes alinhados às Policies do Laravel e aos Voters do Symfony. */
enum Ability: string
{
    case ViewAny = 'viewAny';
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
