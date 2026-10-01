<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Data\QueryPlan;
use PhpTurbo\Admin\Core\Data\Record;
use PhpTurbo\Admin\Core\Data\RecordRef;
use PhpTurbo\Admin\Core\Http\HttpError;
use PhpTurbo\Admin\Core\Http\RecordSerializer;
use PhpTurbo\Admin\Core\Query\Cursor;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\SortDirection;
use PhpTurbo\Admin\Core\Tests\Fixtures\Api\ApiFixtures;

it('round-trips cursors as url-safe strings', function (mixed $value, string|int $id): void {
    $encoded = (new Cursor($value, $id))->encode();

    expect($encoded)->toMatch('/^[A-Za-z0-9_-]+$/')
        ->and(Cursor::decode($encoded))->toEqual(new Cursor($value, $id));
})->with([
    ['Olá? /+', 1],
    [null, 'uuid-1'],
    [1.5, 2],
    [true, 3],
]);

it('rejects tampered cursors', function (string $encoded): void {
    expect(fn () => Cursor::decode($encoded))->toThrow(HttpError::class, 'Requisição inválida');
})->with([
    'not base64' => ['***'],
    'not json' => [rtrim(base64_encode('nope'), '=')],
    'object' => [base64_encode('{"a":1}')],
    'wrong size' => [base64_encode('[1]')],
    'nested value' => [base64_encode('[[1],1]')],
    'float id' => [base64_encode('[1,1.5]')],
]);

it('normalizes values for json', function (FieldType $type, mixed $value, mixed $expected): void {
    expect((new RecordSerializer())->normalize($type, $value))->toBe($expected);
})->with([
    'null' => [FieldType::String, null, null],
    'float' => [FieldType::Decimal, 1.5, 1.5],
    'infinite float' => [FieldType::Decimal, INF, null],
    'date' => [FieldType::Date, new DateTimeImmutable('2026-03-04 10:11:12+00:00'), '2026-03-04'],
    'time' => [FieldType::Time, new DateTimeImmutable('2026-03-04 10:11:12+00:00'), '10:11:12'],
    'datetime' => [FieldType::DateTime, new DateTimeImmutable('2026-03-04 10:11:12+00:00'), '2026-03-04T10:11:12+00:00'],
    'backed enum' => [FieldType::Enum, SortDirection::Desc, 'desc'],
    'unit enum' => [FieldType::Enum, \PhpTurbo\Admin\Core\Tests\Fixtures\UnitStatus::Active, 'Active'],
    'json serializable' => [FieldType::Json, new ArrayObject(['a' => 1]), null],
    'json serializable object' => [FieldType::Json, new class () implements JsonSerializable {
        public function jsonSerialize(): array
        {
            return ['ok' => true];
        }
    }, ['ok' => true]],
    'array' => [FieldType::Json, ['a' => 1], ['a' => 1]],
    'stringable' => [FieldType::String, new class () implements Stringable {
        public function __toString(): string
        {
            return 'texto';
        }
    }, 'texto'],
    'unknown object' => [FieldType::String, new stdClass(), null],
]);

it('serializes to-many relations and falls back to the id as title', function (): void {
    $writers = ApiFixtures::registry()->get('writers');
    $record = new Record(7, ['id' => 7, 'name' => ''], ['posts' => [new RecordRef(1, 'Um'), new RecordRef(2, 'Dois')]]);

    $serialized = (new RecordSerializer())->serialize($writers, $record, new QueryPlan(['id'], ['posts' => 'title', 'missing' => null]));

    expect(json_decode(json_encode($serialized, JSON_THROW_ON_ERROR), true))->toBe([
        'id' => 7,
        'title' => '7',
        'attributes' => ['id' => 7, 'name' => ''],
        'relations' => ['posts' => [['id' => 1, 'title' => 'Um'], ['id' => 2, 'title' => 'Dois']], 'missing' => null],
    ]);
});
