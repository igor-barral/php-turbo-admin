<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Registry;

use PhpTurbo\Admin\Core\Metadata\SchemaResolver;

/** Resolve um conjunto de classes em um registro (usado pelo comando de cache e em dev). */
final readonly class SchemaCompiler
{
    public function __construct(private SchemaResolver $resolver)
    {
    }

    /** @param iterable<class-string> $classes */
    public function compile(iterable $classes): SchemaRegistry
    {
        $schemas = [];
        foreach ($classes as $class) {
            $schemas[] = $this->resolver->resolve($class);
        }

        return new SchemaRegistry($schemas);
    }
}
