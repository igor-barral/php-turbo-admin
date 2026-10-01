<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Laravel\Metadata\ColumnTypeMapper;

it('maps native column types', function (string $typeName, string $type, FieldType $expected): void {
    expect((new ColumnTypeMapper())->map($typeName, $type))->toBe($expected);
})->with([
    ['bigint', 'bigint unsigned', FieldType::Integer],
    ['int4', 'integer', FieldType::Integer],
    ['INTEGER', 'integer', FieldType::Integer],
    ['tinyint', 'tinyint(4)', FieldType::Integer],
    ['tinyint', 'tinyint(1)', FieldType::Boolean],
    ['bool', 'boolean', FieldType::Boolean],
    ['bit', 'bit', FieldType::Boolean],
    ['decimal', 'decimal(10,2)', FieldType::Decimal],
    ['numeric', 'numeric', FieldType::Decimal],
    ['float8', 'double precision', FieldType::Decimal],
    ['date', 'date', FieldType::Date],
    ['datetime', 'datetime', FieldType::DateTime],
    ['timestamptz', 'timestamp with time zone', FieldType::DateTime],
    ['time', 'time', FieldType::Time],
    ['longtext', 'longtext', FieldType::Text],
    ['jsonb', 'jsonb', FieldType::Json],
    ['uuid', 'uuid', FieldType::Uuid],
    ['uniqueidentifier', 'uniqueidentifier', FieldType::Uuid],
    ['enum', "enum('a','b')", FieldType::Enum],
    ['varchar', 'varchar(255)', FieldType::String],
    ['char', 'char(36)', FieldType::String],
]);

it('extracts mysql enum values', function (string $type, array $values): void {
    expect((new ColumnTypeMapper())->enumValues($type))->toBe($values);
})->with([
    ["enum('draft','published')", ['draft', 'published']],
    ["ENUM('open','it''s paid')", ['open', "it's paid"]],
    ["enum('')", ['']],
    ['varchar(255)', []],
]);
