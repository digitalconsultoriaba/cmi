# Tasks: Painel e Relatórios

**Input**: Design documents from `/specs/008-painel-relatorios/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md,
contracts/reports-api.md, quickstart.md — e as specs 001–007 mergeadas.

**Tests**: INCLUÍDOS — exigência da constituição; os invariantes do data-model
viram asserções.

**Organization**: agrupado por user story. **A US4 (auditoria) vem primeiro**
por decisão do plano: é transversal — instrumenta os services existentes antes
de o resto nascer. **Uma migration nova** (a do pacote de auditoria).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizável (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1–US4 (mapeia para spec.md)

## Path Conventions

Services em `app/Domain/Events/Services/`; controllers em
`app/Http/Controllers/Api/{Admin,Treasury}/`; testes em
`tests/Feature/Reports/`.

---

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Instalar dependências via Docker (`docker compose run --rm php
      composer require openspout/openspout spatie/laravel-activitylog`),
      publicar a migration do activitylog (`vendor:publish` +
      `php artisan migrate`) e conferir a tabela `activity_log`
- [X] T002 [P] Adicionar `timezone => 'America/Sao_Paulo'` em
      `config/events.php` e as rotas novas em `routes/api.php`: no grupo
      `/admin` — `GET /dashboard`, `GET /audit`, `GET /reports/attendees.xlsx`,
      `GET /reports/attendance.xlsx`; no grupo `/treasury` — `GET /finance`,
      `GET /reports/finance.xlsx` (controllers das fases seguintes)

---

## Phase 2: User Story 4 - Trilha de auditoria (Priority: P4 — executada primeiro por ser transversal)

**Goal**: toda ação sensível gera exatamente 1 registro imutável com autor
(pessoa ou sistema), dentro da transação da ação; consulta paginada só p/ admin.

**Independent Test**: quickstart.md §US4.

### Tests for User Story 4

- [X] T003 [P] [US4] Feature test em `tests/Feature/Reports/AuditTrailTest.php`:
      baixa manual, estorno, cancelamento de ingresso, transferência, emissão
      de cortesia, check-in e edição de config do evento geram exatamente 1
      registro cada (`log_name` do data-model, causer = operador, subject
      certo); expiração automática de reserva → causer NULL (sistema);
      `GET /api/admin/audit` pagina (mais recente primeiro) e filtra por
      `action` e `from/to`; shape do contrato (description pt-BR, subject com
      código público, properties); treasury/gate/attendee → 403, anônimo → 401
      (primeiro); nenhuma rota de escrita registrada para `/api/admin/audit`

### Implementation for User Story 4

- [X] T004 [US4] Instrumentar os services existentes com `activity()` (log
      explícito, dentro da transação, `log_name` padronizado do data-model,
      description pt-BR com código público, properties mínimas):
      `app/Domain/Events/Services/RegisterPayment.php` (payment.registered),
      `RefundPayment.php` (payment.refunded), `TicketLifecycleService.php`
      (ticket.cancelled, ticket.transferred), `CancelEventCascade.php`
      (event.cancelled), `CourtesyResolver.php` (courtesy.redeemed),
      `SponsorshipService.php` (courtesy.issued), `EventConfigService.php`
      (event.updated), `CheckinService.php` (ticket.checked_in) — causer via
      auth quando houver, senão NULL
- [X] T005 [US4] Criar `app/Http/Controllers/Api/Admin/AuditLogController.php`
      método `index`: paginação (mais recente primeiro), filtros `action`,
      `from`/`to` (fuso do evento), shape do contrato (subject resolvido para
      tipo+código público, causer name ou null)
- [X] T006 [US4] Criar `frontend/src/admin/pages/Auditoria.jsx` (admin):
      lista paginada com autor/ação/descrição/momento, filtros por tipo de
      ação e período, badge "sistema" p/ causer nulo; rota em
      `frontend/src/App.jsx` e item "Auditoria" (roles admin) em
      `frontend/src/admin/AdminLayout.jsx`

**Checkpoint**: tudo que as US1–US3 tocarem já nasce auditado.

---

## Phase 3: User Story 1 - Dashboard do evento (Priority: P1) 🎯 MVP

**Goal**: fotografia completa derivada na consulta — pessoas × capacidade,
receita prevista × confirmada, grade de camisas que FECHA, lotes, formas,
cortesias, presenças.

**Independent Test**: quickstart.md §US1.

### Tests for User Story 1

- [X] T007 [P] [US1] Feature test em `tests/Feature/Reports/DashboardTest.php`:
      pessoas confirmadas (casal = 2) × capacidade; receita confirmada usa o
      valor RECEBIDO (baixa manual com desconto) e abate estornos parciais;
      prevista = confirmada + pedidos em aberto; grade de camisas por pessoa
      (titular + acompanhante, "não informado" p/ nulos) com Σ ≡ pessoas
      (invariante 1); por lote (vendidos×limite×receita) e por forma; estorno
      reflete imediatamente na recarga (invariante 7); evento sem vendas →
      zeros coerentes; treasury/gate/attendee → 403, anônimo → 401 (primeiro)

### Implementation for User Story 1

- [X] T008 [US1] Criar `app/Domain/Events/Services/ReportService.php` método
      `dashboard(Event $event): array` — agregações SQL derivadas conforme
      data-model (pessoas via GREATEST, receita por payments paid − refunds,
      grade holder+companion, byLot, byMethod, cortesias, presenças reusando a
      régua da 007)
- [X] T009 [US1] Criar `app/Http/Controllers/Api/Admin/DashboardController.php`
      método `show` (evento ativo + shape do contrato)
- [X] T010 [US1] Criar `frontend/src/admin/pages/Dashboard.jsx` (cards de
      pessoas/receita, grade de camisas, tabelas lote/forma, presenças);
      `PainelHome` do admin passa a cair no Dashboard em
      `frontend/src/App.jsx`; item "Dashboard" (admin) no topo do MENU em
      `frontend/src/admin/AdminLayout.jsx`

**Checkpoint**: a pergunta diária ("como estamos?") respondida numa tela.

---

## Phase 4: User Story 2 - Financeiro da tesouraria (Priority: P2)

**Goal**: consolidado por forma + estornos + patrocínios, com filtro de
período no fuso do evento aplicado uniformemente.

**Independent Test**: quickstart.md §US2.

### Tests for User Story 2

- [X] T011 [P] [US2] Feature test em `tests/Feature/Reports/FinanceTest.php`:
      total = Σ formas (invariante 2); filtro `month/year` e `from/to` no fuso
      do evento (pagamento 23h BRT do dia 30 entra no mês certo; `from > to` →
      422); pago-e-estornado no período aparece nas duas seções (invariante 4)
      e `net` = confirmado − estornado; parcelas de patrocínio vencidas em
      `overdue`; pendentes como fotografia (sem filtro); treasury E admin →
      200, gate/attendee → 403, anônimo → 401 (primeiro)

### Implementation for User Story 2

- [X] T012 [US2] Adicionar `finance(Event $event, ?CarbonPeriod $period): array`
      ao `app/Domain/Events/Services/ReportService.php` (byMethod/total/
      refunds/net/pendingOrders/sponsorships; conversão período fuso→UTC sobre
      `paid_at`) e criar
      `app/Http/Controllers/Api/Treasury/FinanceController.php` método `show`
      (validação dos filtros, shape do contrato)
- [X] T013 [US2] Criar `frontend/src/admin/pages/Financeiro.jsx`
      (treasury+admin): filtro mês/ano e intervalo, cards total/estornos/
      líquido, tabela por forma, seção patrocínios com atraso em destaque;
      rota em `frontend/src/App.jsx` e item "Financeiro" (roles treasury,
      admin) em `frontend/src/admin/AdminLayout.jsx`

**Checkpoint**: fechamento de caixa mensal possível na tela.

---

## Phase 5: User Story 3 - Relatórios exportáveis .xlsx (Priority: P3)

**Goal**: 3 planilhas em streaming, geradas do MESMO service das telas,
respeitando filtros.

**Independent Test**: quickstart.md §US3.

### Tests for User Story 3

- [X] T014 [P] [US3] Feature test em `tests/Feature/Reports/ExportTest.php`:
      cada rota → 200 com content-type de xlsx e `Content-Disposition`
      attachment; conteúdo validado (abrir o binário com o reader do openspout
      no teste): inscritos tem 1 linha por PESSOA (acompanhante em linha
      própria com a própria camisa) e exclui cancelado/transferido/não pago
      (invariante 5 + FR-007); financeiro filtrado = linhas do consolidado;
      RBAC por rota (attendees/attendance só admin; finance treasury+admin);
      anônimo → 401 (primeiro)

### Implementation for User Story 3

- [X] T015 [US3] Criar `app/Domain/Events/Services/ReportExportService.php`:
      3 writers openspout em `StreamedResponse` (attendees, finance com seção
      de estornos, attendance), cabeçalhos pt-BR, datas no fuso do evento,
      linhas vindas do `ReportService`
- [X] T016 [US3] Criar
      `app/Http/Controllers/Api/Admin/ReportExportController.php` (attendees,
      attendance), adicionar `export` ao
      `app/Http/Controllers/Api/Treasury/FinanceController.php` (mesmos
      filtros do `show`) e botões "Exportar .xlsx" nas páginas
      `frontend/src/admin/pages/{Dashboard,Financeiro,Checkin}.jsx` (link
      direto autenticado por cookie)

**Checkpoint**: todas as US completas — produção de camisas e prestação de
contas viáveis fora do sistema.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T017 Executar `specs/008-painel-relatorios/quickstart.md` de ponta a
      ponta (suíte + manual: dashboard conferido contra Presenças/Tesouraria;
      financeiro filtrado; 3 planilhas baixadas e abertas; baixa manual +
      check-in aparecendo na Auditoria) e corrigir o que falhar
- [X] T018 [P] Varredura: suítes 001–007 verdes; build do frontend ok;
      `.env`/segredos fora do VCS; nenhuma coluna além da `activity_log`
- [X] T019 Atualizar `ROADMAP.md` (008 ✅ — **MVP completo**) e
      `specs/008-painel-relatorios/spec.md` (Status: Draft → Implemented)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** → US4 → US1 → US2 → US3 → Polish
- **US4 primeiro** (decisão do plano): transversal, instrumenta os services
  que as demais consomem — evita retrabalho de re-testar ações sem log
- **US1 antes da US2/US3**: cria o `ReportService` que ambas estendem/consomem
- **US3 por último**: exporta o que US1/US2 calculam (mesmo service)

### Key task-level dependencies

- T001 (pacotes/migration) antes de T003/T004 (auditoria usa activity())
- T004 (instrumentação) antes de T005 (endpoint lê os logs)
- T008 (ReportService) antes de T012 (finance no mesmo arquivo) e T015 (export
  consome)
- T012 antes de T016 (FinanceController mesmo arquivo)
- T003/T007/T011/T014 (testes) antes das implementações correspondentes

### Parallel Opportunities

- Setup: T001 ∥ T002
- Cada teste [P] em paralelo com o início da sua fase (teste primeiro)
- T006 (Auditoria.jsx) ∥ T007/T008 (arquivos distintos)
- T018 ∥ T019 no Polish

## Parallel Example: pós-US4

```bash
Task: "T006 Auditoria.jsx (frontend da US4)"
Task: "T007 DashboardTest (teste primeiro da US1)"
```

## Implementation Strategy

**Auditoria primeiro, MVP em seguida**: Fase 2 (US4) blinda o histórico; Fase
3 (US1) entrega o valor P1 (a tela diária da organização); US2 e US3 completam
tesouraria e planilhas reutilizando o mesmo `ReportService` — divergência
tela×planilha impossível por construção. Merge na `main` só com a suíte
inteira verde e as planilhas abertas de verdade — **fecha o roadmap do MVP**.
