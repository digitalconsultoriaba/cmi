# Implementation Plan: Painel e Relatórios

**Branch**: `008-painel-relatorios` | **Date**: 2026-07-04 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/008-painel-relatorios/spec.md`

## Summary

A visão gerencial que fecha o MVP: `ReportService` concentra TODAS as
derivações (dashboard, financeiro com filtro de período no fuso do evento,
presenças) — telas e planilhas consomem o mesmo service, então nunca divergem.
Exports .xlsx em streaming com `openspout` (constituição). Trilha de auditoria
com `spatie/laravel-activitylog` (a dívida da 001), registrada explicitamente
nos services de negócio dentro das transações — imutável, somente leitura,
`causer` nulo = sistema. Frontend: `Dashboard.jsx` (home do admin),
`Financeiro.jsx` (treasury+admin) e `Auditoria.jsx` (admin) no chrome
existente. **Uma migration nova** (a do pacote de auditoria).

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12); JavaScript ES2022 (React 18)

**Primary Dependencies**: novas (backend) — `openspout/openspout` (.xlsx em
streaming; já fixado na constituição) e `spatie/laravel-activitylog` (trilha;
adoção registrada desde a 001)

**Storage**: MySQL 8 — 1 tabela nova (`activity_log`, migration do pacote);
todo o resto derivado na consulta (zero contadores materializados)

**Testing**: PHPUnit Feature em `app_test`; invariantes do data-model (grade
fecha, total = Σ formas, 1 log por ação) como asserções

**Target Platform**: painel desktop (organização/tesouraria); exports abertos
em Excel/LibreOffice

**Project Type**: web application — API + SPA existentes

**Performance Goals**: dashboard < 10s do login à resposta (SC-001);
fechamento mensal < 2min (SC-006); export streaming sem limite de linhas

**Constraints**: números 100% derivados (FR-012); valores pelo efetivamente
recebido (FR-005); período no fuso America/Sao_Paulo (FR-011); trilha imutável
(FR-009); mesma régua de elegibilidade da portaria nos relatórios (FR-007)

**Scale/Scope**: 6 endpoints (2 JSON + 3 exports + 1 auditoria), 3 páginas,
1 service de domínio + 1 de export, 1 migration, 4 user stories

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação |
|---|---|
| I. Standalone, RBAC | ✅ Grupos de rota existentes; dashboard/auditoria = admin, financeiro = treasury+admin (FR-010); nenhum papel novo. |
| II. Estado derivado + corrida | ✅ O coração da spec: tudo derivado na consulta via `ReportService`; nenhum contador novo; leituras puras (sem corrida de escrita). |
| III. Ponto único de baixa | ✅ N/A — spec só LÊ pagamentos; auditoria instrumenta o `RegisterPayment` sem tocar sua lógica. |
| IV. Segurança | ✅ Exports atrás de papel; planilhas sem dados de cartão (só brand/last4 nem entram); trilha não expõe payloads sensíveis (properties selecionadas). |
| V. Histórico | ✅ Reforçado: `activity_log` imutável (sem rotas de escrita), registro na mesma transação da ação; nada apagado. |
| VI. Specs por área | ✅ Consome derivações canônicas das 003/004/005/006/007 sem alterá-las; instrumentação de auditoria é aditiva. |
| Stack e convenções | ✅ `openspout` já fixado; `spatie/laravel-activitylog` é a adoção prevista na 001 (Decisão 5) — registrada em research. |

**Resultado**: PASS (pré-Phase 0 e pós-Phase 1). Sem entradas em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/008-painel-relatorios/
├── plan.md              # Este arquivo
├── research.md          # 8 decisões
├── data-model.md        # 1 tabela (activity_log) + fórmulas derivadas
├── quickstart.md        # validação por user story
├── contracts/reports-api.md
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks (próximo passo)
```

### Source Code (repository root)

```text
app/
├── Domain/Events/Services/
│   ├── ReportService.php            # dashboard/finance/attendees (fórmulas canônicas)
│   ├── ReportExportService.php      # writers openspout (streaming)
│   └── (instrumentação de auditoria nos services existentes:
│        RegisterPayment, RefundPayment, TicketLifecycleService,
│        CancelEventCascade, CourtesyResolver, SponsorshipService,
│        EventConfigService, CheckinService)
├── Http/Controllers/Api/
│   ├── Admin/DashboardController.php    # GET dashboard
│   ├── Admin/AuditLogController.php     # GET audit (index paginado)
│   ├── Admin/ReportExportController.php # attendees/attendance .xlsx
│   └── Treasury/FinanceController.php   # GET finance + finance.xlsx
config/events.php                        # + timezone
database/migrations/…create_activity_log_table.php  # do pacote (publicada)
routes/api.php                           # rotas novas nos grupos existentes
tests/Feature/Reports/{DashboardTest,FinanceTest,ExportTest,AuditTrailTest}.php
frontend/src/
├── admin/pages/Dashboard.jsx            # home do admin
├── admin/pages/Financeiro.jsx           # treasury+admin
├── admin/pages/Auditoria.jsx            # admin
├── admin/AdminLayout.jsx                # + itens de menu
└── App.jsx                              # rotas; PainelHome admin → Dashboard
```

**Structure Decision**: fórmulas em UM service (tela e planilha nunca
divergem); export separado (formatação ≠ cálculo); auditoria como chamadas
explícitas nos services de negócio (ação certa, autor certo, mesma transação).

## Fases de implementação (visão para /speckit-tasks)

1. **Setup**: composer `openspout` + `spatie/laravel-activitylog`; publicar
   migration; config `events.timezone`; helper de log de auditoria.
2. **US4 — auditoria primeiro** (é transversal): instrumentar os services
   existentes + endpoint `GET /api/admin/audit` + testes (1 log por ação,
   causer sistema, imutabilidade); assim US1–US3 já nascem auditadas onde toca.
3. **US1 — dashboard**: `ReportService::dashboard()` + controller + testes de
   invariantes + `Dashboard.jsx` (home do admin).
4. **US2 — financeiro**: `ReportService::finance()` (filtro de período no fuso)
   + controller + testes + `Financeiro.jsx`.
5. **US3 — exports**: `ReportExportService` (3 planilhas via openspout,
   streaming) + rotas + testes de RBAC/headers + botões de download.
6. **Polish**: `Auditoria.jsx`, quickstart manual (abrir planilhas), suítes
   001–007, ROADMAP/status — **fecha o MVP**.

## Complexity Tracking

Sem violações constitucionais a justificar.
