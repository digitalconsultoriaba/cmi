---

description: "Task list — Aba Orçamento / Previsão Financeira do Evento (spec 011)"
---

# Tasks: Aba Orçamento / Previsão Financeira do Evento

**Input**: Design documents from `/specs/011-orcamento/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/budget-api.md, quickstart.md

**Tests**: INCLUÍDOS — a constituição exige Feature tests (MySQL `app_test`) cobrindo caminho feliz + regras (409/403/422) antes do merge, e o quickstart define as suites.

**Organization**: Tarefas agrupadas por user story (P1→P6) para entrega incremental e teste independente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1..US6 conforme spec.md
- Caminhos de arquivo são absolutos ao repo (`app/…`, `frontend/…`).

## Path Conventions

- Backend: domínio em `app/Domain/Events/`, controllers em `app/Http/Controllers/Api/Admin/`, requests em `app/Http/Requests/Admin/`, resources em `app/Http/Resources/Admin/`, rotas em `routes/api.php`.
- Frontend: `frontend/src/admin/eventos/abas/` (aba do evento) e subpasta `orcamento/`.
- Testes: `tests/Feature/Budget/`.
- PHP roda via Docker: `docker compose run --rm php …`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: estrutura de dados e constantes de status/categorias.

- [X] T001 Criar migration `database/migrations/2026_07_05_100000_create_budget_tables.php` com as 5 tabelas (`budget_plans`, `budget_cost_items`, `budget_ticket_lots`, `budget_sponsorships`, `budget_scenarios`) conforme data-model.md — todas com soft delete + `created_by`/`updated_by`, dinheiro DECIMAL(10,2), FK `event_id` unique em `budget_plans`, FKs `financial_entry_id` nullable em itens/patrocínios.
- [X] T002 [P] Criar classes de constantes `app/Domain/Events/Models/BudgetCostItemStatus.php` (planned/quoted/approved/contracted/cancelled) e `app/Domain/Events/Models/BudgetSponsorshipStatus.php` (planned/negotiating/confirmed/received/lost/cancelled).
- [X] T003 [P] Criar `app/Domain/Events/Models/BudgetCategory.php` com a lista padrão de categorias de custo (FR-007) para validação/agrupamento.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: modelos, cálculo derivado, endpoint base do plano, resources e casca da aba — pré-requisito de TODAS as histórias.

**⚠️ CRITICAL**: nenhuma user story começa antes desta fase.

- [X] T004 [P] Model `app/Domain/Events/Models/BudgetPlan.php` (estende BaseModel; relations hasMany costItems/ticketLots/sponsorships/scenarios; belongsTo Event; helper `totalParticipants()`).
- [X] T005 [P] Model `app/Domain/Events/Models/BudgetCostItem.php` (belongsTo plan; belongsTo FinancialEntry; casts decimais; regra de `total_amount` no `saving`).
- [X] T006 [P] Model `app/Domain/Events/Models/BudgetTicketLot.php` (belongsTo plan; accessor `expectedRevenue`).
- [X] T007 [P] Model `app/Domain/Events/Models/BudgetSponsorship.php` (belongsTo plan; belongsTo FinancialEntry; accessor `expectedRevenue`).
- [X] T008 [P] Model `app/Domain/Events/Models/BudgetScenario.php` (belongsTo plan; accessor `closesBudget`).
- [X] T009 Adicionar relação `budgetPlan(): HasOne` em `app/Domain/Events/Models/Event.php`.
- [X] T010 Service `app/Domain/Events/Services/BudgetCalculator.php` — deriva TODO o resumo (custo total sem cancelados, receitas previstas, resultado, investimento próprio, ticket médio, custo por participante/pagante, ponto de equilíbrio, custo com margem, classificação) e os alertas (FR-027); trata divisores zero → `null` (SC-007).
- [X] T011 [P] Resources em `app/Http/Resources/Admin/`: `BudgetPlanResource`, `BudgetCostItemResource`, `BudgetTicketLotResource`, `BudgetSponsorshipResource`, `BudgetScenarioResource`, `BudgetSummaryResource` (camelCase, valores string; `convertible` derivado de `financial_entry_id`).
- [X] T012 Controller base `app/Http/Controllers/Api/Admin/BudgetController.php` com `show` (firstOrCreate do plano + filhos + summary via calculator) e `update` (cabeçalho do plano) + `app/Http/Requests/Admin/UpdateBudgetPlanRequest.php` (inteiros ≥ 0, otherRevenue ≥ 0, safetyMarginPct 0–100).
- [X] T013 Registrar grupo de rotas em `routes/api.php` sob `events/{event}/budget` com `require.role:admin,treasury` (GET/PUT base; endpoints das histórias adicionados nas fases seguintes).
- [X] T014 Frontend: adicionar aba "Orçamento" em `frontend/src/admin/eventos/EventoLayout.jsx` (antes de "Financeiro") e rota `orcamento` em `frontend/src/App.jsx`.
- [X] T015 Frontend: casca `frontend/src/admin/eventos/abas/Orcamento.jsx` (carrega `/admin/events/:id/budget` via React Query) + `frontend/src/admin/eventos/abas/orcamento/ResumoCards.jsx` (cards do summary com cores verde/amarelo/vermelho/azul e "—" para nulos).

**Checkpoint**: base pronta — a aba abre, cria o plano e mostra o resumo (zerado); histórias podem começar.

---

## Phase 3: User Story 1 — Planilha de custos + resumo (Priority: P1) 🎯 MVP

**Goal**: cadastrar itens de custo e ver custo total, resultado previsto e investimento próprio.

**Independent Test**: cadastrar 3 itens (um cancelado) e verificar que o custo total soma só os ativos e o resultado = receita − custo.

### Tests for User Story 1 ⚠️ (escrever antes; devem FALHAR primeiro)

- [X] T016 [P] [US1] `tests/Feature/Budget/BudgetSummaryTest.php` — derivação de `total_amount` (qtd×unitário e só-total), exclusão de `cancelled` do custo, resultado/investimento próprio (SC-002/SC-006), divisor zero → indicador nulo (SC-007).

### Implementation for User Story 1

- [X] T017 [US1] `app/Http/Requests/Admin/BudgetCostItemRequest.php` — validação (description/category obrigatórios; valores > 0 com 2 casas; status no enum; categoria na lista).
- [X] T018 [US1] `app/Http/Controllers/Api/Admin/BudgetCostItemController.php` — `store`, `update`, `destroy` (soft delete), `duplicate` (sem vínculo financeiro); registra auditoria.
- [X] T019 [US1] Rotas de cost-items em `routes/api.php` (POST/PUT/DELETE + `/duplicate`) sob o grupo do budget.
- [X] T020 [P] [US1] `frontend/src/admin/eventos/abas/orcamento/ItensCusto.jsx` — tabela + modal (`Modal` reutilizável) de criar/editar/duplicar/excluir, cálculo de total no formulário, badge de status sólido.
- [X] T021 [US1] Integrar `ItensCusto` no `Orcamento.jsx` e invalidar a query do budget após mutações (resumo atualiza).

**Checkpoint**: US1 funcional — planilha de custos + resumo com custo/resultado/investimento.

---

## Phase 4: User Story 2 — Simular ingressos, patrocínios e participantes (Priority: P2)

**Goal**: lotes previstos, cotas de patrocínio e estimativa de participantes alimentam ticket médio, custo por participante e ponto de equilíbrio.

**Independent Test**: 3 lotes (200×250/300/350) → receita R$180.000; com custo 250k e patrocínio 100k e ticket 300 → equilíbrio ~500 pagantes.

### Tests for User Story 2 ⚠️

- [X] T022 [P] [US2] `tests/Feature/Budget/BudgetRevenueTest.php` — receita por lote, patrocínio previsto×confirmado (lost/cancelled fora), ponto de equilíbrio e ticket médio dos exemplos (SC-003), cortesia nunca vira receita (FR-032).

### Implementation for User Story 2

- [X] T023 [P] [US2] `app/Http/Requests/Admin/BudgetTicketLotRequest.php` e `app/Http/Requests/Admin/BudgetSponsorshipRequest.php` (valores > 0; quantidades ≥ 0/≥ 1; status no enum).
- [X] T024 [US2] `app/Http/Controllers/Api/Admin/BudgetTicketLotController.php` (store/update/destroy) e rotas em `routes/api.php`.
- [X] T025 [US2] `app/Http/Controllers/Api/Admin/BudgetSponsorshipController.php` (store/update/destroy) e rotas em `routes/api.php`.
- [X] T026 [P] [US2] `frontend/src/admin/eventos/abas/orcamento/Lotes.jsx` — tabela + modal de lotes previstos (receita prevista calculada).
- [X] T027 [P] [US2] `frontend/src/admin/eventos/abas/orcamento/Patrocinios.jsx` — tabela + modal de cotas (dois totais: previsto × confirmado).
- [X] T028 [P] [US2] `frontend/src/admin/eventos/abas/orcamento/Participantes.jsx` — form (modal) dos segmentos + `other_revenue` + `safety_margin_pct` (PUT no plano).
- [X] T029 [US2] Integrar Lotes/Patrocínios/Participantes no `Orcamento.jsx`; exibir ponto de equilíbrio e custo por participante no `ResumoCards`.

**Checkpoint**: US1 + US2 funcionam — modelo de receita e indicadores de decisão completos.

---

## Phase 5: User Story 3 — Converter previsão em financeiro real (Priority: P3)

**Goal**: item→conta a pagar; patrocínio→conta a receber, sem duplicidade.

**Independent Test**: "Sonorização R$26.000" → gerar conta a pagar cria 1 lançamento vinculado; 2ª tentativa → 409; excluir a linha não apaga o lançamento.

### Tests for User Story 3 ⚠️

- [X] T030 [P] [US3] `tests/Feature/Budget/BudgetConversionTest.php` — `generate-payable`/`generate-receivable` criam exatamente 1 FinancialEntry (SC-004); duplicidade → 409 `already_converted`; patrocínio lost/cancelled → 409 `invalid_sponsorship_status`; exclusão da linha preserva o lançamento (FR-025).

### Implementation for User Story 3

- [X] T031 [US3] Service `app/Domain/Events/Services/BudgetConversionService.php` — `toPayable(item)` e `toReceivable(sponsorship)` em `DB::transaction`, chamando `FinancialEntryService::create` (direction/origin/event_id/description/amount/due_date; category por match de nome), gravando `financial_entry_id` e logando; guardas de duplicidade/status → `DomainRuleViolation`.
- [X] T032 [US3] Endpoints `POST …/cost-items/{item}/generate-payable` e `POST …/sponsorships/{sponsorship}/generate-receivable` nos controllers de US1/US2 + rotas em `routes/api.php`.
- [X] T033 [US3] Frontend: botões "Gerar conta a pagar/receber" em `ItensCusto.jsx`/`Patrocinios.jsx`, com estado "conta gerada" e tratamento do 409 (aviso de duplicidade).

**Checkpoint**: previsão vira financeiro real com idempotência; histórico preservado.

---

## Phase 6: User Story 4 — Comparativo orçado × realizado (Priority: P4)

**Goal**: cruzar previsão com Financeiro/vendas reais + % de atingimento.

**Independent Test**: meta 500 / 320 vendidos → 64%; despesa prevista 250k vs paga 230k → "abaixo do orçamento".

### Tests for User Story 4 ⚠️

- [X] T034 [P] [US4] `tests/Feature/Budget/BudgetComparisonTest.php` — pares orçado×realizado usando `FinancialReportService::eventResult` + vendas reais; % de atingimento; evento sem dados reais → zeros coerentes (SC-005).

### Implementation for User Story 4

- [X] T035 [US4] Service `app/Domain/Events/Services/BudgetComparisonService.php` — monta os pares (custo/receita/patrocínio/ingressos/resultado) e `attainmentPct`, lendo realizado de `FinancialReportService::eventResult($event)` e das vendas reais do evento.
- [X] T036 [US4] Endpoint `GET …/budget/comparison` em `BudgetController` + `app/Http/Resources/Admin/BudgetComparisonResource.php` + rota.
- [X] T037 [P] [US4] `frontend/src/admin/eventos/abas/orcamento/Comparativo.jsx` — tabela orçado×realizado com status (under/on/over) e barra de atingimento; integrar no `Orcamento.jsx`.

**Checkpoint**: acompanhamento de execução disponível.

---

## Phase 7: User Story 5 — Simuladores e alertas (Priority: P5)

**Goal**: cenários (Conservador/Realista/Otimista), preço mínimo, margem de segurança e alertas.

**Independent Test**: custo 250k, patrocínio 100k, 500 pagantes → ingresso mínimo R$300; margem 10% → custo com margem 275k sem alterar o base.

### Tests for User Story 5 ⚠️

- [X] T038 [P] [US5] `tests/Feature/Budget/BudgetScenarioTest.php` — upsert dos 3 cenários e `closesBudget` derivado; preço mínimo dos exemplos (SC-003); margem não altera o custo base.

### Implementation for User Story 5

- [X] T039 [US5] `app/Http/Controllers/Api/Admin/BudgetScenarioController.php` (`upsert` por `key`) + `app/Http/Requests/Admin/BudgetScenarioRequest.php` + rota `PUT …/budget/scenarios/{key}`.
- [X] T040 [US5] Estender `BudgetCalculator` com simulador de preço mínimo e cálculo de margem (funções puras) expostos no summary (ou endpoint puro), e finalizar os alertas de FR-027 (itens/patrocínios não convertidos, meta abaixo, etc.).
- [X] T041 [P] [US5] `frontend/src/admin/eventos/abas/orcamento/Simuladores.jsx` — cenários (3 colunas + "qual fecha"), simulador de preço mínimo e margem de segurança; painel de alertas coloridos.

**Checkpoint**: ferramentas de decisão what-if e alertas ativos.

---

## Phase 8: User Story 6 — Exportação e gráficos (Priority: P6)

**Goal**: exportar (Excel/PDF) e visualizar gráficos.

**Independent Test**: exportar um orçamento com itens/lotes e conferir as seções no arquivo.

### Tests for User Story 6 ⚠️

- [X] T042 [P] [US6] `tests/Feature/Budget/BudgetExportTest.php` — `export.xlsx` e `export.pdf` retornam 200 com content-type correto e respeitam o estado atual.

### Implementation for User Story 6

- [X] T043 [US6] Endpoints `GET …/budget/export.xlsx` (openspout) e `…/budget/export.pdf` (dompdf) em `BudgetController` + serviço/gerador com resumo, itens, lotes, patrocínios, resultado e comparativo + rotas.
- [X] T044 [P] [US6] `frontend/src/admin/eventos/abas/orcamento/Graficos.jsx` — ApexCharts (custos por categoria, receitas previstas por tipo, orçado×realizado, participação dos patrocínios) + botões de exportar; integrar no `Orcamento.jsx`.

**Checkpoint**: apresentação e compartilhamento prontos.

---

## Phase 9: Polish & Cross-Cutting Concerns

- [X] T045 [P] `tests/Feature/Budget/BudgetAccessTest.php` — `attendee`/`gate` → 403 em todos os endpoints; `admin`/`treasury` → OK; valor ≤ 0 / status inválido / categoria fora da lista → 422.
- [X] T046 [P] Seeder opcional `database/seeders/BudgetSeeder.php` (massa de demo para o evento de exemplo) e registro no seeder estrutural.
- [X] T047 Rodar `make test` (suite completa) e garantir verde; ajustar regressões.
- [X] T048 Executar o roteiro de `quickstart.md` na UI (5173) com API/Vite no ar; conferir os checkpoints.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Fase 1)**: sem dependências.
- **Foundational (Fase 2)**: depende do Setup — **BLOQUEIA** todas as histórias.
- **User Stories (Fases 3–8)**: dependem da Fase 2. US1 é o MVP. US3/US4 dependem do Financeiro (010) já existente. US2 enriquece o summary que o calculator já suporta.
- **Polish (Fase 9)**: depois das histórias desejadas.

### User Story Dependencies

- **US1 (P1)**: só Foundational. Independente.
- **US2 (P2)**: só Foundational. Independente de US1 (o calculator já lida com filhos vazios).
- **US3 (P3)**: precisa de itens/patrocínios (US1/US2) para converter, mas os endpoints são testáveis isolando fixtures. Usa o módulo Financeiro (010).
- **US4 (P4)**: independente; lê realizado do Financeiro/vendas.
- **US5 (P5)**: independente; estende calculator (adição não-quebra).
- **US6 (P6)**: independente; consome o summary/comparativo.

### Within Each User Story

- Testes primeiro (devem falhar) → models → services → endpoints → frontend.

### Parallel Opportunities

- Setup: T002, T003 em paralelo.
- Foundational: T004–T008 (models) e T011 (resources) em paralelo; T009/T010/T012 dependem dos models.
- Dentro das histórias, arquivos de frontend distintos marcados [P] rodam junto (ex.: T026/T027/T028).
- Com equipe, US1..US6 podem ser tocadas em paralelo após a Fase 2.

---

## Parallel Example: Foundational (models)

```bash
Task: "Model BudgetPlan em app/Domain/Events/Models/BudgetPlan.php"
Task: "Model BudgetCostItem em app/Domain/Events/Models/BudgetCostItem.php"
Task: "Model BudgetTicketLot em app/Domain/Events/Models/BudgetTicketLot.php"
Task: "Model BudgetSponsorship em app/Domain/Events/Models/BudgetSponsorship.php"
Task: "Model BudgetScenario em app/Domain/Events/Models/BudgetScenario.php"
```

---

## Implementation Strategy

### MVP First (US1)

1. Fase 1 Setup → 2. Fase 2 Foundational → 3. Fase 3 US1 → 4. **VALIDAR** planilha+resumo → 5. demo.

### Incremental Delivery

Foundational pronto → US1 (MVP) → US2 (indicadores) → US3 (conversão) → US4 (comparativo) → US5 (simuladores) → US6 (export/gráficos). Cada história agrega valor sem quebrar as anteriores.

---

## Notes

- [P] = arquivos diferentes, sem dependência pendente.
- Estado derivado nunca vira coluna (constituição II) — tudo no `BudgetCalculator`.
- Conversão em `DB::transaction` reutilizando `FinancialEntryService` (não reimplementar baixa).
- Commit por tarefa ou grupo lógico; testes falham antes de implementar.
- Total: 48 tarefas.
