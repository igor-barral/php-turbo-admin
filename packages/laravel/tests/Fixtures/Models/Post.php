<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use PhpTurbo\Admin\Core\Builder\AdminSchema;
use PhpTurbo\Admin\Laravel\HasAdmin;

class Post extends Model
{
    use HasAdmin;
    use SoftDeletes;

    protected $fillable = ['user_id', 'title', 'slug', 'body', 'status', 'metadata', 'price', 'published_at'];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'metadata' => 'array',
            'price' => 'decimal:2',
            'published_at' => 'datetime',
            // Cast sem coluna correspondente: ignorado.
            'reading_time' => 'integer',
            // Cast sem equivalente semântico: mantém o tipo do banco.
            'slug' => 'string',
        ];
    }

    public static function configureAdmin(AdminSchema $schema): void
    {
        $schema->field('title')->searchable()
            ->field('slug')->searchable()->rules(['max:60', 'unique:posts,slug,{id}'])
            // Anulável e ordenável: usa paginação por página.
            ->field('published_at')->sortable();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function labels(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function latestComment(): MorphOne
    {
        return $this->morphOne(Comment::class, 'commentable')->latestOfMany();
    }

    /** Tipo anulável que retorna null: ignorada. */
    public function maybeRelation(): ?BelongsTo
    {
        return null;
    }

    /** Relação customizada sem equivalente no core: ignorada. */
    public function custom(): CustomRelation
    {
        return new CustomRelation(Tag::query(), $this);
    }

    /** Sem tipo de retorno: não é descoberta (e nunca executada). */
    public function editor()
    {
        throw new \LogicException('Não deveria ser executado.');
    }

    /** Lança ao construir: ignorada sem derrubar a resolução. */
    public function broken(): BelongsTo
    {
        throw new \RuntimeException('Relação quebrada.');
    }

    /** Exige parâmetro: não é candidata. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    /** Tipo de retorno não relacional. */
    public function summary(): string
    {
        return mb_substr((string) $this->body, 0, 100);
    }

    /** Estático: não é candidata. */
    public static function staticRelation(): BelongsTo
    {
        throw new \LogicException('Não deveria ser executado.');
    }
}
