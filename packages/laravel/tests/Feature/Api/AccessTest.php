<?php

declare(strict_types=1);

it('rejects guests on the api with a problem response', function (): void {
    $this->getJson($this->api('schema'))
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertExactJson(['type' => 'unauthenticated', 'title' => 'Autenticação necessária', 'status' => 401]);
});

it('answers guests on the page with 401 when there is no login route', function (): void {
    $this->get('/admin')->assertStatus(401);
});

it('redirects guests to the configured login url', function (): void {
    config()->set('turbo-admin.login_url', '/entrar');

    $this->get('/admin')->assertRedirect('/entrar');
});

it('redirects guests to the named login route', function (): void {
    \Illuminate\Support\Facades\Route::get('/login', static fn () => 'login')->name('login');
    app('router')->getRoutes()->refreshNameLookups();

    $this->get('/admin')->assertRedirect('/login');
});

it('requires the viewAdmin gate', function (): void {
    $blocked = $this->makeUser('Bloqueado', 'blocked@example.com');

    $this->actingAs($blocked)->getJson($this->api('schema'))
        ->assertStatus(403)
        ->assertJsonPath('title', 'Acesso ao painel não permitido');

    $this->actingAs($blocked)->get('/admin')->assertStatus(403);
});

it('uses the configured guard', function (): void {
    config()->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
    config()->set('turbo-admin.guard', 'admin');

    $this->actingAs($this->admin, 'web')->getJson($this->api('schema'))->assertStatus(401);
    $this->actingAs($this->admin, 'admin')->getJson($this->api('schema'))->assertOk();
});

it('only opens the panel in local environment by default', function (string $environment, int $status): void {
    $original = app()->environment();
    app()['env'] = $environment;
    // Remove a definição dos testes para exercitar o padrão do pacote.
    $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class);
    $abilities = (new ReflectionProperty($gate, 'abilities'));
    $abilities->setValue($gate, array_diff_key($gate->abilities(), ['viewAdmin' => true]));
    (new \PhpTurbo\Admin\Laravel\AdminServiceProvider(app()))->boot();

    try {
        $this->actingAs($this->admin)->getJson($this->api('schema'))->assertStatus($status);
    } finally {
        app()['env'] = $original;
    }
})->with([['local', 200], ['production', 403]]);
