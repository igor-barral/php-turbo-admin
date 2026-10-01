<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Metadata;

use PhpTurbo\Admin\Core\Builder\AdminSchema;
use PhpTurbo\Admin\Core\Builder\EntityDraft;

/** Camada 3: overrides declarados pelo usuário em configureAdmin(). */
final class ConfigureAdminProvider implements MetadataProvider
{
    public function priority(): int
    {
        return Priority::USER;
    }

    public function supports(string $class): bool
    {
        if (!method_exists($class, 'configureAdmin')) {
            return false;
        }

        return (new \ReflectionMethod($class, 'configureAdmin'))->isStatic();
    }

    public function provide(string $class, EntityDraft $draft): void
    {
        $class::configureAdmin(new AdminSchema($draft));
    }
}
