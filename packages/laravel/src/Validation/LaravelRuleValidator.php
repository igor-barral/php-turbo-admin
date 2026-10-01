<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Validation;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use PhpTurbo\Admin\Core\Data\Record;
use PhpTurbo\Admin\Core\Schema\EntitySchema;
use PhpTurbo\Admin\Core\Schema\RelationType;
use PhpTurbo\Admin\Core\Validation\RuleValidator;

/**
 * Aplica as regras declaradas em ->rules([...]) com o Validator do Laravel e acrescenta
 * `exists` para chaves estrangeiras de belongsTo. Em regras string, "{id}" é trocado pela
 * chave do registro em edição (ex.: "unique:posts,slug,{id}").
 */
final readonly class LaravelRuleValidator implements RuleValidator
{
    public function __construct(private Factory $validator)
    {
    }

    public function validate(EntitySchema $entity, array $data, ?Record $record): array
    {
        $rules = [];
        $labels = [];

        foreach (array_keys($data) as $name) {
            $field = $entity->field($name);
            $fieldRules = array_map(
                static fn (string $rule): string => str_replace('{id}', $record === null ? 'NULL' : (string) $record->id, $rule),
                $field->rules,
            );

            foreach ($entity->relations as $relation) {
                if ($relation->type === RelationType::BelongsTo && $relation->foreignKey === $name
                    && $relation->target !== null && is_subclass_of($relation->target, Model::class)) {
                    $related = new ($relation->target)();
                    $fieldRules[] = Rule::exists($relation->target, $related->getKeyName());
                }
            }

            if ($fieldRules !== []) {
                // Sem "nullable", regras como exists falhariam para null em campos anuláveis.
                $rules[$name] = $field->nullable ? ['nullable', ...$fieldRules] : $fieldRules;
                $labels[$name] = $field->label;
            }
        }

        if ($rules === []) {
            return [];
        }

        /** @var array<string, list<string>> */
        return $this->validator->make($data, $rules, [], $labels)->errors()->toArray();
    }
}
