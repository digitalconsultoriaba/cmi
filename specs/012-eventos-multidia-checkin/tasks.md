---

description: "Task list — Eventos com 1, 2 ou 3 dias e check-in por dia (spec 012)"
---

# Tasks: Eventos com 1, 2 ou 3 dias e check-in por dia

**Input**: Design documents from `/specs/012-eventos-multidia-checkin/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/multiday-api.md, quickstart.md

**Tests**: INCLUÍDOS — a constituição exige Feature tests (MySQL `app_test`) cobrindo caminho feliz + regras (409/403/422) antes do merge.

**Organization**: por user story (P1→P5), entrega incremental e teste independente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizável (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1..US5 conforme spec.md
- Caminhos relativos ao repo. PHP roda via Docker (`docker compose run --rm php …`).

## Path Conventions

- Backend: `app/Domain/Events/` (models/services/observers), `app/Http/Controllers/Api/`, `app/Http/Requests/Admin/`, `app/Http/Resources/Admin/`, `routes/api.php`.
- Frontend: `frontend/src/admin/…`.
- Testes: `tests/Feature/Multiday/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: estrutura de dados e constantes.

- [X] T001 Criar migration `database/migrations/2026_07_06_100000_create_event_days_table.php` (event_id, day_number, event_date, starts_at/ends_at time nullable, label, finalized_at/by, blocked_at/by, reopened_at/by, reopen_reason; soft delete + created_by/updated_by; unique (event_id, day_number)).
- [X] T002 Criar migration `database/migrations/2026_07_06_100010_create_ticket_day_checkins_table.php` (event_id, event_day_id, ticket_id, checked_in_at, operator_id, origin, note; soft delete + auditoria; index (event_day_id),(ticket_id),(event_id)).
- [X] T003 Criar migration `database/migrations/2026_07_06_100020_backfill_event_days.php` — para cada evento existente cria 1 `event_day` (day_number 1, event_date = data de starts_at, starts_at/ends_at do evento) de forma idempotente.
- [X] T004 [P] Criar constantes `app/Domain/Events/Models/EventDayStatus.php` (open|in_progress|finished|blocked) e `app/Domain/Events/Models/CheckinOrigin.php` (qr|manual|admin_adjust).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: modelos, relação no evento, criação do Dia 1, resources e casca de UI de dias — pré-requisito de TODAS as histórias.

**⚠️ CRITICAL**: nenhuma user story começa antes desta fase.

- [X] T005 [P] Model `app/Domain/Events/Models/EventDay.php` (estende BaseModel; belongsTo Event; hasMany TicketDayCheckin; `status()` derivado de finalized_at/blocked_at/have-checkins; helpers `isFinished()/isBlocked()`).
- [X] T006 [P] Model `app/Domain/Events/Models/TicketDayCheckin.php` (belongsTo EventDay/Ticket/Event; belongsTo operator (User); casts).
- [X] T007 Adicionar relação `eventDays(): HasMany` (ordenada por day_number) e helper de duração em `app/Domain/Events/Models/Event.php`.
- [X] T008 Criar `app/Domain/Events/Observers/EventObserver.php` que no `created` cria o Dia 1 a partir de `starts_at` (idempotente) e registrá-lo (AppServiceProvider/EventServiceProvider).
- [X] T009 [P] `app/Http/Resources/Admin/EventDayResource.php` (camelCase: dayNumber, date, startsAt, endsAt, label, status derivado, checkinCount, finalizedAt/By, reopenedAt/reopenReason).
- [X] T010 [P] Frontend: componente reutilizável `frontend/src/admin/eventos/abas/checkin/DiasEvento.jsx` — cards de dia (número, data, situação, nº de check-ins, botão operar; destaca o dia de hoje).

**Checkpoint**: base pronta — todo evento tem ≥ 1 dia; histórias podem começar.

---

## Phase 3: User Story 1 — Configurar duração e dias (Priority: P1) 🎯 MVP

**Goal**: cadastrar/editar evento com 1/2/3 dias e datas por dia; existentes viram 1 dia.

**Independent Test**: evento novo nasce 1 dia; editar para 2 dias exige as duas datas; existente aparece como 1 dia; recusa remover dia com presença.

### Tests for User Story 1 ⚠️

- [X] T011 [P] [US1] `tests/Feature/Multiday/EventDaysTest.php` — evento novo nasce 1 dia (= data principal); backfill cria 1 dia p/ existentes (SC-001); upsert 2/3 dias com datas obrigatórias/distintas; remover dia com check-ins → 409 `day_has_checkins`.

### Implementation for User Story 1

- [X] T012 [US1] `app/Domain/Events/Services/EventDayService.php` — método `upsertDays(event, days[])` em `DB::transaction`: valida 1–3, datas distintas, renumera por data, cria/edita/remove dias; recusa remover dia com check-ins.
- [X] T013 [US1] `app/Http/Requests/Admin/EventDaysRequest.php` — 1–3 itens; `date` obrigatória/distinta; `startsAt`/`endsAt` `HH:MM` opcionais; `label` ≤ 60.
- [X] T014 [US1] `app/Http/Controllers/Api/Admin/EventDayController.php` — `index` (lista dias) e `upsert` (PUT); rotas `GET/PUT /admin/events/{event}/days` em `routes/api.php` (grupo admin do evento).
- [X] T015 [P] [US1] Frontend `frontend/src/admin/components/EventoModal.jsx` — seletor "Duração" (1/2/3) + campos data/horário/rótulo por dia (para 2/3); envia `days[]` ao salvar.

**Checkpoint**: US1 funcional — evento multi-dia cadastrável; 1 dia preservado.

---

## Phase 4: User Story 2 — Check-in por dia (Priority: P2)

**Goal**: operador escolhe o dia e registra presença só naquele dia; duplicidade no dia é informada.

**Independent Test**: mesmo ingresso marca Dia 1 e Dia 2 independentemente; 2ª leitura no mesmo dia avisa data/hora/operador; 1 dia mantém compat (`used`).

### Tests for User Story 2 ⚠️

- [X] T016 [P] [US2] `tests/Feature/Multiday/DayCheckinTest.php` — presença por dia isolada (SC-002); 2ª leitura mesmo dia → 409 `already_checked_in_day` sem duplicar (SC-003); inapto/cancelado/estornado/transferido/não pago recusado; evento de 1 dia espelha `used_at`/`validated_by`/status `used`.

### Implementation for User Story 2

- [X] T017 [US2] Estender `app/Domain/Events/Services/CheckinService.php` — `checkInDay(code, EventDay, operator, origin, note)` sob `lockForUpdate`: recusas atuais + `day_finished`/`day_blocked`; único por (ticket, day) → cria `TicketDayCheckin` (log `ticket.checked_in`); espelho 1-dia (used_at/validated_by/status used quando o evento tem 1 dia).
- [X] T018 [US2] Ajustar `app/Http/Controllers/Api/Gate/GateController.php` — `checkin` recebe `day`+`origin`+`note` e usa `checkInDay`; `events` inclui `days` (situação + checkinCount); `attendance` aceita `day` e deriva presença de `ticket_day_checkins`.
- [X] T019 [US2] Ajustar `app/Http/Controllers/Api/Admin/EventPanelController.php` (+ ReportService) — `attendance` do evento aceita `day` e deriva presentes/ausentes por dia (registrar presença manual usa o mesmo check-in com `origin=manual`).
- [X] T020 [P] [US2] Frontend `frontend/src/admin/pages/Checkin.jsx` (portaria) — usar `DiasEvento` para escolher o dia (hoje destacado), enviar `day`+`origin` no check-in, presença/lista escopadas ao dia.
- [X] T021 [P] [US2] Frontend `frontend/src/admin/eventos/abas/CheckinEvento.jsx` (admin) — cards de dia + validação/registro por dia + lista de presença do dia.

**Checkpoint**: US1 + US2 — presença correta por dia; 1 dia intacto.

---

## Phase 5: User Story 3 — Finalizar o dia (Priority: P3)

**Goal**: finalizar congela o dia; dias posteriores seguem.

**Independent Test**: finalizar Dia 1 recusa novas leituras nele; Dia 2 continua.

### Tests for User Story 3 ⚠️

- [X] T022 [P] [US3] `tests/Feature/Multiday/DayFinalizeReopenTest.php` (parte finalizar) — finalizar bloqueia check-in/edição/exclusão no dia; dias posteriores seguem (SC-004); log grava quem/quando.

### Implementation for User Story 3

- [X] T023 [US3] `EventDayService::finalize(EventDay, operator)` — grava `finalized_at/by`, log; recusa se já finalizado.
- [X] T024 [US3] `EventDayController::finalize` + rota `POST /admin/events/{event}/days/{day}/finalize` (`require.role:gate,admin`).
- [X] T025 [P] [US3] Frontend: botão "Finalizar dia" nos cards (`DiasEvento`/telas de check-in), com confirmação e atualização da situação.

**Checkpoint**: fechamento diário íntegro.

---

## Phase 6: User Story 4 — Reabrir dia finalizado (Priority: P4)

**Goal**: reabrir restrito a admin, com justificativa e histórico.

**Independent Test**: admin reabre com justificativa; operador comum é negado (403).

### Tests for User Story 4 ⚠️

- [X] T026 [P] [US4] Completar `DayFinalizeReopenTest.php` (parte reabrir) — reabrir só admin + justificativa obrigatória; 403 p/ gate; log grava quem/quando/dia/justificativa; após reabrir aceita check-in de novo (SC-005).

### Implementation for User Story 4

- [X] T027 [US4] `EventDayService::reopen(EventDay, admin, reason)` (limpa `finalized_at`, grava `reopened_at/by/reopen_reason`, log) e `block/unblock` (opcional).
- [X] T028 [US4] `app/Http/Requests/Admin/ReopenDayRequest.php` (`reason` obrigatória ≤ 500) + `EventDayController::reopen` (e `block`) com rotas sob `require.role:admin`.
- [X] T029 [P] [US4] Frontend: ação "Reabrir dia" (só admin) com modal de justificativa nos cards de dia.

**Checkpoint**: exceção controlada e auditada.

---

## Phase 7: User Story 5 — Relatórios de presença por dia (Priority: P5)

**Goal**: presença por dia + consolidada + individual por dia.

**Independent Test**: totais por dia, consolidados (todos/parcial/nenhum) e detalhe individual coerentes.

### Tests for User Story 5 ⚠️

- [X] T030 [P] [US5] `tests/Feature/Multiday/AttendanceReportTest.php` — por dia (presentes/ausentes/%), consolidado (todos/parcial/nenhum) e individual por dia (SC-006); 1 dia equivale ao relatório atual.

### Implementation for User Story 5

- [X] T031 [US5] `app/Domain/Events/Services/AttendanceReportService.php` — deriva por dia, consolidado e individual a partir de `ticket_day_checkins`.
- [X] T032 [US5] `EventPanelController::attendanceReport` + rota `GET /admin/events/{event}/attendance-report` (+ `.xlsx`/`.pdf` reusando openspout/dompdf).
- [X] T033 [P] [US5] Frontend `frontend/src/admin/eventos/abas/Relatorios.jsx` — recorte de presença por dia + detalhe individual por dia + export.

**Checkpoint**: acompanhamento por dia completo.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T034 [P] `tests/Feature/Multiday/MultidayAccessTest.php` — 403 (reabrir por não-admin; check-in/dias fora de papel) e 422 (dias inválidos, reabrir sem justificativa, day ausente no check-in).
- [ ] T035 [P] Ajustar seeders demo: evento de exemplo segue 1 dia; opcional criar um evento de 2 dias com presenças mistas (`database/seeders/`). — (opcional; não executado para não recriar dados)
- [X] T036 Rodar `make test` (suite completa) e garantir verde; corrigir regressões (atenção ao check-in/relatórios de 1 dia).
- [X] T037 Executar o roteiro do `quickstart.md` na UI (5173) com API/Vite no ar; conferir os checkpoints (1 dia, 2 dias, finalizar, reabrir, relatórios).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Fase 1)**: sem dependências.
- **Foundational (Fase 2)**: depende do Setup — **BLOQUEIA** todas as histórias.
- **US1 (P1)**: MVP — cadastro de duração/dias. Só Foundational.
- **US2 (P2)**: check-in por dia — usa os dias (US1 ajuda, mas testável com dias criados via fixture). O `checkInDay` lê `finalized_at` (coluna já existe), então independe do código de US3.
- **US3 (P3)**: finalizar — usa `checkInDay` (US2) para provar o bloqueio.
- **US4 (P4)**: reabrir — depende de US3 (dia finalizado).
- **US5 (P5)**: relatórios — usa presenças (US2).
- **Polish (Fase 8)**: depois das histórias desejadas.

### Within Each User Story

- Testes primeiro (devem falhar) → models/services → endpoints → frontend.

### Parallel Opportunities

- Setup: T004 em paralelo.
- Foundational: T005/T006/T009/T010 em paralelo; T007/T008 dependem dos models.
- Frontends distintos por história ([P]).

---

## Parallel Example: Foundational

```bash
Task: "Model EventDay em app/Domain/Events/Models/EventDay.php"
Task: "Model TicketDayCheckin em app/Domain/Events/Models/TicketDayCheckin.php"
Task: "EventDayResource em app/Http/Resources/Admin/EventDayResource.php"
Task: "Componente DiasEvento em frontend/src/admin/eventos/abas/checkin/DiasEvento.jsx"
```

---

## Implementation Strategy

### MVP First (US1)

1. Fase 1 Setup → 2. Fase 2 Foundational → 3. Fase 3 US1 → 4. **VALIDAR** (1 dia preservado; 2/3 dias cadastráveis) → 5. demo.

### Incremental Delivery

Foundational → US1 (duração/dias) → US2 (check-in por dia) → US3 (finalizar) → US4 (reabrir) → US5 (relatórios). Cada história agrega valor sem quebrar 1 dia.

---

## Notes

- [P] = arquivos diferentes, sem dependência pendente.
- Situação do dia e presença **derivadas** (constituição II); colunas só para finalização/reabertura/bloqueio.
- Check-in atômico sob lock (uma entrada por leitura; único por ingresso/dia).
- Compatibilidade 1 dia: espelho de `used_at`/`validated_by`/status `used`.
- Commit por tarefa/grupo; testes falham antes de implementar.
- Total: 37 tarefas.
