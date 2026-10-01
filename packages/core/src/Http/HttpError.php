<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Http;

use PhpTurbo\Admin\Core\Exception\AdminException;

/**
 * Erro que vira resposta Problem Details (RFC 9457). Mensagens são seguras para o cliente:
 * nunca incluem SQL, nomes de classe ou stack trace.
 */
final class HttpError extends \RuntimeException implements AdminException
{
    /** @param array<string, list<string>> $errors */
    private function __construct(
        public readonly int $status,
        public readonly string $type,
        string $title,
        public readonly ?string $detail = null,
        public readonly array $errors = [],
    ) {
        parent::__construct($title, $status);
    }

    public static function badRequest(string $detail): self
    {
        return new self(400, 'bad_request', 'Requisição inválida', $detail);
    }

    public static function forbidden(): self
    {
        return new self(403, 'forbidden', 'Ação não permitida');
    }

    /** Também usado para entidades que o usuário não pode ver: não revela que existem. */
    public static function notFound(): self
    {
        return new self(404, 'not_found', 'Recurso não encontrado');
    }

    public static function methodNotAllowed(): self
    {
        return new self(405, 'method_not_allowed', 'Método não permitido');
    }

    public static function conflict(string $detail): self
    {
        return new self(409, 'conflict', 'Conflito', $detail);
    }

    /** @param array<string, list<string>> $errors */
    public static function validation(array $errors): self
    {
        return new self(422, 'validation', 'Dados inválidos', null, $errors);
    }

    public function title(): string
    {
        return $this->getMessage();
    }

    /** @return array<string, mixed> */
    public function toProblem(): array
    {
        $problem = ['type' => $this->type, 'title' => $this->title(), 'status' => $this->status];

        if ($this->detail !== null) {
            $problem['detail'] = $this->detail;
        }

        if ($this->errors !== []) {
            $problem['errors'] = $this->errors;
        }

        return $problem;
    }
}
