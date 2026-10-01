<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Builder;

use PhpTurbo\Admin\Core\Schema\FieldType;
use PhpTurbo\Admin\Core\Schema\Visibility;

/**
 * Estado mutável de um campo durante a resolução das camadas de metadados.
 * `null` significa "ainda não decidido" — a camada de convenções preenche o padrão.
 *
 * @internal Use AdminSchema em configureAdmin(); drafts são para MetadataProviders.
 */
final class FieldDraft
{
    public ?FieldType $type = null;
    public ?string $label = null;
    public bool $nullable = false;

    /** Fatos vindos do banco/ORM */
    public bool $primary = false;
    public bool $indexed = false;
    public bool $hidden = false;
    public bool $massAssignable = false;
    public bool $readOnly = false;
    public bool $hasDefault = false;

    /** Decisões do usuário */
    public bool $excluded = false;
    public bool $sensitiveAllowed = false;
    public bool $writeOnly = false;

    public ?bool $searchable = null;
    public ?bool $sortable = null;
    public ?bool $filterable = null;
    public ?bool $required = null;

    /** @var list<Visibility>|null */
    public ?array $visibility = null;

    public ?string $widget = null;

    /** @var list<string> */
    public array $rules = [];

    /** @var list<array{value: string|int, label: string}> */
    public array $options = [];

    public function __construct(public readonly string $name)
    {
    }
}
