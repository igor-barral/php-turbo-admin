<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Http\Middleware;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige usuário autenticado e o Gate `viewAdmin`. API recebe Problem Details (401/403);
 * a página do painel redireciona para o login.
 */
final readonly class AuthorizeAdmin
{
    public function __construct(
        private AuthFactory $auth,
        private Gate $gate,
        private Config $config,
        private UrlGenerator $url,
    ) {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $guard = $this->config->get('turbo-admin.guard');
        $user = $this->auth->guard(is_string($guard) ? $guard : null)->user();
        $isApi = $request->is(trim($this->prefix() . '/api/*', '/'));

        if ($user === null) {
            return $isApi
                ? $this->problem(401, 'unauthenticated', 'Autenticação necessária')
                : $this->redirectToLogin();
        }

        if ($this->gate->forUser($user)->denies('viewAdmin')) {
            return $isApi
                ? $this->problem(403, 'forbidden', 'Acesso ao painel não permitido')
                : new Response('Acesso ao painel não permitido.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        // Sem setUserResolver: o controller lê o mesmo guard configurado.
        $request->attributes->set('turbo-admin.user', $user);

        /** @var Response */
        return $next($request);
    }

    private function prefix(): string
    {
        $prefix = $this->config->get('turbo-admin.prefix', 'admin');

        return is_string($prefix) ? $prefix : 'admin';
    }

    private function redirectToLogin(): Response
    {
        $login = $this->config->get('turbo-admin.login_url');

        if (!is_string($login) || $login === '') {
            $login = app('router')->has('login') ? $this->url->route('login') : null;
        }

        return $login === null
            ? new Response('Autenticação necessária.', 401, ['Content-Type' => 'text/plain; charset=UTF-8'])
            : new \Illuminate\Http\RedirectResponse($login);
    }

    private function problem(int $status, string $type, string $title): JsonResponse
    {
        return new JsonResponse(
            ['type' => $type, 'title' => $title, 'status' => $status],
            $status,
            ['Content-Type' => 'application/problem+json', 'Cache-Control' => 'no-store'],
        );
    }
}
