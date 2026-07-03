# Plataforma de Eventos — tooling de desenvolvimento.
# PHP/Composer rodam via Docker (nada de PHP no host); Node roda no host.

COMPOSE = docker compose
PHP     = $(COMPOSE) run --rm php

.PHONY: up down install migrate fresh test dev api logs

## Sobe os serviços (MySQL app+app_test, Redis, Mailpit)
up:
	$(COMPOSE) up -d mysql redis mailpit

## Derruba os serviços
down:
	$(COMPOSE) down

## Instala dependências e prepara o .env
install:
	$(COMPOSE) build php
	$(PHP) composer install
	@test -f .env || (cp .env.example .env && $(PHP) php artisan key:generate)
	npm install --prefix frontend

## Aplica migrations + seeders
migrate: up
	$(PHP) php artisan migrate --seed

## Recria o banco do zero (estrutura + lookups + roles + dados demo)
fresh: up
	$(PHP) php artisan migrate:fresh --seed

## Roda a suíte de testes no banco app_test
test: up
	$(PHP) php artisan test

## Sobe a API (:8000) em background via Docker
api:
	$(COMPOSE) --profile dev up -d api

## Ambiente completo de dev: API :8000 + Vite :5173 (foreground)
dev: up api
	npm run dev --prefix frontend

## Logs dos serviços
logs:
	$(COMPOSE) logs -f
