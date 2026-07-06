# Contrato de API — Eventos multi-dia e check-in por dia (spec 012)

Convenções: sucesso `{ data: … }` camelCase; erros `{ message, type, status }` — 422 validação, 409 regra (`DomainRuleViolation`), 403 papel/escopo, 401 sem sessão. Datas de dia no fuso do evento. Sessão Sanctum.

Situação do dia (derivada): `open` | `in_progress` | `finished` | `blocked`.

---

## A) Dias do evento (painel) — `require.role:admin,treasury`

Base: `/api/admin/events/{event}`

### A1. Listar dias
`GET /api/admin/events/{event}/days`
```json
{ "data": [
  { "id": 10, "dayNumber": 1, "date": "2026-08-10", "startsAt": "08:00", "endsAt": "18:00",
    "label": "Abertura", "status": "in_progress", "checkinCount": 120,
    "finalizedAt": null, "finalizedBy": null, "reopenedAt": null, "reopenReason": null }
] }
```

### A2. Definir duração + dias (upsert)
`PUT /api/admin/events/{event}/days`
Body:
```json
{ "days": [
  { "date": "2026-08-10", "startsAt": "08:00", "endsAt": "18:00", "label": "Abertura" },
  { "date": "2026-08-11", "startsAt": "08:00", "endsAt": "17:00", "label": "Encerramento" }
] }
```
- 1 a 3 itens; datas distintas e obrigatórias; horários opcionais `HH:MM`; label ≤ 60.
- Renumera `dayNumber` pela ordem das datas.
- **200** com a lista (A1). **422** validação. **409** `day_has_checkins` ao tentar remover um dia que já tem presença.

### A3. Finalizar dia — `require.role:gate,admin`
`POST /api/admin/events/{event}/days/{day}/finalize` → **200** dia com `status=finished`, `finalizedAt/By` preenchidos. Idempotente/erro se já finalizado.

### A4. Reabrir dia — `require.role:admin`
`POST /api/admin/events/{event}/days/{day}/reopen`
Body: `{ "reason": "Ajuste de presença do participante X" }` (obrigatória, ≤ 500).
- **200** dia reaberto (`finalizedAt` limpo; `reopenedAt/By/reopenReason` gravados; log). **422** sem justificativa. **403** se não for admin. **409** se o dia não está finalizado.

### A5. Bloquear/desbloquear dia (opcional) — `require.role:admin`
`POST /api/admin/events/{event}/days/{day}/block` e `/unblock` → **200**.

---

## B) Presença e relatórios por dia (painel)

### B1. Presença por dia (tela de check-in do admin)
`GET /api/admin/events/{event}/attendance?day={eventDayId}&search=`
```json
{ "data": {
  "day": { "id": 10, "dayNumber": 1, "date": "2026-08-10", "status": "in_progress" },
  "counters": { "purchased": 200, "present": 120, "absent": 80, "presentPct": "60.00" },
  "items": [
    { "code": "TCK-ABC", "participantName": "Fulano", "companionName": null,
      "ticketTypeName": "Individual", "present": true,
      "checkedInAt": "2026-08-10T11:02:00Z", "operator": "Portaria Dev", "origin": "qr" }
  ]
} }
```
Registrar presença manual reusa o check-in (C2) com `origin=manual`.

### B2. Relatório de presença (por dia + consolidado + individual)
`GET /api/admin/events/{event}/attendance-report`
```json
{ "data": {
  "totalRegistered": 200,
  "byDay": [
    { "dayNumber": 1, "date": "2026-08-10", "present": 120, "absent": 80, "presentPct": "60.00" },
    { "dayNumber": 2, "date": "2026-08-11", "present": 95, "absent": 105, "presentPct": "47.50" }
  ],
  "consolidated": { "allDays": 80, "partial": 55, "none": 65 },
  "individual": [
    { "code": "TCK-ABC", "participantName": "Fulano", "ticketTypeName": "Individual",
      "ticketStatus": "confirmed",
      "days": [
        { "dayNumber": 1, "present": true, "checkedInAt": "…", "operator": "Portaria Dev" },
        { "dayNumber": 2, "present": false, "checkedInAt": null, "operator": null }
      ] }
  ]
} }
```
Export: `GET …/attendance-report.xlsx` e `.pdf` (reusa o padrão de relatórios). Para eventos de 1 dia, equivale ao relatório de presença atual.

---

## C) Portaria — `require.role:gate,admin`

### C1. Eventos + dias
`GET /api/gate/events`
```json
{ "data": [
  { "id": 1, "name": "Seminário 2026", "startsAt": "…",
    "days": [
      { "id": 10, "dayNumber": 1, "date": "2026-08-10", "status": "in_progress", "checkinCount": 120 },
      { "id": 11, "dayNumber": 2, "date": "2026-08-11", "status": "open", "checkinCount": 0 }
    ] }
] }
```

### C2. Check-in por dia
`POST /api/gate/checkin`
Body: `{ "code": "TCK-ABC", "day": 10, "origin": "qr", "note": null }`
- **200** (registrado):
```json
{ "data": { "code": "TCK-ABC", "participantName": "Fulano", "companionName": null,
  "ticketTypeName": "Individual", "seats": 1, "dayNumber": 1,
  "checkedInAt": "2026-08-10T11:02:00Z" } }
```
- **409** `already_checked_in_day` (2ª leitura no mesmo dia):
```json
{ "message": "Participante já possui check-in registrado neste dia.",
  "type": "already_checked_in_day", "status": 409,
  "errors": { "checkedInAt": "2026-08-10T10:40:00Z", "operator": "Portaria Dev" } }
```
- **409** `day_finished` / `day_blocked` / `event_cancelled` / `ticket_cancelled` / `ticket_transferred` / `not_paid`.
- **404** ingresso inexistente. **422** `day` ausente/não pertence ao evento.

### C3. Presença por dia (portaria)
`GET /api/gate/attendance?event={eventId}&day={eventDayId}&search=` — mesmo shape de B1 (por dia).

---

## Compatibilidade (1 dia)

- Ao criar um evento, o Dia 1 é criado automaticamente a partir da data principal.
- Check-in num evento de 1 dia registra a presença no Dia 1 **e** espelha `used_at`/`validated_by` + status `used` do ingresso (comportamento atual). Multi-dia não usa o `used` global.
- `/gate/checkin` sem `day` explícito PODE assumir o único dia quando o evento tem 1 dia (retrocompatibilidade); com 2/3 dias o `day` é obrigatório.
