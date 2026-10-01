<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Discovery;

/** Encontra as classes que devem aparecer no admin. Cada adapter implementa a sua estratégia. */
interface ModelDiscovery
{
    /** @return list<class-string> */
    public function discover(): array;
}
