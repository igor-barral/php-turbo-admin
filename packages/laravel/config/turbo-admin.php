<?php

declare(strict_types=1);

return [
    /*
    | Prefixo das rotas do painel e da API (/admin, /admin/api/v1).
    */
    'prefix' => env('TURBO_ADMIN_PREFIX', 'admin'),

    /*
    | Título exibido no painel (padrão: app.name).
    */
    'title' => env('TURBO_ADMIN_TITLE'),

    /*
    | Middleware das rotas do painel. "web" fornece sessão, cookies e CSRF; o acesso é
    | sempre checado depois pelo Gate `viewAdmin` (por padrão liberado só em local).
    */
    'middleware' => ['web'],

    /*
    | Guard de autenticação (null = padrão do app) e URLs de login/logout.
    | login_url null usa a rota nomeada "login", se existir.
    */
    'guard' => null,
    'login_url' => null,
    'logout_url' => null,

    /*
    | Modo desacoplado: URL absoluta da API quando a UI roda em outro contêiner/domínio.
    | null = mesma origem.
    */
    'api_url' => env('TURBO_ADMIN_API_URL'),

    'pagination' => [
        'default' => 25,
        'max' => 100,
    ],

    'ui' => [
        // Diretório alternativo da build da UI (null = a build que vem no pacote).
        'path' => null,
    ],

    /*
    | Desligue para registrar as rotas manualmente.
    */
    'routes' => true,

    /*
    | Onde procurar models com o trait HasAdmin (namespace => diretório).
    | vendor/ nunca é varrido: para expor um model de pacote, estenda-o no app.
    */
    'discovery' => [
        'paths' => [
            'App\\Models\\' => app_path('Models'),
        ],
        'classes' => [],
    ],

    /*
    | Schemas compilados por `php artisan turbo-admin:cache`. Com o arquivo presente,
    | nenhuma introspecção acontece em tempo de requisição.
    */
    'cache' => [
        'path' => env('TURBO_ADMIN_CACHE', base_path('bootstrap/cache/turbo-admin.php')),
    ],

    'security' => [
        /*
        | Campos com estes nomes (glob) não são lidos nem gravados sem opt-in explícito
        | (->allowSensitive() / ->writeOnly() em configureAdmin()).
        */
        'sensitive_fields' => PhpTurbo\Admin\Core\Metadata\SecurityPolicy::DEFAULT_SENSITIVE_PATTERNS,
    ],
];
