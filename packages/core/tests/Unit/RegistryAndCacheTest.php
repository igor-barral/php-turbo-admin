<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Builder\AdminSchema;
use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Cache\PhpFileSchemaCache;
use PhpTurbo\Admin\Core\Exception\DuplicateEntityKeyException;
use PhpTurbo\Admin\Core\Exception\EntityNotFoundException;
use PhpTurbo\Admin\Core\Exception\InvalidSchemaException;
use PhpTurbo\Admin\Core\Metadata\ConfigureAdminProvider;
use PhpTurbo\Admin\Core\Metadata\SchemaResolver;
use PhpTurbo\Admin\Core\Registry\SchemaCompiler;
use PhpTurbo\Admin\Core\Registry\SchemaRegistry;
use PhpTurbo\Admin\Core\Schema\EntitySchema;
use PhpTurbo\Admin\Core\Tests\Fixtures\Post;
use PhpTurbo\Admin\Core\Tests\Fixtures\User;

function compileFixtures(): SchemaRegistry
{
    $resolver = new SchemaResolver([
        postsTable(),
        provider(static fn (EntityDraft $d) => $d->field('id')->primary = true, supports: static fn (string $c): bool => $c === User::class),
        new ConfigureAdminProvider(),
    ]);

    return (new SchemaCompiler($resolver))->compile([Post::class, User::class]);
}

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir() . '/php-turbo-admin-' . bin2hex(random_bytes(4));
    $this->path = $this->directory . '/nested/schemas.php';
});

afterEach(function (): void {
    if (is_dir($this->directory)) {
        array_map('unlink', glob($this->directory . '/nested/*') ?: []);
        @rmdir($this->directory . '/nested');
        @rmdir($this->directory);
    }
});

it('compiles classes into a registry', function (): void {
    $registry = compileFixtures();

    expect(array_keys($registry->all()))->toBe(['post', 'user'])
        ->and($registry->has('post'))->toBeTrue()
        ->and($registry->has('nope'))->toBeFalse()
        ->and($registry->get('user')->class)->toBe(User::class)
        ->and($registry->hasClass(Post::class))->toBeTrue()
        ->and($registry->hasClass('Nope'))->toBeFalse()
        ->and($registry->forClass(Post::class)->key)->toBe('post');
});

it('rejects duplicated entity keys', function (): void {
    Post::$configure = static fn (AdminSchema $s) => $s->key('user');

    compileFixtures();
})->throws(DuplicateEntityKeyException::class, 'A chave "user" é usada por');

it('throws for unknown keys and classes', function (callable $lookup, string $message): void {
    expect(fn () => $lookup(new SchemaRegistry()))->toThrow(EntityNotFoundException::class, $message);
})->with([
    [static fn (SchemaRegistry $r) => $r->get('posts'), 'Nenhuma entidade registrada com a chave "posts".'],
    [static fn (SchemaRegistry $r) => $r->forClass('App\\Post'), 'A classe App\\Post não está registrada no admin.'],
]);

it('serializes the registry with a format version', function (): void {
    $registry = compileFixtures();
    $array = $registry->toArray();

    expect($array['version'])->toBe(SchemaRegistry::FORMAT_VERSION)
        ->and($array['entities'])->toHaveCount(2)
        ->and(SchemaRegistry::fromArray($array))->toEqual($registry);
});

it('writes and reads the cache file atomically', function (): void {
    $cache = new PhpFileSchemaCache($this->path);
    $registry = compileFixtures();

    expect($cache->exists())->toBeFalse()
        ->and($cache->read())->toBeNull()
        ->and($cache->path())->toBe($this->path);

    $cache->write($registry);

    expect($cache->exists())->toBeTrue()
        ->and(glob(dirname($this->path) . '/*.tmp'))->toBe([])
        ->and(file_get_contents($this->path))->toStartWith("<?php\n\n// Gerado pelo php-turbo-admin. Não edite.")
        ->and($cache->read())->toEqual($registry)
        ->and($cache->read()?->get('post'))->toBeInstanceOf(EntitySchema::class);
});

it('overwrites an existing cache', function (): void {
    $cache = new PhpFileSchemaCache($this->path);
    $cache->write(new SchemaRegistry());
    $cache->write(compileFixtures());

    expect($cache->read()?->all())->toHaveCount(2);
});

it('clears the cache', function (): void {
    $cache = new PhpFileSchemaCache($this->path);
    $cache->write(compileFixtures());

    $cache->clear();
    $cache->clear();

    expect($cache->exists())->toBeFalse();
});

it('rejects caches written in another format version', function (mixed $contents): void {
    mkdir(dirname($this->path), 0775, true);
    file_put_contents($this->path, '<?php return ' . var_export($contents, true) . ';');

    (new PhpFileSchemaCache($this->path))->read();
})->with([
    'old version' => [['version' => 0, 'entities' => []]],
    'missing entities' => [['version' => SchemaRegistry::FORMAT_VERSION]],
    'not an array' => ['garbage'],
])->throws(InvalidSchemaException::class, 'versão de formato incompatível');

it('fails when the directory cannot be created', function (): void {
    $blocker = $this->directory;
    touch($blocker);

    try {
        (new PhpFileSchemaCache($blocker . '/nested/schemas.php'))->write(new SchemaRegistry());
    } finally {
        unlink($blocker);
    }
})->throws(RuntimeException::class, 'Não foi possível criar o diretório');

it('fails and cleans up when the file cannot be written', function (): void {
    $directory = dirname($this->path);
    mkdir($directory, 0775, true);
    chmod($directory, 0555);

    try {
        (new PhpFileSchemaCache($this->path))->write(new SchemaRegistry());
    } finally {
        chmod($directory, 0775);
        expect(glob($directory . '/*.tmp'))->toBe([]);
    }
})->throws(RuntimeException::class, 'Não foi possível gravar o cache')
    ->skip(fn (): bool => function_exists('posix_geteuid') && posix_geteuid() === 0, 'root ignora permissões de diretório');
