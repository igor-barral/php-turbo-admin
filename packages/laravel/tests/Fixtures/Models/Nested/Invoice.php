<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\Nested;

use Illuminate\Database\Eloquent\Model;
use PhpTurbo\Admin\Laravel\HasAdmin;

/** Em subdiretório: descoberto pelo mapeamento PSR-4. */
class Invoice extends Model
{
    use HasAdmin;

    protected $fillable = ['number', 'status', 'paid'];
}
