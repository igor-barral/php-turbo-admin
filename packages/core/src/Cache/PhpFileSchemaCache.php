<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Cache;

use PhpTurbo\Admin\Core\Exception\InvalidSchemaException;
use PhpTurbo\Admin\Core\Registry\SchemaRegistry;
use PhpTurbo\Admin\Core\Schema\EntitySchema;

/**
 * @phpstan-import-type EntityArray from EntitySchema
 *
 * Persiste o registro como um arquivo PHP que retorna um array puro: carregado pelo
 * OPcache, sem desserialização nem introspecção em tempo de requisição.
 */
final readonly class PhpFileSchemaCache
{
    public function __construct(private string $path)
    {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function write(SchemaRegistry $registry): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !self::attempt(static fn (): bool => mkdir($directory, 0775, true)) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Não foi possível criar o diretório %s.', $directory));
        }

        $contents = "<?php\n\n// Gerado pelo php-turbo-admin. Não edite.\n\nreturn "
            . var_export($registry->toArray(), true) . ";\n";

        // Escrita atômica: requisições concorrentes nunca leem um arquivo pela metade.
        $temporary = $this->path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $path = $this->path;
        $written = self::attempt(static fn (): bool => file_put_contents($temporary, $contents, LOCK_EX) !== false
            && rename($temporary, $path));

        if (!$written) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw new \RuntimeException(sprintf('Não foi possível gravar o cache em %s.', $this->path));
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->path, true);
        }
    }

    public function read(): ?SchemaRegistry
    {
        if (!$this->exists()) {
            return null;
        }

        $data = require $this->path;

        if (!is_array($data) || ($data['version'] ?? null) !== SchemaRegistry::FORMAT_VERSION || !is_array($data['entities'] ?? null)) {
            throw InvalidSchemaException::unreadableCache($this->path, 'versão de formato incompatível');
        }

        /** @var array{version: int, entities: list<EntityArray>} $data */
        return SchemaRegistry::fromArray($data);
    }

    public function clear(): void
    {
        if ($this->exists()) {
            unlink($this->path);
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->path, true);
        }
    }

    /**
     * Executa uma operação de sistema de arquivos convertendo warnings do PHP em `false`,
     * sem o operador @ (que esconde erros também de handlers globais).
     *
     * @param callable(): bool $operation
     */
    private static function attempt(callable $operation): bool
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }
}
