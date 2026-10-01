# Ambiente de desenvolvimento do php-turbo-admin.
# Tudo roda dentro do contêiner do Sail (playground/), que também monta packages/.

COMPOSE := cd playground && docker compose
EXEC    := $(COMPOSE) exec -T -u sail
APP     := laravel.test
DB      ?= sqlite

.DEFAULT_GOAL := help

.PHONY: help setup up down shell install seed test test-core test-laravel test-dbs test-ui coverage analyse ui-build ui-dev e2e check

help: ## Lista os comandos
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

setup: ## Primeira vez: .env, dependências do playground, contêineres e dependências dos pacotes
	@test -f playground/.env || (cp playground/.env.example playground/.env \
		&& sed -i "s/^WWWUSER=.*/WWWUSER=$$(id -u)/; s/^WWWGROUP=.*/WWWGROUP=$$(id -g)/" playground/.env)
	@test -d playground/vendor || docker run --rm -u "$$(id -u):$$(id -g)" -v "$$(pwd):/var/www" \
		-w /var/www/playground laravelsail/php84-composer:latest composer install --ignore-platform-reqs
	$(MAKE) up
	$(EXEC) $(APP) php artisan key:generate
	$(MAKE) install
	$(MAKE) seed
	$(MAKE) ui-build

up: ## Sobe os contêineres (app, MySQL, PostgreSQL)
	$(COMPOSE) up -d

down: ## Derruba os contêineres
	$(COMPOSE) down

shell: ## Shell no contêiner do app
	$(COMPOSE) exec -u sail $(APP) bash

install: ## Dependências dos pacotes (composer e npm)
	$(EXEC) $(APP) bash -c 'cd /var/www/packages/core && composer install --no-interaction'
	$(EXEC) $(APP) bash -c 'cd /var/www/packages/laravel && composer install --no-interaction'
	$(EXEC) $(APP) bash -c 'cd /var/www/packages/ui && npm ci --no-audit --no-fund'

seed: ## Recria o banco do playground com dados de exemplo (login: admin@example.com / password)
	$(EXEC) $(APP) php artisan migrate:fresh --seed --force

test: test-core test-laravel test-ui ## Todos os testes (adapter no banco DB=sqlite|mysql|pgsql)

test-core: ## Testes do core
	$(EXEC) $(APP) bash -c 'cd /var/www/packages/core && vendor/bin/pest'

test-laravel: ## Testes do adapter Laravel (DB=sqlite|mysql|pgsql)
	$(EXEC) -e TEST_DB=$(DB) $(APP) bash -c 'cd /var/www/packages/laravel && vendor/bin/pest'

test-dbs: ## Adapter Laravel contra SQLite, MySQL e PostgreSQL
	$(MAKE) test-laravel DB=sqlite
	$(MAKE) test-laravel DB=mysql
	$(MAKE) test-laravel DB=pgsql

test-ui: ## Testes da SPA (Vitest)
	$(EXEC) $(APP) bash -c 'cd /var/www/packages/ui && npx vitest run'

coverage: ## Cobertura (PHP: mínimo 95%; UI: mínimo 90% de linhas)
	$(EXEC) $(APP) bash -c 'cd /var/www/packages/core && vendor/bin/pest --coverage --min=95'
	$(EXEC) -e TEST_DB=$(DB) $(APP) bash -c 'cd /var/www/packages/laravel && vendor/bin/pest --coverage --min=95'
	$(EXEC) $(APP) bash -c 'cd /var/www/packages/ui && npx vitest run --coverage'

analyse: ## PHPStan nível máximo + svelte-check
	$(EXEC) $(APP) bash -c 'cd /var/www/packages/core && vendor/bin/phpstan analyse --memory-limit=1G'
	$(EXEC) $(APP) bash -c 'cd /var/www/packages/laravel && vendor/bin/phpstan analyse --memory-limit=1G'
	$(EXEC) $(APP) bash -c 'cd /var/www/packages/ui && npm run check'

ui-build: ## Compila a SPA para packages/laravel/dist (com orçamento de tamanho)
	$(EXEC) $(APP) bash -c 'cd /var/www/packages/ui && npm run build'

ui-dev: ## Vite com hot reload em :5173 usando a API do playground
	cd packages/ui && npm run dev

e2e: ui-build ## Playwright contra o playground (no host; `npx playwright install chromium` ou TURBO_ADMIN_E2E_CHANNEL=chrome)
	cd packages/ui && npx playwright test

check: analyse coverage test-dbs ## Tudo o que o CI roda
