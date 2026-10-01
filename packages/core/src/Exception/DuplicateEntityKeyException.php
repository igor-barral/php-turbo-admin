<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Exception;

final class DuplicateEntityKeyException extends \LogicException implements AdminException
{
    public static function between(string $key, string $first, string $second): self
    {
        return new self(sprintf(
            'A chave "%s" é usada por %s e %s. Defina uma chave única com $schema->key(...).',
            $key,
            $first,
            $second,
        ));
    }
}
