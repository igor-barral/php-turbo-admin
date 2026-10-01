<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\Post;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\User;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Policies\PermissiveUserPolicy;

describe('listing', function (): void {
    beforeEach(function (): void {
        foreach (range(1, 7) as $i) {
            $this->makePost($i % 2 === 0 ? $this->admin : $this->author, "Post {$i}", [
                'status' => $i <= 3 ? 'published' : 'draft',
                'published_at' => $i === 2 ? null : "2026-01-0{$i} 12:00:00",
            ]);
        }
    });

    it('paginates with cursors across pages', function (): void {
        $first = $this->actingAs($this->author)->getJson($this->api('entities/posts?limit=3'))->assertOk();
        $second = $this->getJson($this->api('entities/posts?limit=3&cursor=' . $first->json('meta.nextCursor')))->assertOk();
        $third = $this->getJson($this->api('entities/posts?limit=3&cursor=' . $second->json('meta.nextCursor')))->assertOk();

        expect(array_column($first->json('data'), 'title'))->toBe(['Post 7', 'Post 6', 'Post 5'])
            ->and(array_column($second->json('data'), 'title'))->toBe(['Post 4', 'Post 3', 'Post 2'])
            ->and(array_column($third->json('data'), 'title'))->toBe(['Post 1'])
            ->and($third->json('meta'))->toMatchArray(['pagination' => 'cursor', 'hasMore' => false, 'nextCursor' => null]);
    });

    it('paginates with cursors on a non key column with ties', function (): void {
        Post::query()->update(['title' => 'Mesmo título']);

        $first = $this->actingAs($this->author)->getJson($this->api('entities/posts?sort=title&limit=4'))->assertOk();
        $second = $this->getJson($this->api('entities/posts?sort=title&limit=4&cursor=' . $first->json('meta.nextCursor')))->assertOk();

        $ids = [...array_column($first->json('data'), 'id'), ...array_column($second->json('data'), 'id')];

        expect($ids)->toBe(Post::query()->orderBy('id')->pluck('id')->all());
    });

    it('serializes casts, hides sensitive columns and loads the author title', function (): void {
        $record = $this->actingAs($this->author)->getJson($this->api('entities/posts?limit=1&sort=id'))->json('data.0');

        expect($record['title'])->toBe('Post 1')
            ->and($record['attributes'])->toMatchArray(['status' => 'published', 'user_id' => $this->author->id])
            ->and($record['attributes'])->not->toHaveKeys(['api_token', 'body'])
            ->and($record['relations'])->toBe(['author' => ['id' => $this->author->id, 'title' => 'Autora']])
            ->and($record['abilities'])->toBe(['update' => true, 'delete' => true]);
    });

    it('runs a constant number of queries regardless of page size', function (): void {
        $this->actingAs($this->author);
        $count = function (int $limit): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($this->api("entities/posts?limit={$limit}"))->assertOk();

            return count(array_filter(DB::getQueryLog(), static fn (array $q): bool => str_contains($q['query'], 'posts') || str_contains($q['query'], 'users')));
        };

        $count(1); // aquece o registro de schemas (introspecção só acontece uma vez)

        expect($count(2))->toBe($count(7))->and($count(7))->toBe(2);
    });

    it('selects only planned columns for the author relation', function (): void {
        DB::enableQueryLog();

        $this->actingAs($this->author)->getJson($this->api('entities/posts?limit=2'))->assertOk();

        $authorQuery = collect(DB::getQueryLog())->pluck('query')->first(static fn (string $q): bool => str_contains($q, 'from "users"') || str_contains($q, 'from `users`'));
        expect($authorQuery)->not->toBeNull()
            ->and($authorQuery)->not->toContain('*')
            ->and($authorQuery)->not->toContain('password');
    });

    it('filters by enum, foreign key and null', function (): void {
        $this->actingAs($this->author);

        expect($this->getJson($this->api('entities/posts?filter[status]=published'))->json('data'))->toHaveCount(3)
            ->and($this->getJson($this->api("entities/posts?filter[user_id]={$this->admin->id}"))->json('data'))->toHaveCount(3);
    });

    it('searches case-insensitively and treats LIKE wildcards literally', function (): void {
        $this->makePost($this->author, 'Desconto 100% garantido', ['slug' => 'desconto']);
        $this->actingAs($this->author);

        expect(array_column($this->getJson($this->api('entities/posts?q=POST 3'))->json('data'), 'title'))->toBe(['Post 3'])
            ->and(array_column($this->getJson($this->api('entities/posts?q=post-5'))->json('data'), 'title'))->toBe(['Post 5']);

        if (driver() !== 'sqlite') {
            // SQLite não tem caractere de escape padrão em LIKE.
            expect(array_column($this->getJson($this->api('entities/posts?q=100%'))->json('data'), 'title'))->toBe(['Desconto 100% garantido']);
        }
    });

    it('reports per-record abilities from the policy', function (): void {
        $abilities = collect($this->actingAs($this->author)->getJson($this->api('entities/posts?sort=id'))->json('data'))
            ->mapWithKeys(fn (array $r) => [$r['title'] => $r['abilities']['delete']]);

        expect($abilities['Post 1'])->toBeTrue()->and($abilities['Post 2'])->toBeFalse();
    });

    it('applies global scopes such as soft deletes', function (): void {
        Post::query()->where('title', 'Post 7')->firstOrFail()->delete();

        expect($this->actingAs($this->author)->getJson($this->api('entities/posts'))->json('data'))->toHaveCount(6);
    });

    it('rejects invalid query parameters', function (): void {
        $this->actingAs($this->author)->getJson($this->api('entities/posts?sort=api_token'))
            ->assertStatus(400)
            ->assertJsonPath('detail', 'Não é possível ordenar por "api_token".');
    });
});

describe('detail', function (): void {
    it('shows a record with all readable fields', function (): void {
        $post = $this->makePost($this->author, 'Detalhe', ['metadata' => ['a' => 1], 'price' => '9.90']);

        $data = $this->actingAs($this->admin)->getJson($this->api("entities/posts/{$post->id}"))->assertOk()->json('data');

        expect($data['attributes'])->toMatchArray(['body' => 'Conteúdo de Detalhe', 'metadata' => ['a' => 1], 'price' => '9.90'])
            ->and($data['attributes'])->not->toHaveKey('api_token')
            ->and($data['relations']['author'])->toBe(['id' => $this->author->id, 'title' => 'Autora'])
            ->and($data['abilities'])->toBe(['update' => true, 'delete' => false]);
    });

    it('returns 404 for missing and malformed ids', function (string $id): void {
        $this->actingAs($this->admin)->getJson($this->api("entities/posts/{$id}"))->assertNotFound();
    })->with(['999', 'abc', '1 OR 1=1']);

    it('never exposes the password of users', function (): void {
        $data = $this->actingAs($this->admin)->getJson($this->api("entities/users/{$this->author->id}"))->json('data');

        expect($data['attributes'])->not->toHaveKeys(['password', 'remember_token'])
            ->and($data['attributes']['name'])->toBe('Autora');
    });
});

describe('create', function (): void {
    it('creates through eloquent and returns the stored record', function (): void {
        $response = $this->actingAs($this->admin)->postJson($this->api('entities/posts'), [
            'user_id' => $this->author->id,
            'title' => 'Criado',
            'slug' => 'criado',
            'body' => 'Corpo',
            'status' => 'in_review',
            'price' => '12.5',
            'published_at' => '2026-05-01T10:00:00+00:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.attributes.title', 'Criado')
            ->assertJsonPath('data.attributes.status', 'in_review')
            ->assertJsonPath('data.attributes.price', '12.50')
            ->assertJsonPath('data.relations.author.title', 'Autora');

        expect(Post::query()->where('slug', 'criado')->exists())->toBeTrue();
    });

    it('requires the create ability', function (): void {
        $this->actingAs($this->author)->postJson($this->api('entities/posts'), ['title' => 'x'])->assertForbidden();
    });

    it('validates structure, laravel rules and foreign keys', function (): void {
        $this->makePost($this->author, 'Existente', ['slug' => 'existente']);

        $this->actingAs($this->admin)->postJson($this->api('entities/posts'), [
            'user_id' => 999999,
            'title' => 'Novo',
            'slug' => 'existente',
            'body' => 'x',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'slug']);

        $this->postJson($this->api('entities/posts'), ['title' => 'x', 'api_token' => 'hack'])
            ->assertStatus(422)
            ->assertJsonPath('errors.api_token.0', 'Campo não pode ser alterado.')
            ->assertJsonPath('errors.slug.0', 'Campo obrigatório.');
    });

    it('turns database conflicts into 409', function (): void {
        // users.email é único no banco e não tem regra "unique": quem barra é o índice.
        \Illuminate\Support\Facades\Gate::policy(User::class, PermissiveUserPolicy::class);

        $this->actingAs($this->admin)->postJson($this->api('entities/users'), [
            'name' => 'Duplicada', 'email' => 'autora@example.com', 'password' => 'secret-password',
        ])->assertStatus(409)->assertJsonPath('detail', 'Já existe um registro com esses dados.');
    });
});

describe('update', function (): void {
    it('updates partially and keeps unique rules aware of the current record', function (): void {
        $post = $this->makePost($this->author, 'Original', ['slug' => 'original']);

        $this->actingAs($this->author)->patchJson($this->api("entities/posts/{$post->id}"), ['title' => 'Editado', 'slug' => 'original'])
            ->assertOk()
            ->assertJsonPath('data.attributes.title', 'Editado');

        expect($post->fresh()?->title)->toBe('Editado');
    });

    it('requires the update ability on the record', function (): void {
        $post = $this->makePost($this->admin, 'Do admin');
        $other = $this->makeUser('Outra', 'outra@example.com');

        $this->actingAs($other)->patchJson($this->api("entities/posts/{$post->id}"), ['title' => 'x'])->assertForbidden();
    });

    it('hashes write-only passwords through the model cast', function (): void {
        \Illuminate\Support\Facades\Gate::policy(User::class, PermissiveUserPolicy::class);

        $this->actingAs($this->admin)->patchJson($this->api("entities/users/{$this->author->id}"), ['password' => 'nova-senha-123'])
            ->assertOk()
            ->assertJsonMissingPath('data.attributes.password');

        expect(\Illuminate\Support\Facades\Hash::check('nova-senha-123', (string) $this->author->fresh()?->password))->toBeTrue();
    });
});

describe('delete', function (): void {
    it('soft deletes through eloquent', function (): void {
        $post = $this->makePost($this->author, 'Apagar');

        $this->actingAs($this->author)->deleteJson($this->api("entities/posts/{$post->id}"))->assertNoContent();

        expect(Post::withTrashed()->find($post->id)?->trashed())->toBeTrue();
    });

    it('requires the delete ability', function (): void {
        $post = $this->makePost($this->admin, 'Do admin');

        $this->actingAs($this->author)->deleteJson($this->api("entities/posts/{$post->id}"))->assertForbidden();
    });

    it('reports records still referenced by foreign keys as conflicts', function (): void {
        $this->makePost($this->author, 'Referência');

        $this->actingAs($this->admin)->deleteJson($this->api("entities/users/{$this->author->id}"))
            ->assertStatus(409)
            ->assertJsonPath('type', 'conflict');
    });
});

describe('lookup', function (): void {
    it('returns id and title pairs for relation pickers', function (): void {
        $this->actingAs($this->admin)->getJson($this->api('entities/users/lookup?q=auto'))
            ->assertOk()
            ->assertExactJson(['data' => [['id' => $this->author->id, 'title' => 'Autora']]]);
    });

    it('lists the first options without a query', function (): void {
        expect($this->actingAs($this->admin)->getJson($this->api('entities/users/lookup'))->json('data'))->toHaveCount(2);
    });
});
