<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use PhpTurbo\Admin\Laravel\AdminServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected string $cachePath;

    protected function getPackageProviders($app): array
    {
        return [AdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->cachePath = sys_get_temp_dir() . '/turbo-admin-tests/' . bin2hex(random_bytes(6)) . '/schemas.php';

        $config = $app->make('config');
        $config->set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
        $config->set('turbo-admin.discovery.paths', [
            'PhpTurbo\\Admin\\Laravel\\Tests\\Fixtures\\Models\\' => __DIR__ . '/Fixtures/Models',
        ]);
        $config->set('turbo-admin.cache.path', $this->cachePath);

        $this->configureDatabase($config);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Fixtures/migrations');
    }

    protected function tearDown(): void
    {
        if (is_file($this->cachePath)) {
            unlink($this->cachePath);
            rmdir(dirname($this->cachePath));
        }

        parent::tearDown();
    }

    public static function driver(): string
    {
        return (string) (getenv('TEST_DB') ?: 'sqlite');
    }

    /**
     * Mesmo conjunto de testes contra SQLite, MySQL e PostgreSQL (serviços do Sail).
     *
     * @param \Illuminate\Contracts\Config\Repository $config
     */
    private function configureDatabase($config): void
    {
        $driver = self::driver();

        if ($driver === 'sqlite') {
            $config->set('database.default', 'testing');
            $config->set('database.connections.testing.foreign_key_constraints', true);

            return;
        }

        $defaults = [
            'mysql' => ['host' => 'mysql', 'port' => 3306],
            'pgsql' => ['host' => 'pgsql', 'port' => 5432],
        ][$driver] ?? throw new \InvalidArgumentException("TEST_DB inválido: {$driver}");

        $config->set('database.default', $driver);
        $config->set("database.connections.{$driver}", array_merge($config->get("database.connections.{$driver}", []), [
            'driver' => $driver,
            'host' => getenv('DB_HOST') ?: $defaults['host'],
            'port' => getenv('DB_PORT') ?: $defaults['port'],
            'database' => getenv('DB_DATABASE') ?: 'testing',
            'username' => getenv('DB_USERNAME') ?: 'sail',
            'password' => getenv('DB_PASSWORD') ?: 'password',
        ]));
    }
}
