<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Http;

/**
 * Requisição mínima, independente de PSR-7/HttpFoundation: cada adapter converte a sua
 * em poucas linhas, sem dependências nem custo de conversão de objetos de stream.
 */
final readonly class ApiRequest
{
    /**
     * @param string                  $path  relativo à base da API (ex.: "entities/posts/1")
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $body  corpo JSON já decodificado
     * @param mixed                   $actor usuário autenticado (opaco para o core)
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $body = [],
        public mixed $actor = null,
    ) {
    }
}
