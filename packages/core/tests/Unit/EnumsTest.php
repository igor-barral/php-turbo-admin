<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\RelationType;
use PhpTurbo\Admin\Core\Schema\Visibility;

it('flags long field types', function (FieldType $type, bool $long): void {
    expect($type->isLong())->toBe($long);
})->with([
    [FieldType::Text, true],
    [FieldType::Json, true],
    [FieldType::String, false],
    [FieldType::Integer, false],
]);

it('flags low cardinality field types', function (FieldType $type, bool $low): void {
    expect($type->isLowCardinality())->toBe($low);
})->with([
    [FieldType::Boolean, true],
    [FieldType::Enum, true],
    [FieldType::String, false],
    [FieldType::Date, false],
]);

it('knows which relations are single', function (RelationType $type, bool $single): void {
    expect($type->isSingle())->toBe($single);
})->with([
    [RelationType::BelongsTo, true],
    [RelationType::HasOne, true],
    [RelationType::HasOneThrough, true],
    [RelationType::MorphTo, true],
    [RelationType::MorphOne, true],
    [RelationType::HasMany, false],
    [RelationType::BelongsToMany, false],
    [RelationType::HasManyThrough, false],
    [RelationType::MorphMany, false],
    [RelationType::MorphToMany, false],
]);

it('treats through relations as read only', function (RelationType $type, bool $writable): void {
    expect($type->isWritable())->toBe($writable);
})->with([
    [RelationType::HasOneThrough, false],
    [RelationType::HasManyThrough, false],
    [RelationType::BelongsTo, true],
    [RelationType::HasMany, true],
]);

it('groups visibilities', function (): void {
    expect(Visibility::all())->toBe([Visibility::Index, Visibility::Detail, Visibility::Create, Visibility::Update])
        ->and(Visibility::read())->toBe([Visibility::Index, Visibility::Detail])
        ->and(Visibility::forms())->toBe([Visibility::Create, Visibility::Update])
        ->and(Visibility::Create->isForm())->toBeTrue()
        ->and(Visibility::Update->isForm())->toBeTrue()
        ->and(Visibility::Index->isForm())->toBeFalse();
});
