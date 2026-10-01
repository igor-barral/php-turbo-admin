<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Tests\Fixtures;

use PhpTurbo\Admin\Core\Builder\AdminSchema;

/** configureAdmin() de instância não é aceito: o schema é por classe. */
final class InstanceConfigured
{
    public function configureAdmin(AdminSchema $schema): void
    {
        $schema->label('nunca chamado');
    }
}
