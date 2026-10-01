<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Exception;

final class InvalidSchemaException extends \LogicException implements AdminException
{
    public static function missingPrimaryKey(string $class): self
    {
        return new self(sprintf('Não foi possível determinar a chave primária de %s.', $class));
    }

    public static function unreadableCache(string $path, string $reason): self
    {
        return new self(sprintf('Cache de schema inválido em %s: %s. Rode o comando de cache novamente.', $path, $reason));
    }
}
