<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Console;

use Illuminate\Console\Command;
use PhpTurbo\Admin\Core\Cache\PhpFileSchemaCache;

final class ClearCommand extends Command
{
    protected $signature = 'turbo-admin:clear';

    protected $description = 'Remove o cache de schemas do admin';

    public function handle(PhpFileSchemaCache $cache): int
    {
        $cache->clear();

        $this->components->info('Cache de schemas removido.');

        return self::SUCCESS;
    }
}
