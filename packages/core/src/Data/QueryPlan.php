<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Data;

/**
 * O que o DataSource deve carregar: só as colunas necessárias para a tela e as relações
 * exibidas (com o campo de título do alvo), evitando SELECT * e N+1.
 */
final readonly class QueryPlan
{
    /**
     * @param list<string>               $columns
     * @param array<string, string|null> $relations nome da relação => campo de título do alvo (null = chave)
     */
    public function __construct(public array $columns, public array $relations = [])
    {
    }
}
