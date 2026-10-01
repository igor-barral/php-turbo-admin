<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Exception;

final class UnknownRelationException extends \InvalidArgumentException implements AdminException
{
    /** @param list<string> $known */
    public static function on(string $class, string $relation, array $known): self
    {
        return new self(sprintf(
            'Relação "%s" não existe em %s. Relações conhecidas: %s. '
            . 'Declare o tipo de retorno do método ou use relation("%s", RelationType::..., Target::class).',
            $relation,
            $class,
            $known === [] ? '(nenhuma)' : implode(', ', $known),
            $relation,
        ));
    }
}
