<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\RelationType;
use PhpTurbo\Admin\Laravel\Metadata\EloquentDatabaseProvider;
use PhpTurbo\Admin\Laravel\Metadata\EloquentModelProvider;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\Comment;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\Country;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\NotAModel;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\Post;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\Tag;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\User;

function eloquentDraft(string $class): EntityDraft
{
    $draft = new EntityDraft($class);
    (new EloquentDatabaseProvider())->provide($class, $draft);
    (new EloquentModelProvider())->provide($class, $draft);

    return $draft;
}

it('supports only eloquent models', function (): void {
    $provider = new EloquentModelProvider();

    expect($provider->supports(User::class))->toBeTrue()
        ->and($provider->supports(NotAModel::class))->toBeFalse();
});

it('derives key and labels with laravel inflection', function (): void {
    $draft = eloquentDraft(Country::class);

    expect($draft->key)->toBe('countries')
        ->and($draft->label)->toBe('Country')
        ->and($draft->labelPlural)->toBe('Countries')
        ->and($draft->primaryKey)->toBe('id')
        ->and($draft->field('id')->primary)->toBeTrue();
});

it('applies casts on top of database types', function (): void {
    $post = eloquentDraft(Post::class)->fields;
    $user = eloquentDraft(User::class)->fields;

    expect($post['status']->type)->toBe(FieldType::Enum)
        ->and($post['status']->options)->toBe([
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'in_review', 'label' => 'In Review'],
            ['value' => 'published', 'label' => 'Published'],
        ])
        ->and($post['metadata']->type)->toBe(FieldType::Json)
        ->and($post['price']->type)->toBe(FieldType::Decimal)
        ->and($user['password']->type)->toBe(FieldType::Password)
        ->and($user['is_admin']->type)->toBe(FieldType::Boolean)
        ->and($user['email_verified_at']->type)->toBe(FieldType::DateTime)
        ->and(eloquentDraft(Post::class)->hasField('reading_time'))->toBeFalse()
        // Cast "string"/sem equivalente mantém o tipo do banco.
        ->and($post['title']->type)->toBe(FieldType::String);
});

it('marks hidden attributes', function (): void {
    $fields = eloquentDraft(User::class)->fields;

    expect($fields['password']->hidden)->toBeTrue()
        ->and($fields['remember_token']->hidden)->toBeTrue()
        ->and($fields['email']->hidden)->toBeFalse();
});

it('treats attributes outside $visible as hidden', function (): void {
    $fields = eloquentDraft(Country::class)->fields;

    expect($fields['name']->hidden)->toBeFalse()
        ->and($fields['iso_code']->hidden)->toBeTrue();
});

it('respects fillable and guarded', function (): void {
    $user = eloquentDraft(User::class)->fields;
    $comment = eloquentDraft(Comment::class)->fields;

    expect($user['name']->massAssignable)->toBeTrue()
        ->and($user['is_admin']->massAssignable)->toBeFalse()
        ->and($user['remember_token']->massAssignable)->toBeFalse()
        ->and($comment['body']->massAssignable)->toBeTrue()
        ->and($comment['id']->massAssignable)->toBeFalse();
});

it('marks timestamps and soft delete columns as read only', function (): void {
    $post = eloquentDraft(Post::class)->fields;
    $country = eloquentDraft(Country::class)->fields;

    expect($post['created_at']->readOnly)->toBeTrue()
        ->and($post['updated_at']->readOnly)->toBeTrue()
        ->and($post['deleted_at']->readOnly)->toBeTrue()
        ->and($post['published_at']->readOnly)->toBeFalse()
        ->and($country['name']->readOnly)->toBeFalse()
        ->and($post['updated_at']->visibility)->toBe([\PhpTurbo\Admin\Core\Schema\Visibility::Detail])
        ->and($post['deleted_at']->visibility)->toBe([\PhpTurbo\Admin\Core\Schema\Visibility::Detail])
        ->and($post['created_at']->visibility)->toBeNull();
});

it('discovers typed relations', function (): void {
    $relations = array_map(
        static fn ($r): array => [$r->type, $r->target, $r->foreignKey],
        eloquentDraft(Post::class)->relations,
    );

    expect($relations)->toBe([
        'author' => [RelationType::BelongsTo, User::class, 'user_id'],
        'tags' => [RelationType::BelongsToMany, Tag::class, null],
        'labels' => [RelationType::MorphToMany, Tag::class, null],
        'comments' => [RelationType::MorphMany, Comment::class, 'commentable_id'],
        'latestComment' => [RelationType::MorphOne, Comment::class, 'commentable_id'],
    ]);
});

it('discovers has-one, has-many, through and morph-to relations', function (): void {
    $type = static fn (string $class): array => array_map(static fn ($r) => $r->type, eloquentDraft($class)->relations);

    expect($type(User::class))->toBe([
        'posts' => RelationType::HasMany,
        'latestPost' => RelationType::HasOne,
        'country' => RelationType::BelongsTo,
    ])
        ->and($type(Country::class))->toBe([
            'posts' => RelationType::HasManyThrough,
            'firstPost' => RelationType::HasOneThrough,
        ])
        ->and($type(Comment::class))->toBe(['commentable' => RelationType::MorphTo])
        ->and(eloquentDraft(Comment::class)->relations['commentable']->target)->toBeNull()
        ->and(eloquentDraft(Comment::class)->relations['commentable']->foreignKey)->toBe('commentable_id');
});

it('ignores untyped, broken, static, parameterized and framework trait methods', function (): void {
    $post = eloquentDraft(Post::class)->relations;
    $user = eloquentDraft(User::class)->relations;

    expect($post)->not->toHaveKeys(['editor', 'broken', 'staticRelation', 'scopePublished', 'summary', 'maybeRelation', 'custom'])
        // notifications() vem do trait Notifiable do framework.
        ->and($user)->not->toHaveKey('notifications');
});

it('does not query the database while inspecting relations', function (): void {
    $queries = 0;
    \Illuminate\Support\Facades\DB::listen(function () use (&$queries): void {
        $queries++;
    });

    (new EloquentModelProvider())->provide(Post::class, new EntityDraft(Post::class));

    expect($queries)->toBe(0);
});
