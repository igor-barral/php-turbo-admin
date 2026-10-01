<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Query;

use PhpTurbo\Admin\Core\Http\HttpError;

/**
 * Posição na paginação keyset: valor da coluna ordenada + chave primária (desempate).
 * Serializado como base64url(JSON) — opaco para o cliente, validado ao decodificar.
 */
final readonly class Cursor
{
    public function __construct(
        public string|int|float|bool|null $value,
        public string|int $id,
    ) {
    }

    public function encode(): string
    {
        $json = json_encode([$this->value, $this->id], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    public static function decode(string $encoded): self
    {
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);

        if (!is_array($data) || count($data) !== 2 || !array_is_list($data)) {
            throw HttpError::badRequest('Cursor inválido.');
        }

        [$value, $id] = $data;

        if (!(is_scalar($value) || $value === null) || !(is_string($id) || is_int($id))) {
            throw HttpError::badRequest('Cursor inválido.');
        }

        return new self($value, $id);
    }
}
