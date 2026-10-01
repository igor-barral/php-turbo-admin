<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Exception;

final class EntityNotFoundException extends \OutOfBoundsException implements AdminException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Nenhuma entidade registrada com a chave "%s".', $key));
    }

    public static function forClass(string $class): self
    {
        return new self(sprintf('A classe %s não está registrada no admin.', $class));
    }
}
