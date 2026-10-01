<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use PhpTurbo\Admin\Laravel\HasAdmin;

/** Abstrato: não deve ser descoberto. */
abstract class AbstractModel extends Model
{
    use HasAdmin;
}
