<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Validation;

use PhpTurbo\Admin\Core\Data\Record;
use PhpTurbo\Admin\Core\Schema\EntitySchema;

/**
 * Porta para regras de validação do framework (as strings em FieldSchema::$rules são
 * interpretadas pelo adapter). Roda depois da validação estrutural do core.
 */
interface RuleValidator
{
    /**
     * @param array<string, mixed> $data   dados já normalizados
     * @param Record|null          $record null na criação
     *
     * @return array<string, list<string>> mensagens por campo (vazio = válido)
     */
    public function validate(EntitySchema $entity, array $data, ?Record $record): array;
}
