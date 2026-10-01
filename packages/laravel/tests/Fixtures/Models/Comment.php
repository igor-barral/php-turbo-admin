<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use PhpTurbo\Admin\Laravel\HasAdmin;

class Comment extends Model
{
    use HasAdmin;

    protected $guarded = ['id'];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
