<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Laravel\Metadata\EloquentDatabaseProvider;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\Nested\Invoice as NestedInvoice;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\NotAModel;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\Post;

function introspect(string $class): EntityDraft
{
    $draft = new EntityDraft($class);
    (new EloquentDatabaseProvider())->provide($class, $draft);

    return $draft;
}

it('supports only eloquent models', function (): void {
    $provider = new EloquentDatabaseProvider();

    expect($provider->supports(Post::class))->toBeTrue()
        ->and($provider->supports(NotAModel::class))->toBeFalse();
});

it('reads every column of the table in order', function (): void {
    expect(array_keys(introspect(Post::class)->fields))->toBe([
        'id', 'user_id', 'title', 'slug', 'body', 'status', 'metadata', 'price', 'external_id',
        'publish_on', 'publish_at_time', 'published_at', 'api_token', 'deleted_at', 'created_at', 'updated_at',
    ]);
});

it('maps column types portable across databases', function (): void {
    $fields = introspect(Post::class)->fields;

    expect($fields['id']->type)->toBe(FieldType::Integer)
        ->and($fields['user_id']->type)->toBe(FieldType::Integer)
        ->and($fields['title']->type)->toBe(FieldType::String)
        ->and($fields['body']->type)->toBe(FieldType::Text)
        ->and($fields['price']->type)->toBe(FieldType::Decimal)
        ->and($fields['publish_on']->type)->toBe(FieldType::Date)
        ->and($fields['publish_at_time']->type)->toBe(FieldType::Time)
        ->and($fields['published_at']->type)->toBe(FieldType::DateTime);
});

it('maps database specific types', function (): void {
    $fields = introspect(Post::class)->fields;

    // SQLite guarda json como text; uuid vira varchar no SQLite e char(36) no MySQL.
    [$json, $uuid] = match (driver()) {
        'sqlite' => [FieldType::Text, FieldType::String],
        'mysql' => [FieldType::Json, FieldType::String],
        'pgsql' => [FieldType::Json, FieldType::Uuid],
    };

    expect($fields['metadata']->type)->toBe($json)
        ->and($fields['external_id']->type)->toBe($uuid);
});

it('detects booleans and enums', function (): void {
    $fields = introspect(NestedInvoice::class)->fields;

    expect($fields['paid']->type)->toBe(FieldType::Boolean);

    if (driver() === 'mysql') {
        expect($fields['status']->type)->toBe(FieldType::Enum)
            ->and($fields['status']->options)->toBe([
                ['value' => 'open', 'label' => 'open'],
                ['value' => 'paid', 'label' => 'paid'],
            ]);
    } else {
        // SQLite e PostgreSQL implementam enum como varchar + CHECK: o cast do model define o tipo.
        expect($fields['status']->type)->toBe(FieldType::String);
    }
});

it('reads nullability', function (): void {
    $fields = introspect(Post::class)->fields;

    expect($fields['title']->nullable)->toBeFalse()
        ->and($fields['published_at']->nullable)->toBeTrue();
});

it('flags the primary key and leading index columns only', function (): void {
    $fields = introspect(Post::class)->fields;

    expect($fields['id']->primary)->toBeTrue()
        ->and($fields['id']->indexed)->toBeTrue()
        ->and($fields['title']->indexed)->toBeTrue()
        ->and($fields['slug']->indexed)->toBeTrue()
        // Só o MySQL (InnoDB) cria índice automaticamente para colunas de FK.
        ->and($fields['user_id']->indexed)->toBe(driver() === 'mysql')
        ->and($fields['status']->indexed)->toBeTrue()
        ->and($fields['published_at']->indexed)->toBeFalse()
        ->and($fields['body']->indexed)->toBeFalse()
        ->and($fields['title']->primary)->toBeFalse();
});
