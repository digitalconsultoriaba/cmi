# Implementation Plan: Módulo Financeiro — Contas a Pagar e Receber

**Branch**: `010-fluxo-caixa` | **Date**: 2026-07-04 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/010-fluxo-caixa/spec.md`

## Summary

Módulo financeiro **central** de contas a pagar e a receber: lançamentos com
vínculo **opcional** a evento (o evento vira centro de resultado), situação
**derivada** (em aberto/vencido/pago/parcial/cancelado), baixa total/parcial com
recontagem sob lock, parcelamento, recorrência, anexos, cadastros
(categorias/pessoas/formas), dashboard geral e por evento, relatórios com
export (.xlsx/PDF) e histórico via activity_log. Ingressos e patrocínios são
**espelhados** em contas a receber por **observers idempotentes** (upsert por
origem, zero duplicidade) — projeção sincronizada ao ponto único de baixa (005),
nunca segunda contabilização. Coexiste com as telas financeiras da 008/009 sem
alterá-las. Papéis reutilizados: admin e financeiro (tesouraria).

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12); JavaScript ES2022 (React 18)

**Primary Dependencies**: nenhuma nova — `openspout` (.xlsx) e
`barryvdh/laravel-dompdf` (PDF) e `spatie/laravel-activitylog` (histórico) já no
projeto; ApexCharts no front (009)

**Storage**: MySQL 8 — **6 tabelas novas** (`financial_categories`,
`financial_people`, `financial_payment_methods`, `financial_entries`,
`financial_settlements`, `financial_attachments`, `financial_recurrences`);
histórico reusa `activity_log`

**Testing**: PHPUnit Feature em `app_test` (invariantes do data-model,
integração espelhada sem duplicidade, não-regressão 001–009)

**Target Platform**: painel web (admin/financeiro)

**Performance Goals**: resultado de evento < 10s (SC-001); baixa < 30s
(SC-003); `settled_amount` como cache evita somas por linha em listas grandes

**Constraints**: valor > 0; situação derivada (nunca coluna); sem duplicidade
de espelho (UNIQUE source); sem alteração silenciosa de baixados; cortesia não
gera receita; cancelado fora dos saldos; coexistência com 008/009

**Scale/Scope**: ~7 tabelas, ~25 endpoints, 2 observers, 1 comando de
recorrência, ~10 telas, 8 user stories

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação |
|---|---|
| I. Standalone, RBAC | ✅ Reutiliza admin+treasury (sem papéis novos); menu próprio; standalone. |
| II. Estado derivado + corrida | ✅ Situação 100% derivada; `settled_amount` é cache recontável sob `lockForUpdate` a cada baixa/estorno (padrão sold_count). |
| III. Ponto único de baixa | ✅ Reforçado: a conta a receber de ingresso/patrocínio é **projeção** sincronizada ao `RegisterPayment` (005), não um segundo caminho de baixa do dinheiro do pedido. As baixas do módulo são de lançamentos próprios (domínio distinto). |
| IV. Segurança | ✅ Sem PAN; anexos validados (tipo/tamanho); atrás de papel; segredos em env. |
| V. Histórico | ✅ Soft delete + audit; cancelamento preserva histórico; edição de baixado exige justificativa logada; movimentações no activity_log imutável. |
| VI. Specs por área | ✅ Área funcional nova (financeiro central) — spec própria legítima; consome eventos/pagamentos/patrocínios sem alterá-los (observers aditivos). |
| Stack e convenções | ✅ Libs já presentes; API `{ data }` camelCase, DECIMAL(10,2), datas UTC/fuso do evento. |

**Resultado**: PASS (pré-Phase 0 e pós-Phase 1). Sem entradas em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/010-fluxo-caixa/
├── plan.md              # Este arquivo
├── research.md          # 10 decisões
├── data-model.md        # 6-7 tabelas + derivações + espelho
├── quickstart.md        # validação por user story
├── contracts/finance-api.md
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks (próximo passo)
```

### Source Code (repository root)

```text
app/
├── Domain/Events/Models/{FinancialEntry,FinancialSettlement,FinancialCategory,
│     FinancialPerson,FinancialPaymentMethod,FinancialAttachment,
│     FinancialRecurrence}.php
├── Domain/Events/Services/
│   ├── FinancialEntryService.php     # criar, editar(justificativa), settle(lock),
│   │                                 #   reverse, cancel, duplicate, parcelamento
│   ├── FinancialReportService.php    # dashboard, resultado do evento, relatórios
│   ├── FinancialSyncService.php      # upsert espelho por origem (idempotente)
│   └── FinancialRecurrenceService.php# gera lançamentos recorrentes
├── Domain/Events/Observers/{OrderObserver,SponsorshipInstallmentObserver}.php
├── Http/Controllers/Api/Finance/{EntryController,SettlementController,
│     DashboardController,CategoryController,PersonController,
│     PaymentMethodController,RecurrenceController,ReportController,
│     AttachmentController}.php
├── Console/Commands/GenerateRecurrences.php   # financial:generate-recurrences
database/migrations/…create_financial_tables.php
database/seeders/FinancialSeeder.php           # categorias + formas (+ demo)
routes/api.php                                 # grupo /finance (admin,treasury)
tests/Feature/Finance/{EntryTest,SettlementTest,EventResultTest,DashboardTest,
      CadastrosTest,InstallmentRecurrenceTest,AttachmentCancelReverseTest,
      ReportTest,MirrorSyncTest}.php
frontend/src/admin/financeiro/
├── FinanceiroLayout.jsx (abas) · Dashboard.jsx · ContasPagar.jsx ·
│   ContasReceber.jsx · LancamentoModal.jsx · LancamentoDetalhe.jsx ·
│   BaixaModal.jsx · Categorias.jsx · Pessoas.jsx · FormasPagamento.jsx ·
│   Relatorios.jsx
└── (menu "Financeiro" no AdminLayout p/ admin+treasury; App.jsx rotas)
```

**Structure Decision**: serviços de domínio concentram as regras (situação,
baixa sob lock, espelho, recorrência); observers mantêm o espelho sincronizado
sem tocar os services de pagamento; frontend reusa os componentes da 009.

## Fases de implementação (visão para /speckit-tasks)

1. **Setup**: migrations das 7 tabelas; models + relações; seeder (categorias +
   formas + demo); rotas `/finance` (admin,treasury); menu Financeiro no front.
2. **US1 — lançar/situar**: FinancialEntry + service (criar, validação valor>0,
   status derivado) + EntryController (list/create/show) + telas Contas a
   Pagar/Receber + LancamentoModal; testes.
3. **US2 — baixa**: FinancialSettlement + `settle()` sob lock + endpoint +
   BaixaModal + detalhe do lançamento; testes (parcial/total, saldo, edição
   com justificativa, histórico).
4. **US3 — centro de resultado**: FinancialReportService (resultado do evento) +
   endpoint + visão por evento (filtro) no front; testes de saldos.
5. **US4 — dashboard**: dashboard service + controller + Dashboard.jsx
   (cards + gráficos + vencidos + próximos); testes.
6. **US5 — cadastros**: categorias/pessoas/formas (CRUD + guarda de exclusão) +
   telas; testes.
7. **US6 — parcelamento/recorrência**: parcelamento no service; recurrence +
   comando; testes.
8. **US7 — anexos/cancelamento/estorno/histórico**: attachments + cancel +
   reverse + activity_log; telas; testes.
9. **US8 — relatórios**: FinancialReportService (11 relatórios) + export
   xlsx/PDF + Relatorios.jsx; testes prévia≡export.
10. **Integração espelhada (FR-020)**: observers + FinancialSyncService (upsert
    idempotente) para ingressos e patrocínios; testes de não-duplicidade e
    estados; cortesia fora.
11. **Polish**: quickstart, não-regressão 001–009, build, ROADMAP/status.

## Complexity Tracking

Sem violações constitucionais a justificar. O item III (espelho) foi desenhado
como projeção sincronizada exatamente para não criar segundo ponto de baixa.
