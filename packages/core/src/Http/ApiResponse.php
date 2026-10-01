<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Http;

final readonly class ApiResponse
{
    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string>     $headers
     */
    public function __construct(
        public int $status,
        public ?array $body,
        public array $headers = ['Content-Type' => 'application/json'],
    ) {
    }

    /** @param array<string, mixed> $body */
    public static function json(array $body, int $status = 200): self
    {
        return new self($status, $body);
    }

    public static function noContent(): self
    {
        return new self(204, null, []);
    }

    public static function problem(HttpError $error): self
    {
        return new self($error->status, $error->toProblem(), ['Content-Type' => 'application/problem+json']);
    }
}
