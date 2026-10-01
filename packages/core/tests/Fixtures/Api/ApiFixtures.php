<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Tests\Fixtures\Api;

use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Data\RecordRef;
use PhpTurbo\Admin\Core\Metadata\MetadataProvider;
use PhpTurbo\Admin\Core\Metadata\Priority;
use PhpTurbo\Admin\Core\Metadata\SchemaResolver;
use PhpTurbo\Admin\Core\Registry\SchemaCompiler;
use PhpTurbo\Admin\Core\Registry\SchemaRegistry;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\RelationType;

/** Registro com articles → writers (belongsTo) e uma entidade "secrets" para testar visibilidade. */
final class ApiFixtures
{
    public static function registry(): SchemaRegistry
    {
        $provider = new class () implements MetadataProvider {
            public function priority(): int
            {
                return Priority::DATABASE;
            }

            public function supports(string $class): bool
            {
                return true;
            }

            public function provide(string $class, EntityDraft $draft): void
            {
                $field = static function (string $name, FieldType $type, bool $nullable = false, bool $writable = true) use ($draft) {
                    $f = $draft->field($name);
                    $f->type = $type;
                    $f->nullable = $nullable;
                    $f->massAssignable = $writable;

                    return $f;
                };

                $draft->field('id')->type = FieldType::Integer;
                $draft->field('id')->primary = true;

                match ($class) {
                    Article::class => (static function () use ($draft, $field): void {
                        $draft->key = 'articles';
                        $field('title', FieldType::String)->searchable = true;
                        $draft->fields['title']->indexed = true;
                        $field('body', FieldType::Text, nullable: true);
                        $status = $field('status', FieldType::Enum);
                        $status->options = [['value' => 'draft', 'label' => 'Rascunho'], ['value' => 'published', 'label' => 'Publicado']];
                        $status->hasDefault = true;
                        $field('featured', FieldType::Boolean)->hasDefault = true;
                        $field('rating', FieldType::Integer, nullable: true)->sortable = true;
                        $field('writer_id', FieldType::Integer, nullable: true);
                        $field('internal_note', FieldType::String, nullable: true)->hidden = true;
                        $field('created_at', FieldType::DateTime, nullable: true)->readOnly = true;
                        $writer = $draft->relation('writer');
                        $writer->type = RelationType::BelongsTo;
                        $writer->target = Writer::class;
                        $writer->foreignKey = 'writer_id';
                        $secret = $draft->relation('secret');
                        $secret->type = RelationType::BelongsTo;
                        $secret->target = Secret::class;
                        $owner = $draft->relation('owner');
                        $owner->type = RelationType::MorphTo;
                    })(),
                    Writer::class => (static function () use ($draft, $field): void {
                        $draft->key = 'writers';
                        $field('name', FieldType::String);
                        $field('password', FieldType::Password)->writeOnly = true;
                    })(),
                    default => (static function () use ($draft, $field): void {
                        $draft->key = 'secrets';
                        $field('code', FieldType::String);
                    })(),
                };
            }
        };

        return (new SchemaCompiler(new SchemaResolver([$provider])))->compile([Article::class, Writer::class, Secret::class]);
    }

    public static function seed(InMemoryDataSource $data): void
    {
        $data->seed('writers', ['id' => 1, 'name' => 'Ana', 'password' => 'hash']);
        $data->seed('writers', ['id' => 2, 'name' => 'Bruno', 'password' => 'hash']);

        foreach (range(1, 5) as $i) {
            $data->seed('articles', [
                'id' => $i,
                'title' => 'Artigo ' . $i,
                'body' => 'Texto ' . $i,
                'status' => $i % 2 === 0 ? 'published' : 'draft',
                'featured' => $i === 1,
                'rating' => $i === 3 ? null : $i * 10,
                'writer_id' => 1,
                'internal_note' => 'não exponha',
                'created_at' => new \DateTimeImmutable('2026-01-0' . $i . ' 10:00:00+00:00'),
            ]);
            $data->relations['articles'][$i] = ['writer' => new RecordRef(1, 'Ana'), 'owner' => new RecordRef(9, '9')];
        }
    }
}
