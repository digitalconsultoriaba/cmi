# Tasks: Módulo Financeiro — Contas a Pagar e Receber

**Input**: Design documents from `/specs/010-fluxo-caixa/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md,
contracts/finance-api.md, quickstart.md — e as specs 001–009 mergeadas.

**Tests**: INCLUÍDOS — exigência da constituição; os invariantes do data-model
viram asserções.

**Organization**: por user story (spec.md). **7 tabelas novas** (módulo novo).
Zero dependência nova. Telas 008/009 permanecem intactas.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizável (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1–US8 (mapeia para spec.md)

## Path Conventions

Backend: models/services/observers em `app/Domain/Events/`; controllers em
`app/Http/Controllers/Api/Finance/`; testes em `tests/Feature/Finance/`.
Frontend em `frontend/src/admin/financeiro/`.

---

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Criar a migration `database/migrations/…_create_financial_tables.php`
      com as 7 tabelas do data-model (`financial_categories`,
      `financial_people`, `financial_payment_methods`, `financial_entries` com
      UNIQUE (source_type, source_id), `financial_settlements`,
      `financial_attachments`, `financial_recurrences`); soft delete + audit
      nas de negócio; migrar
- [X] T002 [P] Criar os models em `app/Domain/Events/Models/` (FinancialEntry,
      FinancialSettlement, FinancialCategory, FinancialPerson,
      FinancialPaymentMethod, FinancialAttachment, FinancialRecurrence) com
      relações, casts DECIMAL e escopos; FinancialEntry com acessor de status
      derivado e saldo
- [X] T003 [P] Criar `database/seeders/FinancialSeeder.php` (categorias de
      receita/despesa + formas de pagamento seedadas; alguns lançamentos demo
      fora de produção) e registrar no `DatabaseSeeder.php`
- [X] T004 Adicionar o grupo de rotas `/finance` em `routes/api.php`
      (`auth:sanctum` + `require.role:admin,treasury`) e o item "Financeiro" no
      menu do `frontend/src/admin/AdminLayout.jsx` (admin+treasury) +
      `FinanceiroLayout.jsx` (abas) e rotas em `frontend/src/App.jsx`

---

## Phase 2: User Story 1 - Lançar e situar contas a pagar/receber (Priority: P1) 🎯 MVP

**Goal**: criar e listar lançamentos (a pagar/receber), vínculo opcional a
evento, valor > 0, situação derivada (em aberto/vencido).

**Independent Test**: quickstart.md §US1.

### Tests for User Story 1

- [X] T005 [P] [US1] Feature test `tests/Feature/Finance/EntryTest.php`: cria a
      pagar/receber (com e sem evento) → nasce "em aberto"; valor ≤ 0 → 422;
      vencimento passado sem baixa → status "vencido" derivado; listagem com
      filtros (direction/status/event/categoria/pessoa/texto) e paginação
      25/50/100; RBAC (admin/treasury 200; gate/attendee 403; anônimo 401)

### Implementation for User Story 1

- [X] T006 [US1] Criar `app/Domain/Events/Services/FinancialEntryService.php`
      método `create()` (validação valor > 0, origem, status inicial) e a
      derivação de status/saldo (em aberto/vencido/pago/parcial/cancelado)
- [X] T007 [US1] Criar `app/Http/Controllers/Api/Finance/EntryController.php`
      (`index` com filtros+paginação+totais, `store`, `show`) e o
      FormRequest de criação; shape do contrato
- [X] T008 [P] [US1] Telas `frontend/src/admin/financeiro/ContasPagar.jsx` e
      `ContasReceber.jsx` (listagem com filtros, status colorido, paginação) +
      `LancamentoModal.jsx` (criar, evento opcional, categoria/pessoa/forma)

**Checkpoint**: registrar e situar contas — núcleo do módulo.

---

## Phase 3: User Story 2 - Baixa (pagamento/recebimento) total ou parcial (Priority: P2)

**Goal**: baixa parcial/total sob lock, saldo restante, status automático,
histórico; edição de baixado exige justificativa.

**Independent Test**: quickstart.md §US2.

### Tests for User Story 2

- [X] T009 [P] [US2] Feature test `tests/Feature/Finance/SettlementTest.php`:
      baixa parcial → "parcial" com saldo correto; quitar → "pago/recebido";
      baixa > saldo → 422; entry cancelada não recebe baixa → 409; editar
      entry baixada sem justificativa → 422, com justificativa → 200 + log;
      recontagem de `settled_amount` sob concorrência (invariante 9)

### Implementation for User Story 2

- [X] T010 [US2] Adicionar ao `FinancialEntryService.php`: `settle()`
      (DB::transaction + lockForUpdate, recontagem de settled_amount, piso
      zero), `update()` (justificativa obrigatória se já baixado, log
      `financial.updated`); registrar movimentações no activity_log
- [X] T011 [US2] Criar `app/Http/Controllers/Api/Finance/SettlementController.php`
      (`settle`) e o `PUT` de edição no EntryController; validações do contrato
- [X] T012 [P] [US2] `frontend/src/admin/financeiro/LancamentoDetalhe.jsx`
      (valor original/pago/saldo, histórico, ações) e `BaixaModal.jsx` (data,
      valor, forma, observação); wire de editar com justificativa

**Checkpoint**: caixa operável — lançar e baixar.

---

## Phase 4: User Story 3 - Evento como centro de resultado (Priority: P3)

**Goal**: filtrar por evento e ver saldos + resultado; telas 008/009 intactas.

**Independent Test**: quickstart.md §US3.

### Tests for User Story 3

- [X] T013 [P] [US3] Feature test `tests/Feature/Finance/EventResultTest.php`:
      filtrar por evento traz só os dele; saldo previsto/realizado e resultado
      batendo; cancelados fora dos saldos; endpoint
      `GET /finance/events/{event}/result`; RBAC

### Implementation for User Story 3

- [X] T014 [US3] Criar `app/Domain/Events/Services/FinancialReportService.php`
      com `eventResult(Event)` (receita/despesa prevista/realizada, saldos,
      resultado; + informativos reusando derivações 008/009) e
      `app/Http/Controllers/Api/Finance/DashboardController.php@eventResult`
- [X] T015 [US3] Visão por evento no front: filtro de evento nas listagens e um
      bloco de resultado (cards) em `FinanceiroLayout.jsx`/Dashboard quando um
      evento é selecionado

**Checkpoint**: lucro/prejuízo por evento.

---

## Phase 5: User Story 4 - Dashboard financeiro geral (Priority: P4)

**Goal**: cards do mês, vencidos, saldos, resultado, melhores/piores eventos,
próximos vencimentos, com filtros.

**Independent Test**: quickstart.md §US4.

### Tests for User Story 4

- [X] T016 [P] [US4] Feature test `tests/Feature/Finance/DashboardTest.php`:
      cards (a receber/recebido/a pagar/pago no mês) batendo; vencidos
      agrupados (hoje/7 dias/30+); saldos e resultado; melhores/piores eventos;
      filtros recalculam; RBAC

### Implementation for User Story 4

- [X] T017 [US4] Adicionar `dashboard(filtros)` ao `FinancialReportService.php`
      e `DashboardController@show` (shape do contrato)
- [X] T018 [US4] `frontend/src/admin/financeiro/Dashboard.jsx` (cards +
      DonutChart/AreaChart da 009 + blocos de vencidos e próximos vencimentos +
      filtros por período/evento/categoria/situação/forma)

**Checkpoint**: leitura executiva do caixa.

---

## Phase 6: User Story 5 - Cadastros de apoio (Priority: P5)

**Goal**: categorias, pessoas e formas de pagamento (CRUD + guarda de exclusão).

**Independent Test**: quickstart.md §US5.

### Tests for User Story 5

- [X] T019 [P] [US5] Feature test `tests/Feature/Finance/CadastrosTest.php`:
      categoria em uso não exclui (409, inativa); pessoa/forma vinculadas
      aparecem em filtro; criar/editar/ativar/inativar; RBAC

### Implementation for User Story 5

- [X] T020 [US5] Controllers
      `app/Http/Controllers/Api/Finance/{CategoryController,PersonController,
      PaymentMethodController}.php` (CRUD; destroy de categoria/pessoa em uso →
      409, só inativa)
- [X] T021 [P] [US5] Telas `frontend/src/admin/financeiro/{Categorias,Pessoas,
      FormasPagamento}.jsx`

**Checkpoint**: dados organizados para filtros e relatórios.

---

## Phase 7: User Story 6 - Parcelamento e recorrência (Priority: P6)

**Goal**: gerar parcelas (baixa independente) e lançamentos recorrentes.

**Independent Test**: quickstart.md §US6.

### Tests for User Story 6

- [X] T022 [P] [US6] Feature test
      `tests/Feature/Finance/InstallmentRecurrenceTest.php`: 12.000 em 3
      parcelas = 3× 4.000 (soma fecha) com vencimentos; pagar uma não altera as
      outras; recorrência mensal com término gera N lançamentos pelo comando

### Implementation for User Story 6

- [X] T023 [US6] Parcelamento no `FinancialEntryService.php` (gera N entries
      irmãs com installment_group/number/total, resto na última) e
      `FinancialRecurrenceService.php` + `Console/Commands/GenerateRecurrences.php`
      (`financial:generate-recurrences`, limite seguro); agendar no scheduler
- [X] T024 [P] [US6] Opções de parcelamento no `LancamentoModal.jsx` (parcelas,
      1ª + frequência | datas personalizadas) e cadastro de recorrência

**Checkpoint**: despesas parceladas e recorrentes.

---

## Phase 8: User Story 7 - Anexos, cancelamento, estorno, histórico (Priority: P7)

**Goal**: anexar comprovantes; cancelar com motivo; estornar; histórico completo.

**Independent Test**: quickstart.md §US7.

### Tests for User Story 7

- [X] T025 [P] [US7] Feature test
      `tests/Feature/Finance/AttachmentCancelReverseTest.php`: anexar/baixar/
      remover (tipo/tamanho validados); cancelar com motivo (fora dos saldos,
      no histórico, só aparece com filtro de cancelados); estornar recebimento
      (saldo atualiza, log); RBAC dos anexos

### Implementation for User Story 7

- [X] T026 [US7] `FinancialEntryService.php`: `cancel()` (motivo, fora dos
      saldos, log), `reverse()` (estorno com motivo/valor, recontagem);
      `AttachmentController.php` (upload validado no disco public, download,
      remover)
- [X] T027 [P] [US7] No `LancamentoDetalhe.jsx`: anexos (upload/baixar/remover),
      botão cancelar (motivo), estorno (motivo/valor), aba de histórico

**Checkpoint**: governança e rastreabilidade do dinheiro.

---

## Phase 9: User Story 8 - Relatórios financeiros e exportação (Priority: P8)

**Goal**: relatórios com prévia + export (.xlsx/PDF) respeitando filtros.

**Independent Test**: quickstart.md §US8.

### Tests for User Story 8

- [X] T028 [P] [US8] Feature test `tests/Feature/Finance/ReportTest.php`:
      prévia e export do MESMO recorte batem (abrir binário xlsx com o reader);
      tipos (geral/evento/a pagar/a receber/categoria/pessoa/forma/…);
      filtro por período; RBAC

### Implementation for User Story 8

- [X] T029 [US8] `FinancialReportService.php`: `reportPreview(type, filtros)` e
      `reportRows()` (fonte única) para os 11 relatórios; export xlsx (openspout,
      streaming) e PDF (dompdf) em `ReportController.php`
- [X] T030 [P] [US8] `frontend/src/admin/financeiro/Relatorios.jsx` (seletor +
      filtros + prévia + Exportar .xlsx/PDF)

**Checkpoint**: prestação de contas completa.

---

## Phase 10: Integração espelhada de ingressos/patrocínios (FR-020)

**Goal**: cada pedido/parcela de patrocínio espelha UMA conta a receber
sincronizada, sem duplicidade; cortesias fora.

**Independent Test**: quickstart.md §"Integração automática".

### Tests

- [X] T031 [P] Feature test `tests/Feature/Finance/MirrorSyncTest.php`: comprar
      ingresso (pendente) → conta a receber "em aberto" espelhada; pagar →
      "recebido"; cancelar → cancelada; reembolsar → estorno; reprocessar não
      cria segundo lançamento (UNIQUE source); cortesia NÃO gera receita;
      patrocínio (e parcela) espelhado; espelhadas são read-only (editar → 409)

### Implementation

- [X] T032 Criar `app/Domain/Events/Services/FinancialSyncService.php`
      (upsert por (source_type, source_id): Order e SponsorshipInstallment →
      financial_entry receivable, status espelha o estado; cortesia ignorada)
- [X] T033 Criar `app/Domain/Events/Observers/{OrderObserver,
      SponsorshipInstallmentObserver}.php` chamando o FinancialSyncService no
      `saved`; registrar no `AppServiceProvider`; marcar entries espelhadas como
      read-only nas ações de edição

**Checkpoint**: financeiro reflete ingressos e patrocínios sem duplicar.

---

## Phase 11: Polish & Cross-Cutting Concerns

- [X] T034 Executar `specs/010-fluxo-caixa/quickstart.md` de ponta a ponta
      (fluxos manuais das 8 US + integração espelhada) e corrigir o que falhar
- [X] T035 [P] Não-regressão: suítes 001–009 verdes; build do frontend ok;
      `.env`/segredos fora do VCS
- [X] T036 Atualizar `ROADMAP.md` (010 ✅) e
      `specs/010-fluxo-caixa/spec.md` (Status: Draft → Implemented)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (P1)** → US1 → US2 → US3/US4 → US5 → US6 → US7 → US8 → Integração →
  Polish
- **US1 primeiro** (P1, MVP): sem lançamento não há módulo
- **US2** depende de US1 (baixa sobre o lançamento); **US3/US4** dependem de
  US1+US2 (saldos vêm das baixas); **US5** é independente (cadastros) mas
  enriquece US1; **Integração (FR-020)** depende do FinancialEntry existir
  (Setup+US1) e pode ir por último, pois é aditiva

### Key task-level dependencies

- T001 (migration) antes de tudo; T002/T003 dependem de T001
- T006 (service create) antes de T007; T010 (settle) estende T006 (mesmo
  arquivo — sequenciar); T014/T017/T029 estendem o FinancialReportService
- Testes [P] (T005/T009/T013/T016/T019/T022/T025/T028/T031) antes das
  implementações da sua US
- T032 (sync) antes de T033 (observers)

### Parallel Opportunities

- Setup: T002 ∥ T003 (após T001)
- Cada teste [P] com o início da sua fase (teste primeiro no backend)
- Telas [P] (T008/T012/T021/T024/T027/T030) em paralelo com o backend da sua US
- T035 ∥ T036 no Polish

## Parallel Example: US1

```bash
Task: "T005 EntryTest (teste primeiro)"
# depois: T006 (service) → T007 (controller) ; T008 (telas) em paralelo
```

## Implementation Strategy

**MVP incremental**: Fases 1–3 (US1+US2) entregam o caixa utilizável (lançar +
baixar). US3/US4 dão a leitura gerencial; US5–US8 completam cadastros,
parcelamento, governança e relatórios; a Integração espelhada (Fase 10) fecha o
vínculo com ingressos/patrocínios. **Recomendado entregar por partes** (US1–US2
primeiro; validar; seguir). Merge na `main` só com a suíte inteira verde
(incluindo não-regressão 001–009) e os fluxos manuais conferidos.
