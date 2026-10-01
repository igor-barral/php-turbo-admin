<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Builder;

final class RelationConfigurator extends Configurator
{
    public function __construct(AdminSchema $schema, private readonly RelationDraft $relation)
    {
        parent::__construct($schema);
    }

    public function label(string $label): self
    {
        $this->relation->label = $label;

        return $this;
    }

    /** Campo do model relacionado usado como texto (ex.: author → name). */
    public function display(string $field): self
    {
        $this->relation->displayField = $field;

        return $this;
    }

    public function eager(bool $eager = true): self
    {
        $this->relation->eager = $eager;

        return $this;
    }

    public function foreignKey(string $column): self
    {
        $this->relation->foreignKey = $column;

        return $this;
    }
}
