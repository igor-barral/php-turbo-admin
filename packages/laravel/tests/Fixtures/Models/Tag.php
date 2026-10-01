<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** Sem HasAdmin: não deve ser descoberto. */
class Tag extends Model
{
    protected $fillable = ['name'];
}
