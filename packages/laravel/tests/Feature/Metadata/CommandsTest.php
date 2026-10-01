<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Cache\PhpFileSchemaCache;
use PhpTurbo\Admin\Core\Registry\SchemaRegistry;
use PhpTurbo\Admin\Core\Schema\EntitySchema;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\Post;

it('compiles the schema cache', function (): void {
    $this->artisan('turbo-admin:cache')
        ->expectsOutputToContain('5 entidade(s) compilada(s) em ' . $this->cachePath)
        ->assertSuccessful();

    expect(is_file($this->cachePath))->toBeTrue()
        ->and((new PhpFileSchemaCache($this->cachePath))->read()?->get('posts')->class)->toBe(Post::class);
});

it('serves the registry from cache without touching the database', function (): void {
    $this->artisan('turbo-admin:cache')->assertSuccessful();
    $this->app->forgetInstance(SchemaRegistry::class);

    $queries = 0;
    \Illuminate\Support\Facades\DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $registry = app(SchemaRegistry::class);

    expect($queries)->toBe(0)
        ->and($registry->get('posts'))->toBeInstanceOf(EntitySchema::class);
});

it('clears the schema cache', function (): void {
    $this->artisan('turbo-admin:cache')->assertSuccessful();

    $this->artisan('turbo-admin:clear')->expectsOutputToContain('Cache de schemas removido.')->assertSuccessful();

    expect(is_file($this->cachePath))->toBeFalse();
});

it('lists entities', function (): void {
    $this->artisan('turbo-admin:schema')
        ->expectsTable(['Chave', 'Classe', 'Campos', 'Relações'], [
            ['comments', 'PhpTurbo\\Admin\\Laravel\\Tests\\Fixtures\\Models\\Comment', 6, 1],
            ['countries', 'PhpTurbo\\Admin\\Laravel\\Tests\\Fixtures\\Models\\Country', 2, 2],
            ['invoices', 'PhpTurbo\\Admin\\Laravel\\Tests\\Fixtures\\Models\\Nested\\Invoice', 7, 0],
            ['posts', 'PhpTurbo\\Admin\\Laravel\\Tests\\Fixtures\\Models\\Post', 15, 5],
            ['users', 'PhpTurbo\\Admin\\Laravel\\Tests\\Fixtures\\Models\\User', 9, 3],
        ])
        ->assertSuccessful();
});

it('prints a resolved schema as json', function (): void {
    expect(\Illuminate\Support\Facades\Artisan::call('turbo-admin:schema', ['entity' => 'countries']))->toBe(0);

    $json = json_decode(\Illuminate\Support\Facades\Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($json)->toBe(entity('countries')->toArray());
});

it('fails for unknown entities', function (): void {
    $this->artisan('turbo-admin:schema', ['entity' => 'nope'])
        ->expectsOutputToContain('Nenhuma entidade registrada com a chave "nope".')
        ->assertFailed();
});
