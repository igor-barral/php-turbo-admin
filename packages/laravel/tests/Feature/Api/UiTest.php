<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->dist = sys_get_temp_dir() . '/turbo-admin-dist-' . bin2hex(random_bytes(4));
    mkdir($this->dist . '/assets/nested', 0775, true);
    file_put_contents($this->dist . '/index.html', '<!doctype html><html><head><script type="module" src="./assets/app.js"></script></head><body></body></html>');
    file_put_contents($this->dist . '/assets/app.js', 'console.log(1)');
    file_put_contents($this->dist . '/assets/nested/app.css', 'body{}');
    file_put_contents($this->dist . '/assets/notes.txt', 'nope');
    file_put_contents($this->dist . '/secret.js', 'nope');
    config()->set('turbo-admin.ui.path', $this->dist);
});

afterEach(function (): void {
    (new Illuminate\Filesystem\Filesystem())->deleteDirectory($this->dist);
});

it('serves the spa with a base href and strict security headers', function (): void {
    $response = $this->actingAs($this->admin)->get('/admin')->assertOk();

    expect($response->getContent())->toContain('<head><base href="/admin/">')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("script-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("connect-src 'self'")
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('allows the external api origin in decoupled mode', function (): void {
    config()->set('turbo-admin.api_url', 'https://app.example.com/admin/api/v1');

    $csp = $this->actingAs($this->admin)->get('/admin')->headers->get('Content-Security-Policy');

    expect($csp)->toContain("connect-src 'self' https://app.example.com");
});

it('explains when the ui was not built', function (): void {
    config()->set('turbo-admin.ui.path', '/does/not/exist');

    $this->actingAs($this->admin)->get('/admin')->assertStatus(503)->assertSee('npm run build');
});

it('falls back to the bundled build path', function (): void {
    config()->set('turbo-admin.ui.path', null);

    expect($this->actingAs($this->admin)->get('/admin')->getStatusCode())->toBeIn([200, 503]);
});

it('serves hashed assets with immutable caching and no auth', function (): void {
    $response = $this->get('/admin/assets/app.js')->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/javascript; charset=UTF-8')
        ->and($response->headers->get('Cache-Control'))->toContain('immutable');

    $this->get('/admin/assets/nested/app.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');
});

it('blocks traversal, unknown types and missing files', function (string $path): void {
    $this->get('/admin/assets/' . $path)->assertNotFound();
})->with(['../secret.js', '..%2Fsecret.js', 'notes.txt', 'missing.js', 'nested']);

it('returns 404 for assets when the build is missing', function (): void {
    config()->set('turbo-admin.ui.path', '/does/not/exist');

    $this->get('/admin/assets/app.js')->assertNotFound();
});

it('provides runtime config for the spa', function (): void {
    config()->set('turbo-admin.title', 'Painel');
    config()->set('turbo-admin.logout_url', '/logout');

    $this->actingAs($this->admin)->getJson('/admin/config.json')
        ->assertOk()
        ->assertExactJson([
            'apiBase' => '/admin/api/v1',
            'appName' => 'Painel',
            'user' => ['name' => 'Admin'],
            'logoutUrl' => '/logout',
        ]);
});

it('uses the configured api url and app name fallback', function (): void {
    config()->set('turbo-admin.api_url', 'https://app.example.com/admin/api/v1/');
    config()->set('app.name', 'Meu App');

    $this->actingAs($this->admin)->getJson('/admin/config.json')
        ->assertJsonPath('apiBase', 'https://app.example.com/admin/api/v1')
        ->assertJsonPath('appName', 'Meu App');
});
