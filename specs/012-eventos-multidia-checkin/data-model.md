# Data Model — Eventos multi-dia e check-in por dia (spec 012)

Novas tabelas estendem `BaseModel` (soft delete + `created_by`/`updated_by`). Datas no fuso do evento, armazenadas em UTC. Código em inglês; rótulos de UI em pt-BR. Situação e presença são **derivadas** (colunas só para ações auditadas).

## Tabela `event_days`

Dias operacionais de um evento (1..3 por evento).

| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| event_id | FK events | |
| day_number | tinyint | 1, 2 ou 3 (ordem pela data) |
| event_date | date | data do dia (obrigatória) |
| starts_at | time nullable | horário de início (opcional) |
| ends_at | time nullable | horário de término (opcional) |
| label | string(60) nullable | rótulo (ex.: Abertura, Palestras) |
| finalized_at | datetime nullable | quando finalizado |
| finalized_by | FK users nullable | quem finalizou |
| blocked_at | datetime nullable | quando bloqueado (opcional/admin) |
| blocked_by | FK users nullable | quem bloqueou |
| reopened_at | datetime nullable | última reabertura |
| reopened_by | FK users nullable | quem reabriu |
| reopen_reason | string(500) nullable | justificativa da reabertura |
| auditoria | | timestamps + soft delete + created_by/updated_by |

Índices: unique `(event_id, day_number)`; index `(event_id, event_date)`.
Relacionamentos: `belongsTo Event`; `hasMany TicketDayCheckin`.

**Situação (derivada)** — `EventDay::status()`:
`finished` se `finalized_at` != null; senão `blocked` se `blocked_at` != null; senão `in_progress` se existe ≥ 1 check-in não deletado; senão `open`.

**Regras**:
- Um evento tem **sempre ≥ 1 dia**; máximo 3.
- Datas distintas entre os dias do evento.
- Não permitir excluir/remover um dia que tenha check-ins (FR-005).
- `finalized_at`/`blocked_at` bloqueiam novo check-in e edição/exclusão de check-in daquele dia.

## Tabela `ticket_day_checkins`

Presença de um ingresso num dia (única por ingresso+dia).

| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| event_id | FK events | redundância p/ consulta rápida |
| event_day_id | FK event_days | |
| ticket_id | FK tickets | |
| checked_in_at | datetime | data/hora do check-in |
| operator_id | FK users | usuário responsável |
| origin | string(15) | `qr` \| `manual` \| `admin_adjust` |
| note | string(255) nullable | observação opcional |
| auditoria | | timestamps + soft delete + created_by/updated_by |

Índices: index `(event_day_id)`, `(ticket_id)`, `(event_id)`. **Unicidade `(ticket_id, event_day_id)` garantida no serviço sob lock** (soft delete impede índice único físico simples).
Relacionamentos: `belongsTo EventDay`, `belongsTo Ticket`, `belongsTo Event`, `operator (User)`.

## Ajustes em tabelas existentes

- **events**: sem coluna nova obrigatória. A "duração" é `count(event_days)`. (Opcional: nada.)
- **tickets**: mantém `used_at`/`validated_by`/status `used` como **espelho do Dia 1 em eventos de 1 dia** (compatibilidade). Em eventos multi-dia esses campos NÃO são usados para presença (a presença vive em `ticket_day_checkins`).

## Enums / constantes

- **EventDayStatus** (derivado): `open`, `in_progress`, `finished`, `blocked`.
- **CheckinOrigin**: `qr`, `manual`, `admin_adjust`.

## Regras de negócio (→ 409 `DomainRuleViolation`)

- Check-in num dia com `finalized_at` → `day_finished`.
- Check-in num dia com `blocked_at` → `day_blocked`.
- Segundo check-in do mesmo ingresso no mesmo dia → `already_checked_in_day` (com `checkedInAt`, `operator` do anterior). **Não** cria novo registro.
- Ingresso inapto (cancelled/refunded/transferred/não pago) → mesmas recusas atuais do `CheckinService`.
- Evento cancelado → `event_cancelled`.
- Excluir/editar check-in de dia finalizado → recusa.
- Reduzir duração removendo dia com check-ins → `day_has_checkins`.

## Escopo/papel (→ 403)

- Check-in e finalizar dia: `gate`/`admin`.
- Reabrir dia (e bloquear/desbloquear): `admin`.
- Gerir dias/duração no cadastro: `admin`/`treasury` (grupo do painel do evento).

## Validação (FormRequests → 422)

- Upsert de dias: `days` com 1–3 itens; cada item `date` obrigatória e distinta; `startsAt`/`endsAt` opcionais em `HH:MM`; `label` ≤ 60.
- Reabertura: `reason` obrigatória, ≤ 500.
- Check-in: `code` obrigatório; `day` (event_day_id) obrigatório e pertencente ao evento; `origin` no enum; `note` ≤ 255.

## Derivações de presença (AttendanceReportService)

- **Presente no dia D** (ingresso T): existe `ticket_day_checkins(T, D)` não deletado.
- **Por dia**: presentes = nº de ingressos elegíveis com check-in no dia; ausentes = elegíveis − presentes; % = presentes/elegíveis.
- **Consolidado**: presentes em **todos** os dias (check-in em cada dia), **parcial** (em ≥ 1 e < total), **nenhum** (0 dias).
- **Individual**: por ingresso, para cada dia → sim/não + `checked_in_at` + operador.
- Contagem de "elegíveis" segue a régua atual (pago/confirmado/cortesia; casal conta pessoas onde aplicável aos totais de pessoas).
