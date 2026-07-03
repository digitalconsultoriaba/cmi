# Implementation Plan: Fundação da Plataforma de Eventos

**Branch**: `001-fundacao` | **Date**: 2026-07-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-fundacao/spec.md`

## Summary

Criar o alicerce do produto: scaffold Laravel 12 (raiz) + React 18/Vite
(`frontend/`), ambiente dev com Docker Compose (MySQL 8 dual, Redis, Mailpit) e
Makefile; todo o modelo de dados do domínio (23 tabelas em 6 grupos) com soft delete +
auditoria; models com derivações calculadas (salesOpen, lote vigente, esgotamentos) e
guarda de transição terminal; RBAC de 4 papéis (middleware + policy); handler de
exceções com envelope `{ data }`/erros padronizados; seeders estruturais e de
demonstração; suíte Feature em MySQL de teste. O código do 061 não existe neste repo —
implementação nova guiada por `base/data-model.md` (ver research, Decisão 1).

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 12); JavaScript ES2022 (Node 20, React 18)

**Primary Dependencies**: laravel/framework 12, laravel/sanctum (instalado, fluxo na
spec 002), vite + @vitejs/plugin-react, @tanstack/react-query (scaffold); dev:
phpunit 11

**Storage**: MySQL 8 (bancos `app` e `app_test`); Redis (cache/filas — consumo real
nas specs 004+)

**Testing**: PHPUnit Feature tests com `RefreshDatabase` em MySQL dedicado
(`app_test`); nunca SQLite (research, Decisão 4)

**Target Platform**: Linux server (produção futura); dev em macOS/Linux com Docker
Compose para serviços

**Project Type**: web application — backend Laravel na raiz + SPA em `frontend/`

**Performance Goals**: `migrate:fresh --seed` < 60s; suíte da spec < 5 min; setup
completo em máquina limpa < 15 min (SC-001)

**Constraints**: constituição — soft delete + auditoria em toda tabela de negócio;
estado derivado (nunca coluna); DECIMAL(10,2); UTC; envelope `{ data }` camelCase;
403/409/422 na shape padrão; código em inglês, UI/docs pt-BR; sem segredos no VCS

**Scale/Scope**: single-event MVP (~milhares de inscritos); 23 tabelas; ~20 models;
sem telas de negócio (scaffold React apenas)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação |
|---|---|
| I. Standalone, zero acoplamento | ✅ Núcleo da spec: nenhum conceito de GL/loja/membro; RBAC próprio de 4 papéis; `events` como tabela. SC-004 vira teste de regressão. |
| II. Snapshot + estado derivado | ✅ Snapshot em orders/tickets; derivações como accessors/scopes com contrato próprio (`contracts/domain-derivations.md`); `sold_count` documentado como cache recalculável; recontagem transacional preparada (exercida na 004). |
| III. Ponto único de baixa idempotente | ✅ (estrutura) Unique `(provider, provider_charge_id)` em payments + `webhook_events` com dedupe nascem aqui; `RegisterPayment` em si é spec 005 — sem violação, o schema não permite burlar. |
| IV. Segurança de pagamento | ✅ Nenhuma credencial nesta spec; `.env.example` só placeholders; payments guarda apenas brand/last4/token — jamais PAN/CVV (colunas não existem). |
| V. Histórico — nada some | ✅ Soft delete + created_by/updated_by via BaseModel; colunas de cancelamento; `transitionTo()` rejeita terminal com 409. Activity log rico adiado para spec 008 (registrado em research, Decisão 5 — aditivo, sem violação). |
| VI. Specs por área | ✅ Corte por domínio (fundação); não redefine nada de outra spec; base/ usado só como referência. |
| Stack e convenções | ✅ Idênticas à constituição. |

**Resultado**: PASS (pré-Phase 0 e pós-Phase 1). Sem entradas em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/001-fundacao/
├── plan.md              # Este arquivo
├── research.md          # Fase 0 — 10 decisões
├── data-model.md        # Fase 1 — 23 tabelas, 6 grupos, derivações
├── quickstart.md        # Fase 1 — guia de validação
├── contracts/
│   ├── api-conventions.md      # envelope { data }, erros 401/403/404/409/422
│   ├── rbac.md                 # papéis, middleware require.role, policies
│   └── domain-derivations.md   # salesOpen, lote vigente, esgotamentos, terminais
├── checklists/requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks — ainda não criado)
```

### Source Code (repository root)

```text
app/
├── Domain/Events/
│   ├── Models/          # BaseModel, BaseLookupModel, Event, TicketType, TicketLot,
│   │                    # EventShirtModel/Size, LandingBlock, Order, Ticket, Payment,
│   │                    # WebhookEvent, CourtesyVoucher, Sponsorship(+Installment),
│   │                    # SupportCase(+Note), Role, *Status, EventType
│   ├── Exceptions/      # DomainRuleViolation
│   └── Support/         # TicketCodeGenerator (trait HasPublicCode)
├── Http/
│   ├── Middleware/      # RoleMiddleware (require.role)
│   └── Controllers/Api/ # HealthController (apenas)
├── Models/User.php      # hasRole/hasAnyRole + roles()
└── Policies/            # EventPolicy
bootstrap/app.php        # alias require.role + render de exceções (envelope de erro)
database/
├── migrations/          # 6 grupos: auth, lookups, event+config, orders+tickets,
│                        # payments+webhooks, courtesy+sponsorship+support
└── seeders/             # DatabaseSeeder, RoleSeeder, LookupSeeders,
│                        # AdminUserSeeder (dev), SampleEventSeeder (dev)
routes/api.php           # GET /api/health
tests/Feature/Foundation/ # Ambiente, Dominio, Derivacoes, Rbac, Seeders
frontend/
├── src/                 # main.tsx? não — JSX simples; App, router placeholder,
│                        # lib/api.ts (axios + envelope), QueryClient
├── index.html
└── vite.config.js
docker-compose.yml       # mysql (app/app_test), redis, mailpit
Makefile                 # up/down/install/migrate/fresh/test/dev
.env.example             # placeholders apenas
```

**Structure Decision**: Laravel na raiz + `frontend/` (research, Decisão 2); domínio
em `app/Domain/Events` conforme constituição; testes da spec agrupados em
`tests/Feature/Foundation/`.

## Fases de implementação (visão para /speckit-tasks)

1. **Scaffold e tooling** (US1): laravel new na raiz (preservando base/, template/,
   specs/), frontend Vite+React, docker-compose, Makefile, .env.example, phpunit.xml
   apontando app_test, GET /api/health + handler de exceções/envelope.
2. **Migrations** (US2): 6 grupos na ordem de dependência (auth → lookups →
   event+config → orders+tickets → payments+webhooks → courtesy/sponsorship/support).
3. **Models + derivações** (US2/US3): BaseModel/BaseLookupModel/HasPublicCode →
   models concretos → accessors/scopes do contrato de derivações → transitionTo().
4. **RBAC** (US4): RoleMiddleware + registro + EventPolicy + User::hasRole.
5. **Seeders** (US4): lookups, roles, AdminUserSeeder, SampleEventSeeder.
6. **Testes** (todas): por contrato — ambiente/envelope, domínio/soft delete/audit,
   derivações (7 cenários), RBAC (4 cenários), seeds; varredura SC-004 como teste.

## Complexity Tracking

Sem violações constitucionais a justificar.
