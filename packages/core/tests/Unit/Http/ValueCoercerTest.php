<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Schema\FieldSchema;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\Visibility;
use PhpTurbo\Admin\Core\Validation\InvalidValue;
use PhpTurbo\Admin\Core\Validation\ValueCoercer;

function coercionField(FieldType $type, bool $nullable = false): FieldSchema
{
    return new FieldSchema('f', 'F', $type, $nullable, true, true, false, false, false, Visibility::all(), options: [
        ['value' => 'draft', 'label' => 'Rascunho'],
        ['value' => 2, 'label' => 'Dois'],
    ]);
}

beforeEach(function (): void {
    $this->timezone = date_default_timezone_get();
    date_default_timezone_set('America/Sao_Paulo');
});

afterEach(function (): void {
    date_default_timezone_set($this->timezone);
});

it('coerces valid values', function (FieldType $type, mixed $input, mixed $expected): void {
    expect((new ValueCoercer())->coerce(coercionField($type), $input))->toBe($expected);
})->with([
    'int' => [FieldType::Integer, 42, 42],
    'int string' => [FieldType::Integer, '-7', -7],
    'int float' => [FieldType::Integer, 3.0, 3],
    'decimal string' => [FieldType::Decimal, '10.50', '10.50'],
    'decimal int' => [FieldType::Decimal, 3, '3'],
    'decimal float' => [FieldType::Decimal, 1.5, '1.5'],
    'money' => [FieldType::Money, '-2', '-2'],
    'bool' => [FieldType::Boolean, false, false],
    'bool 1' => [FieldType::Boolean, 1, true],
    'bool "1"' => [FieldType::Boolean, '1', true],
    'bool "true"' => [FieldType::Boolean, 'true', true],
    'bool 0' => [FieldType::Boolean, 0, false],
    'bool "0"' => [FieldType::Boolean, '0', false],
    'bool "false"' => [FieldType::Boolean, 'false', false],
    'date' => [FieldType::Date, '2026-02-28', '2026-02-28'],
    'datetime iso utc' => [FieldType::DateTime, '2026-02-28T15:00:00+00:00', '2026-02-28 12:00:00'],
    'datetime iso ms' => [FieldType::DateTime, '2026-02-28T15:00:00.123Z', '2026-02-28 12:00:00'],
    'datetime local input' => [FieldType::DateTime, '2026-02-28T15:30', '2026-02-28 15:30:00'],
    'datetime sql' => [FieldType::DateTime, '2026-02-28 15:30:10', '2026-02-28 15:30:10'],
    'time' => [FieldType::Time, '08:15', '08:15:00'],
    'time seconds' => [FieldType::Time, '08:15:30', '08:15:30'],
    'enum string' => [FieldType::Enum, 'draft', 'draft'],
    'enum int as string' => [FieldType::Enum, '2', 2],
    'json object' => [FieldType::Json, ['a' => 1], ['a' => 1]],
    'json list' => [FieldType::Json, [1, 2], [1, 2]],
    'email' => [FieldType::Email, 'igor@example.com', 'igor@example.com'],
    'url' => [FieldType::Url, 'https://example.com/a', 'https://example.com/a'],
    'uuid' => [FieldType::Uuid, '0190a5e1-2b3c-7d4e-8f90-123456789abc', '0190a5e1-2b3c-7d4e-8f90-123456789abc'],
    'string' => [FieldType::String, 'texto', 'texto'],
    'string from int' => [FieldType::String, 12, '12'],
    'text empty stays empty' => [FieldType::Text, '', ''],
    'password' => [FieldType::Password, 's3cr3t', 's3cr3t'],
]);

it('rejects invalid values', function (FieldType $type, mixed $input, string $message): void {
    expect(fn () => (new ValueCoercer())->coerce(coercionField($type), $input))->toThrow(InvalidValue::class, $message);
})->with([
    'int text' => [FieldType::Integer, 'abc', 'Deve ser um número inteiro.'],
    'int fraction' => [FieldType::Integer, 1.5, 'Deve ser um número inteiro.'],
    'int too long' => [FieldType::Integer, '1234567890123456789', 'Deve ser um número inteiro.'],
    'decimal text' => [FieldType::Decimal, '1,5', 'Deve ser um número.'],
    'decimal array' => [FieldType::Decimal, [1], 'Deve ser um número.'],
    'bool text' => [FieldType::Boolean, 'yes', 'Deve ser verdadeiro ou falso.'],
    'date invalid day' => [FieldType::Date, '2026-02-30', 'Data inválida (use AAAA-MM-DD).'],
    'date format' => [FieldType::Date, '28/02/2026', 'Data inválida (use AAAA-MM-DD).'],
    'date type' => [FieldType::Date, 20260228, 'Data inválida (use AAAA-MM-DD).'],
    'datetime' => [FieldType::DateTime, 'amanhã', 'Data e hora inválidas.'],
    'time' => [FieldType::Time, '25:00', 'Horário inválido (use HH:MM).'],
    'enum' => [FieldType::Enum, 'archived', 'Opção inválida.'],
    'enum array' => [FieldType::Enum, ['draft'], 'Opção inválida.'],
    'json string' => [FieldType::Json, '{"a":1}', 'Deve ser um objeto ou lista JSON.'],
    'email' => [FieldType::Email, 'igor', 'E-mail inválido.'],
    'url' => [FieldType::Url, 'not a url', 'URL inválida.'],
    'url javascript' => [FieldType::Url, 'javascript://alert(1)', 'A URL deve começar com http:// ou https://.'],
    'uuid' => [FieldType::Uuid, '1234', 'UUID inválido.'],
    'string array' => [FieldType::String, ['x'], 'Deve ser um texto.'],
    'string bool' => [FieldType::String, true, 'Deve ser um texto.'],
]);

it('handles null according to nullability', function (): void {
    $coercer = new ValueCoercer();

    expect($coercer->coerce(coercionField(FieldType::Integer, nullable: true), null))->toBeNull()
        ->and($coercer->coerce(coercionField(FieldType::Date, nullable: true), ''))->toBeNull()
        ->and($coercer->coerce(coercionField(FieldType::String, nullable: true), ''))->toBe('')
        ->and(fn () => $coercer->coerce(coercionField(FieldType::Integer), null))->toThrow(InvalidValue::class, 'Campo obrigatório.')
        ->and(fn () => $coercer->coerce(coercionField(FieldType::Integer), ''))->toThrow(InvalidValue::class, 'Deve ser um número inteiro.');
});

it('validates without throwing through check()', function (): void {
    $articles = \PhpTurbo\Admin\Core\Tests\Fixtures\Api\ApiFixtures::registry()->get('articles');
    $validator = new \PhpTurbo\Admin\Core\Validation\InputValidator();

    $result = $validator->check($articles, ['title' => 'Ok', 'rating' => 'x'], creating: false);

    expect($result->data)->toBe(['title' => 'Ok'])
        ->and($result->errors)->toBe(['rating' => ['Deve ser um número inteiro.']])
        ->and($validator->validate($articles, ['title' => 'Ok'], creating: false))->toBe(['title' => 'Ok'])
        ->and(fn () => $validator->validate($articles, ['rating' => 'x'], creating: false))
        ->toThrow(\PhpTurbo\Admin\Core\Http\HttpError::class);
});
