<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Metadata;

use PhpTurbo\Admin\Core\Schema\FieldType;

/**
 * Converte tipos nativos do banco (MySQL/MariaDB, PostgreSQL, SQLite, SQL Server) em tipos semânticos.
 *
 * @internal
 */
final class ColumnTypeMapper
{
    /**
     * @param string $typeName nome do tipo (ex.: "varchar", "int4")
     * @param string $type     declaração completa (ex.: "tinyint(1)", "enum('a','b')")
     */
    public function map(string $typeName, string $type): FieldType
    {
        $typeName = strtolower($typeName);

        // MySQL não tem boolean nativo: Laravel cria tinyint(1).
        if ($typeName === 'tinyint' && str_starts_with(strtolower($type), 'tinyint(1)')) {
            return FieldType::Boolean;
        }

        return match ($typeName) {
            'bigint', 'int', 'integer', 'mediumint', 'smallint', 'tinyint', 'int2', 'int4', 'int8',
            'serial', 'bigserial', 'smallserial', 'year' => FieldType::Integer,
            'decimal', 'numeric', 'float', 'double', 'real', 'float4', 'float8', 'double precision', 'money' => FieldType::Decimal,
            'bool', 'boolean', 'bit' => FieldType::Boolean,
            'date' => FieldType::Date,
            'datetime', 'datetime2', 'datetimeoffset', 'smalldatetime', 'timestamp', 'timestamptz' => FieldType::DateTime,
            'time', 'timetz' => FieldType::Time,
            'text', 'tinytext', 'mediumtext', 'longtext', 'ntext', 'clob' => FieldType::Text,
            'json', 'jsonb' => FieldType::Json,
            'uuid', 'uniqueidentifier' => FieldType::Uuid,
            'enum' => FieldType::Enum,
            default => FieldType::String,
        };
    }

    /**
     * Extrai os valores de "enum('draft','published')" (MySQL).
     *
     * @return list<string>
     */
    public function enumValues(string $type): array
    {
        if (preg_match('/^enum\((.*)\)$/i', $type, $matches) !== 1) {
            return [];
        }

        preg_match_all("/'((?:[^']|'')*)'/", $matches[1], $values);

        return array_map(static fn (string $value): string => str_replace("''", "'", $value), $values[1]);
    }
}
