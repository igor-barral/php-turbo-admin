<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Validation;

final readonly class ValidationResult
{
    /**
     * @param array<string, mixed>        $data   valores válidos e normalizados
     * @param array<string, list<string>> $errors mensagens por campo
     */
    public function __construct(public array $data, public array $errors)
    {
    }
}
