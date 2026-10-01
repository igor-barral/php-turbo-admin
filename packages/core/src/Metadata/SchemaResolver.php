<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Metadata;

use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Builder\FieldDraft;
use PhpTurbo\Admin\Core\Builder\RelationDraft;
use PhpTurbo\Admin\Core\Schema\EntitySchema;
use PhpTurbo\Admin\Core\Schema\FieldSchema;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\RelationSchema;
use PhpTurbo\Admin\Core\Schema\RelationType;
use PhpTurbo\Admin\Core\Schema\Sort;
use PhpTurbo\Admin\Core\Schema\SortDirection;
use PhpTurbo\Admin\Core\Schema\Visibility;

/**
 * Executa as camadas (providers → convenções → segurança) e produz o EntitySchema final.
 */
final class SchemaResolver
{
    /** @var list<MetadataProvider> */
    private readonly array $providers;

    /** @param iterable<MetadataProvider> $providers */
    public function __construct(
        iterable $providers,
        private readonly SecurityPolicy $security = new SecurityPolicy(),
        private readonly Conventions $conventions = new Conventions(),
    ) {
        $sorted = [...$providers];
        // usort é estável desde o PHP 8: providers de mesma prioridade mantêm a ordem de registro.
        usort($sorted, static fn (MetadataProvider $a, MetadataProvider $b): int => $a->priority() <=> $b->priority());
        $this->providers = $sorted;
    }

    /** @param class-string $class */
    public function resolve(string $class): EntitySchema
    {
        $draft = new EntityDraft($class);

        foreach ($this->providers as $provider) {
            if ($provider->supports($class)) {
                $provider->provide($class, $draft);
            }
        }

        $this->conventions->apply($draft);

        return $this->build($draft);
    }

    private function build(EntityDraft $draft): EntitySchema
    {
        $fields = [];
        foreach ($draft->fields as $field) {
            $schema = $this->buildField($field);
            if ($schema !== null) {
                $fields[$schema->name] = $schema;
            }
        }

        $relations = [];
        foreach ($draft->relations as $relation) {
            if (!$relation->excluded && $relation->type !== null) {
                $relations[] = $this->buildRelation($relation, $draft);
            }
        }

        $primaryKey = (string) $draft->primaryKey;
        $titleField = $draft->titleField;
        if ($titleField === null || !($fields[$titleField] ?? null)?->readable) {
            $titleField = isset($fields[$primaryKey]) ? $primaryKey : null;
        }

        $sort = $draft->sort;
        if ($sort !== null && !($fields[$sort->field] ?? null)?->readable) {
            $sort = isset($fields[$primaryKey]) ? new Sort($primaryKey, SortDirection::Desc) : null;
        }

        return new EntitySchema(
            key: (string) $draft->key,
            class: $draft->class,
            label: (string) $draft->label,
            labelPlural: (string) $draft->labelPlural,
            primaryKey: $primaryKey,
            titleField: $titleField,
            fields: array_values($fields),
            relations: $relations,
            actions: array_values($draft->actions),
            sort: $sort,
        );
    }

    /** Campos sem leitura nem escrita são omitidos: o schema não revela que a coluna existe. */
    private function buildField(FieldDraft $field): ?FieldSchema
    {
        $readable = $this->security->isReadable($field);
        $writable = $this->security->isWritable($field);

        if (!$readable && !$writable) {
            return null;
        }

        $visibility = array_values(array_filter(
            $field->visibility ?? [],
            static fn (Visibility $v): bool => $v->isForm() ? $writable : $readable,
        ));

        return new FieldSchema(
            name: $field->name,
            label: (string) $field->label,
            type: $field->type ?? FieldType::String,
            nullable: $field->nullable,
            readable: $readable,
            writable: $writable,
            // Busca/ordenação/filtro em campo ilegível permitiriam inferir seu valor.
            searchable: $readable && (bool) $field->searchable,
            sortable: $readable && (bool) $field->sortable,
            filterable: $readable && (bool) $field->filterable,
            visibility: $visibility,
            widget: $field->widget,
            rules: $field->rules,
            options: $field->options,
            required: $writable && ($field->required ?? (!$field->nullable && !$field->hasDefault && !$field->primary)),
        );
    }

    private function buildRelation(RelationDraft $relation, EntityDraft $draft): RelationSchema
    {
        /** @var RelationType $type */
        $type = $relation->type;

        return new RelationSchema(
            name: $relation->name,
            label: (string) $relation->label,
            type: $type,
            target: $relation->target,
            foreignKey: $relation->foreignKey,
            displayField: $relation->displayField,
            eager: (bool) $relation->eager,
            writable: $this->security->isRelationWritable($relation, $draft->fields),
        );
    }
}
