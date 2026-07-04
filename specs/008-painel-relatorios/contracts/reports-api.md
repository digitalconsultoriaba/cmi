# Contrato — Painel e Relatórios (`/api/admin/*`, `/api/treasury/*`)

Convenções globais (001): sucesso `{ data }` camelCase; erros
`{ message, type, status }`; DECIMAL como string `"1234.56"`; datas ISO-8601
UTC. Papéis por rota (FR-010); demais → 403.

## GET /api/admin/dashboard — papel: admin

```jsonc
{ "data": {
  "event": { "name": "…", "startsAt": "…", "capacity": 500 },
  "people": { "confirmed": 312, "capacity": 500, "present": 87, "absent": 225 },
  "ticketsByStatus": [ { "status": "paid", "label": "Pago", "count": 180 }, … ],
  "revenue": { "confirmed": "98750.00", "refunded": "1200.00",
               "pending": "12400.00", "projected": "111150.00" },
  "shirts": {
    "grid": [ { "model": "Tradicional", "size": "M", "count": 84 },
              { "model": null, "size": null, "count": 3 } ],   // não informado
    "totalPeople": 312                                          // ≡ Σ grid
  },
  "byLot": [ { "lot": "1º lote", "ticketType": "Individual",
               "sold": 120, "limit": 150, "revenue": "42000.00" }, … ],
  "byMethod": [ { "method": "pix", "count": 90, "amount": "31500.00" }, … ],
  "courtesies": { "issued": 12, "redeemed": 9, "limits": [ … ] }
} }
```

## GET /api/treasury/finance — papéis: treasury, admin

Query: `month=6&year=2026` OU `from=2026-06-01&to=2026-06-30` (fuso do
evento; sem filtro = tudo). `from > to` → 422.

```jsonc
{ "data": {
  "period": { "from": "…", "to": "…" } | null,
  "byMethod": [ { "method": "pix", "label": "Pix",
                  "count": 90, "amount": "31500.00" }, … ],
  "total": { "count": 132, "amount": "56900.00" },
  "refunds": { "count": 4, "amount": "1200.00" },
  "net": "55700.00",
  "pendingOrders": { "count": 18, "amount": "6300.00" },   // fotografia atual
  "sponsorships": { "received": "20000.00", "receivable": "10000.00",
                    "overdue": { "count": 2, "amount": "4000.00" } }
} }
```

## Exports .xlsx (GET; resposta = arquivo, Content-Disposition attachment)

| Rota | Papéis | Conteúdo (1 linha por…) |
|---|---|---|
| `GET /api/admin/reports/attendees.xlsx` | admin | PESSOA elegível (titular/acompanhante em linhas próprias): nome, papel, contato do titular, tipo, lote, camisa, situação, presença |
| `GET /api/treasury/reports/finance.xlsx?month&year|from&to` | treasury, admin | pagamento pago no período (+ aba/seção de estornos): pedido, comprador, forma, valor, data (fuso do evento), registrado por |
| `GET /api/admin/reports/attendance.xlsx` | admin | ingresso elegível: código, nomes, pessoas, situação, entrada, validado por |

Mesmo service das telas — a planilha reproduz exatamente as linhas filtradas.

## GET /api/admin/audit — papel: admin (somente leitura; não há POST/PUT/DELETE)

Query: `action=payment.refunded` (opcional), `from/to` (opcional, fuso do
evento), `page` (paginado, mais recente primeiro).

```jsonc
{ "data": {
  "items": [ {
    "id": 812,
    "action": "payment.registered",
    "description": "Baixa manual de R$ 350,00 no pedido ORD-AB12CD34",
    "subject": { "type": "payment", "reference": "ORD-AB12CD34" },
    "causer": { "name": "Tesouraria Dev" } | null,     // null = sistema
    "properties": { "amount": "350.00", "method": "manual" },
    "createdAt": "2026-07-04T14:22:31.000000Z"
  } ],
  "meta": { "currentPage": 1, "lastPage": 9, "total": 812 }
} }
```

## Erros relevantes

| Caso | Status/type |
|---|---|
| Papel insuficiente em qualquer rota | 403 `forbidden` |
| Anônimo | 401 `unauthenticated` |
| Filtro inválido (`from > to`, mês fora de 1–12) | 422 `validation` |
