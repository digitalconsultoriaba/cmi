# Plataforma de Eventos (cmi)

Produto standalone de venda e gestão de ingressos (seminário internacional),
desenvolvido por specs funcionais (Spec Kit). **Leia sempre**
`.specify/memory/constitution.md` — prevalece sobre tudo.

## Estado atual

- Spec ativa: ver `.specify/feature.json`. Roadmap completo em `ROADMAP.md`.
- `base/` = material de referência (nunca fonte direta); `template/` = tema Tabler de
  referência visual; `specs/NNN-nome/` = artefatos de cada spec.

## Stack

- Backend: Laravel 12 (PHP 8.3+), MySQL 8, Redis, Sanctum SPA (cookie), domínio em
  `app/Domain/Events`.
- Frontend: React 18 + Vite em `frontend/`, React Query.
- Testes: PHPUnit Feature em MySQL dedicado `app_test` (nunca SQLite).
- Dev: `make up` (Docker: MySQL/Redis/Mailpit), `make fresh`, `make test`, `make dev`.
  PHP/Composer rodam **via Docker** (`docker compose run --rm php …`) — não há PHP no host.

## Regras que sempre pegam em revisão

- API: sucesso `{ data }` camelCase; erros `{ message, type, status }` — 422
  validação, 409 regra de negócio (`DomainRuleViolation`), 403 papel/escopo.
- Estado operacional (salesOpen, lote vigente, esgotado) é **derivado, nunca coluna**;
  contratos em `specs/001-fundacao/contracts/`.
- Toda tabela de negócio: soft delete + `created_by`/`updated_by`; status terminais
  rejeitam transição (409); nada é apagado fisicamente.
- Escritas multi-passo em `DB::transaction` com recontagem (race de vaga/lote/estoque).
- Dinheiro DECIMAL(10,2); datas UTC; código em inglês; UI/mensagens/docs em pt-BR.
- Segredos jamais no VCS; `.env.example` só placeholders; PAN/CVV nunca no backend.
- RBAC: 4 papéis (`admin`, `treasury`, `gate`, `attendee`), middleware `require.role`.
