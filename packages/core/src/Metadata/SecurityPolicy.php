<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Metadata;

use PhpTurbo\Admin\Core\Builder\FieldDraft;
use PhpTurbo\Admin\Core\Builder\RelationDraft;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\RelationType;

/**
 * Regras de "negar por padrão". Aplicadas sempre por último, depois de qualquer override,
 * para que um erro de configuração não exponha dados sem opt-in explícito.
 */
final class SecurityPolicy
{
    /** Padrões (glob, `*` = qualquer sequência) de nomes considerados sensíveis. */
    public const DEFAULT_SENSITIVE_PATTERNS = [
        'password',
        'password_*',
        '*_password',
        'remember_token',
        'token',
        '*_token',
        'secret',
        '*_secret',
        'api_key',
        '*_api_key',
        'private_key',
        'two_factor_*',
    ];

    private readonly string $sensitiveRegex;

    /** @param list<string> $sensitivePatterns */
    public function __construct(array $sensitivePatterns = self::DEFAULT_SENSITIVE_PATTERNS)
    {
        $alternatives = array_map(
            static fn (string $pattern): string => str_replace('\*', '.*', preg_quote(strtolower($pattern), '/')),
            $sensitivePatterns,
        );

        $this->sensitiveRegex = $alternatives === [] ? '/(?!)/' : '/^(?:' . implode('|', $alternatives) . ')$/';
    }

    public function isSensitive(FieldDraft $field): bool
    {
        return $field->type === FieldType::Password || preg_match($this->sensitiveRegex, strtolower($field->name)) === 1;
    }

    /** Campos em $hidden e write-only nunca são lidos; sensíveis só com allowSensitive(). */
    public function isReadable(FieldDraft $field): bool
    {
        if ($field->excluded || $field->hidden || $field->writeOnly) {
            return false;
        }

        return !$this->isSensitive($field) || $field->sensitiveAllowed;
    }

    /** Gravável exige: liberado para atribuição em massa pelo ORM, não somente-leitura e não PK. */
    public function isWritable(FieldDraft $field): bool
    {
        if ($field->excluded || !$field->massAssignable || $field->readOnly || $field->primary) {
            return false;
        }

        return !$this->isSensitive($field) || $field->sensitiveAllowed || $field->writeOnly;
    }

    /** @param array<string, FieldDraft> $fields */
    public function isRelationWritable(RelationDraft $relation, array $fields): bool
    {
        return match ($relation->type) {
            RelationType::BelongsTo => $relation->foreignKey !== null
                && isset($fields[$relation->foreignKey])
                && $this->isWritable($fields[$relation->foreignKey]),
            RelationType::BelongsToMany, RelationType::MorphToMany => true,
            default => false,
        };
    }
}
