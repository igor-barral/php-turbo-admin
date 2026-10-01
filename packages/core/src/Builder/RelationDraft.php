<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Builder;

use PhpTurbo\Admin\Core\Schema\RelationType;

/** @internal */
final class RelationDraft
{
    public ?RelationType $type = null;

    /** @var class-string|null */
    public ?string $target = null;

    public ?string $foreignKey = null;
    public ?string $label = null;
    public ?string $displayField = null;
    public ?bool $eager = null;
    public bool $excluded = false;

    public function __construct(public readonly string $name)
    {
    }
}
