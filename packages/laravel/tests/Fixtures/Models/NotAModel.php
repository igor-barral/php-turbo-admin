<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Tests\Fixtures\Models;

use PhpTurbo\Admin\Laravel\HasAdmin;

/** Usa o trait mas não é model: não deve ser descoberto. */
class NotAModel
{
    use HasAdmin;
}
