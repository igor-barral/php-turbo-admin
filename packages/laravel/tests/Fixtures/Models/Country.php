<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use PhpTurbo\Admin\Laravel\HasAdmin;

class Country extends Model
{
    use HasAdmin;

    public $timestamps = false;

    protected $fillable = ['name'];

    protected $visible = ['id', 'name'];

    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(Post::class, User::class);
    }

    public function firstPost(): HasOneThrough
    {
        return $this->hasOneThrough(Post::class, User::class);
    }
}
