<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Metadata;

use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Exception\InvalidSchemaException;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\RelationType;
use PhpTurbo\Admin\Core\Schema\Sort;
use PhpTurbo\Admin\Core\Schema\SortDirection;
use PhpTurbo\Admin\Core\Schema\Visibility;
use PhpTurbo\Admin\Core\Support\Text;

/**
 * Preenche apenas o que nenhuma camada decidiu (valores null). Roda depois de todos os
 * providers, então nunca sobrescreve uma escolha explícita.
 */
final class Conventions
{
    /** Campos candidatos a título, em ordem de preferência. */
    private const TITLE_CANDIDATES = ['name', 'title', 'label', 'subject', 'email'];

    public function apply(EntityDraft $draft): void
    {
        $basename = Text::classBasename($draft->class);

        $draft->key ??= Text::snake($basename);
        $draft->label ??= Text::humanize($basename);
        $draft->labelPlural ??= $draft->label;
        $primaryKey = $draft->primaryKey ??= $this->guessPrimaryKey($draft);

        $this->applyRelations($draft);
        $this->applyFields($draft);

        $draft->titleField ??= $this->guessTitleField($draft);
        $draft->sort ??= new Sort($primaryKey, SortDirection::Desc);
    }

    private function guessPrimaryKey(EntityDraft $draft): string
    {
        foreach ($draft->fields as $field) {
            if ($field->primary) {
                return $field->name;
            }
        }

        if ($draft->hasField('id')) {
            return 'id';
        }

        throw InvalidSchemaException::missingPrimaryKey($draft->class);
    }

    private function applyRelations(EntityDraft $draft): void
    {
        foreach ($draft->relations as $relation) {
            $relation->label ??= Text::humanize($relation->name);
            $relation->eager ??= $relation->type === RelationType::BelongsTo;

            // A coluna da FK é representada pela relação: some das telas, mas filtra
            // ("posts deste autor") por padrão.
            $isOwning = $relation->type === RelationType::BelongsTo || $relation->type === RelationType::MorphTo;
            if (!$relation->excluded && $isOwning && $relation->foreignKey !== null && $draft->hasField($relation->foreignKey)) {
                $draft->fields[$relation->foreignKey]->visibility ??= [];
                $draft->fields[$relation->foreignKey]->filterable ??= true;
            }
        }
    }

    private function applyFields(EntityDraft $draft): void
    {
        foreach ($draft->fields as $field) {
            if ($field->name === $draft->primaryKey) {
                $field->primary = true;
            }

            $field->type ??= FieldType::String;
            $field->label ??= Text::humanize($field->name);
            $field->searchable ??= false;
            $field->sortable ??= $field->primary || $field->indexed;
            $field->filterable ??= $field->type->isLowCardinality();
            $field->visibility ??= match (true) {
                $field->primary, $field->readOnly => Visibility::read(),
                $field->type->isLong() => [Visibility::Detail, Visibility::Create, Visibility::Update],
                default => Visibility::all(),
            };
        }
    }

    private function guessTitleField(EntityDraft $draft): string
    {
        foreach (self::TITLE_CANDIDATES as $candidate) {
            if ($draft->hasField($candidate) && !$draft->fields[$candidate]->excluded) {
                return $candidate;
            }
        }

        return (string) $draft->primaryKey;
    }
}
