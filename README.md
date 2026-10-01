# php-turbo-admin

Painel admin **gratuito (MIT)** para PHP, gerado a partir dos seus models — sem classes de Resource, sem configuração obrigatória.

```php
class Post extends Model
{
    use HasAdmin; // pronto: campos, tipos, relações e regras vêm do banco e do Eloquent
}
```

Precisa ajustar? Só o que difere da inferência:

```php
public static function configureAdmin(AdminSchema $schema): void
{
    $schema->label('Publicação', 'Publicações')
        ->field('title')->searchable()
        ->field('body')->widget('richtext')
        ->relation('author')->display('name');
}
```

> **Status:** em desenvolvimento (0.1). Core, adapter Laravel (metadados, API, Policies) e SPA em Svelte 5 funcionando no modo monólito. Arquitetura completa em [`docs/architecture.md`](docs/architecture.md).

Instalado o pacote, o painel fica em `/admin`: listagem com busca, filtros, ordenação e paginação por cursor, detalhe, criação, edição e exclusão — tudo autorizado pelas **Policies** do seu app. Por padrão o painel só abre em `local`; em produção, defina o Gate:

```php
Gate::define('viewAdmin', fn (User $user) => $user->is_admin);
```

## Pilares

- **Segurança:** nega por padrão. Respeita `$hidden`, `$fillable` e o Gate/Policies; campos sensíveis (`password`, `*_token`, `*_secret`…) só com opt-in explícito.
- **Performance:** o schema é compilado em um array PHP (OPcache); sem introspecção em tempo de requisição, sem `SELECT *`, sem N+1, sem `COUNT(*)`. A UI inteira tem ~28 KB gzip.
- **Manutenibilidade:** core em PHP puro, sem dependência de framework, com adapters finos (Laravel agora; Symfony depois).

## Estrutura

```
packages/core      PHP puro: schema, camadas de metadados, convenções, segurança, cache
packages/laravel   Adapter: trait HasAdmin, introspecção Eloquent, DataSource, Gate/Policies, rotas, comandos
packages/ui        SPA Svelte 5 (build vai para packages/laravel/dist)
playground/        App Laravel 13 com Sail: desenvolvimento, demo e E2E
docs/              Arquitetura e decisões
```

## Desenvolvimento

Requisitos: Docker e `make`.

```bash
make setup         # primeira vez: .env, dependências, contêineres, banco com dados de exemplo, build da UI
make test          # core + adapter (SQLite) + UI
make test-dbs      # adapter contra SQLite, MySQL e PostgreSQL
make coverage      # falha abaixo de 95%
make analyse       # PHPStan nível máximo
make check         # tudo o que o CI roda
make ui-dev        # Vite com hot reload em :5173 usando a API do playground
make e2e           # Playwright contra o playground
make help          # todos os comandos
```

Painel do playground: <http://localhost:8080/admin> — login `admin@example.com` / `password`.

No app de exemplo:

```bash
cd playground
./vendor/bin/sail artisan turbo-admin:schema         # lista entidades
./vendor/bin/sail artisan turbo-admin:schema users   # schema resolvido em JSON
./vendor/bin/sail artisan turbo-admin:cache          # compila para produção
```

Compatibilidade: PHP 8.2–8.5, Laravel 12 e 13, SQLite, MySQL e PostgreSQL. Navegadores modernos (ES2022).

## Licença

[MIT](LICENSE)
