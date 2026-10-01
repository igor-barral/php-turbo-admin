<?php

declare(strict_types=1);

use PhpTurbo\Admin\Laravel\Discovery\DirectoryModelDiscovery;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models;

const FIXTURE_NAMESPACE = 'PhpTurbo\\Admin\\Laravel\\Tests\\Fixtures\\Models\\';

it('finds concrete models using HasAdmin, including subdirectories', function (): void {
    $discovery = new DirectoryModelDiscovery([FIXTURE_NAMESPACE => __DIR__ . '/../../Fixtures/Models/']);

    expect($discovery->discover())->toBe([
        Models\Comment::class,
        Models\Country::class,
        Models\Nested\Invoice::class,
        Models\Post::class,
        Models\User::class,
    ]);
});

it('accepts namespaces without trailing separator', function (): void {
    $discovery = new DirectoryModelDiscovery([rtrim(FIXTURE_NAMESPACE, '\\') => __DIR__ . '/../../Fixtures/Models/Nested']);

    expect($discovery->discover())->toBe([]);
});

it('adds explicit classes and filters invalid ones', function (): void {
    $discovery = new DirectoryModelDiscovery([], [
        Models\User::class,
        Models\Tag::class,
        Models\AbstractModel::class,
        Models\NotAModel::class,
        'App\\Models\\DoesNotExist',
    ]);

    expect($discovery->discover())->toBe([Models\User::class]);
});

it('deduplicates classes found in both sources', function (): void {
    $discovery = new DirectoryModelDiscovery([FIXTURE_NAMESPACE => __DIR__ . '/../../Fixtures/Models'], [Models\User::class]);

    expect($discovery->discover())->toHaveCount(5);
});

it('ignores missing directories', function (): void {
    expect((new DirectoryModelDiscovery(['App\\Models\\' => '/nope']))->discover())->toBe([]);
});
