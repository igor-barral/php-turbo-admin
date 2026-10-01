<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Schema;

enum RelationType: string
{
    case BelongsTo = 'belongsTo';
    case HasOne = 'hasOne';
    case HasMany = 'hasMany';
    case BelongsToMany = 'belongsToMany';
    case HasOneThrough = 'hasOneThrough';
    case HasManyThrough = 'hasManyThrough';
    case MorphTo = 'morphTo';
    case MorphOne = 'morphOne';
    case MorphMany = 'morphMany';
    case MorphToMany = 'morphToMany';

    /** Relações que retornam no máximo um registro. */
    public function isSingle(): bool
    {
        return match ($this) {
            self::BelongsTo, self::HasOne, self::HasOneThrough, self::MorphTo, self::MorphOne => true,
            default => false,
        };
    }

    /** Relações "through" são somente leitura. */
    public function isWritable(): bool
    {
        return $this !== self::HasOneThrough && $this !== self::HasManyThrough;
    }
}
