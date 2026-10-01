<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Console;

use Illuminate\Console\Command;
use PhpTurbo\Admin\Core\Cache\PhpFileSchemaCache;
use PhpTurbo\Admin\Core\Discovery\ModelDiscovery;
use PhpTurbo\Admin\Core\Registry\SchemaCompiler;

final class CacheCommand extends Command
{
    protected $signature = 'turbo-admin:cache';

    protected $description = 'Compila os schemas do admin em um arquivo PHP (sem introspecção em runtime)';

    public function handle(ModelDiscovery $discovery, SchemaCompiler $compiler, PhpFileSchemaCache $cache): int
    {
        $registry = $compiler->compile($discovery->discover());
        $cache->write($registry);

        $this->components->info(sprintf('%d entidade(s) compilada(s) em %s.', count($registry->all()), $cache->path()));

        return self::SUCCESS;
    }
}
