<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Builder;

use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\Visibility;

final class FieldConfigurator extends Configurator
{
    public function __construct(AdminSchema $schema, private readonly FieldDraft $field)
    {
        parent::__construct($schema);
    }

    public function label(string $label): self
    {
        $this->field->label = $label;

        return $this;
    }

    public function type(FieldType $type): self
    {
        $this->field->type = $type;

        return $this;
    }

    public function widget(string $widget): self
    {
        $this->field->widget = $widget;

        return $this;
    }

    public function searchable(bool $searchable = true): self
    {
        $this->field->searchable = $searchable;

        return $this;
    }

    public function sortable(bool $sortable = true): self
    {
        $this->field->sortable = $sortable;

        return $this;
    }

    public function filterable(bool $filterable = true): self
    {
        $this->field->filterable = $filterable;

        return $this;
    }

    public function onlyOn(Visibility ...$visibility): self
    {
        $this->field->visibility = array_values($visibility);

        return $this;
    }

    public function hideOn(Visibility ...$hidden): self
    {
        $current = $this->field->visibility ?? Visibility::all();
        $this->field->visibility = array_values(array_filter(
            $current,
            static fn (Visibility $v): bool => !in_array($v, $hidden, true),
        ));

        return $this;
    }

    public function hideOnIndex(): self
    {
        return $this->hideOn(Visibility::Index);
    }

    public function hideOnDetail(): self
    {
        return $this->hideOn(Visibility::Detail);
    }

    public function hideOnForms(): self
    {
        return $this->hideOn(...Visibility::forms());
    }

    /** Sobrescreve a obrigatoriedade inferida (não nulo e sem default no banco). */
    public function required(bool $required = true): self
    {
        $this->field->required = $required;

        return $this;
    }

    public function readOnly(bool $readOnly = true): self
    {
        $this->field->readOnly = $readOnly;

        return $this;
    }

    /** @param list<string> $rules */
    public function rules(array $rules): self
    {
        $this->field->rules = $rules;

        return $this;
    }

    /** @param array<string|int, string> $options valor => rótulo */
    public function options(array $options): self
    {
        $this->field->options = [];
        foreach ($options as $value => $label) {
            $this->field->options[] = ['value' => $value, 'label' => $label];
        }

        return $this;
    }

    /**
     * Libera leitura de um campo considerado sensível (nome como password/token/secret).
     * Não libera campos em $hidden do model: esses nunca são expostos.
     */
    public function allowSensitive(): self
    {
        $this->field->sensitiveAllowed = true;

        return $this;
    }

    /** Campo gravável que nunca é devolvido pela API (ex.: definir senha). */
    public function writeOnly(): self
    {
        $this->field->writeOnly = true;

        return $this;
    }
}
