<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\Visibility;

it('exposes write-only password and hides tokens on users', function (): void {
    $users = entity('users');

    expect($users->field('password')->readable)->toBeFalse()
        ->and($users->field('password')->writable)->toBeTrue()
        ->and($users->field('password')->visibility)->toBe(Visibility::forms())
        ->and($users->hasField('remember_token'))->toBeFalse()
        ->and($users->field('is_admin')->writable)->toBeFalse()
        ->and($users->titleField)->toBe('name');
});

it('never exposes sensitive columns without opt-in', function (): void {
    expect(entity('posts')->hasField('api_token'))->toBeFalse();
});

it('resolves posts end to end', function (): void {
    $posts = entity('posts');

    expect($posts->label)->toBe('Post')
        ->and($posts->labelPlural)->toBe('Posts')
        ->and($posts->titleField)->toBe('title')
        ->and($posts->field('id')->writable)->toBeFalse()
        ->and($posts->field('title')->sortable)->toBeTrue()
        ->and($posts->field('body')->sortable)->toBeFalse()
        ->and($posts->field('body')->isVisibleOn(Visibility::Index))->toBeFalse()
        ->and($posts->field('status')->type)->toBe(FieldType::Enum)
        ->and($posts->field('status')->filterable)->toBeTrue()
        ->and($posts->field('user_id')->visibility)->toBe([])
        ->and($posts->field('created_at')->writable)->toBeFalse()
        ->and($posts->relation('author')->eager)->toBeTrue()
        ->and($posts->relation('author')->writable)->toBeTrue()
        ->and($posts->relation('comments')->writable)->toBeFalse();
});

it('registers only discovered admin models', function (): void {
    expect(array_keys(app(\PhpTurbo\Admin\Core\Registry\SchemaRegistry::class)->all()))
        ->toBe(['comments', 'countries', 'invoices', 'posts', 'users']);
});
