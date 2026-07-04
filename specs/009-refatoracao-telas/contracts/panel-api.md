# Contrato — Painel v2 (`/api/admin/*`)

Convenções (001): sucesso `{ data }` camelCase; erros `{ message, type, status }`;
DECIMAL string; datas ISO-8601 UTC. Todos sob `require.role:admin` salvo nota.
**Somente leitura** (a escrita reusa endpoints já existentes). Nenhum endpoint
novo de escrita.

## GET /api/admin/overview — painel do módulo

Query (opcionais): `event` (id, recorta 1 evento), `from`, `to` (fuso do evento).

```jsonc
{ "data": {
  "cards": {
    "events": 5, "published": 4, "upcoming": 1, "activeRegistrations": 30,
    "revenueConfirmed": "2240.00", "revenueProjected": "2680.00",
    "sponsorshipPaid": "2000.00", "refundsOpen": 1
  },
  "eventsByStatus": [ { "status": "published", "label": "Publicado", "count": 4 },
                      { "status": "cancelled", "label": "Cancelado", "count": 1 } ],
  "inscriptionsByMonth": [ { "month": "2025-08", "count": 0 }, …,
                           { "month": "2026-06", "count": 30 } ]
} }
```

## GET /api/admin/events/{event}/dashboard — painel do evento

Estende o payload da 008 (people/revenue/shirts/byLot/byMethod/cortesias/
ticketsByStatus) com:

```jsonc
{ "data": {
  "counters": {
    "capacity": 200, "registeredTotal": 2, "paidConfirmed": 2, "courtesies": 0,
    "present": 1, "awaitingPayment": 2, "cancelled": 1, "refunded": 0
  },
  "financial": { "expected": "680.00", "confirmed": "240.00",
                 "receivable": "440.00", "sponsorshipPaid": "2000.00" },
  "ticketsByStatus": [ … ],                    // rosca (situação dos ingressos)
  "byTicketType": [ { "type": "Individual", "count": 3, "revenue": "360.00" }, … ],
  "inscriptionsByMonth": [ … ]
} }
```

## GET /api/admin/events/{event}/attendees — inscritos

Query: `search`, `status`, `type` (id do tipo), `from`, `to`.

```jsonc
{ "data": { "items": [ {
  "code": "TCK-…", "participantName": "…", "companionName": "…", "isCouple": true,
  "ticketTypeName": "Casal", "shirt": "GG/Masculina", "companionShirt": "PP/Feminina",
  "amount": "220.00", "status": "awaiting_payment", "statusLabel": "Aguardando pagamento",
  "purchasedAt": "…", "paymentStatus": null
} ], "total": 5 } }
```

## GET /api/admin/events/{event}/attendance — check-in (lista + contadores)

Query: `search`. Também acessível pela portaria via `/gate` (007) — este é o
recorte por evento para a aba do admin.

```jsonc
{ "data": {
  "counters": { "purchased": 2, "present": 1, "absent": 1, "presentPct": 50 },
  "presence": { "present": 1, "absent": 1 },     // donut
  "items": [ { "code": "TCK-…", "participantName": "…", "seats": 1,
               "present": true, "usedAt": "…", "validatedBy": "Admin" } ]
} }
```

**Presença manual**: `POST /api/gate/checkin { code }` (endpoint da 007/008 —
`require.role:gate,admin`; mesma régua e trilha `ticket.checked_in`). A aba
chama exatamente este ao clicar "Registrar presença".

## GET /api/admin/events/{event}/reports/preview — prévia

Query: `type` (inscritos|financeiro|presencas|camisas), + filtros
(`ticketType`, `year`+`month` OU `from`+`to`, `search`).

```jsonc
{ "data": {
  "columns": [ "Participante", "Tipo", "Situação", "Cortesia", "Tam.", "Valor" ],
  "rows": [ [ "Carlos Eduardo Silva", "Individual", "Usado", "Não", "M", "120.00" ], … ],
  "total": 5, "shown": 5
} }
```

## GET /api/admin/events/{event}/reports/{type}.xlsx — export escopado

`type` como acima; mesmos filtros do preview. Resposta = arquivo
(`Content-Disposition: attachment`, planilha em streaming — reuso da 008). As
linhas batem com a prévia do mesmo recorte.

## Erros

| Caso | Status/type |
|---|---|
| Papel insuficiente | 403 `forbidden` |
| Anônimo | 401 `unauthenticated` |
| Evento inexistente | 404 |
| Filtro inválido (`from > to`, mês fora de 1–12, type desconhecido) | 422 `validation` |

## Sem regressão (permanecem da 008)

`GET /admin/dashboard`, `GET /gate/attendance`, `GET /admin/reports/*.xlsx`,
`GET /treasury/finance` — inalterados; suítes 007/008 continuam verdes.
