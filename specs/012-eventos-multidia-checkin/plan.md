# Implementation Plan: Eventos com 1, 2 ou 3 dias e check-in por dia

**Branch**: `012-eventos-multidia-checkin` | **Date**: 2026-07-06 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/012-eventos-multidia-checkin/spec.md`

## Summary

Introduz **dias do evento** (1 a 3) e **check-in por dia**. Cada evento passa a ter 1..3 `event_days` (data, horário/rótulo opcionais, situação); a presença deixa de ser única por ingresso e vira um registro por `(ingresso, dia)` em `ticket_day_checkins` (com operador, origem, observação). O mesmo QR é lido em cada dia e cada leitura marca só o dia selecionado. Dias podem ser **finalizados** (congelam) e **reabertos** (só admin, com justificativa) — tudo auditado. Relatórios passam a mostrar presença por dia e consolidada.

Abordagem: estender o domínio existente sem quebrar 1 dia. `CheckinService` ganha consciência de dia; para eventos de **1 dia** a presença do Dia 1 continua espelhando `tickets.used_at`/`validated_by`/status `used` (compatibilidade total); para multi-dia o ingresso **não** vira `used` global (pode ser lido nos outros dias) e a presença vive só em `ticket_day_checkins`. Migração cria automaticamente 1 `event_day` por evento existente. Frontend: seletor de duração + datas por dia no cadastro; cards de dia + finalizar/reabrir nas telas de check-in (admin e portaria); relatórios por dia.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12); JavaScript (React 18 + Vite)

**Primary Dependencies**: Laravel 12, MySQL 8, Sanctum (cookie SPA); React Query; spatie/laravel-activitylog (histórico); html5-qrcode (leitor); openspout/dompdf (relatórios). Estende specs 003 (config de evento), 007 (check-in/portaria) e 008 (relatórios de presença).

**Storage**: MySQL 8 — novas tabelas `event_days` e `ticket_day_checkins` (soft delete + `created_by`/`updated_by`). Reaproveita `tickets`, `events`, `users`.

**Testing**: PHPUnit Feature em MySQL `app_test` (nunca SQLite); cobre caminho feliz + regras (duplicidade por dia, dia finalizado/bloqueado, reabertura só admin, migração 1 dia, compat 1 dia) — 409/403/422.

**Target Platform**: Web (backend Laravel Docker :8000; SPA Vite :5173).

**Project Type**: Web application (backend + frontend no mesmo repo).

**Performance Goals**: Operação de portaria — check-in por leitura < 300 ms; consultas de presença/relatório por evento com dezenas a poucos milhares de ingressos sem degradação perceptível.

**Constraints**: Check-in atômico sob lock por linha (uma entrada por leitura, sem corrida) mantido; situação do dia e presença **derivadas** (colunas só para ações auditadas: finalização/reabertura/bloqueio); datas no fuso do evento, UTC no banco; UI/mensagens pt-BR, código em inglês; nada apagado fisicamente.

**Scale/Scope**: 1–3 dias por evento; 2 telas de check-in ajustadas (admin + portaria) + cadastro de evento + relatórios; ~2 tabelas novas e ~6–8 endpoints.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Standalone / RBAC 4 papéis** — ✅ Sem conceitos maçônicos. **Reabrir dia** = `admin`; **finalizar dia** = `gate`/`admin`; **check-in** = `gate`/`admin` (como hoje). Nenhum papel novo (o "perfil autorizado" mapeia para admin, ver Assumptions da spec).
- **II. Estado derivado, nunca armazenado** — ✅ A **situação do dia** (Aberto/Em andamento/Finalizado/Bloqueado) é **derivada** de `finalized_at`/`blocked_at` + existência de check-ins; a **presença** é derivada de `ticket_day_checkins`; contadores são recontáveis. Colunas persistidas só para **ações auditadas** (finalização/reabertura/bloqueio), análogas a `cancelled_at` (princípio V). Escritas multi-passo (check-in, finalizar) em `DB::transaction` com `lockForUpdate` (uma entrada por leitura; sem corrida).
- **III. Ponto único de baixa, idempotente** — ✅ N/A financeiro. O **ponto único de check-in** (`CheckinService`) é estendido para o dia; a idempotência vira **único por (ingresso, dia)** garantida sob lock (2ª leitura no mesmo dia não cria registro e informa o anterior).
- **IV. Segurança de pagamento** — ✅ N/A (sem dados de cartão). QR usa `code` público do ingresso (nunca id).
- **V. Histórico — nada some** — ✅ `event_days` e `ticket_day_checkins` com soft delete + `created_by`/`updated_by`; check-in, finalização e reabertura vão para o activity log; dia finalizado bloqueia exclusão/alteração; reabertura guarda quem/quando/qual dia/justificativa.
- **VI. Specs por área funcional** — ✅ Spec própria `012`; estende (não redefine) 003/007/008 como emenda funcional. Compatibilidade total com eventos de 1 dia.

**Resultado do gate**: PASS — sem violações; sem entradas em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/012-eventos-multidia-checkin/
├── plan.md              # Este arquivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   └── multiday-api.md  # Fase 1 — dias do evento + check-in por dia + relatórios
└── tasks.md             # Fase 2 (/speckit-tasks — não criado aqui)
```

### Source Code (repository root)

```text
app/
├── Domain/Events/
│   ├── Models/
│   │   ├── EventDay.php
│   │   ├── EventDayStatus.php          # constantes: open|in_progress|finished|blocked
│   │   ├── TicketDayCheckin.php
│   │   └── CheckinOrigin.php           # constantes: qr|manual|admin_adjust
│   ├── Services/
│   │   ├── CheckinService.php          # (estende) check-in por dia + espelho 1-dia
│   │   ├── EventDayService.php         # upsert de dias (duração), finalizar, reabrir, bloquear
│   │   └── AttendanceReportService.php # presença por dia + consolidada + individual
│   └── Observers/EventObserver.php     # cria Dia 1 ao criar evento (ou no serviço de criação)
├── Http/
│   ├── Controllers/Api/
│   │   ├── Admin/EventDayController.php     # list/upsert/finalize/reopen/block
│   │   ├── Admin/EventPanelController.php   # (ajuste) attendance por dia + relatório
│   │   └── Gate/GateController.php          # (ajuste) checkin por dia + attendance por dia
│   ├── Requests/Admin/                      # EventDaysRequest, ReopenDayRequest, …
│   └── Resources/Admin/                     # EventDayResource, DayAttendanceResource, …
database/
├── migrations/2026_07_06_*_create_event_days_table.php
├── migrations/2026_07_06_*_create_ticket_day_checkins_table.php
├── migrations/2026_07_06_*_backfill_event_days_for_existing_events.php
└── seeders/ (ajuste demo: 1 dia; opcional um evento de 2 dias)

frontend/src/
├── admin/components/EventoModal.jsx           # (ajuste) duração + datas/horário/rótulo por dia
├── admin/eventos/abas/CheckinEvento.jsx       # (ajuste) cards de dia + finalizar/reabrir + escopo dia
├── admin/pages/Checkin.jsx                    # (ajuste) portaria: dia selecionado + finalizar
├── admin/eventos/abas/Relatorios.jsx          # (ajuste) presença por dia + individual
└── admin/eventos/abas/checkin/DiasEvento.jsx  # cards de dias (reuso admin + portaria)

tests/Feature/Multiday/
├── EventDaysTest.php          # duração, datas, migração 1 dia, remover dia com presença
├── DayCheckinTest.php         # presença por dia, duplicidade por dia, compat 1 dia, inapto
├── DayFinalizeReopenTest.php  # finalizar bloqueia; reabrir só admin + justificativa + log
└── AttendanceReportTest.php   # presença por dia + consolidada + individual
```

**Structure Decision**: Web application (Laravel + React). O domínio ganha `EventDay` e `TicketDayCheckin` em `app/Domain/Events`; o `CheckinService` existente é estendido para o dia (mantendo o lock atômico) e um `EventDayService` concentra duração/finalização/reabertura. As telas de check-in (admin `CheckinEvento` e portaria `Checkin`) ganham a camada de **dias**; o cadastro (`EventoModal`) ganha a duração; os relatórios ganham o recorte por dia.

## Complexity Tracking

> Sem violações constitucionais — seção não aplicável.
