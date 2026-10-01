<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Console;

use Illuminate\Console\Command;
use PhpTurbo\Admin\Core\Discovery\ModelDiscovery;
use PhpTurbo\Admin\Core\Exception\EntityNotFoundException;
use PhpTurbo\Admin\Core\Registry\SchemaCompiler;
use PhpTurbo\Admin\Core\Schema\EntitySchema;

final class SchemaCommand extends Command
{
    protected $signature = 'turbo-admin:schema {entity? : Chave da entidade (ex.: posts)}';

    protected $description = 'Lista as entidades do admin ou mostra o schema resolvido de uma delas';

    public function handle(ModelDiscovery $discovery, SchemaCompiler $compiler): int
    {
        // Sempre resolve do zero: o objetivo é depurar a configuração atual, não o cache.
        $registry = $compiler->compile($discovery->discover());
        $key = $this->argument('entity');

        if (!is_string($key)) {
            $this->table(
                ['Chave', 'Classe', 'Campos', 'Relações'],
                array_map(static fn (EntitySchema $schema): array => [
                    $schema->key,
                    $schema->class,
                    count($schema->fields),
                    count($schema->relations),
                ], array_values($registry->all())),
            );

            return self::SUCCESS;
        }

        try {
            $schema = $registry->get($key);
        } catch (EntityNotFoundException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line((string) json_encode($schema->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
