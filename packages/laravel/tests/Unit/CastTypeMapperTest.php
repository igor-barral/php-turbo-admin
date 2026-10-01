<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Laravel\Metadata\CastTypeMapper;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\PostStatus;

enum UnitEnumFixture
{
    case Only;
}

it('maps eloquent casts', function (string $cast, ?FieldType $expected): void {
    expect((new CastTypeMapper())->map($cast))->toBe($expected);
})->with([
    ['int', FieldType::Integer],
    ['integer', FieldType::Integer],
    ['float', FieldType::Decimal],
    ['decimal:2', FieldType::Decimal],
    ['bool', FieldType::Boolean],
    ['boolean', FieldType::Boolean],
    ['date', FieldType::Date],
    ['immutable_date', FieldType::Date],
    ['datetime', FieldType::DateTime],
    ['datetime:Y-m-d H:i', FieldType::DateTime],
    ['immutable_datetime', FieldType::DateTime],
    ['timestamp', FieldType::DateTime],
    ['array', FieldType::Json],
    ['json', FieldType::Json],
    ['json:unicode', FieldType::Json],
    ['object', FieldType::Json],
    ['collection', FieldType::Json],
    ['encrypted:array', FieldType::Json],
    [AsArrayObject::class, FieldType::Json],
    [AsCollection::class . ':App\\Collection', FieldType::Json],
    ['hashed', FieldType::Password],
    [PostStatus::class, FieldType::Enum],
    ['string', null],
    ['encrypted', null],
    ['App\\Casts\\Money', null],
]);

it('builds options from backed enums', function (): void {
    expect((new CastTypeMapper())->enumOptions(PostStatus::class))->toBe([
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'in_review', 'label' => 'In Review'],
        ['value' => 'published', 'label' => 'Published'],
    ]);
});

it('returns no options for pure enums or non enums', function (string $cast): void {
    expect((new CastTypeMapper())->enumOptions($cast))->toBe([]);
})->with([UnitEnumFixture::class, 'string']);
