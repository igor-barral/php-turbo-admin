<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Metadata\ConfigureAdminProvider;
use PhpTurbo\Admin\Core\Metadata\MetadataProvider;
use PhpTurbo\Admin\Core\Metadata\Priority;
use PhpTurbo\Admin\Core\Metadata\SchemaResolver;
use PhpTurbo\Admin\Core\Metadata\SecurityPolicy;
use PhpTurbo\Admin\Core\Schema\EntitySchema;

/**
 * Provider de teste que aplica um callback ao draft (simula banco/ORM).
 *
 * @param callable(EntityDraft): void $callback
 */
function provider(callable $callback, int $priority = Priority::DATABASE, ?callable $supports = null): MetadataProvider
{
    return new class ($callback, $priority, $supports) implements MetadataProvider {
        /** @var callable */
        private $callback;

        /** @var callable|null */
        private $supports;

        public function __construct(callable $callback, private readonly int $priority, ?callable $supports)
        {
            $this->callback = $callback;
            $this->supports = $supports;
        }

        public function priority(): int
        {
            return $this->priority;
        }

        public function supports(string $class): bool
        {
            return $this->supports === null || ($this->supports)($class);
        }

        public function provide(string $class, EntityDraft $draft): void
        {
            ($this->callback)($draft);
        }
    };
}

/** Banco "fake" com as colunas típicas de uma tabela posts. */
function postsTable(): MetadataProvider
{
    return provider(static function (EntityDraft $draft): void {
        $id = $draft->field('id');
        $id->primary = true;
        $id->indexed = true;

        foreach (['title', 'body', 'status', 'author_id', 'created_at'] as $name) {
            $draft->field($name)->massAssignable = true;
        }

        $draft->field('title')->indexed = true;
        $draft->field('body')->type = \PhpTurbo\Admin\Core\Schema\FieldType::Text;
        $draft->field('status')->type = \PhpTurbo\Admin\Core\Schema\FieldType::Enum;
        $draft->field('created_at')->readOnly = true;

        $author = $draft->relation('author');
        $author->type = \PhpTurbo\Admin\Core\Schema\RelationType::BelongsTo;
        $author->target = \PhpTurbo\Admin\Core\Tests\Fixtures\User::class;
        $author->foreignKey = 'author_id';
    });
}

/** @param list<MetadataProvider> $providers */
function resolveWith(string $class, array $providers, ?SecurityPolicy $security = null): EntitySchema
{
    return (new SchemaResolver([...$providers, new ConfigureAdminProvider()], $security ?? new SecurityPolicy()))
        ->resolve($class);
}

pest()->beforeEach(function (): void {
    \PhpTurbo\Admin\Core\Tests\Fixtures\Post::$configure = null;
})->in('Unit');
