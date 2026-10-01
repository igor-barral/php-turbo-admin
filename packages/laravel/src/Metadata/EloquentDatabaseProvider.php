<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Metadata;

use Illuminate\Database\Eloquent\Model;
use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Metadata\MetadataProvider;
use PhpTurbo\Admin\Core\Metadata\Priority;
use PhpTurbo\Admin\Core\Schema\FieldType;

/**
 * Camada 1: introspecção da tabela do model (colunas, tipos, nulabilidade, índices).
 * Roda só na compilação do schema — nunca em tempo de requisição com o cache ativo.
 */
final class EloquentDatabaseProvider implements MetadataProvider
{
    public function __construct(private readonly ColumnTypeMapper $types = new ColumnTypeMapper())
    {
    }

    public function priority(): int
    {
        return Priority::DATABASE;
    }

    public function supports(string $class): bool
    {
        return is_subclass_of($class, Model::class);
    }

    public function provide(string $class, EntityDraft $draft): void
    {
        /** @var Model $model */
        $model = new $class();
        $schema = $model->getConnection()->getSchemaBuilder();
        $table = $model->getTable();

        foreach ($schema->getColumns($table) as $column) {
            $field = $draft->field($column['name']);
            $field->type = $this->types->map($column['type_name'], $column['type']);
            $field->nullable = $column['nullable'];
            $field->hasDefault = $column['default'] !== null || $column['auto_increment'];

            if ($field->type === FieldType::Enum) {
                foreach ($this->types->enumValues($column['type']) as $value) {
                    $field->options[] = ['value' => $value, 'label' => $value];
                }
            }
        }

        foreach ($schema->getIndexes($table) as $index) {
            // Só a coluna líder de um índice acelera ORDER BY nela.
            $leading = $index['columns'][0] ?? null;
            if ($leading !== null && $draft->hasField($leading)) {
                $draft->fields[$leading]->indexed = true;
            }

            if ($index['primary'] && count($index['columns']) === 1 && $leading !== null && $draft->hasField($leading)) {
                $draft->fields[$leading]->primary = true;
            }
        }
    }
}
