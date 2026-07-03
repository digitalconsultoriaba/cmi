# Plataforma de Eventos (cmi)

Produto standalone de venda e gestão de ingressos para um seminário internacional.
Desenvolvido por specs funcionais (Spec Kit) — roadmap em [ROADMAP.md](ROADMAP.md),
regras em [.specify/memory/constitution.md](.specify/memory/constitution.md).

## Stack

- **Backend**: Laravel 12 (PHP 8.3), MySQL 8, Redis, Sanctum SPA — domínio em
  `app/Domain/Events`
- **Frontend**: React 18 + Vite + React Query em `frontend/`
- **Dev**: Docker Compose (MySQL com bancos `app` e `app_test`, Redis, Mailpit);
  PHP/Composer rodam via Docker — **não é preciso PHP no host**

## Pré-requisitos

- Docker (Compose v2)
- Node 20+ (para o frontend)

## Quickstart

```bash
make up        # sobe MySQL, Redis e Mailpit
make install   # composer install (via Docker), npm install, .env + APP_KEY
make fresh     # migrate:fresh --seed (estrutura + lookups + papéis + dados demo)
make test      # suíte de testes no banco app_test
make dev       # API em :8000 + Vite em :5173
```

Smoke test: `curl http://localhost:8000/api/health` → `{"data":{"status":"ok"}}`.

Usuários de desenvolvimento (seed): `admin@dev.local`, `tesouraria@dev.local`,
`portaria@dev.local` — senha `password`.

## Comandos úteis

| Comando | O quê |
|---|---|
| `make migrate` | migrations + seeders (sem recriar) |
| `make api` | só a API em background |
| `make logs` | logs dos serviços |
| `make down` | derruba os serviços |

Mailpit (e-mails de dev): http://localhost:8025

## Layout do repositório

```
app/Domain/Events/   # domínio (models, exceções, geradores)
app/Http/            # controllers de API, middleware (require.role)
database/            # migrations, factories, seeders
frontend/            # SPA React 18 + Vite
tests/Feature/       # feature tests (MySQL app_test)
specs/               # specs funcionais (Spec Kit)
base/                # material de referência (não é fonte de implementação)
docker/              # Dockerfile PHP + init do MySQL
```

## Convenções (resumo da constituição)

- API: sucesso `{ data }` camelCase; erros `{ message, type, status }`
  (422 validação, 409 regra de negócio, 403 papel/escopo)
- Estado operacional (inscrições abertas, lote vigente, esgotado) é **derivado**,
  nunca coluna — contratos em `specs/001-fundacao/contracts/`
- Soft delete + `created_by`/`updated_by` em toda tabela de negócio
- RBAC: `admin`, `treasury`, `gate`, `attendee` (acumuláveis)
- Segredos jamais versionados — `.env.example` só com placeholders

Guia de validação da fundação: [specs/001-fundacao/quickstart.md](specs/001-fundacao/quickstart.md)
