<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Schema;

enum Visibility: string
{
    case Index = 'index';
    case Detail = 'detail';
    case Create = 'create';
    case Update = 'update';

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }

    /** @return list<self> */
    public static function read(): array
    {
        return [self::Index, self::Detail];
    }

    /** @return list<self> */
    public static function forms(): array
    {
        return [self::Create, self::Update];
    }

    public function isForm(): bool
    {
        return $this === self::Create || $this === self::Update;
    }
}
