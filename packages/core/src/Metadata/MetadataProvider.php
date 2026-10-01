<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Metadata;

use PhpTurbo\Admin\Core\Builder\EntityDraft;

/**
 * Uma camada de metadados. Providers rodam em ordem crescente de prioridade e cada um
 * pode sobrescrever o que os anteriores definiram.
 */
interface MetadataProvider
{
    /** Ver constantes em Priority. */
    public function priority(): int;

    /** @param class-string $class */
    public function supports(string $class): bool;

    /** @param class-string $class */
    public function provide(string $class, EntityDraft $draft): void;
}
