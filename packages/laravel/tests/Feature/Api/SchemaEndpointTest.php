<?php

declare(strict_types=1);

it('lists only entities with a policy allowing viewAny', function (): void {
    $response = $this->actingAs($this->author)->getJson($this->api('schema'))->assertOk();

    expect(array_column($response->json('entities'), 'key'))->toBe(['posts', 'users']);
});

it('reflects policy abilities per user', function (): void {
    $create = fn ($user) => collect($this->actingAs($user)->getJson($this->api('schema'))->json('entities'))
        ->keyBy('key')->map(fn (array $e) => $e['abilities']['create'])->all();

    expect($create($this->admin))->toBe(['posts' => true, 'users' => false])
        ->and($create($this->author))->toBe(['posts' => false, 'users' => false]);
});

it('exposes relations only to visible entities', function (): void {
    $posts = collect($this->actingAs($this->admin)->getJson($this->api('schema'))->json('entities'))->firstWhere('key', 'posts');

    expect(array_column($posts['relations'], 'name'))->toBe(['author'])
        ->and($posts['relations'][0])->toMatchArray(['target' => 'users', 'foreignKey' => 'user_id', 'writable' => true]);
});

it('never ships sensitive columns or validation rules', function (): void {
    $json = $this->actingAs($this->admin)->get($this->api('schema'))->getContent();

    expect($json)->not->toContain('api_token')
        ->not->toContain('remember_token')
        ->not->toContain('unique:posts')
        ->not->toContain('PhpTurbo\\\\Admin');
});
