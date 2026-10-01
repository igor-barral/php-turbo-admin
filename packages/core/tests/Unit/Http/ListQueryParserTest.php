<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Http\HttpError;
use PhpTurbo\Admin\Core\Query\Cursor;
use PhpTurbo\Admin\Core\Query\Filter;
use PhpTurbo\Admin\Core\Query\ListQueryParser;
use PhpTurbo\Admin\Core\Schema\Sort;
use PhpTurbo\Admin\Core\Schema\SortDirection;
use PhpTurbo\Admin\Core\Tests\Fixtures\Api\ApiFixtures;

beforeEach(function (): void {
    $this->articles = ApiFixtures::registry()->get('articles');
    $this->writers = ApiFixtures::registry()->get('writers');
    $this->parse = fn (array $params, ?string $entity = null) => (new ListQueryParser(defaultLimit: 10, maxLimit: 50))
        ->parse($entity === 'writers' ? $this->writers : $this->articles, $params);
});

function problemOf(callable $callback): HttpError
{
    try {
        $callback();
    } catch (HttpError $error) {
        return $error;
    }

    throw new LogicException('Nenhum HttpError lançado.');
}

it('uses schema defaults', function (): void {
    $query = ($this->parse)([]);

    expect($query->sort)->toEqual(new Sort('id', SortDirection::Desc))
        ->and($query->primaryKey)->toBe('id')
        ->and($query->limit)->toBe(10)
        ->and($query->keyset)->toBeTrue()
        ->and($query->cursor)->toBeNull()
        ->and($query->page)->toBe(1)
        ->and($query->offset())->toBe(0)
        ->and($query->filters)->toBe([])
        ->and($query->search)->toBeNull()
        ->and($query->searchFields)->toBe([]);
});

it('parses sort, limit, cursor, filters and search', function (): void {
    $cursor = (new Cursor('Artigo 3', 3))->encode();

    $query = ($this->parse)([
        'sort' => '-title',
        'limit' => '5',
        'cursor' => $cursor,
        'filter' => ['status' => 'published', 'featured' => '1', 'writer_id' => 'null'],
        'q' => '  laravel ',
    ]);

    expect($query->sort)->toEqual(new Sort('title', SortDirection::Desc))
        ->and($query->limit)->toBe(5)
        ->and($query->keyset)->toBeTrue()
        ->and($query->cursor)->toEqual(new Cursor('Artigo 3', 3))
        ->and($query->filters)->toEqual([new Filter('status', 'published'), new Filter('featured', true), new Filter('writer_id', null)])
        ->and($query->search)->toBe('laravel')
        ->and($query->searchFields)->toBe(['title']);
});

it('treats blank values as absent', function (): void {
    $query = ($this->parse)(['sort' => '', 'limit' => '', 'cursor' => '', 'page' => '', 'q' => '   ']);

    expect($query->limit)->toBe(10)->and($query->search)->toBeNull()->and($query->cursor)->toBeNull();
});

it('falls back to page pagination for nullable sort columns', function (): void {
    $query = ($this->parse)(['sort' => 'rating', 'page' => '3', 'limit' => 20]);

    expect($query->keyset)->toBeFalse()
        ->and($query->page)->toBe(3)
        ->and($query->offset())->toBe(40);
});

it('rejects invalid parameters', function (array $params, string $detail, ?string $entity = null): void {
    $error = problemOf(fn () => ($this->parse)($params, $entity));

    expect($error->status)->toBe(400)->and($error->detail)->toBe($detail);
})->with([
    'sort array' => [['sort' => ['id']], 'Parâmetro sort inválido.'],
    'sort unknown' => [['sort' => 'nope'], 'Não é possível ordenar por "nope".'],
    'sort not sortable' => [['sort' => '-body'], 'Não é possível ordenar por "body".'],
    'cursor with page pagination' => [['sort' => 'rating', 'cursor' => (new Cursor(1, 1))->encode()], 'Esta ordenação usa paginação por página, não por cursor.'],
    'cursor array' => [['cursor' => ['x']], 'Cursor inválido.'],
    'search array' => [['q' => ['x']], 'Parâmetro q inválido.'],
    'search too long' => [['q' => str_repeat('á', 101)], 'A busca aceita no máximo 100 caracteres.'],
    'search unsupported' => [['q' => 'x'], 'Esta entidade não possui campos pesquisáveis.', 'writers'],
    'limit zero' => [['limit' => '0'], 'Parâmetro limit deve ser um inteiro positivo.'],
    'limit text' => [['limit' => 'ten'], 'Parâmetro limit deve ser um inteiro positivo.'],
    'limit negative int' => [['limit' => -1], 'Parâmetro limit deve ser um inteiro positivo.'],
    'limit too high' => [['limit' => '51'], 'O limite máximo é 50.'],
    'page invalid' => [['sort' => 'rating', 'page' => 'x'], 'Parâmetro page deve ser um inteiro positivo.'],
    'filter not array' => [['filter' => 'status'], 'Parâmetro filter inválido.'],
    'filter unknown' => [['filter' => ['nope' => 1]], 'Não é possível filtrar por "nope".'],
    'filter not filterable' => [['filter' => ['title' => 'x']], 'Não é possível filtrar por "title".'],
]);

it('reports invalid filter values as validation errors', function (): void {
    $error = problemOf(fn () => ($this->parse)(['filter' => ['status' => 'archived', 'featured' => 'maybe']]));

    expect($error->status)->toBe(422)
        ->and($error->errors)->toBe(['status' => ['Opção inválida.'], 'featured' => ['Deve ser verdadeiro ou falso.']]);
});

it('treats "null" literally on non nullable filters', function (): void {
    $error = problemOf(fn () => ($this->parse)(['filter' => ['status' => 'null']]));

    expect($error->errors)->toBe(['status' => ['Opção inválida.']]);
});
