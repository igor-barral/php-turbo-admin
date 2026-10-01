<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Query;

/** Igualdade (ou IS NULL quando value é null) em um campo filtrável. */
final readonly class Filter
{
    public function __construct(public string $field, public mixed $value)
    {
    }
}
