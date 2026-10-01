<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Tests\Fixtures;

use PhpTurbo\Admin\Core\AdminConfigurable;
use PhpTurbo\Admin\Core\Builder\AdminSchema;

final class Post implements AdminConfigurable
{
    /** @var (callable(AdminSchema): void)|null */
    public static $configure = null;

    public static function configureAdmin(AdminSchema $schema): void
    {
        if (self::$configure !== null) {
            (self::$configure)($schema);
        }
    }
}
