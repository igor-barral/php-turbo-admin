<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Builder;

use PhpTurbo\Admin\Core\Exception\UnknownFieldException;
use PhpTurbo\Admin\Core\Exception\UnknownRelationException;
use PhpTurbo\Admin\Core\Schema\ActionSchema;
use PhpTurbo\Admin\Core\Schema\RelationType;
use PhpTurbo\Admin\Core\Schema\Sort;
use PhpTurbo\Admin\Core\Schema\SortDirection;
use PhpTurbo\Admin\Core\Support\Text;

/**
 * API fluente usada em configureAdmin(). Só altera o que já foi descoberto pelas camadas
 * anteriores: referenciar um campo inexistente é erro, nunca criação silenciosa.
 */
final class AdminSchema
{
    public function __construct(private readonly EntityDraft $draft)
    {
    }

    public function key(string $key): self
    {
        $this->draft->key = $key;

        return $this;
    }

    public function label(string $singular, ?string $plural = null): self
    {
        $this->draft->label = $singular;
        $this->draft->labelPlural = $plural ?? $this->draft->labelPlural ?? $singular;

        return $this;
    }

    public function titleField(string $field): self
    {
        $this->assertField($field);
        $this->draft->titleField = $field;

        return $this;
    }

    public function sort(string $field, SortDirection|string $direction = SortDirection::Asc): self
    {
        $this->assertField($field);
        $this->draft->sort = new Sort(
            $field,
            $direction instanceof SortDirection ? $direction : SortDirection::from(strtolower($direction)),
        );

        return $this;
    }

    public function field(string $name): FieldConfigurator
    {
        $this->assertField($name);

        return new FieldConfigurator($this, $this->draft->fields[$name]);
    }

    /**
     * Configura uma relação descoberta ou declara uma nova (quando o método do model
     * não declara tipo de retorno e por isso não pôde ser descoberto).
     *
     * @param class-string|null $target
     */
    public function relation(string $name, ?RelationType $type = null, ?string $target = null): RelationConfigurator
    {
        if (!$this->draft->hasRelation($name)) {
            if ($type === null) {
                throw UnknownRelationException::on($this->draft->class, $name, array_keys($this->draft->relations));
            }

            $relation = $this->draft->relation($name);
            $relation->type = $type;
            $relation->target = $target;
        }

        return new RelationConfigurator($this, $this->draft->relations[$name]);
    }

    public function exclude(string ...$names): self
    {
        foreach ($names as $name) {
            if ($this->draft->hasField($name)) {
                $this->draft->fields[$name]->excluded = true;
            } elseif ($this->draft->hasRelation($name)) {
                $this->draft->relations[$name]->excluded = true;
            } else {
                throw UnknownFieldException::on($this->draft->class, $name, array_keys($this->draft->fields));
            }
        }

        return $this;
    }

    public function action(string $name, ?string $label = null, bool $bulk = false, bool $destructive = false): self
    {
        $this->draft->addAction(new ActionSchema($name, $label ?? Text::humanize($name), $bulk, $destructive));

        return $this;
    }

    private function assertField(string $name): void
    {
        if ($this->draft->hasField($name)) {
            return;
        }

        $hint = $this->draft->hasRelation($name) ? sprintf('"%s" é uma relação: use relation("%s").', $name, $name) : null;

        throw UnknownFieldException::on($this->draft->class, $name, array_keys($this->draft->fields), $hint);
    }
}
