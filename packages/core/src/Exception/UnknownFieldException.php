<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Exception;

final class UnknownFieldException extends \InvalidArgumentException implements AdminException
{
    /** @param list<string> $known */
    public static function on(string $class, string $field, array $known, ?string $hint = null): self
    {
        $message = sprintf(
            'Campo "%s" não existe em %s. Campos conhecidos: %s.',
            $field,
            $class,
            $known === [] ? '(nenhum)' : implode(', ', $known),
        );

        return new self($hint === null ? $message : $message . ' ' . $hint);
    }
}
