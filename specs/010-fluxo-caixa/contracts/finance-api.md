# Contrato — Módulo Financeiro (`/api/finance/*`)

Convenções (001): sucesso `{ data }` camelCase; erros `{ message, type, status }`
(422 validação, 409 `DomainRuleViolation`, 403 papel, 401); DECIMAL string;
datas ISO-8601 UTC. **Todas** sob `require.role:admin,treasury`.

## Lançamentos — `financial_entries`

### GET /api/finance/entries
Filtros (query): `direction` (payable|receivable), `status`, `event`,
`category`, `person`, `paymentMethod`, `origin`, `from`/`to` (vencimento),
`settledFrom`/`settledTo`, `minValue`/`maxValue`, `search`, `includeCancelled`,
`page`, `perPage` (25|50|100). Paginado.

```jsonc
{ "data": {
  "items": [ {
    "id": 12, "direction": "payable", "description": "Buffet — Seminário",
    "amount": "12000.00", "settledAmount": "4000.00", "balance": "8000.00",
    "status": "partial", "statusLabel": "Pago parcialmente",
    "dueDate": "2026-08-10", "origin": "event_expense",
    "category": "Buffet", "person": "Acme Buffet Ltda.",
    "event": { "id": 1, "name": "Seminário 2026" } | null,
    "paymentMethod": "Boleto", "readonly": false,
    "installment": { "number": 1, "total": 3 } | null,
    "overdue": false
  } ],
  "total": 40, "page": 1, "perPage": 25, "lastPage": 2,
  "totals": { "amount": "…", "settled": "…", "balance": "…" }
} }
```

### POST /api/finance/entries
Cria lançamento (a pagar/receber). Corpo: `direction*`, `description*`,
`amount*` (>0), `category_id`, `payment_method_id`, `due_date*`, `origin`,
`event_id` (nullable), `person_id`, `notes`. Parcelado: `installments` (>1) +
`due_dates[]` ou `first_due_date`+`frequency`. → 201.

### GET /api/finance/entries/{entry}
Detalhe completo: campos + settlements + attachments + histórico (do
activity_log filtrado ao subject).

### PUT /api/finance/entries/{entry}
Edita. Se a entry já tem baixa → exige `justification` (senão 422). Origem
espelhada (`ticket`/`sponsorship`) → 409 (read-only).

### POST /api/finance/entries/{entry}/settle
Baixa total/parcial. Corpo: `amount*` (>0, ≤ saldo), `settled_on*`,
`payment_method_id*`, `bank_account`, `note`, `attachment` (opcional). Atualiza
`settledAmount`/status. Excede saldo → 422; entry cancelada → 409.

### POST /api/finance/entries/{entry}/reverse
Estorno de valor já baixado. Corpo: `amount*`, `reason*`, `settled_on`. Ajusta
saldo; registra no histórico.

### POST /api/finance/entries/{entry}/cancel
Cancela. Corpo: `reason*`. Some dos saldos, permanece no histórico.

### POST /api/finance/entries/{entry}/duplicate
Duplica o lançamento (novo em aberto, sem baixas).

### Anexos
- `POST /api/finance/entries/{entry}/attachments` (multipart `file`, `kind`).
- `GET /api/finance/entries/{entry}/attachments/{attachment}` (download).
- `DELETE /api/finance/entries/{entry}/attachments/{attachment}`.

## Dashboard e resultado por evento

### GET /api/finance/dashboard
Filtros: `from`/`to`, `event`, `direction`, `category`, `status`,
`paymentMethod`.
```jsonc
{ "data": {
  "month": { "toReceive": "…", "received": "…", "toPay": "…", "paid": "…" },
  "overdue": { "payable": { "count": n, "amount": "…" },
               "receivable": { "count": n, "amount": "…" } },
  "balances": { "expected": "…", "realized": "…", "monthResult": "…" },
  "byEvent": [ { "event": "Seminário 2026", "result": "5000.00" }, … ],
  "worstEvents": [ … ],  "bestEvents": [ … ],
  "dueBuckets": { "today": [...], "next7": [...], "over30": [...] },
  "upcoming": [ { "entryId": …, "description": …, "dueDate": …, "amount": … } ]
} }
```

### GET /api/finance/events/{event}/result
Indicadores do evento (centro de resultado): receita prevista/recebida/pendente,
despesa prevista/paga/pendente, saldo previsto/realizado, resultado, vencidos;
+ informativos (ingressos vendidos, recebido com ingressos, recebido com
patrocínios, cortesias) reaproveitando as derivações da 008/009.

## Cadastros

- `GET/POST/PUT /api/finance/categories` (+ `DELETE` → 409 se em uso, sugere
  inativar).
- `GET/POST/PUT/DELETE /api/finance/people`.
- `GET/POST/PUT /api/finance/payment-methods`.

## Recorrências

- `GET/POST/PUT/DELETE /api/finance/recurrences`.
- Comando agendado `financial:generate-recurrences` materializa os lançamentos.

## Relatórios

### GET /api/finance/reports/{type}   (JSON prévia)  e  /{type}.{xlsx|pdf}
`type` ∈ geral, evento, contas-a-pagar, contas-a-receber, categoria, pessoa,
forma, ingressos, patrocinios, despesas-evento, previsto-realizado. Filtros por
período (+ evento etc.); export respeita os filtros (mesmo service da prévia).

## Erros relevantes

| Caso | Status/type |
|---|---|
| Papel insuficiente / anônimo | 403 / 401 |
| Valor ≤ 0 · baixa > saldo · mês inválido | 422 `validation` |
| Editar entry espelhada / baixar cancelada / excluir categoria em uso | 409 `domain_rule` |
| Editar entry baixada sem justificativa | 422 `validation` |
