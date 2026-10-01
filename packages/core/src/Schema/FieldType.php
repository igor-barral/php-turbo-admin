<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Schema;

enum FieldType: string
{
    case String = 'string';
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Money = 'money';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';
    case Time = 'time';
    case Enum = 'enum';
    case Json = 'json';
    case Uuid = 'uuid';
    case Email = 'email';
    case Url = 'url';
    case Password = 'password';
    case File = 'file';

    /** Tipos com conteúdo longo, ocultos na listagem por padrão. */
    public function isLong(): bool
    {
        return $this === self::Text || $this === self::Json;
    }

    /** Tipos de baixa cardinalidade, filtráveis por padrão. */
    public function isLowCardinality(): bool
    {
        return $this === self::Boolean || $this === self::Enum;
    }
}
