<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Builder\FieldDraft;
use PhpTurbo\Admin\Core\Builder\RelationDraft;
use PhpTurbo\Admin\Core\Metadata\SecurityPolicy;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\RelationType;

function securityField(string $name, bool $massAssignable = true): FieldDraft
{
    $field = new FieldDraft($name);
    $field->massAssignable = $massAssignable;

    return $field;
}

it('detects sensitive names', function (string $name, bool $sensitive): void {
    expect((new SecurityPolicy())->isSensitive(securityField($name)))->toBe($sensitive);
})->with([
    ['password', true],
    ['PASSWORD', true],
    ['password_confirmation', true],
    ['old_password', true],
    ['remember_token', true],
    ['token', true],
    ['access_token', true],
    ['secret', true],
    ['client_secret', true],
    ['api_key', true],
    ['stripe_api_key', true],
    ['private_key', true],
    ['two_factor_recovery_codes', true],
    ['title', false],
    ['tokenizer', false],
    ['secretary', false],
    ['email', false],
]);

it('treats password typed fields as sensitive regardless of name', function (): void {
    $field = securityField('pin');
    $field->type = FieldType::Password;

    expect((new SecurityPolicy())->isSensitive($field))->toBeTrue();
});

it('accepts custom sensitive patterns', function (): void {
    $policy = new SecurityPolicy(['cpf', 'card_*']);

    expect($policy->isSensitive(securityField('cpf')))->toBeTrue()
        ->and($policy->isSensitive(securityField('card_number')))->toBeTrue()
        ->and($policy->isSensitive(securityField('password')))->toBeFalse();
});

it('accepts an empty pattern list', function (): void {
    expect((new SecurityPolicy([]))->isSensitive(securityField('password')))->toBeFalse();
});

it('escapes regex characters in patterns', function (): void {
    $policy = new SecurityPolicy(['a.b']);

    expect($policy->isSensitive(securityField('a.b')))->toBeTrue()
        ->and($policy->isSensitive(securityField('axb')))->toBeFalse();
});

it('decides readability', function (callable $setup, bool $readable): void {
    $field = securityField('title');
    $setup($field);

    expect((new SecurityPolicy())->isReadable($field))->toBe($readable);
})->with([
    'plain field' => [static fn (FieldDraft $f) => null, true],
    'excluded' => [static fn (FieldDraft $f) => $f->excluded = true, false],
    'hidden in model' => [static fn (FieldDraft $f) => $f->hidden = true, false],
    'hidden even when sensitive allowed' => [static function (FieldDraft $f): void {
        $f->hidden = true;
        $f->sensitiveAllowed = true;
    }, false],
    'write only' => [static fn (FieldDraft $f) => $f->writeOnly = true, false],
    'sensitive' => [static fn (FieldDraft $f) => $f->type = FieldType::Password, false],
    'sensitive allowed' => [static function (FieldDraft $f): void {
        $f->type = FieldType::Password;
        $f->sensitiveAllowed = true;
    }, true],
]);

it('decides writability', function (callable $setup, bool $writable): void {
    $field = securityField('title');
    $setup($field);

    expect((new SecurityPolicy())->isWritable($field))->toBe($writable);
})->with([
    'mass assignable' => [static fn (FieldDraft $f) => null, true],
    'not mass assignable' => [static fn (FieldDraft $f) => $f->massAssignable = false, false],
    'excluded' => [static fn (FieldDraft $f) => $f->excluded = true, false],
    'read only' => [static fn (FieldDraft $f) => $f->readOnly = true, false],
    'primary key' => [static fn (FieldDraft $f) => $f->primary = true, false],
    'sensitive' => [static fn (FieldDraft $f) => $f->type = FieldType::Password, false],
    'sensitive write only' => [static function (FieldDraft $f): void {
        $f->type = FieldType::Password;
        $f->writeOnly = true;
    }, true],
    'sensitive allowed' => [static function (FieldDraft $f): void {
        $f->type = FieldType::Password;
        $f->sensitiveAllowed = true;
    }, true],
    'write only does not bypass mass assignment' => [static function (FieldDraft $f): void {
        $f->massAssignable = false;
        $f->writeOnly = true;
    }, false],
]);

it('decides relation writability', function (RelationType $type, ?string $foreignKey, bool $writable): void {
    $relation = new RelationDraft('rel');
    $relation->type = $type;
    $relation->foreignKey = $foreignKey;
    $fields = [
        'author_id' => securityField('author_id'),
        'locked_id' => securityField('locked_id', massAssignable: false),
    ];

    expect((new SecurityPolicy())->isRelationWritable($relation, $fields))->toBe($writable);
})->with([
    'belongsTo with writable fk' => [RelationType::BelongsTo, 'author_id', true],
    'belongsTo with guarded fk' => [RelationType::BelongsTo, 'locked_id', false],
    'belongsTo with unknown fk' => [RelationType::BelongsTo, 'missing_id', false],
    'belongsTo without fk' => [RelationType::BelongsTo, null, false],
    'belongsToMany' => [RelationType::BelongsToMany, null, true],
    'morphToMany' => [RelationType::MorphToMany, null, true],
    'hasMany' => [RelationType::HasMany, null, false],
    'hasManyThrough' => [RelationType::HasManyThrough, null, false],
    'morphTo' => [RelationType::MorphTo, 'author_id', false],
]);
