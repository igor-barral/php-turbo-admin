<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel;

use PhpTurbo\Admin\Core\Builder\AdminSchema;

/**
 * Marca um model Eloquent para o admin. Sobrescreva configureAdmin() apenas quando
 * a inferência (banco + convenções do Eloquent) não bastar.
 *
 * @phpstan-ignore trait.unused
 */
trait HasAdmin
{
    public static function configureAdmin(AdminSchema $schema): void
    {
    }
}
