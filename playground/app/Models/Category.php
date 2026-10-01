<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PhpTurbo\Admin\Core\Builder\AdminSchema;
use PhpTurbo\Admin\Laravel\HasAdmin;

class Category extends Model
{
    use HasAdmin;

    protected $fillable = ['name', 'description'];

    public static function configureAdmin(AdminSchema $schema): void
    {
        $schema->label('Categoria', 'Categorias')
            ->field('name')->label('Nome')->searchable()
            ->field('description')->label('Descrição');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
