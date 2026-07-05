# Implementation Plan: Aba Orçamento / Previsão Financeira do Evento

**Branch**: `011-orcamento` | **Date**: 2026-07-04 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/011-orcamento/spec.md`

## Summary

Nova aba **Orçamento** dentro do painel do evento (`/painel/eventos/:eventId/orcamento`) para planejar e simular a viabilidade financeira **antes** da execução. O organizador cadastra itens de custo previstos, lotes de ingresso previstos, cotas de patrocínio previstas e estimativas de participantes; o sistema **deriva** todos os totais (custo total, receitas previstas, resultado, investimento próprio, ponto de equilíbrio, ticket médio, custo por participante). Itens/patrocínios podem ser **convertidos** em lançamentos reais do módulo Financeiro (spec 010) sem duplicidade, e um **comparativo orçado × realizado** cruza a previsão com o Financeiro/vendas reais. Simuladores (cenários, preço mínimo, margem de segurança), alertas e exportação (Excel/PDF) completam a aba.

Abordagem técnica: seguir os padrões já estabelecidos nas specs 009/010 — domínio em `app/Domain/Events`, um `BudgetPlan` 1:1 com o evento e coleções filhas; **estado derivado nunca persistido** (calculado em um `BudgetCalculator`); conversão para financeiro reutilizando `FinancialEntryService::create`; frontend em React com o padrão de aba do `EventoLayout`, cards Tabler, modais reutilizáveis (`Modal`) e gráficos ApexCharts já presentes.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12); JavaScript (React 18 + Vite)

**Primary Dependencies**: Laravel 12, MySQL 8, Sanctum (cookie SPA); React Query, ApexCharts (react-apexcharts), openspout (.xlsx), barryvdh/laravel-dompdf (PDF). Reutiliza o módulo Financeiro da spec 010 (`FinancialEntry`, `FinancialEntryService`, `FinancialReportService`).

**Storage**: MySQL 8 — novas tabelas `budget_plans`, `budget_cost_items`, `budget_ticket_lots`, `budget_sponsorships`, `budget_scenarios` (todas com soft delete + `created_by`/`updated_by`). Dinheiro DECIMAL(10,2).

**Testing**: PHPUnit Feature em MySQL `app_test` (nunca SQLite); cobre caminho feliz + regras de negócio (409 duplicidade/valor inválido, 403 escopo/papel), derivações de cálculo e integração de conversão.

**Target Platform**: Web (backend Laravel em Docker :8000; SPA Vite :5173).

**Project Type**: Web application (backend + frontend no mesmo repo).

**Performance Goals**: Interação de painel administrativo — resposta de cálculo do resumo < 300 ms para orçamentos com até ~200 itens; sem metas de alta concorrência (uso interno da organização).

**Constraints**: Estado derivado nunca vira coluna editável; escritas multi-passo (conversão em financeiro) em `DB::transaction`; segredos fora do VCS; UI/mensagens pt-BR, código em inglês; datas UTC no banco.

**Scale/Scope**: Escopo por evento (1 orçamento por evento); dezenas de itens/lotes/patrocínios por orçamento; ~1 tela nova com 6 seções + modais e 2–3 endpoints de leitura agregada.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Standalone / RBAC 4 papéis** — ✅ Sem conceitos maçônicos. Acesso à aba só para `admin` e `treasury` via middleware `require.role:admin,treasury` (mesmo escopo do painel do evento). Nenhum papel novo (perfis "Organizador"/"Consulta" da descrição mapeados aos existentes, ver spec Assumptions).
- **II. Estado derivado, nunca armazenado** — ✅ Custo total, receitas previstas, resultado, ponto de equilíbrio, investimento próprio e % de atingimento são **calculados na leitura** por um `BudgetCalculator`. Nenhuma coluna de total editável. (Não há race de vaga aqui; a única escrita multi-passo — conversão em financeiro — usa `DB::transaction`.)
- **III. Ponto único de baixa** — ✅ N/A direto: o orçamento não dá baixa. A conversão cria um `FinancialEntry` via `FinancialEntryService::create`; baixas continuam exclusivas do módulo Financeiro (010) e do `RegisterPayment`.
- **IV. Segurança de pagamento** — ✅ N/A: nenhum dado de cartão/PAN. Sem segredos novos.
- **V. Histórico — nada some** — ✅ Todas as tabelas com soft delete + `created_by`/`updated_by` + activity log nas ações relevantes (conversão, exclusão). Excluir/cancelar linha do orçamento **não** apaga lançamento financeiro já gerado.
- **VI. Specs por área funcional** — ✅ Spec própria `011-orcamento`, entrega backend + frontend + testes. Não redefine specs anteriores; **consome** o Financeiro (010) e as vendas (004/005) via serviços/leituras existentes, sem alterá-los.

**Resultado do gate**: PASS — sem violações; sem entradas em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/011-orcamento/
├── plan.md              # Este arquivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   └── budget-api.md    # Fase 1 — contrato dos endpoints da aba
└── tasks.md             # Fase 2 (/speckit-tasks — não criado aqui)
```

### Source Code (repository root)

```text
app/
├── Domain/Events/
│   ├── Models/
│   │   ├── BudgetPlan.php
│   │   ├── BudgetCostItem.php
│   │   ├── BudgetTicketLot.php
│   │   ├── BudgetSponsorship.php
│   │   ├── BudgetScenario.php
│   │   └── BudgetCostItemStatus.php / BudgetSponsorshipStatus.php   # constantes de status
│   └── Services/
│       ├── BudgetCalculator.php        # deriva todos os totais/indicadores
│       └── BudgetConversionService.php # item→conta a pagar; patrocínio→conta a receber (DB::transaction)
├── Http/
│   ├── Controllers/Api/Admin/
│   │   ├── BudgetController.php         # show plan + summary + comparativo; update plano
│   │   ├── BudgetCostItemController.php # CRUD + duplicar + gerar conta a pagar
│   │   ├── BudgetTicketLotController.php
│   │   ├── BudgetSponsorshipController.php # CRUD + gerar conta a receber
│   │   └── BudgetScenarioController.php
│   ├── Requests/Admin/                  # FormRequests (validação → 422)
│   └── Resources/Admin/                 # BudgetPlanResource, BudgetSummaryResource, itens…
database/
├── migrations/2026_07_05_*_create_budget_tables.php
└── seeders/BudgetSeeder.php (opcional, massa de demo)

frontend/src/admin/eventos/abas/
├── Orcamento.jsx                # container da aba (resumo + seções)
└── orcamento/                   # subcomponentes: ResumoCards, ItensCusto, Lotes,
                                 # Patrocinios, Participantes, Comparativo, Simuladores, Graficos

tests/Feature/Budget/
├── BudgetSummaryTest.php        # derivações e regras de cálculo
├── BudgetConversionTest.php     # item→pagar / patrocínio→receber + duplicidade
└── BudgetAccessTest.php         # 403 escopo/papel; 422 valor inválido
```

**Structure Decision**: Web application com backend Laravel (domínio em `app/Domain/Events`) e frontend React. A aba entra como nova rota-filha no `EventoLayout` existente (`/painel/eventos/:eventId/orcamento`), seguindo o padrão das specs 009/010. Os cálculos ficam num serviço `BudgetCalculator` (estado derivado); a conversão em financeiro num `BudgetConversionService` que chama `FinancialEntryService::create` dentro de `DB::transaction`.

## Complexity Tracking

> Sem violações constitucionais — seção não aplicável.
