<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core;

use PhpTurbo\Admin\Core\Builder\AdminSchema;

/**
 * Contrato framework-neutro para classes expostas no admin.
 *
 * Implementar a interface é opcional (o trait de cada adapter já fornece o método),
 * mas dá checagem de tipo explícita.
 */
interface AdminConfigurable
{
    public static function configureAdmin(AdminSchema $schema): void;
}
