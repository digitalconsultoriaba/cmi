# Contrato — Catálogo público e compra (004)

Envelope e erros da 001. Rotas do inscrito exigem sessão (Sanctum, 002); códigos
públicos nas URLs (nunca id sequencial).

## Catálogo público (sem auth)

### `GET /api/public/events/{slug}`

- Publicado → 200:

```json
{
  "data": {
    "name": "Seminário Internacional 2026",
    "slug": "seminario-internacional-2026",
    "bannerUrl": null,
    "startsAt": "…", "endsAt": "…", "location": "…", "locationMapUrl": null,
    "salesState": "open",              // open | soon | closed | soldOut
    "salesStartAt": "…", "salesEndAt": "…",
    "allowShirtChoice": true, "requiresShirt": false, "allowCourtesy": true,
    "blocks": [ { "type": "hero", "sort": 0, "payload": { } } ],
    "ticketTypes": [
      {
        "id": 1, "name": "Individual", "isCouple": false, "includesShirt": true,
        "audience": "any", "effectivePrice": "200.00", "currentLotName": "1º lote",
        "soldOut": false, "available": 120
      }
    ],
    "shirtModels": [ { "id": 1, "label": "Unissex", "sizes": [
      { "id": 1, "label": "M", "soldOut": false } ] } ]
  }
}
```

- Rascunho/inexistente → 404. Cancelado → 200 com
  `{ "cancelled": true, "cancelReason": …, "name": … }` e sem catálogo.

## Compra (auth)

### `POST /api/orders`

```json
{
  "eventSlug": "seminario-internacional-2026",
  "items": [
    {
      "ticketTypeId": 1,
      "participantName": "Ana Silva",
      "participantEmail": "ana@x.com",
      "participantDocument": null,
      "shirtModelId": 1, "shirtSizeId": 2,
      "companionName": null, "companionShirtModelId": null, "companionShirtSizeId": null
    }
  ],
  "courtesyParticipants": [ { "participantName": "Convidado" } ],
  "voucherCode": null
}
```

- Regras: 1–20 itens (config); casal exige `companionName`; camisa obrigatória se
  `requiresShirt`; cortesias automáticas usam `courtesyParticipants` (fallback:
  dados do comprador); `voucherCode` pode vir sem itens (resgate puro).
- 201 → `{ data: { orders: [OrderResource, …] } }` (2 pedidos quando carrinho +
  voucher — research Decisão 2).
- 409 conforme tabela do data-model (`sales_closed`, `sold_out`,
  `invalid_voucher`); 422 validação por campo.

### `GET /api/orders` · `GET /api/orders/{code}`

- Lista/detalhe dos pedidos do próprio usuário (policy; outro dono → 403).
- OrderResource: `code, status, totalAmount, reservedUntil, createdAt, event{name,slug},
  tickets[TicketResource]`.

### `GET /api/tickets` · `GET /api/tickets/{code}`

- "Meus ingressos": participante (user_id **ou** e-mail — claim preguiçoso) e
  compras próprias.
- TicketResource: `code, status, participantName, ticketTypeName, isCourtesy,
  unitPrice, shirt{model,size}, companion{...}, event{name,slug,startsAt},
  orderCode, receiptAvailable`.

### `GET /api/tickets/{code}/receipt`

- PDF (download) para status ∈ {confirmed, courtesy, paid, used}; dono =
  participante ou comprador (403 caso contrário); outros status → 409 com
  orientação.
- Conteúdo: evento (nome/data/local), participante, tipo, código e QR (código
  público).

## Expiração

- Comando `orders:expire` (agendado 5 min): pending vencidos → order `expired`,
  tickets vivos → `cancelled` ("Reserva expirada"), disponibilidades liberadas
  (recount na mesma transação, sob lock do evento).

## Contrato de frontend

- `/evento/:slug`: LandingRenderer (7 tipos de bloco) + TicketPicker (respeita
  `salesState`/esgotados); botão de compra leva ao `/checkout`.
- Carrinho: `CartProvider` sobre localStorage (por slug de evento); sobrevive ao
  login; limpa após pedido criado.
- `/checkout` (protegido): formulário por participante (camisa quando aplicável,
  acompanhante para casal), campo de voucher, revisão com totais; se o preço da
  resposta divergir do exibido, mostra aviso de lote virado.
- `/minha-conta/pedidos` e `/minha-conta/ingressos`: listas com situação, prazo
  de reserva (contagem regressiva simples) e botão de comprovante quando
  `receiptAvailable`.
