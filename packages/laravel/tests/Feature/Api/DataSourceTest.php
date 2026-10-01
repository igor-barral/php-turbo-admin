<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PhpTurbo\Admin\Core\Data\QueryPlan;
use PhpTurbo\Admin\Core\Data\Record;
use PhpTurbo\Admin\Core\Metadata\SchemaResolver;
use PhpTurbo\Admin\Laravel\Data\EloquentDataSource;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\Setting;

it('uses page pagination when sorting by a nullable column', function (): void {
    foreach (range(1, 5) as $i) {
        $this->makePost($this->author, "P{$i}", ['published_at' => $i === 3 ? null : "2026-02-0{$i} 08:00:00"]);
    }

    $response = $this->actingAs($this->author)->getJson($this->api('entities/posts?sort=published_at&limit=2&page=2'))->assertOk();

    expect($response->json('meta'))->toMatchArray(['pagination' => 'page', 'page' => 2, 'hasMore' => true, 'nextCursor' => null])
        ->and($response->json('data'))->toHaveCount(2);
});

it('returns null for values the cast cannot read', function (): void {
    $post = $this->makePost($this->author, 'Enum inválido');
    DB::table('posts')->where('id', $post->id)->update(['status' => 'archived']);

    $this->actingAs($this->author)->getJson($this->api("entities/posts/{$post->id}"))
        ->assertOk()
        ->assertJsonPath('data.attributes.status', null);
});

it('supports string primary keys', function (): void {
    Setting::query()->create(['key' => 'site.name', 'value' => 'Turbo']);
    $schema = app(SchemaResolver::class)->resolve(Setting::class);
    $plan = new QueryPlan(['key', 'value']);

    $record = (new EloquentDataSource())->find($schema, 'site.name', $plan);

    expect($record?->id)->toBe('site.name')
        ->and($record?->attributes)->toBe(['key' => 'site.name', 'value' => 'Turbo'])
        ->and((new EloquentDataSource())->find($schema, 'missing', $plan))->toBeNull();
});

it('refuses records without an eloquent source', function (): void {
    $schema = app(SchemaResolver::class)->resolve(Setting::class);

    (new EloquentDataSource())->update($schema, new Record('x', []), ['value' => 'y']);
})->throws(LogicException::class, 'Registro sem model Eloquent de origem.');

it('rethrows database errors that are not integrity violations', function (): void {
    $guard = new ReflectionMethod(EloquentDataSource::class, 'guard');

    $guard->invoke(new EloquentDataSource(), new Setting(), static function (): void {
        throw new QueryException('testing', 'select 1', [], new PDOException('connection lost', 0));
    });
})->throws(QueryException::class);
