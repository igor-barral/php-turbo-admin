<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PhpTurbo\Admin\Core\Auth\Authorizer;
use PhpTurbo\Admin\Core\Data\DataSource;
use PhpTurbo\Admin\Core\Http\ApiKernel;
use PhpTurbo\Admin\Core\Query\ListQueryParser;
use PhpTurbo\Admin\Core\Validation\RuleValidator;
use PhpTurbo\Admin\Laravel\Auth\GateAuthorizer;
use PhpTurbo\Admin\Laravel\Data\EloquentDataSource;
use PhpTurbo\Admin\Laravel\Http\ApiController;
use PhpTurbo\Admin\Laravel\Http\Middleware\AuthorizeAdmin;
use PhpTurbo\Admin\Laravel\Http\UiController;
use PhpTurbo\Admin\Laravel\Validation\LaravelRuleValidator;
use PhpTurbo\Admin\Core\Cache\PhpFileSchemaCache;
use PhpTurbo\Admin\Core\Discovery\ModelDiscovery;
use PhpTurbo\Admin\Core\Metadata\ConfigureAdminProvider;
use PhpTurbo\Admin\Core\Metadata\MetadataProvider;
use PhpTurbo\Admin\Core\Metadata\SchemaResolver;
use PhpTurbo\Admin\Core\Metadata\SecurityPolicy;
use PhpTurbo\Admin\Core\Registry\SchemaCompiler;
use PhpTurbo\Admin\Core\Registry\SchemaRegistry;
use PhpTurbo\Admin\Laravel\Console\CacheCommand;
use PhpTurbo\Admin\Laravel\Console\ClearCommand;
use PhpTurbo\Admin\Laravel\Console\SchemaCommand;
use PhpTurbo\Admin\Laravel\Discovery\DirectoryModelDiscovery;
use PhpTurbo\Admin\Laravel\Metadata\EloquentDatabaseProvider;
use PhpTurbo\Admin\Laravel\Metadata\EloquentModelProvider;

final class AdminServiceProvider extends ServiceProvider
{
    /** Tag para registrar MetadataProviders adicionais a partir do app ou de outros pacotes. */
    public const METADATA_PROVIDERS_TAG = 'turbo-admin.metadata-providers';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/turbo-admin.php', 'turbo-admin');

        $this->app->singleton(SecurityPolicy::class, static fn (Application $app): SecurityPolicy => new SecurityPolicy(
            self::config($app, 'turbo-admin.security.sensitive_fields', SecurityPolicy::DEFAULT_SENSITIVE_PATTERNS),
        ));

        $this->app->singleton(ModelDiscovery::class, static fn (Application $app): ModelDiscovery => new DirectoryModelDiscovery(
            self::config($app, 'turbo-admin.discovery.paths', []),
            self::config($app, 'turbo-admin.discovery.classes', []),
        ));

        $this->app->singleton(SchemaResolver::class, static function (Application $app): SchemaResolver {
            /** @var iterable<MetadataProvider> $extra */
            $extra = $app->tagged(self::METADATA_PROVIDERS_TAG);

            return new SchemaResolver(
                [new EloquentDatabaseProvider(), new EloquentModelProvider(), new ConfigureAdminProvider(), ...$extra],
                $app->make(SecurityPolicy::class),
            );
        });

        $this->app->singleton(PhpFileSchemaCache::class, static fn (Application $app): PhpFileSchemaCache => new PhpFileSchemaCache(
            self::config($app, 'turbo-admin.cache.path', $app->bootstrapPath('cache/turbo-admin.php')),
        ));

        $this->app->singleton(SchemaCompiler::class);

        $this->registerApi();

        // Com cache: só leitura de array (OPcache). Sem cache (dev): compila sob demanda.
        $this->app->singleton(SchemaRegistry::class, static fn (Application $app): SchemaRegistry => $app->make(PhpFileSchemaCache::class)->read()
            ?? $app->make(SchemaCompiler::class)->compile($app->make(ModelDiscovery::class)->discover()));
    }

    private function registerApi(): void
    {
        $this->app->singleton(DataSource::class, EloquentDataSource::class);
        $this->app->singleton(Authorizer::class, static fn (Application $app): Authorizer => new GateAuthorizer($app->make(Gate::class)));
        $this->app->singleton(RuleValidator::class, static fn (Application $app): RuleValidator => new LaravelRuleValidator($app->make(ValidationFactory::class)));

        // Sem estado por requisição: seguro como singleton também no Octane.
        $this->app->singleton(ApiKernel::class, static fn (Application $app): ApiKernel => new ApiKernel(
            $app->make(SchemaRegistry::class),
            $app->make(DataSource::class),
            $app->make(Authorizer::class),
            $app->make(RuleValidator::class),
            new ListQueryParser(
                self::config($app, 'turbo-admin.pagination.default', 25),
                self::config($app, 'turbo-admin.pagination.max', 100),
            ),
        ));
    }

    private function defineDefaultGate(): void
    {
        $gate = $this->app->make(Gate::class);

        // Como o Horizon/Telescope: sem definição do app, o painel só abre em ambiente local.
        if (!$gate->has('viewAdmin')) {
            $gate->define('viewAdmin', fn (): bool => $this->app->environment('local'));
        }
    }

    private function registerRoutes(): void
    {
        if ($this->app->make('config')->get('turbo-admin.routes', true) === false || $this->app->routesAreCached()) {
            return;
        }

        /** @var list<string> $middleware */
        $middleware = self::config($this->app, 'turbo-admin.middleware', ['web']);

        Route::prefix(self::config($this->app, 'turbo-admin.prefix', 'admin'))->name('turbo-admin.')->group(static function () use ($middleware): void {
            // Assets são públicos (código da SPA, sem dados) e cacheáveis.
            Route::get('assets/{path}', [UiController::class, 'asset'])->where('path', '.*')->name('asset');

            Route::middleware([...$middleware, AuthorizeAdmin::class])->group(static function (): void {
                Route::get('/', [UiController::class, 'index'])->name('index');
                Route::get('config.json', [UiController::class, 'config'])->name('config');
                Route::match(['GET', 'POST', 'PATCH', 'DELETE'], 'api/v1/{path?}', ApiController::class)
                    ->where('path', '.*')
                    ->name('api');
            });
        });
    }

    public function boot(): void
    {
        $this->defineDefaultGate();
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/turbo-admin.php' => $this->app->configPath('turbo-admin.php'),
            ], 'turbo-admin-config');

            $this->commands([CacheCommand::class, ClearCommand::class, SchemaCommand::class]);
        }
    }

    /**
     * @template T
     *
     * @param T $default
     *
     * @return T
     */
    private static function config(Application $app, string $key, mixed $default): mixed
    {
        /** @var T */
        return $app->make('config')->get($key, $default);
    }
}
