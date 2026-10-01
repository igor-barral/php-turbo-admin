<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Exception\InvalidSchemaException;
use PhpTurbo\Admin\Core\Metadata\Conventions;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\RelationType;
use PhpTurbo\Admin\Core\Schema\Sort;
use PhpTurbo\Admin\Core\Schema\SortDirection;
use PhpTurbo\Admin\Core\Schema\Visibility;

function conventionDraft(string ...$fields): EntityDraft
{
    $draft = new EntityDraft('App\\Models\\BlogPost');
    foreach ($fields as $field) {
        $draft->field($field);
    }

    return $draft;
}

it('derives entity key and labels from the class name', function (): void {
    $draft = conventionDraft('id');

    (new Conventions())->apply($draft);

    expect($draft->key)->toBe('blog_post')
        ->and($draft->label)->toBe('Blog post')
        ->and($draft->labelPlural)->toBe('Blog post');
});

it('keeps values decided by earlier layers', function (): void {
    $draft = conventionDraft('id', 'title');
    $draft->key = 'posts';
    $draft->label = 'Post';
    $draft->labelPlural = 'Posts';
    $draft->titleField = 'id';
    $draft->sort = new Sort('title');
    $draft->field('title')->type = FieldType::Email;
    $draft->field('title')->label = 'E-mail';
    $draft->field('title')->sortable = false;
    $draft->field('title')->visibility = [Visibility::Detail];

    (new Conventions())->apply($draft);

    expect($draft->key)->toBe('posts')
        ->and($draft->labelPlural)->toBe('Posts')
        ->and($draft->titleField)->toBe('id')
        ->and($draft->sort)->toEqual(new Sort('title'))
        ->and($draft->field('title')->type)->toBe(FieldType::Email)
        ->and($draft->field('title')->label)->toBe('E-mail')
        ->and($draft->field('title')->sortable)->toBeFalse()
        ->and($draft->field('title')->visibility)->toBe([Visibility::Detail]);
});

it('prefers the field flagged as primary key', function (): void {
    $draft = conventionDraft('id', 'uuid');
    $draft->field('uuid')->primary = true;

    (new Conventions())->apply($draft);

    expect($draft->primaryKey)->toBe('uuid')
        ->and($draft->field('id')->primary)->toBeFalse();
});

it('falls back to "id" and marks it as primary', function (): void {
    $draft = conventionDraft('id', 'title');

    (new Conventions())->apply($draft);

    expect($draft->primaryKey)->toBe('id')
        ->and($draft->field('id')->primary)->toBeTrue();
});

it('fails when no primary key can be found', function (): void {
    (new Conventions())->apply(conventionDraft('title'));
})->throws(InvalidSchemaException::class, 'Não foi possível determinar a chave primária de App\\Models\\BlogPost.');

it('applies field defaults', function (): void {
    $draft = conventionDraft('id', 'title', 'body', 'status', 'published', 'created_at', 'slug');
    $draft->field('body')->type = FieldType::Text;
    $draft->field('status')->type = FieldType::Enum;
    $draft->field('published')->type = FieldType::Boolean;
    $draft->field('created_at')->readOnly = true;
    $draft->field('slug')->indexed = true;

    (new Conventions())->apply($draft);
    $f = $draft->fields;

    expect($f['title']->type)->toBe(FieldType::String)
        ->and($f['title']->label)->toBe('Title')
        ->and($f['title']->searchable)->toBeFalse()
        ->and($f['title']->sortable)->toBeFalse()
        ->and($f['title']->filterable)->toBeFalse()
        ->and($f['title']->visibility)->toBe(Visibility::all())
        ->and($f['id']->sortable)->toBeTrue()
        ->and($f['id']->visibility)->toBe(Visibility::read())
        ->and($f['slug']->sortable)->toBeTrue()
        ->and($f['body']->visibility)->toBe([Visibility::Detail, Visibility::Create, Visibility::Update])
        ->and($f['status']->filterable)->toBeTrue()
        ->and($f['published']->filterable)->toBeTrue()
        ->and($f['created_at']->visibility)->toBe(Visibility::read());
});

it('hides foreign key columns represented by owning relations', function (RelationType $type): void {
    $draft = conventionDraft('id', 'author_id');
    $relation = $draft->relation('author');
    $relation->type = $type;
    $relation->foreignKey = 'author_id';

    (new Conventions())->apply($draft);

    expect($draft->field('author_id')->visibility)->toBe([])
        ->and($draft->field('author_id')->filterable)->toBeTrue();
})->with([RelationType::BelongsTo, RelationType::MorphTo]);

it('keeps foreign keys visible when the relation is excluded or not owning', function (): void {
    $draft = conventionDraft('id', 'author_id', 'post_id');
    $excluded = $draft->relation('author');
    $excluded->type = RelationType::BelongsTo;
    $excluded->foreignKey = 'author_id';
    $excluded->excluded = true;
    $notOwning = $draft->relation('comments');
    $notOwning->type = RelationType::HasMany;
    $notOwning->foreignKey = 'post_id';
    $missing = $draft->relation('editor');
    $missing->type = RelationType::BelongsTo;
    $missing->foreignKey = 'editor_id';

    (new Conventions())->apply($draft);

    expect($draft->field('author_id')->visibility)->toBe(Visibility::all())
        ->and($draft->field('post_id')->visibility)->toBe(Visibility::all())
        ->and($draft->hasField('editor_id'))->toBeFalse();
});

it('applies relation defaults', function (): void {
    $draft = conventionDraft('id');
    $draft->relation('author')->type = RelationType::BelongsTo;
    $draft->relation('comments')->type = RelationType::HasMany;
    $draft->relation('tags')->eager = true;
    $draft->relation('tags')->label = 'Etiquetas';

    (new Conventions())->apply($draft);

    expect($draft->relations['author']->eager)->toBeTrue()
        ->and($draft->relations['author']->label)->toBe('Author')
        ->and($draft->relations['comments']->eager)->toBeFalse()
        ->and($draft->relations['tags']->eager)->toBeTrue()
        ->and($draft->relations['tags']->label)->toBe('Etiquetas');
});

it('guesses the title field by preference order', function (array $fields, string $expected): void {
    $draft = conventionDraft('id', ...$fields);

    (new Conventions())->apply($draft);

    expect($draft->titleField)->toBe($expected);
})->with([
    'name wins' => [['email', 'title', 'name'], 'name'],
    'title next' => [['email', 'title'], 'title'],
    'email last' => [['email'], 'email'],
    'primary key fallback' => [['body'], 'id'],
]);

it('skips excluded title candidates', function (): void {
    $draft = conventionDraft('id', 'name', 'email');
    $draft->field('name')->excluded = true;

    (new Conventions())->apply($draft);

    expect($draft->titleField)->toBe('email');
});

it('sorts by primary key descending by default', function (): void {
    $draft = conventionDraft('id');

    (new Conventions())->apply($draft);

    expect($draft->sort)->toEqual(new Sort('id', SortDirection::Desc));
});
