<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use PhpTurbo\Admin\Core\Builder\AdminSchema;
use PhpTurbo\Admin\Laravel\HasAdmin;

class User extends Authenticatable
{
    use HasAdmin;
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'country_id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'email_verified_at' => 'datetime',
        ];
    }

    public static function configureAdmin(AdminSchema $schema): void
    {
        $schema->field('password')->writeOnly();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function latestPost(): HasOne
    {
        return $this->hasOne(Post::class)->latestOfMany();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
