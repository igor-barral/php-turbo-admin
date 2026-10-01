<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Support;

/**
 * Transformações mínimas de texto. Adapters podem sobrescrever os resultados
 * (ex.: pluralização do Laravel); aqui ficam apenas os padrões neutros.
 *
 * @internal
 */
final class Text
{
    public static function classBasename(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    /** "BlogPost" → "blog_post", "createdAt" → "created_at" */
    public static function snake(string $value): string
    {
        $value = preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '_', $value) ?? $value;
        $value = preg_replace('/[\s\-]+/', '_', $value) ?? $value;

        return strtolower($value);
    }

    /** "author_id" → "Author id", "BlogPost" → "Blog post" */
    public static function humanize(string $value): string
    {
        $words = trim(str_replace('_', ' ', self::snake($value)));

        return ucfirst($words);
    }
}
