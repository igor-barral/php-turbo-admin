<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** Chave primária string, sem HasAdmin (usado direto pelo DataSource nos testes). */
class Setting extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];
}
