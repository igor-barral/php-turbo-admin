<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Metadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Metadata\MetadataProvider;
use PhpTurbo\Admin\Core\Metadata\Priority;
use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\Visibility;

/**
 * Camada 2: convenções do Eloquent — chave primária, casts, $hidden/$visible,
 * $fillable/$guarded, timestamps, soft deletes e relações.
 */
final class EloquentModelProvider implements MetadataProvider
{
    public function __construct(
        private readonly CastTypeMapper $casts = new CastTypeMapper(),
        private readonly RelationInspector $relations = new RelationInspector(),
    ) {
    }

    public function priority(): int
    {
        return Priority::ORM;
    }

    public function supports(string $class): bool
    {
        return is_subclass_of($class, Model::class);
    }

    public function provide(string $class, EntityDraft $draft): void
    {
        /** @var Model $model */
        $model = new $class();
        $basename = class_basename($class);

        $draft->key = Str::snake(Str::pluralStudly($basename));
        $draft->label = Str::headline($basename);
        $draft->labelPlural = Str::plural($draft->label);
        $draft->primaryKey = $model->getKeyName();
        $draft->field($model->getKeyName())->primary = true;

        $this->applyCasts($model, $draft);
        $this->applyAccessRules($model, $draft);
        $this->applyReadOnlyColumns($model, $draft);
        $this->applyRelations($model, $draft);
    }

    private function applyCasts(Model $model, EntityDraft $draft): void
    {
        foreach ($model->getCasts() as $name => $cast) {
            if (!$draft->hasField($name)) {
                continue;
            }

            $type = $this->casts->map($cast);
            if ($type === null) {
                continue;
            }

            $field = $draft->fields[$name];
            $field->type = $type;

            if ($type === FieldType::Enum) {
                $field->options = $this->casts->enumOptions($cast);
            }
        }
    }

    private function applyAccessRules(Model $model, EntityDraft $draft): void
    {
        $hidden = array_flip($model->getHidden());
        $visible = $model->getVisible();
        $visible = $visible === [] ? null : array_flip($visible);

        foreach ($draft->fields as $name => $field) {
            $field->hidden = isset($hidden[$name]) || ($visible !== null && !isset($visible[$name]));
            $field->massAssignable = $model->isFillable($name);
        }
    }

    private function applyReadOnlyColumns(Model $model, EntityDraft $draft): void
    {
        $columns = [];
        $detailOnly = [];

        if ($model->usesTimestamps()) {
            $columns[] = $model->getCreatedAtColumn();
            $columns[] = $detailOnly[] = $model->getUpdatedAtColumn();
        }

        if (method_exists($model, 'getDeletedAtColumn')) {
            $columns[] = $detailOnly[] = $model->getDeletedAtColumn();
        }

        foreach ($columns as $column) {
            if (is_string($column) && $draft->hasField($column)) {
                $draft->fields[$column]->readOnly = true;
            }
        }

        // updated_at/deleted_at poluem a listagem: ficam só no detalhe (sobrescrevível).
        foreach ($detailOnly as $column) {
            if (is_string($column) && $draft->hasField($column)) {
                $draft->fields[$column]->visibility = [Visibility::Detail];
            }
        }
    }

    private function applyRelations(Model $model, EntityDraft $draft): void
    {
        foreach ($this->relations->inspect($model) as $found) {
            $relation = $draft->relation($found['name']);
            $relation->type = $found['type'];
            $relation->target = $found['target'];
            $relation->foreignKey = $found['foreignKey'];
        }
    }
}
