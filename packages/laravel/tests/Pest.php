<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Registry\SchemaRegistry;
use PhpTurbo\Admin\Core\Schema\EntitySchema;
use PhpTurbo\Admin\Laravel\Tests\TestCase;

pest()->extend(\PhpTurbo\Admin\Laravel\Tests\Feature\Api\ApiTestCase::class)->in('Feature/Api');
pest()->extend(TestCase::class)->in('Feature/Metadata');

/** Schema resolvido pelo container, como o app real faria. */
function entity(string $key): EntitySchema
{
    return app(SchemaRegistry::class)->get($key);
}

function driver(): string
{
    return TestCase::driver();
}
