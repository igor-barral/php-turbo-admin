<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Builder\AdminSchema;
use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Builder\FieldConfigurator;
use PhpTurbo\Admin\Core\Builder\RelationConfigurator;
use PhpTurbo\Admin\Core\Exception\UnknownFieldException;
use PhpTurbo\Admin\Core\Exception\UnknownRelationException;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\RelationType;
use PhpTurbo\Admin\Core\Schema\Sort;
use PhpTurbo\Admin\Core\Schema\SortDirection;
use PhpTurbo\Admin\Core\Schema\Visibility;

beforeEach(function (): void {
    $this->draft = new EntityDraft('App\\Models\\Post');
    foreach (['id', 'title', 'body', 'password'] as $name) {
        $this->draft->field($name);
    }
    $this->draft->relation('author')->type = RelationType::BelongsTo;
    $this->schema = new AdminSchema($this->draft);
});

it('sets entity level options', function (): void {
    $this->schema->key('articles')->label('Artigo', 'Artigos')->titleField('title')->sort('title', 'DESC');

    expect($this->draft->key)->toBe('articles')
        ->and($this->draft->label)->toBe('Artigo')
        ->and($this->draft->labelPlural)->toBe('Artigos')
        ->and($this->draft->titleField)->toBe('title')
        ->and($this->draft->sort)->toEqual(new Sort('title', SortDirection::Desc));
});

it('uses the singular label as plural when none was given', function (): void {
    $this->schema->label('Notícia');

    expect($this->draft->labelPlural)->toBe('Notícia');
});

it('keeps a plural label defined earlier', function (): void {
    $this->draft->labelPlural = 'Posts';

    $this->schema->label('Post');

    expect($this->draft->labelPlural)->toBe('Posts');
});

it('accepts a sort direction enum', function (): void {
    $this->schema->sort('id', SortDirection::Asc);

    expect($this->draft->sort)->toEqual(new Sort('id'));
});

it('configures fields fluently', function (): void {
    $this->schema->field('title')
        ->label('Título')
        ->type(FieldType::Text)
        ->widget('textarea')
        ->searchable()
        ->sortable()
        ->filterable()
        ->rules(['required', 'max:255'])
        ->options(['a' => 'Opção A', 2 => 'Opção B'])
        ->readOnly();

    $field = $this->draft->field('title');

    expect($field->label)->toBe('Título')
        ->and($field->type)->toBe(FieldType::Text)
        ->and($field->widget)->toBe('textarea')
        ->and($field->searchable)->toBeTrue()
        ->and($field->sortable)->toBeTrue()
        ->and($field->filterable)->toBeTrue()
        ->and($field->rules)->toBe(['required', 'max:255'])
        ->and($field->options)->toBe([['value' => 'a', 'label' => 'Opção A'], ['value' => 2, 'label' => 'Opção B']])
        ->and($field->readOnly)->toBeTrue();
});

it('can turn flags off explicitly', function (): void {
    $this->schema->field('title')->searchable(false)->sortable(false)->filterable(false)->readOnly(false);
    $field = $this->draft->field('title');

    expect($field->searchable)->toBeFalse()
        ->and($field->sortable)->toBeFalse()
        ->and($field->filterable)->toBeFalse()
        ->and($field->readOnly)->toBeFalse();
});

it('controls visibility', function (): void {
    $this->schema
        ->field('title')->onlyOn(Visibility::Index, Visibility::Detail)
        ->field('body')->hideOnIndex()
        ->field('id')->hideOnForms()->hideOnDetail();

    expect($this->draft->field('title')->visibility)->toBe([Visibility::Index, Visibility::Detail])
        ->and($this->draft->field('body')->visibility)->toBe([Visibility::Detail, Visibility::Create, Visibility::Update])
        ->and($this->draft->field('id')->visibility)->toBe([Visibility::Index]);
});

it('flags sensitive opt-ins', function (): void {
    $this->schema->field('password')->writeOnly()->field('title')->allowSensitive();

    expect($this->draft->field('password')->writeOnly)->toBeTrue()
        ->and($this->draft->field('title')->sensitiveAllowed)->toBeTrue();
});

it('rejects unknown fields instead of creating them', function (): void {
    $this->schema->field('subtitle');
})->throws(UnknownFieldException::class, 'Campo "subtitle" não existe');

it('hints when a relation is used as a field', function (): void {
    $this->schema->field('author');
})->throws(UnknownFieldException::class, '"author" é uma relação: use relation("author").');

it('validates title and sort fields', function (string $method): void {
    $this->schema->{$method}('nope');
})->with(['titleField', 'sort'])->throws(UnknownFieldException::class);

it('configures discovered relations', function (): void {
    $this->schema->relation('author')->label('Autor')->display('name')->eager(false)->foreignKey('user_id');
    $relation = $this->draft->relations['author'];

    expect($relation->label)->toBe('Autor')
        ->and($relation->displayField)->toBe('name')
        ->and($relation->eager)->toBeFalse()
        ->and($relation->foreignKey)->toBe('user_id');
});

it('declares relations that could not be discovered', function (): void {
    $this->schema->relation('tags', RelationType::BelongsToMany, 'App\\Models\\Tag')->eager();

    expect($this->draft->relations['tags']->type)->toBe(RelationType::BelongsToMany)
        ->and($this->draft->relations['tags']->target)->toBe('App\\Models\\Tag')
        ->and($this->draft->relations['tags']->eager)->toBeTrue();
});

it('does not overwrite a discovered relation type when redeclared', function (): void {
    $this->schema->relation('author', RelationType::HasOne, 'X');

    expect($this->draft->relations['author']->type)->toBe(RelationType::BelongsTo);
});

it('rejects unknown relations without a type', function (): void {
    $this->schema->relation('tags');
})->throws(UnknownRelationException::class, 'Relação "tags" não existe');

it('excludes fields and relations', function (): void {
    $this->schema->exclude('body', 'author');

    expect($this->draft->field('body')->excluded)->toBeTrue()
        ->and($this->draft->relations['author']->excluded)->toBeTrue();
});

it('rejects excluding unknown names', function (): void {
    $this->schema->exclude('nope');
})->throws(UnknownFieldException::class);

it('adds actions with humanized default labels', function (): void {
    $this->schema->action('mark_as_spam', destructive: true)->action('publish', 'Publicar', bulk: true);

    expect($this->draft->actions['mark_as_spam']->label)->toBe('Mark as spam')
        ->and($this->draft->actions['mark_as_spam']->destructive)->toBeTrue()
        ->and($this->draft->actions['publish']->label)->toBe('Publicar')
        ->and($this->draft->actions['publish']->bulk)->toBeTrue();
});

it('chains back to the schema from configurators', function (): void {
    $field = $this->schema->field('title');

    expect($field)->toBeInstanceOf(FieldConfigurator::class)
        ->and($field->relation('author'))->toBeInstanceOf(RelationConfigurator::class)
        ->and($field->exclude('body'))->toBe($this->schema)
        ->and($field->action('publish'))->toBe($this->schema)
        ->and($field->end())->toBe($this->schema)
        ->and($this->schema->relation('author')->field('id'))->toBeInstanceOf(FieldConfigurator::class);
});

it('overrides inferred requiredness', function (): void {
    $this->schema->field('title')->required()->field('body')->required(false);

    expect($this->draft->field('title')->required)->toBeTrue()
        ->and($this->draft->field('body')->required)->toBeFalse();
});
