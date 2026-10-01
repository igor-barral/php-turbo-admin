<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Builder;

use PhpTurbo\Admin\Core\Schema\RelationType;

/**
 * Permite encadear a configuração de vários campos/relações sem voltar ao AdminSchema.
 */
abstract class Configurator
{
    public function __construct(protected readonly AdminSchema $schema)
    {
    }

    public function field(string $name): FieldConfigurator
    {
        return $this->schema->field($name);
    }

    /** @param class-string|null $target */
    public function relation(string $name, ?RelationType $type = null, ?string $target = null): RelationConfigurator
    {
        return $this->schema->relation($name, $type, $target);
    }

    public function exclude(string ...$names): AdminSchema
    {
        return $this->schema->exclude(...$names);
    }

    public function action(string $name, ?string $label = null, bool $bulk = false, bool $destructive = false): AdminSchema
    {
        return $this->schema->action($name, $label, $bulk, $destructive);
    }

    public function end(): AdminSchema
    {
        return $this->schema;
    }
}
