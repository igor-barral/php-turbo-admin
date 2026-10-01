<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Query;

use PhpTurbo\Admin\Core\Http\HttpError;
use PhpTurbo\Admin\Core\Schema\EntitySchema;
use PhpTurbo\Admin\Core\Schema\Sort;
use PhpTurbo\Admin\Core\Schema\SortDirection;
use PhpTurbo\Admin\Core\Validation\InvalidValue;
use PhpTurbo\Admin\Core\Validation\ValueCoercer;

/**
 * Converte a query string em ListQuery, validando tudo contra o schema.
 * Nomes de campo da requisição nunca chegam ao banco sem passar por aqui.
 *
 * Parâmetros: sort=-campo, filter[campo]=valor, q=busca, limit=25, cursor=..., page=2
 */
final readonly class ListQueryParser
{
    public const MAX_SEARCH_LENGTH = 100;

    public function __construct(
        private int $defaultLimit = 25,
        private int $maxLimit = 100,
        private ValueCoercer $coercer = new ValueCoercer(),
    ) {
    }

    /** @param array<array-key, mixed> $params */
    public function parse(EntitySchema $entity, array $params): ListQuery
    {
        $sort = $this->sort($entity, $params['sort'] ?? null);
        $keyset = $this->supportsKeyset($entity, $sort);
        $cursor = $this->cursor($params['cursor'] ?? null);

        if ($cursor !== null && !$keyset) {
            throw HttpError::badRequest('Esta ordenação usa paginação por página, não por cursor.');
        }

        $search = $this->search($params['q'] ?? null);
        $searchFields = array_keys(array_filter($entity->fields, static fn ($f): bool => $f->searchable));

        if ($search !== null && $searchFields === []) {
            throw HttpError::badRequest('Esta entidade não possui campos pesquisáveis.');
        }

        return new ListQuery(
            sort: $sort,
            primaryKey: $entity->primaryKey,
            limit: $this->limit($params['limit'] ?? null),
            keyset: $keyset,
            cursor: $cursor,
            page: $keyset ? 1 : $this->page($params['page'] ?? null),
            filters: $this->filters($entity, $params['filter'] ?? []),
            search: $search,
            searchFields: $search === null ? [] : $searchFields,
        );
    }

    /**
     * Keyset exige ordenação total e sem NULLs: chave primária ou coluna não anulável
     * (com a PK como desempate). Caso contrário, cai para paginação por página.
     */
    private function supportsKeyset(EntitySchema $entity, Sort $sort): bool
    {
        return $sort->field === $entity->primaryKey || !$entity->field($sort->field)->nullable;
    }

    private function sort(EntitySchema $entity, mixed $value): Sort
    {
        if ($value === null || $value === '') {
            return $entity->sort ?? new Sort($entity->primaryKey, SortDirection::Desc);
        }

        if (!is_string($value)) {
            throw HttpError::badRequest('Parâmetro sort inválido.');
        }

        $direction = str_starts_with($value, '-') ? SortDirection::Desc : SortDirection::Asc;
        $name = ltrim($value, '-');
        $field = $entity->fields[$name] ?? null;

        if ($field === null || !$field->sortable) {
            throw HttpError::badRequest(sprintf('Não é possível ordenar por "%s".', $name));
        }

        return new Sort($name, $direction);
    }

    private function cursor(mixed $value): ?Cursor
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw HttpError::badRequest('Cursor inválido.');
        }

        return Cursor::decode($value);
    }

    private function search(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw HttpError::badRequest('Parâmetro q inválido.');
        }

        $value = trim($value);

        if (mb_strlen($value) > self::MAX_SEARCH_LENGTH) {
            throw HttpError::badRequest(sprintf('A busca aceita no máximo %d caracteres.', self::MAX_SEARCH_LENGTH));
        }

        return $value === '' ? null : $value;
    }

    private function limit(mixed $value): int
    {
        if ($value === null || $value === '') {
            return $this->defaultLimit;
        }

        $limit = $this->positiveInt($value, 'limit');

        if ($limit > $this->maxLimit) {
            throw HttpError::badRequest(sprintf('O limite máximo é %d.', $this->maxLimit));
        }

        return $limit;
    }

    private function page(mixed $value): int
    {
        return $value === null || $value === '' ? 1 : $this->positiveInt($value, 'page');
    }

    private function positiveInt(mixed $value, string $name): int
    {
        if (is_string($value) && preg_match('/^[1-9]\d{0,8}$/', $value) === 1) {
            return (int) $value;
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        throw HttpError::badRequest(sprintf('Parâmetro %s deve ser um inteiro positivo.', $name));
    }

    /** @return list<Filter> */
    private function filters(EntitySchema $entity, mixed $filters): array
    {
        if (!is_array($filters)) {
            throw HttpError::badRequest('Parâmetro filter inválido.');
        }

        $parsed = [];
        $errors = [];

        foreach ($filters as $name => $value) {
            $name = (string) $name;
            $field = $entity->fields[$name] ?? null;

            if ($field === null || !$field->filterable) {
                throw HttpError::badRequest(sprintf('Não é possível filtrar por "%s".', $name));
            }

            // Na query string, "null" representa IS NULL.
            $value = $value === 'null' && $field->nullable ? null : $value;

            try {
                $parsed[] = new Filter($name, $this->coercer->coerce($field, $value));
            } catch (InvalidValue $exception) {
                $errors[$name][] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            throw HttpError::validation($errors);
        }

        return $parsed;
    }
}
