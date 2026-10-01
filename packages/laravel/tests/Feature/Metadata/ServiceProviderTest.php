<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Builder\EntityDraft;
use PhpTurbo\Admin\Core\Metadata\MetadataProvider;
use PhpTurbo\Admin\Core\Metadata\Priority;
use PhpTurbo\Admin\Core\Metadata\SchemaResolver;
use PhpTurbo\Admin\Core\Metadata\SecurityPolicy;
use PhpTurbo\Admin\Laravel\AdminServiceProvider;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\Country;

it('merges the package config', function (): void {
    expect(config('turbo-admin.prefix'))->toBe('admin')
        ->and(config('turbo-admin.security.sensitive_fields'))->toBe(SecurityPolicy::DEFAULT_SENSITIVE_PATTERNS);
});

it('publishes the config file', function (): void {
    $paths = AdminServiceProvider::pathsToPublish(AdminServiceProvider::class, 'turbo-admin-config');

    expect(array_values($paths))->toBe([config_path('turbo-admin.php')])
        ->and(is_file((string) array_key_first($paths)))->toBeTrue();
});

it('uses configured sensitive patterns', function (): void {
    config()->set('turbo-admin.security.sensitive_fields', ['name']);
    $this->app->forgetInstance(SecurityPolicy::class);
    $this->app->forgetInstance(SchemaResolver::class);

    expect(app(SchemaResolver::class)->resolve(Country::class)->hasField('name'))->toBeFalse();
});

it('runs metadata providers registered by tag', function (): void {
    $this->app->instance('test.provider', new class () implements MetadataProvider {
        public function priority(): int
        {
            return Priority::USER + 1;
        }

        public function supports(string $class): bool
        {
            return true;
        }

        public function provide(string $class, EntityDraft $draft): void
        {
            $draft->label = 'Tagged';
        }
    });
    $this->app->tag('test.provider', AdminServiceProvider::METADATA_PROVIDERS_TAG);
    $this->app->forgetInstance(SchemaResolver::class);

    expect(app(SchemaResolver::class)->resolve(Country::class)->label)->toBe('Tagged');
});

it('skips route registration when disabled', function (): void {
    config()->set('turbo-admin.routes', false);
    $before = count(app('router')->getRoutes());

    (new AdminServiceProvider(app()))->boot();

    expect(count(app('router')->getRoutes()))->toBe($before);
});
