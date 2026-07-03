# Implementation Plan: Configuração do Evento (Admin)

**Branch**: `003-config-evento` | **Date**: 2026-07-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/003-config-evento/spec.md`

## Summary

Painel administrativo completo sobre a fundação: API `/api/admin/*` aninhada por
evento (multi-evento-ready) com `require.role:admin` + `EventPolicy`; publicação
com requisitos mínimos e cancelamento com guarda terminal via
`EventConfigService`; CRUDs de tipos de evento, tipos de ingresso, lotes, camisas
e blocos de landing (payload validado por tipo + reorder transacional); vouchers
de cortesia em lote com ciclo só-avança; patrocínios com parcelas e status
recalculado em transação. Frontend `/painel` com layout Tabler (`@tabler/core`),
`RoleRoute` sobre a auth da 002 e telas que exibem as derivações da fundação
(vigente/preço efetivo/esgotado) sem recalcular nada. **Nenhuma tabela nova.**

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12); JavaScript ES2022 (React 18, Node 20)

**Primary Dependencies**: já presentes (Sanctum, React Query, react-router-dom);
**novo**: `@tabler/core` (CSS do painel)

**Storage**: MySQL 8 — tabelas da fundação; disco `public` para banner
(`storage:link` no make install); nenhuma migration nova

**Testing**: PHPUnit Feature em `app_test`; uploads com `Storage::fake`

**Target Platform**: idem 001/002 (Docker dev)

**Project Type**: web application — API + SPA existentes

**Performance Goals**: telas do painel respondem < 1s em dev; suíte da spec < 2 min

**Constraints**: envelope/erros da 001 (409 para toda regra violada); derivações
sempre da fundação (nunca duplicadas no front); auditoria automática preservada;
dinheiro DECIMAL string (vírgula normalizada no front); pt-BR

**Scale/Scope**: ~30 endpoints admin, 6 áreas de tela, 0 migrations, 6 user stories

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação |
|---|---|
| I. Standalone, RBAC | ✅ Tudo sob `require.role:admin`; rotas por id de evento preparam multi-evento sem reescrita. |
| II. Estado derivado | ✅ Telas consomem derivações da 001 via API; `sponsorships.status` segue o padrão cache-recalculado (`recalculateStatus` em transação, como `sold_count`). |
| III. Ponto único de baixa | ✅ N/A a pedidos (spec 005). Baixa de parcela de patrocínio é gestão direta fora do gateway — registrada em Assumptions da spec; não conflita (o princípio cobre pagamentos de pedidos). |
| IV. Segurança | ✅ Sem credenciais novas; upload validado (mime/tamanho); códigos de voucher não sequenciais (trait da 001). |
| V. Histórico | ✅ Guardas de exclusão com vendas (409); cancelamentos registram autor/motivo; soft delete + auditoria automáticos; parcela paga imutável. |
| VI. Specs por área | ✅ Consome contratos da 001/002 sem redefinir; renderização pública e resgate de voucher explicitamente delegados à 004. |
| Stack e convenções | ✅ `@tabler/core` é CSS de apresentação — não altera stack (React 18 + Vite mantidos); tema já era referência do projeto. |

**Resultado**: PASS (pré-Phase 0 e pós-Phase 1). Sem entradas em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/003-config-evento/
├── plan.md              # Este arquivo
├── research.md          # 9 decisões
├── data-model.md        # 0 tabelas novas — regras de escrita + transições
├── quickstart.md        # validação por user story
├── contracts/admin-api.md
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks (próximo passo)
```

### Source Code (repository root)

```text
app/
├── Domain/Events/
│   ├── Services/
│   │   ├── EventConfigService.php      # publish (requisitos mínimos) / cancel
│   │   └── SponsorshipService.php      # parcelas + baixa + recálculo de status
│   └── Models/                         # + hasSales()/guardas e recalculateStatus()
├── Http/
│   ├── Controllers/Api/Admin/
│   │   ├── EventController.php         # index/show/update/publish/cancel/banner
│   │   ├── EventTypeController.php
│   │   ├── TicketTypeController.php    # CRUD + reorder
│   │   ├── TicketLotController.php     # CRUD + reorder
│   │   ├── ShirtModelController.php / ShirtSizeController.php
│   │   ├── LandingBlockController.php  # CRUD + reorder
│   │   ├── CourtesyVoucherController.php # index/generate/distribute
│   │   └── SponsorshipController.php   # CRUD + payInstallment
│   ├── Requests/Admin/                 # FormRequests (payload por tipo, money, banner)
│   └── Resources/Admin/                # EventResource, TicketTypeResource (com derivações), …
routes/api.php                          # grupo /admin (auth + require.role:admin)
tests/Feature/Admin/                    # EventConfig, EventType, Catalog, Shirt,
                                        # LandingBlock, Voucher, Sponsorship, Rbac
frontend/src/
├── admin/                              # layout do painel (Tabler): AdminLayout, Sidebar
│   └── pages/                          # Evento, TiposLotes, Camisas, Landing,
│                                       # Cortesias, Patrocinios
├── auth/RoleRoute.jsx                  # guarda por papel + página 403
└── lib/money.js                        # parseMoney/formatMoney (vírgula ↔ ponto)
```

**Structure Decision**: controllers admin em `Api/Admin` (um por recurso);
services de escrita multi-passo no domínio; telas do painel isoladas em
`frontend/src/admin/`.

## Fases de implementação (visão para /speckit-tasks)

1. **Setup**: `@tabler/core`; `storage:link` no make install; grupo de rotas
   `/admin` + `RoleRoute` + AdminLayout (sidebar).
2. **US1 — evento**: EventConfigService (publish/cancel), EventController
   (+banner com Storage), resources com derivações; tela Evento; testes.
3. **US2 — catálogo**: guardas hasSales/capacidade, TicketType/LotControllers
   (+reorder), tela Tipos & Lotes (vigente/preço efetivo); testes.
4. **US3 — camisas**: Shirt controllers com guarda de estoque; tela; testes.
5. **US4 — landing**: LandingBlockController (payload por tipo + reorder); tela
   editor; testes.
6. **US5 — cortesias**: geração em lote + distribute; tela; testes.
7. **US6 — patrocínios**: SponsorshipService (parcelas/baixa/recalculo); tela;
   testes.
8. **Polish**: quickstart manual completo, EventTypeController, ROADMAP/status.

## Complexity Tracking

Sem violações constitucionais a justificar.
