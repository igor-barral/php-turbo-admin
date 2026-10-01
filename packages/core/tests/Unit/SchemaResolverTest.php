<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Builder\AdminSchema;
use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Metadata\Priority;
use PhpTurbo\Admin\Core\Metadata\SchemaResolver;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\Sort;
use PhpTurbo\Admin\Core\Schema\SortDirection;
use PhpTurbo\Admin\Core\Schema\Visibility;
use PhpTurbo\Admin\Core\Tests\Fixtures\Post;
use PhpTurbo\Admin\Core\Tests\Fixtures\User;

it('resolves an entity purely from inference', function (): void {
    $schema = resolveWith(Post::class, [postsTable()]);

    expect($schema->key)->toBe('post')
        ->and($schema->class)->toBe(Post::class)
        ->and($schema->primaryKey)->toBe('id')
        ->and($schema->titleField)->toBe('title')
        ->and($schema->sort)->toEqual(new Sort('id', SortDirection::Desc))
        ->and(array_keys($schema->fields))->toBe(['id', 'title', 'body', 'status', 'author_id', 'created_at'])
        ->and($schema->field('id')->writable)->toBeFalse()
        ->and($schema->field('title')->writable)->toBeTrue()
        ->and($schema->field('title')->sortable)->toBeTrue()
        ->and($schema->field('status')->filterable)->toBeTrue()
        ->and($schema->field('body')->isVisibleOn(Visibility::Index))->toBeFalse()
        ->and($schema->field('author_id')->visibility)->toBe([])
        ->and($schema->field('created_at')->writable)->toBeFalse()
        ->and($schema->relation('author')->target)->toBe(User::class)
        ->and($schema->relation('author')->eager)->toBeTrue()
        ->and($schema->relation('author')->writable)->toBeTrue();
});

it('runs providers in priority order regardless of registration order', function (): void {
    $calls = [];
    $resolver = new SchemaResolver([
        provider(static function (EntityDraft $d) use (&$calls): void {
            $calls[] = 'orm';
            $d->label = 'from orm';
        }, Priority::ORM),
        provider(static function (EntityDraft $d) use (&$calls): void {
            $calls[] = 'database';
            $d->field('id');
            $d->label = 'from database';
        }, Priority::DATABASE),
    ]);

    $schema = $resolver->resolve(User::class);

    expect($calls)->toBe(['database', 'orm'])
        ->and($schema->label)->toBe('from orm');
});

it('keeps registration order for providers with the same priority', function (): void {
    $calls = [];
    $resolver = new SchemaResolver([
        provider(static function (EntityDraft $d) use (&$calls): void {
            $calls[] = 'first';
            $d->field('id');
        }),
        provider(static function () use (&$calls): void {
            $calls[] = 'second';
        }),
    ]);

    $resolver->resolve(User::class);

    expect($calls)->toBe(['first', 'second']);
});

it('skips providers that do not support the class', function (): void {
    $resolver = new SchemaResolver([
        postsTable(),
        provider(static fn (EntityDraft $d) => $d->label = 'only users', Priority::ORM, static fn (string $c): bool => $c === User::class),
    ]);

    expect($resolver->resolve(Post::class)->label)->toBe('Post')
        ->and($resolver->resolve(User::class)->label)->toBe('only users');
});

it('lets configureAdmin override inferred metadata', function (): void {
    Post::$configure = static function (AdminSchema $schema): void {
        $schema->key('posts')
            ->label('Publicação', 'Publicações')
            ->field('title')->label('Título')->searchable()
            ->field('body')->widget('richtext')
            ->relation('author')->display('name');
    };

    $schema = resolveWith(Post::class, [postsTable()]);

    expect($schema->key)->toBe('posts')
        ->and($schema->label)->toBe('Publicação')
        ->and($schema->labelPlural)->toBe('Publicações')
        ->and($schema->field('title')->label)->toBe('Título')
        ->and($schema->field('title')->searchable)->toBeTrue()
        ->and($schema->field('body')->widget)->toBe('richtext')
        ->and($schema->relation('author')->displayField)->toBe('name');
});

it('omits fields that are neither readable nor writable', function (): void {
    $schema = resolveWith(User::class, [provider(static function (EntityDraft $d): void {
        $d->field('id')->primary = true;
        $d->field('password')->massAssignable = true;
        $d->field('remember_token')->hidden = true;
    })]);

    expect(array_keys($schema->fields))->toBe(['id']);
});

it('exposes write-only fields only on forms', function (): void {
    Post::$configure = static fn (AdminSchema $s) => $s->field('password')->writeOnly();

    $schema = resolveWith(Post::class, [provider(static function (EntityDraft $d): void {
        $d->field('id')->primary = true;
        $d->field('password')->massAssignable = true;
        $d->field('password')->hidden = true;
    })]);
    $password = $schema->field('password');

    expect($password->readable)->toBeFalse()
        ->and($password->writable)->toBeTrue()
        ->and($password->visibility)->toBe([Visibility::Create, Visibility::Update]);
});

it('never allows search, sort or filter on unreadable fields', function (): void {
    Post::$configure = static fn (AdminSchema $s) => $s->field('password')->writeOnly()->searchable()->sortable()->filterable();

    $schema = resolveWith(Post::class, [provider(static function (EntityDraft $d): void {
        $d->field('id')->primary = true;
        $d->field('password')->massAssignable = true;
    })]);

    expect($schema->field('password')->searchable)->toBeFalse()
        ->and($schema->field('password')->sortable)->toBeFalse()
        ->and($schema->field('password')->filterable)->toBeFalse();
});

it('removes form visibility from read only fields', function (): void {
    Post::$configure = static fn (AdminSchema $s) => $s->field('title')->readOnly();

    $schema = resolveWith(Post::class, [postsTable()]);

    expect($schema->field('title')->visibility)->toBe([Visibility::Index, Visibility::Detail]);
});

it('falls back to the primary key when the title field is not readable', function (): void {
    Post::$configure = static fn (AdminSchema $s) => $s->titleField('title')->exclude('title');

    expect(resolveWith(Post::class, [postsTable()])->titleField)->toBe('id');
});

it('falls back to primary key sort when the sort field is not readable', function (): void {
    Post::$configure = static fn (AdminSchema $s) => $s->sort('title', 'asc')->exclude('title');

    expect(resolveWith(Post::class, [postsTable()])->sort)->toEqual(new Sort('id', SortDirection::Desc));
});

it('drops title and sort when even the primary key is excluded', function (): void {
    Post::$configure = static fn (AdminSchema $s) => $s->exclude('id', 'title');

    $schema = resolveWith(Post::class, [postsTable()]);

    expect($schema->titleField)->toBeNull()
        ->and($schema->sort)->toBeNull()
        ->and($schema->hasField('id'))->toBeFalse();
});

it('drops excluded relations and relations without a type', function (): void {
    Post::$configure = static fn (AdminSchema $s) => $s->exclude('author');

    $schema = resolveWith(Post::class, [postsTable(), provider(static fn (EntityDraft $d) => $d->relation('untyped'), Priority::ORM)]);

    expect($schema->relations)->toBe([])
        ->and($schema->field('author_id')->visibility)->toBe(Visibility::all());
});

it('keeps actions declared by the user', function (): void {
    Post::$configure = static fn (AdminSchema $s) => $s->action('publish', bulk: true);

    $schema = resolveWith(Post::class, [postsTable()]);

    expect($schema->actions['publish']->label)->toBe('Publish')
        ->and($schema->actions['publish']->bulk)->toBeTrue();
});

it('defaults missing field types to string', function (): void {
    $schema = resolveWith(User::class, [provider(static fn (EntityDraft $d) => $d->field('id'))]);

    expect($schema->field('id')->type)->toBe(FieldType::String);
});

it('infers required writable fields without default', function (): void {
    Post::$configure = static fn (AdminSchema $s) => $s->field('body')->required();

    $schema = resolveWith(Post::class, [postsTable(), provider(static function (EntityDraft $d): void {
        $d->field('status')->hasDefault = true;
        $d->field('author_id')->nullable = true;
    }, Priority::ORM)]);

    expect($schema->field('title')->required)->toBeTrue()
        ->and($schema->field('status')->required)->toBeFalse()
        ->and($schema->field('author_id')->required)->toBeFalse()
        ->and($schema->field('body')->required)->toBeTrue()
        ->and($schema->field('id')->required)->toBeFalse()
        ->and($schema->field('created_at')->required)->toBeFalse();
});
