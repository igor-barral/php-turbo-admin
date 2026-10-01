<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Registry;

use PhpTurbo\Admin\Core\Exception\DuplicateEntityKeyException;
use PhpTurbo\Admin\Core\Exception\EntityNotFoundException;
use PhpTurbo\Admin\Core\Schema\EntitySchema;

/**
 * @phpstan-import-type EntityArray from EntitySchema
 *
 * Conjunto imutável de schemas resolvidos, indexado por chave e por classe.
 */
final readonly class SchemaRegistry
{
    /** Versão do formato serializado; mudar invalida caches antigos. */
    public const FORMAT_VERSION = 1;

    /** @var array<string, EntitySchema> */
    private array $byKey;

    /** @var array<string, string> */
    private array $keyByClass;

    /** @param iterable<EntitySchema> $schemas */
    public function __construct(iterable $schemas = [])
    {
        $byKey = [];
        $keyByClass = [];

        foreach ($schemas as $schema) {
            if (isset($byKey[$schema->key])) {
                throw DuplicateEntityKeyException::between($schema->key, $byKey[$schema->key]->class, $schema->class);
            }

            $byKey[$schema->key] = $schema;
            $keyByClass[$schema->class] = $schema->key;
        }

        $this->byKey = $byKey;
        $this->keyByClass = $keyByClass;
    }

    public function has(string $key): bool
    {
        return isset($this->byKey[$key]);
    }

    public function get(string $key): EntitySchema
    {
        return $this->byKey[$key] ?? throw EntityNotFoundException::forKey($key);
    }

    public function hasClass(string $class): bool
    {
        return isset($this->keyByClass[$class]);
    }

    public function forClass(string $class): EntitySchema
    {
        $key = $this->keyByClass[$class] ?? throw EntityNotFoundException::forClass($class);

        return $this->byKey[$key];
    }

    /** @return array<string, EntitySchema> */
    public function all(): array
    {
        return $this->byKey;
    }

    /** @return array{version: int, entities: list<EntityArray>} */
    public function toArray(): array
    {
        return [
            'version' => self::FORMAT_VERSION,
            'entities' => array_values(array_map(static fn (EntitySchema $s): array => $s->toArray(), $this->byKey)),
        ];
    }

    /** @param array{version: int, entities: list<EntityArray>} $data */
    public static function fromArray(array $data): self
    {
        return new self(array_map(EntitySchema::fromArray(...), $data['entities']));
    }
}
