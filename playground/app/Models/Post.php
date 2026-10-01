<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use PhpTurbo\Admin\Core\Builder\AdminSchema;
use PhpTurbo\Admin\Laravel\HasAdmin;

class Post extends Model
{
    use HasAdmin, SoftDeletes;

    protected $fillable = ['user_id', 'category_id', 'title', 'slug', 'body', 'status', 'featured', 'metadata', 'published_at'];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'featured' => 'boolean',
            'metadata' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public static function configureAdmin(AdminSchema $schema): void
    {
        $schema->label('Publicação', 'Publicações')
            ->field('title')->label('Título')->searchable()
            ->field('slug')->searchable()->rules(['max:120', 'alpha_dash', 'unique:posts,slug,{id}'])
            ->field('body')->label('Conteúdo')
            ->field('status')->options(['draft' => 'Rascunho', 'review' => 'Em revisão', 'published' => 'Publicado'])
            ->field('featured')->label('Destaque')
            ->field('published_at')->label('Publicado em')
            ->relation('author')->label('Autor')
            ->relation('category')->label('Categoria');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
