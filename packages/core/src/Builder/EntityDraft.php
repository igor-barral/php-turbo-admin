<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Builder;

use PhpTurbo\Admin\Core\Schema\ActionSchema;
use PhpTurbo\Admin\Core\Schema\Sort;

/**
 * Estado mutável de uma entidade, compartilhado entre os MetadataProviders.
 *
 * @internal
 */
final class EntityDraft
{
    public ?string $key = null;
    public ?string $label = null;
    public ?string $labelPlural = null;
    public ?string $primaryKey = null;
    public ?string $titleField = null;
    public ?Sort $sort = null;

    /** @var array<string, FieldDraft> */
    public array $fields = [];

    /** @var array<string, RelationDraft> */
    public array $relations = [];

    /** @var array<string, ActionSchema> */
    public array $actions = [];

    /** @param class-string $class */
    public function __construct(public readonly string $class)
    {
    }

    /** Retorna o campo, criando-o se ainda não existir. */
    public function field(string $name): FieldDraft
    {
        return $this->fields[$name] ??= new FieldDraft($name);
    }

    public function hasField(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    /** Retorna a relação, criando-a se ainda não existir. */
    public function relation(string $name): RelationDraft
    {
        return $this->relations[$name] ??= new RelationDraft($name);
    }

    public function hasRelation(string $name): bool
    {
        return isset($this->relations[$name]);
    }

    public function addAction(ActionSchema $action): void
    {
        $this->actions[$action->name] = $action;
    }
}
