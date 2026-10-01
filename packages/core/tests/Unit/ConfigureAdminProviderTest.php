<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Builder\AdminSchema;
use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Metadata\ConfigureAdminProvider;
use PhpTurbo\Admin\Core\Metadata\Priority;
use PhpTurbo\Admin\Core\Tests\Fixtures\InstanceConfigured;
use PhpTurbo\Admin\Core\Tests\Fixtures\Post;
use PhpTurbo\Admin\Core\Tests\Fixtures\User;

it('runs at user priority', function (): void {
    expect((new ConfigureAdminProvider())->priority())->toBe(Priority::USER);
});

it('supports only classes with a static configureAdmin', function (string $class, bool $supported): void {
    expect((new ConfigureAdminProvider())->supports($class))->toBe($supported);
})->with([
    [Post::class, true],
    [User::class, false],
    [InstanceConfigured::class, false],
]);

it('passes an AdminSchema bound to the draft', function (): void {
    Post::$configure = static fn (AdminSchema $schema) => $schema->label('Configurado');
    $draft = new EntityDraft(Post::class);

    (new ConfigureAdminProvider())->provide(Post::class, $draft);

    expect($draft->label)->toBe('Configurado');
});
