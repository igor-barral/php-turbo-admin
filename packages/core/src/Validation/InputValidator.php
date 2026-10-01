<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Validation;

use PhpTurbo\Admin\Core\Http\HttpError;
use PhpTurbo\Admin\Core\Schema\EntitySchema;

/**
 * Validação estrutural de escrita: só campos graváveis, obrigatórios presentes na criação,
 * tipos corretos. Devolve os dados normalizados prontos para o DataSource.
 */
final readonly class InputValidator
{
    public function __construct(private ValueCoercer $coercer = new ValueCoercer())
    {
    }

    /**
     * Valida tudo e devolve dados válidos + erros, para que o kernel possa juntar os erros
     * das regras do framework numa única resposta 422.
     *
     * @param array<array-key, mixed> $input
     */
    public function check(EntitySchema $entity, array $input, bool $creating): ValidationResult
    {
        $data = [];
        $errors = [];

        foreach ($input as $name => $value) {
            $name = (string) $name;
            $field = $entity->fields[$name] ?? null;

            // Desconhecido ou não gravável é erro explícito: nunca ignorado em silêncio.
            if ($field === null || !$field->writable) {
                $errors[$name][] = 'Campo não pode ser alterado.';
                continue;
            }

            try {
                $data[$name] = $this->coercer->coerce($field, $value);
            } catch (InvalidValue $exception) {
                $errors[$name][] = $exception->getMessage();
            }
        }

        if ($creating) {
            foreach ($entity->fields as $name => $field) {
                if ($field->required && !array_key_exists($name, $input)) {
                    $errors[$name][] = 'Campo obrigatório.';
                }
            }
        }

        return new ValidationResult($data, $errors);
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @return array<string, mixed>
     *
     * @throws HttpError 422 com mensagens por campo
     */
    public function validate(EntitySchema $entity, array $input, bool $creating): array
    {
        $result = $this->check($entity, $input, $creating);

        if ($result->errors !== []) {
            throw HttpError::validation($result->errors);
        }

        return $result->data;
    }
}
