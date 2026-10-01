<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Auth\Ability;
use PhpTurbo\Admin\Core\Data\Record;
use PhpTurbo\Admin\Core\Http\ApiKernel;
use PhpTurbo\Admin\Core\Http\ApiRequest;
use PhpTurbo\Admin\Core\Http\ApiResponse;
use PhpTurbo\Admin\Core\Query\Cursor;
use PhpTurbo\Admin\Core\Schema\EntitySchema;
use PhpTurbo\Admin\Core\Tests\Fixtures\Api\ApiFixtures;
use PhpTurbo\Admin\Core\Tests\Fixtures\Api\InMemoryDataSource;
use PhpTurbo\Admin\Core\Tests\Fixtures\Api\RuleAuthorizer;
use PhpTurbo\Admin\Core\Validation\RuleValidator;

beforeEach(function (): void {
    $this->data = new InMemoryDataSource();
    ApiFixtures::seed($this->data);
    $this->auth = new RuleAuthorizer(['articles.*', 'writers.*']);
    $this->rules = null;
    $this->call = function (string $method, string $path, array $query = [], array $body = []): ApiResponse {
        $kernel = new ApiKernel(ApiFixtures::registry(), $this->data, $this->auth, $this->rules);

        return $kernel->handle(new ApiRequest($method, $path, $query, $body, actor: 'igor'));
    };
});

/** Corpo como o cliente o vê (passando por JSON). */
function body(ApiResponse $response): array
{
    return json_decode(json_encode($response->body ?? [], JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
}

describe('schema', function (): void {
    it('lists only entities the actor can view, without internal details', function (): void {
        $response = ($this->call)('GET', 'schema');
        $entities = body($response)['entities'];

        expect($response->status)->toBe(200)
            ->and(body($response)['version'])->toBe(1)
            ->and(array_column($entities, 'key'))->toBe(['articles', 'writers'])
            ->and($entities[0])->not->toHaveKey('class')
            ->and($entities[0]['fields'][0])->not->toHaveKey('rules')
            ->and($entities[0]['abilities'])->toBe(['create' => true])
            ->and($entities[0]['searchable'])->toBeTrue()
            ->and($entities[1]['searchable'])->toBeFalse();
    });

    it('hides relations to invisible entities and resolves target keys', function (): void {
        $relations = body(($this->call)('GET', 'schema'))['entities'][0]['relations'];

        expect($relations)->toBe([
            ['name' => 'writer', 'label' => 'Writer', 'type' => 'belongsTo', 'target' => 'writers', 'foreignKey' => 'writer_id', 'writable' => true],
            ['name' => 'owner', 'label' => 'Owner', 'type' => 'morphTo', 'target' => null, 'foreignKey' => null, 'writable' => false],
        ]);
    });

    it('marks belongsTo as not writable when the target is not visible', function (): void {
        $this->auth->allowed = ['articles.*'];

        $relations = body(($this->call)('GET', 'schema'))['entities'][0]['relations'];

        expect(array_column($relations, 'name'))->toBe(['owner']);
    });

    it('reports create ability per entity', function (): void {
        $this->auth->allowed = ['articles.viewAny', 'writers.viewAny'];

        expect(body(($this->call)('GET', 'schema'))['entities'][0]['abilities'])->toBe(['create' => false]);
    });

    it('rejects other methods', function (): void {
        expect(($this->call)('POST', 'schema')->status)->toBe(405);
    });
});

describe('routing', function (): void {
    it('returns 404 problems for unknown routes', function (string $path): void {
        $response = ($this->call)('GET', $path);

        expect($response->status)->toBe(404)
            ->and($response->headers)->toBe(['Content-Type' => 'application/problem+json'])
            ->and(body($response))->toBe(['type' => 'not_found', 'title' => 'Recurso não encontrado', 'status' => 404]);
    })->with(['', 'nope', 'entities', 'entities/nope', 'entities/articles/1/extra', 'other/articles']);

    it('hides entities the actor cannot view behind 404', function (): void {
        expect(($this->call)('GET', 'entities/secrets')->status)->toBe(404);
    });

    it('rejects unsupported methods', function (string $method, string $path): void {
        expect(($this->call)($method, $path)->status)->toBe(405);
    })->with([
        ['PUT', 'entities/articles'],
        ['PUT', 'entities/articles/1'],
        ['POST', 'entities/articles/lookup'],
    ]);

    it('accepts lowercase methods and surrounding slashes', function (): void {
        expect(($this->call)('get', '/entities/articles/1/')->status)->toBe(200);
    });
});

describe('index', function (): void {
    it('lists records with cursor pagination by default', function (): void {
        $response = ($this->call)('GET', 'entities/articles', ['limit' => '2']);
        $json = body($response);

        expect($response->status)->toBe(200)
            ->and(array_column($json['data'], 'id'))->toBe([5, 4])
            ->and($json['meta'])->toMatchArray([
                'pagination' => 'cursor',
                'limit' => 2,
                'page' => null,
                'hasMore' => true,
                'sort' => ['field' => 'id', 'direction' => 'desc'],
            ])
            ->and(Cursor::decode($json['meta']['nextCursor']))->toEqual(new Cursor(4, 4));
    });

    it('follows the cursor to the end', function (): void {
        $first = body(($this->call)('GET', 'entities/articles', ['limit' => '3']));
        $second = body(($this->call)('GET', 'entities/articles', ['limit' => '3', 'cursor' => $first['meta']['nextCursor']]));

        expect(array_column($second['data'], 'id'))->toBe([2, 1])
            ->and($second['meta']['hasMore'])->toBeFalse()
            ->and($second['meta']['nextCursor'])->toBeNull();
    });

    it('serializes only readable fields with normalized values', function (): void {
        $record = body(($this->call)('GET', 'entities/articles', ['limit' => '1', 'sort' => 'id']))['data'][0];

        expect($record['id'])->toBe(1)
            ->and($record['title'])->toBe('Artigo 1')
            ->and($record['attributes'])->not->toHaveKey('internal_note')
            ->and($record['attributes']['created_at'])->toBe('2026-01-01T10:00:00+00:00')
            ->and($record['relations'])->toBe(['writer' => ['id' => 1, 'title' => 'Ana']])
            ->and($record['abilities'])->toBe(['update' => true, 'delete' => true]);
    });

    it('plans only the columns needed for the index', function (): void {
        ($this->call)('GET', 'entities/articles', ['sort' => '-rating']);

        [, [$query, $plan]] = $this->data->calls[0];

        expect($plan->columns)->toBe(['id', 'title', 'rating', 'status', 'featured', 'created_at', 'writer_id'])
            ->and($plan->relations)->toBe(['writer' => 'name'])
            ->and($query->keyset)->toBeFalse();
    });

    it('uses page pagination when sorting by a nullable column', function (): void {
        $json = body(($this->call)('GET', 'entities/articles', ['sort' => 'rating', 'limit' => '2', 'page' => '2']));

        expect($json['meta'])->toMatchArray(['pagination' => 'page', 'page' => 2, 'nextCursor' => null, 'hasMore' => true])
            ->and(array_column($json['data'], 'id'))->toBe([2, 4]);
    });

    it('filters and searches', function (): void {
        $json = body(($this->call)('GET', 'entities/articles', ['filter' => ['status' => 'published'], 'q' => 'artigo 4']));

        expect(array_column($json['data'], 'id'))->toBe([4]);
    });

    it('reports per-record abilities', function (): void {
        $this->auth = new RuleAuthorizer(['*'], static fn (Ability $a, Record $r): bool => $a !== Ability::Delete || $r->id !== 5);

        $json = body(($this->call)('GET', 'entities/articles', ['limit' => '2']));

        expect(array_column($json['data'], 'abilities'))->toBe([
            ['update' => true, 'delete' => false],
            ['update' => true, 'delete' => true],
        ]);
    });

    it('turns invalid parameters into 400 problems', function (): void {
        $response = ($this->call)('GET', 'entities/articles', ['sort' => 'internal_note']);

        expect($response->status)->toBe(400)
            ->and(body($response)['detail'])->toBe('Não é possível ordenar por "internal_note".');
    });

    it('omits relations whose target is not visible', function (): void {
        $this->auth->allowed = ['articles.*'];

        $record = body(($this->call)('GET', 'entities/articles'))['data'][0];

        expect($record['relations'])->toBe([])
            ->and(json_encode(($this->call)('GET', 'entities/articles', ['limit' => '1'])->body))->toContain('"relations":{}');
    });
});

describe('show', function (): void {
    it('returns a record with detail relations', function (): void {
        $json = body(($this->call)('GET', 'entities/articles/2'));

        expect($json['data']['id'])->toBe(2)
            ->and($json['data']['attributes'])->toHaveKey('body')
            ->and($json['data']['relations'])->toBe(['writer' => ['id' => 1, 'title' => 'Ana'], 'owner' => ['id' => 9, 'title' => '9']]);
    });

    it('returns 404 for missing records', function (): void {
        expect(($this->call)('GET', 'entities/articles/99')->status)->toBe(404);
    });

    it('checks the view ability on the record', function (): void {
        $this->auth = new RuleAuthorizer(['*'], static fn (Ability $a): bool => $a !== Ability::View);

        expect(($this->call)('GET', 'entities/articles/1')->status)->toBe(403);
    });

    it('never exposes write-only fields', function (): void {
        expect(body(($this->call)('GET', 'entities/writers/1'))['data']['attributes'])->toBe(['id' => 1, 'name' => 'Ana']);
    });
});

describe('store', function (): void {
    it('creates a record and returns it reloaded', function (): void {
        $response = ($this->call)('POST', 'entities/articles', [], ['title' => 'Novo', 'rating' => '7', 'featured' => 'true']);

        expect($response->status)->toBe(201)
            ->and(body($response)['data']['id'])->toBe(6)
            ->and(body($response)['data']['attributes'])->toMatchArray(['title' => 'Novo', 'rating' => 7, 'featured' => true])
            ->and($this->data->callNames())->toBe(['create', 'find']);
    });

    it('validates input before touching the data source', function (): void {
        $response = ($this->call)('POST', 'entities/articles', [], ['status' => 'archived', 'created_at' => '2026-01-01', 'hacker' => 1]);

        expect($response->status)->toBe(422)
            ->and(body($response)['errors'])->toBe([
                'status' => ['Opção inválida.'],
                'created_at' => ['Campo não pode ser alterado.'],
                'hacker' => ['Campo não pode ser alterado.'],
                'title' => ['Campo obrigatório.'],
            ])
            ->and($this->data->calls)->toBe([]);
    });

    it('runs framework rules after structural validation', function (): void {
        $this->rules = new class () implements RuleValidator {
            public ?array $received = null;

            public function validate(EntitySchema $entity, array $data, ?Record $record): array
            {
                $this->received = [$entity->key, $data, $record];

                return ['title' => ['Já existe.']];
            }
        };

        $response = ($this->call)('POST', 'entities/articles', [], ['title' => 'Dup']);

        expect($response->status)->toBe(422)
            ->and(body($response)['errors'])->toBe(['title' => ['Já existe.']])
            ->and($this->rules->received)->toBe(['articles', ['title' => 'Dup'], null]);
    });

    it('merges structural and rule errors in one response', function (): void {
        $this->rules = new class () implements RuleValidator {
            public ?array $received = null;

            public function validate(EntitySchema $entity, array $data, ?Record $record): array
            {
                $this->received = $data;

                return ['title' => ['Já existe.'], 'rating' => ['Muito alto.']];
            }
        };

        $response = ($this->call)('POST', 'entities/articles', [], ['title' => 'Dup', 'rating' => '5', 'status' => 'x']);

        expect(body($response)['errors'])->toBe([
            'status' => ['Opção inválida.'],
            'title' => ['Já existe.'],
            'rating' => ['Muito alto.'],
        ])
            ->and($this->rules->received)->toBe(['title' => 'Dup', 'rating' => 5]);
    });

    it('requires the create ability', function (): void {
        $this->auth->allowed = ['articles.viewAny'];

        expect(($this->call)('POST', 'entities/articles', [], ['title' => 'x'])->status)->toBe(403)
            ->and($this->data->calls)->toBe([]);
    });

    it('maps conflicts to 409', function (): void {
        $this->data->conflictOn = 'articles';

        $response = ($this->call)('POST', 'entities/articles', [], ['title' => 'x']);

        expect($response->status)->toBe(409)
            ->and(body($response)['detail'])->toBe('Já existe um registro com esses dados.');
    });

    it('accepts write-only fields', function (): void {
        $response = ($this->call)('POST', 'entities/writers', [], ['name' => 'Carla', 'password' => 'segredo']);

        expect($response->status)->toBe(201)
            ->and(body($response)['data']['attributes'])->toBe(['id' => 3, 'name' => 'Carla'])
            ->and($this->data->rows['writers'][3]['password'])->toBe('segredo');
    });
});

describe('update', function (): void {
    it('applies a partial update', function (): void {
        $response = ($this->call)('PATCH', 'entities/articles/1', [], ['title' => 'Editado']);

        expect($response->status)->toBe(200)
            ->and(body($response)['data']['attributes']['title'])->toBe('Editado')
            ->and(body($response)['data']['attributes']['status'])->toBe('draft')
            ->and($this->data->callNames())->toBe(['find', 'update', 'find']);
    });

    it('passes the current record to rule validation', function (): void {
        $this->rules = new class () implements RuleValidator {
            public mixed $record = null;

            public function validate(EntitySchema $entity, array $data, ?Record $record): array
            {
                $this->record = $record?->id;

                return [];
            }
        };

        ($this->call)('PATCH', 'entities/articles/2', [], ['title' => 'x']);

        expect($this->rules->record)->toBe(2);
    });

    it('does not require fields on update', function (): void {
        expect(($this->call)('PATCH', 'entities/articles/1', [], [])->status)->toBe(200);
    });

    it('requires the update ability on the record', function (): void {
        $this->auth = new RuleAuthorizer(['*'], static fn (Ability $a): bool => $a !== Ability::Update);

        expect(($this->call)('PATCH', 'entities/articles/1', [], ['title' => 'x'])->status)->toBe(403)
            ->and($this->data->callNames())->toBe(['find']);
    });

    it('returns 404 for missing records', function (): void {
        expect(($this->call)('PATCH', 'entities/articles/99', [], [])->status)->toBe(404);
    });
});

describe('destroy', function (): void {
    it('deletes a record', function (): void {
        $response = ($this->call)('DELETE', 'entities/articles/3');

        expect($response->status)->toBe(204)
            ->and($response->body)->toBeNull()
            ->and($response->headers)->toBe([])
            ->and($this->data->rows['articles'])->not->toHaveKey(3);
    });

    it('requires the delete ability', function (): void {
        $this->auth = new RuleAuthorizer(['*'], static fn (Ability $a): bool => $a !== Ability::Delete);

        expect(($this->call)('DELETE', 'entities/articles/3')->status)->toBe(403)
            ->and($this->data->rows['articles'])->toHaveKey(3);
    });
});

describe('lookup', function (): void {
    it('searches the title and searchable fields', function (): void {
        $json = body(($this->call)('GET', 'entities/articles/lookup', ['q' => ' artigo 2 ']));

        expect($json['data'])->toBe([['id' => 2, 'title' => 'Artigo 2']])
            ->and($this->data->calls[0][1])->toBe(['artigo 2', ['title'], 'title', ApiKernel::LOOKUP_LIMIT]);
    });

    it('works without a query', function (): void {
        expect(body(($this->call)('GET', 'entities/writers/lookup'))['data'])->toHaveCount(2);
    });

    it('rejects invalid queries', function (mixed $q): void {
        expect(($this->call)('GET', 'entities/writers/lookup', ['q' => $q])->status)->toBe(400);
    })->with([[['array']], [str_repeat('a', 101)]]);
});

it('passes the actor to the authorizer', function (): void {
    ($this->call)('GET', 'entities/articles/1');

    expect(array_unique(array_column($this->auth->checks, 0)))->toBe(['igor']);
});
