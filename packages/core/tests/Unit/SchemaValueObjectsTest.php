<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Exception\UnknownFieldException;
use PhpTurbo\Admin\Core\Exception\UnknownRelationException;
use PhpTurbo\Admin\Core\Schema\ActionSchema;
use PhpTurbo\Admin\Core\Schema\EntitySchema;
use PhpTurbo\Admin\Core\Schema\FieldSchema;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\RelationSchema;
use PhpTurbo\Admin\Core\Schema\RelationType;
use PhpTurbo\Admin\Core\Schema\Sort;
use PhpTurbo\Admin\Core\Schema\SortDirection;
use PhpTurbo\Admin\Core\Schema\Visibility;

function sampleEntity(): EntitySchema
{
    return new EntitySchema(
        key: 'posts',
        class: 'App\\Models\\Post',
        label: 'Post',
        labelPlural: 'Posts',
        primaryKey: 'id',
        titleField: 'title',
        fields: [
            new FieldSchema('id', 'Id', FieldType::Integer, false, true, false, false, true, false, Visibility::read()),
            new FieldSchema(
                'title', 'Título', FieldType::String, false, true, true, true, true, false, Visibility::all(),
                widget: 'text', rules: ['required'], options: [],
            ),
            new FieldSchema(
                'status', 'Status', FieldType::Enum, true, true, true, false, false, true, [Visibility::Detail],
                options: [['value' => 'draft', 'label' => 'Rascunho']],
            ),
        ],
        relations: [
            new RelationSchema('author', 'Autor', RelationType::BelongsTo, 'App\\Models\\User', 'author_id', 'name', true, true),
        ],
        actions: [new ActionSchema('publish', 'Publicar', bulk: true)],
        sort: new Sort('id', SortDirection::Desc),
    );
}

it('indexes fields, relations and actions by name', function (): void {
    $entity = sampleEntity();

    expect(array_keys($entity->fields))->toBe(['id', 'title', 'status'])
        ->and($entity->hasField('title'))->toBeTrue()
        ->and($entity->hasField('nope'))->toBeFalse()
        ->and($entity->field('title')->label)->toBe('Título')
        ->and($entity->hasRelation('author'))->toBeTrue()
        ->and($entity->hasRelation('nope'))->toBeFalse()
        ->and($entity->relation('author')->displayField)->toBe('name')
        ->and(array_keys($entity->actions))->toBe(['publish']);
});

it('throws a helpful error for unknown fields', function (): void {
    sampleEntity()->field('subtitle');
})->throws(UnknownFieldException::class, 'Campo "subtitle" não existe em App\\Models\\Post. Campos conhecidos: id, title, status.');

it('throws a helpful error for unknown relations', function (): void {
    sampleEntity()->relation('tags');
})->throws(UnknownRelationException::class, 'Relação "tags" não existe em App\\Models\\Post. Relações conhecidas: author.');

it('filters fields by visibility', function (): void {
    $names = static fn (array $fields): array => array_map(static fn (FieldSchema $f): string => $f->name, $fields);
    $entity = sampleEntity();

    expect($names($entity->fieldsVisibleOn(Visibility::Index)))->toBe(['id', 'title'])
        ->and($names($entity->fieldsVisibleOn(Visibility::Detail)))->toBe(['id', 'title', 'status'])
        ->and($names($entity->fieldsVisibleOn(Visibility::Create)))->toBe(['title']);
});

it('round-trips through arrays without losing information', function (): void {
    $entity = sampleEntity();

    $restored = EntitySchema::fromArray($entity->toArray());

    expect($restored)->toEqual($entity)
        ->and($restored->toArray())->toBe($entity->toArray());
});

it('serializes enums as their scalar values', function (): void {
    $array = sampleEntity()->toArray();

    expect($array['fields'][1]['type'])->toBe('string')
        ->and($array['fields'][0]['visibility'])->toBe(['index', 'detail'])
        ->and($array['relations'][0]['type'])->toBe('belongsTo')
        ->and($array['sort'])->toBe(['field' => 'id', 'direction' => 'desc'])
        ->and($array['actions'][0])->toBe(['name' => 'publish', 'label' => 'Publicar', 'bulk' => true, 'destructive' => false]);
});

it('round-trips an entity without sort', function (): void {
    $entity = new EntitySchema('tags', 'Tag', 'Tag', 'Tags', 'id', null, []);

    expect(EntitySchema::fromArray($entity->toArray()))->toEqual($entity)
        ->and($entity->toArray()['sort'])->toBeNull();
});
