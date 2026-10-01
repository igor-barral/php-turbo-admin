<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Schema;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
