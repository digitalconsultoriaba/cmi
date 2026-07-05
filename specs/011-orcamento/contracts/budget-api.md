# Contrato de API — Aba Orçamento (spec 011)

Convenções da plataforma: sucesso `{ data: … }` em **camelCase**; erros `{ message, type, status }` — 422 validação, 409 regra de negócio (`DomainRuleViolation`), 403 papel/escopo, 401 sem sessão. Dinheiro como string `"1500.00"`. Todos os endpoints exigem sessão Sanctum e `require.role:admin,treasury`, aninhados sob o evento.

Base: `/api/admin/events/{event}/budget`

---

## 1. Ler o orçamento + resumo

`GET /api/admin/events/{event}/budget`

Cria o `BudgetPlan` sob demanda (firstOrCreate) e retorna o plano com filhos e o resumo derivado.

**200**
```json
{
  "data": {
    "plan": {
      "id": 1,
      "eventId": 1,
      "expectedPaying": 500,
      "expectedCourtesy": 50,
      "expectedGuests": 0,
      "expectedStaff": 20,
      "expectedSpeakers": 5,
      "otherRevenue": "0.00",
      "safetyMarginPct": "10.00",
      "notes": null
    },
    "costItems": [
      { "id": 10, "description": "Sonorização", "category": "Som e iluminação",
        "quantity": "1.00", "unitPrice": "26000.00", "totalAmount": "26000.00",
        "supplierName": null, "status": "quoted", "notes": null,
        "financialEntryId": null, "convertible": true }
    ],
    "ticketLots": [
      { "id": 5, "name": "Primeiro lote", "unitPrice": "250.00",
        "expectedQuantity": 200, "expectedPaying": 200, "expectedRevenue": "50000.00" }
    ],
    "sponsorships": [
      { "id": 3, "name": "Patrocínio Master", "unitValue": "100000.00", "quantity": 1,
        "status": "confirmed", "expectedRevenue": "100000.00",
        "financialEntryId": null, "convertible": true }
    ],
    "scenarios": [
      { "key": "realistic", "paying": 500, "avgTicket": "300.00",
        "sponsorship": "100000.00", "cost": "250000.00", "otherRevenue": "0.00",
        "closesBudget": true }
    ],
    "summary": {
      "totalCost": "250000.00",
      "ticketRevenue": "180000.00",
      "sponsorshipExpected": "100000.00",
      "sponsorshipConfirmed": "100000.00",
      "otherRevenue": "0.00",
      "totalRevenue": "280000.00",
      "result": "30000.00",
      "classification": "surplus",              // surplus | breakeven | deficit
      "amountMissing": "0.00",
      "ownInvestment": "0.00",
      "avgTicket": "360.00",                     // null → "—" na UI
      "costPerParticipant": "434.78",
      "costPerPaying": "500.00",
      "breakEvenPaying": 500,                     // null se ticket médio ausente
      "costWithMargin": "275000.00",
      "totalParticipants": 575
    }
  }
}
```

## 2. Atualizar cabeçalho do plano

`PUT /api/admin/events/{event}/budget`

Body: `expectedPaying, expectedCourtesy, expectedGuests, expectedStaff, expectedSpeakers, otherRevenue, safetyMarginPct, notes` (todos opcionais). Validação: inteiros ≥ 0; `otherRevenue` ≥ 0; `safetyMarginPct` 0–100. → **200** com o mesmo shape de (1).

## 3. Itens de custo

- `POST /api/admin/events/{event}/budget/cost-items` — cria. Body: `description*` , `category*`, `quantity`, `unitPrice`, `totalAmount`, `supplierName`, `status`, `notes`. Regra: se `quantity`+`unitPrice` → total derivado; senão exige `totalAmount` > 0. → **201**.
- `PUT …/budget/cost-items/{item}` — edita. → **200**.
- `DELETE …/budget/cost-items/{item}` — soft delete (não remove `FinancialEntry` já gerado). → **204**.
- `POST …/budget/cost-items/{item}/duplicate` — duplica a linha (sem o vínculo financeiro). → **201**.
- `POST …/budget/cost-items/{item}/generate-payable` — cria conta a pagar no Financeiro do evento e vincula.
  - **201** `{ "data": { "financialEntryId": 88, "item": { … "financialEntryId": 88, "convertible": false } } }`
  - **409** `{ "message": "Este item já gerou uma conta a pagar.", "type": "already_converted", "status": 409 }`

**Erros comuns**: 422 valor ≤ 0 / status inválido / categoria fora da lista; 403 papel/escopo.

## 4. Lotes de ingresso previstos

- `POST …/budget/ticket-lots` — Body: `name*`, `unitPrice*` (>0), `expectedQuantity*` (≥0), `expectedPaying`, `notes`. → **201**.
- `PUT …/budget/ticket-lots/{lot}` → **200**.
- `DELETE …/budget/ticket-lots/{lot}` → **204**.

## 5. Patrocínios previstos

- `POST …/budget/sponsorships` — Body: `name*`, `unitValue*` (>0), `quantity` (≥1), `status`, `notes`. → **201**.
- `PUT …/budget/sponsorships/{sponsorship}` → **200**.
- `DELETE …/budget/sponsorships/{sponsorship}` → **204**.
- `POST …/budget/sponsorships/{sponsorship}/generate-receivable` — cria conta a receber e vincula.
  - **201** shape análogo ao item.
  - **409** `already_converted` (já convertido) ou `invalid_sponsorship_status` (lost/cancelled).

## 6. Cenários

- `PUT …/budget/scenarios/{key}` — upsert do cenário (`key` ∈ conservative|realistic|optimistic). Body: `paying, avgTicket, sponsorship, cost, otherRevenue`. → **200** com `closesBudget` derivado.

## 7. Comparativo orçado × realizado

`GET /api/admin/events/{event}/budget/comparison`

**200**
```json
{
  "data": {
    "cost":        { "budgeted": "250000.00", "actual": "230000.00", "status": "under" },
    "revenue":     { "budgeted": "280000.00", "actual": "180000.00", "diff": "100000.00" },
    "sponsorship": { "budgeted": "100000.00", "actual": "120000.00", "diff": "-20000.00" },
    "tickets":     { "budgeted": 600, "actual": 320, "attainmentPct": "53.33" },
    "result":      { "budgeted": "30000.00", "actual": "-50000.00" }
  }
}
```
`cost.status` ∈ `under` | `on` | `over` (real vs. previsto). Sem dados reais → zeros coerentes.

## 8. Alertas

Incluídos no payload de (1) como `summary.alerts: [{ "level": "danger|warning|info", "message": "…" }]`, derivados das regras de FR-027 (déficit, valor faltante, meta abaixo, patrocínio confirmado insuficiente, custo/pagante acima do ticket médio, dependência de investimento próprio, sem margem, itens/patrocínios não convertidos).

## 9. Exportação

- `GET /api/admin/events/{event}/budget/export.xlsx` — planilha (resumo, itens, lotes, patrocínios, resultado, comparativo). **200** `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`.
- `GET /api/admin/events/{event}/budget/export.pdf` — PDF do orçamento. **200** `application/pdf`.

Respeitam o estado atual do orçamento. Acesso `require.role:admin,treasury`.
