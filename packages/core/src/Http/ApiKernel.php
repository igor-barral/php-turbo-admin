<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Http;

use PhpTurbo\Admin\Core\Auth\Ability;
use PhpTurbo\Admin\Core\Auth\Authorizer;
use PhpTurbo\Admin\Core\Data\ConflictException;
use PhpTurbo\Admin\Core\Data\DataSource;
use PhpTurbo\Admin\Core\Data\Record;
use PhpTurbo\Admin\Core\Query\ListQueryParser;
use PhpTurbo\Admin\Core\Registry\SchemaRegistry;
use PhpTurbo\Admin\Core\Schema\EntitySchema;
use PhpTurbo\Admin\Core\Validation\InputValidator;
use PhpTurbo\Admin\Core\Validation\RuleValidator;

/**
 * Implementação completa da API /v1, independente de framework.
 *
 * GET    schema
 * GET    entities/{key}              listagem
 * GET    entities/{key}/lookup?q=    autocomplete
 * GET    entities/{key}/{id}         detalhe
 * POST   entities/{key}              criação
 * PATCH  entities/{key}/{id}         atualização parcial
 * DELETE entities/{key}/{id}         exclusão
 *
 * Autenticação é responsabilidade do adapter; autorização acontece aqui, em toda rota.
 */
final readonly class ApiKernel
{
    public const LOOKUP_LIMIT = 20;

    public function __construct(
        private SchemaRegistry $registry,
        private DataSource $data,
        private Authorizer $authorizer,
        private ?RuleValidator $rules = null,
        private ListQueryParser $parser = new ListQueryParser(),
        private InputValidator $input = new InputValidator(),
        private RecordSerializer $serializer = new RecordSerializer(),
        private QueryPlanner $planner = new QueryPlanner(),
    ) {
    }

    public function handle(ApiRequest $request): ApiResponse
    {
        try {
            return $this->route($request, new EntityVisibility($this->registry, $this->authorizer, $request->actor));
        } catch (HttpError $error) {
            return ApiResponse::problem($error);
        } catch (ConflictException $conflict) {
            return ApiResponse::problem(HttpError::conflict($conflict->getMessage()));
        }
    }

    private function route(ApiRequest $request, EntityVisibility $visibility): ApiResponse
    {
        $segments = $request->path === '' ? [] : explode('/', trim($request->path, '/'));
        $method = strtoupper($request->method);

        if ($segments === ['schema']) {
            return $this->only($method, 'GET', fn (): ApiResponse => ApiResponse::json(
                (new SchemaPresenter($this->authorizer))->present($visibility, $request->actor),
            ));
        }

        if (($segments[0] ?? null) !== 'entities' || !isset($segments[1]) || count($segments) > 3) {
            throw HttpError::notFound();
        }

        $entity = $this->entity($segments[1], $visibility);
        $id = $segments[2] ?? null;

        if ($id === null) {
            return match ($method) {
                'GET' => $this->index($entity, $request, $visibility),
                'POST' => $this->store($entity, $request, $visibility),
                default => throw HttpError::methodNotAllowed(),
            };
        }

        if ($id === 'lookup') {
            return $this->only($method, 'GET', fn (): ApiResponse => $this->lookup($entity, $request));
        }

        return match ($method) {
            'GET' => $this->show($entity, $id, $request, $visibility),
            'PATCH' => $this->update($entity, $id, $request, $visibility),
            'DELETE' => $this->destroy($entity, $id, $request, $visibility),
            default => throw HttpError::methodNotAllowed(),
        };
    }

    /** @param callable(): ApiResponse $handler */
    private function only(string $method, string $allowed, callable $handler): ApiResponse
    {
        if ($method !== $allowed) {
            throw HttpError::methodNotAllowed();
        }

        return $handler();
    }

    /** Entidade inexistente e entidade sem permissão respondem igual: 404. */
    private function entity(string $key, EntityVisibility $visibility): EntitySchema
    {
        if (!$this->registry->has($key)) {
            throw HttpError::notFound();
        }

        $entity = $this->registry->get($key);

        if (!$visibility->canSee($entity)) {
            throw HttpError::notFound();
        }

        return $entity;
    }

    private function index(EntitySchema $entity, ApiRequest $request, EntityVisibility $visibility): ApiResponse
    {
        $query = $this->parser->parse($entity, $request->query);
        $plan = $this->planner->forIndex($entity, $query->sort, $visibility);
        $page = $this->data->list($entity, $query, $plan);

        return ApiResponse::json([
            'data' => array_map(
                fn (Record $record): array => $this->serializer->serialize(
                    $entity,
                    $record,
                    $plan,
                    $this->recordAbilities($entity, $record, $request->actor),
                ),
                $page->records,
            ),
            'meta' => [
                'pagination' => $query->keyset ? 'cursor' : 'page',
                'limit' => $query->limit,
                'page' => $query->keyset ? null : $query->page,
                'hasMore' => $page->hasMore,
                'nextCursor' => $query->keyset && $page->hasMore ? $page->next?->encode() : null,
                'sort' => $query->sort->toArray(),
            ],
        ]);
    }

    private function show(EntitySchema $entity, string $id, ApiRequest $request, EntityVisibility $visibility): ApiResponse
    {
        $plan = $this->planner->forDetail($entity, $visibility);
        $record = $this->findOrFail($entity, $id, $plan);
        $this->authorize($request->actor, Ability::View, $entity, $record);

        return $this->respondWith($entity, $record, $request->actor, $visibility);
    }

    private function store(EntitySchema $entity, ApiRequest $request, EntityVisibility $visibility): ApiResponse
    {
        $this->authorize($request->actor, Ability::Create, $entity);

        $data = $this->validated($entity, $request->body, null);
        $created = $this->data->create($entity, $data);

        return $this->respondWith($entity, $created, $request->actor, $visibility, status: 201, reload: true);
    }

    private function update(EntitySchema $entity, string $id, ApiRequest $request, EntityVisibility $visibility): ApiResponse
    {
        $record = $this->findOrFail($entity, $id, $this->planner->forDetail($entity, $visibility));
        $this->authorize($request->actor, Ability::Update, $entity, $record);

        $data = $this->validated($entity, $request->body, $record);
        $updated = $this->data->update($entity, $record, $data);

        return $this->respondWith($entity, $updated, $request->actor, $visibility, reload: true);
    }

    private function destroy(EntitySchema $entity, string $id, ApiRequest $request, EntityVisibility $visibility): ApiResponse
    {
        $record = $this->findOrFail($entity, $id, $this->planner->forDetail($entity, $visibility));
        $this->authorize($request->actor, Ability::Delete, $entity, $record);

        $this->data->delete($entity, $record);

        return ApiResponse::noContent();
    }

    private function lookup(EntitySchema $entity, ApiRequest $request): ApiResponse
    {
        $search = $request->query['q'] ?? '';

        if (!is_string($search) || mb_strlen($search) > ListQueryParser::MAX_SEARCH_LENGTH) {
            throw HttpError::badRequest('Parâmetro q inválido.');
        }

        $title = $entity->titleField ?? $entity->primaryKey;
        $fields = array_keys(array_filter($entity->fields, static fn ($f): bool => $f->searchable));
        if ($entity->hasField($title) && $entity->field($title)->readable) {
            $fields[] = $title;
        }

        $refs = $this->data->lookup($entity, trim($search), array_values(array_unique($fields)), $title, self::LOOKUP_LIMIT);

        return ApiResponse::json(['data' => array_map(static fn ($ref): array => $ref->toArray(), $refs)]);
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function validated(EntitySchema $entity, array $body, ?Record $record): array
    {
        $result = $this->input->check($entity, $body, creating: $record === null);

        // Regras do framework rodam só nos campos estruturalmente válidos; os dois conjuntos
        // de erros voltam juntos, para o usuário corrigir tudo de uma vez.
        $errors = $result->errors;
        foreach ($this->rules?->validate($entity, $result->data, $record) ?? [] as $name => $messages) {
            $errors[$name] = [...($errors[$name] ?? []), ...$messages];
        }

        if ($errors !== []) {
            throw HttpError::validation($errors);
        }

        return $result->data;
    }

    private function findOrFail(EntitySchema $entity, string $id, \PhpTurbo\Admin\Core\Data\QueryPlan $plan): Record
    {
        return $this->data->find($entity, $id, $plan) ?? throw HttpError::notFound();
    }

    private function authorize(mixed $actor, Ability $ability, EntitySchema $entity, ?Record $record = null): void
    {
        if (!$this->authorizer->can($actor, $ability, $entity, $record)) {
            throw HttpError::forbidden();
        }
    }

    /** @return array{update: bool, delete: bool} */
    private function recordAbilities(EntitySchema $entity, Record $record, mixed $actor): array
    {
        return [
            'update' => $this->authorizer->can($actor, Ability::Update, $entity, $record),
            'delete' => $this->authorizer->can($actor, Ability::Delete, $entity, $record),
        ];
    }

    /** Após escrita, relê o registro com o plano de detalhe (valores como o banco os guardou). */
    private function respondWith(
        EntitySchema $entity,
        Record $record,
        mixed $actor,
        EntityVisibility $visibility,
        int $status = 200,
        bool $reload = false,
    ): ApiResponse {
        $plan = $this->planner->forDetail($entity, $visibility);

        if ($reload) {
            $record = $this->data->find($entity, (string) $record->id, $plan) ?? $record;
        }

        return ApiResponse::json([
            'data' => $this->serializer->serialize($entity, $record, $plan, $this->recordAbilities($entity, $record, $actor)),
        ], $status);
    }
}
