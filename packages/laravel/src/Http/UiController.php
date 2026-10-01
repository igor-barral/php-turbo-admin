<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Http;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega a SPA pré-compilada que vem dentro do pacote: o usuário não precisa de Node.
 * Assets têm hash no nome e são servidos com cache imutável.
 */
final readonly class UiController
{
    private const MIME_TYPES = [
        'js' => 'text/javascript; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'ico' => 'image/x-icon',
        'woff2' => 'font/woff2',
        'json' => 'application/json',
    ];

    public function __construct(private Config $config)
    {
    }

    public function index(): Response
    {
        $index = $this->distPath() . '/index.html';

        if (!is_file($index)) {
            return new Response(
                'A interface do php-turbo-admin não foi compilada. Rode "npm run build" em packages/ui.',
                503,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        // <base> faz os assets relativos funcionarem em /admin e /admin/.
        $html = str_replace('<head>', '<head><base href="' . e($this->basePath()) . '/">', (string) file_get_contents($index));

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'Content-Security-Policy' => $this->contentSecurityPolicy(),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
        ]);
    }

    public function config(Request $request): JsonResponse
    {
        $user = $request->attributes->get('turbo-admin.user');
        $name = is_object($user) ? ($user->name ?? $user->email ?? null) : null;

        return new JsonResponse([
            'apiBase' => $this->apiBase(),
            'appName' => $this->config->get('turbo-admin.title') ?? $this->config->get('app.name'),
            'user' => ['name' => is_scalar($name) ? (string) $name : null],
            'logoutUrl' => $this->config->get('turbo-admin.logout_url'),
        ], 200, ['Cache-Control' => 'no-store']);
    }

    public function asset(string $path): Response
    {
        $root = realpath($this->distPath() . '/assets');
        $file = $root === false ? false : realpath($root . '/' . $path);
        $extension = $file === false ? '' : strtolower(pathinfo($file, PATHINFO_EXTENSION));

        // realpath + prefixo impede path traversal (../../.env).
        if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)
            || !is_file($file) || !isset(self::MIME_TYPES[$extension])) {
            return new Response('', 404);
        }

        return new BinaryFileResponse($file, 200, [
            'Content-Type' => self::MIME_TYPES[$extension],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function distPath(): string
    {
        $path = $this->config->get('turbo-admin.ui.path');

        return is_string($path) && $path !== '' ? rtrim($path, '/') : dirname(__DIR__, 2) . '/dist';
    }

    private function basePath(): string
    {
        $prefix = $this->config->get('turbo-admin.prefix', 'admin');

        return '/' . trim(is_string($prefix) ? $prefix : 'admin', '/');
    }

    private function apiBase(): string
    {
        $url = $this->config->get('turbo-admin.api_url');

        return is_string($url) && $url !== '' ? rtrim($url, '/') : $this->basePath() . '/api/v1';
    }

    private function contentSecurityPolicy(): string
    {
        $connect = "'self'";
        $api = $this->config->get('turbo-admin.api_url');
        if (is_string($api) && preg_match('#^https?://[^/]+#', $api, $origin) === 1) {
            $connect .= ' ' . $origin[0];
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src {$connect}",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
        ]);
    }
}
