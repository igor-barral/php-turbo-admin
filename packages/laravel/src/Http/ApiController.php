<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpTurbo\Admin\Core\Http\ApiKernel;
use PhpTurbo\Admin\Core\Http\ApiRequest;
use Symfony\Component\HttpFoundation\Response;

/** Ponte fina: Request do Laravel → ApiRequest do core → JsonResponse. */
final readonly class ApiController
{
    public function __construct(private ApiKernel $kernel)
    {
    }

    public function __invoke(Request $request, string $path = ''): Response
    {
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();

        $response = $this->kernel->handle(new ApiRequest(
            method: $request->getMethod(),
            path: $path,
            query: $request->query->all(),
            body: $body,
            actor: $request->attributes->get('turbo-admin.user'),
        ));

        $headers = $response->headers + ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'];

        if ($response->body === null) {
            return new Response('', $response->status, $headers);
        }

        return new JsonResponse($response->body, $response->status, $headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
