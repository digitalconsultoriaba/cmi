# Contract — Público: Checkout guest (sem auth)

Rotas **abertas** (sem `auth:sanctum`), fora do grupo admin. Sucesso `{ data }` camelCase; erros `{ message, type, status, errors? }` (422 validação, 409 `DomainRuleViolation`).

## `GET /public/events/{event:slug}/checkout-config`

Dados para montar o checkout do evento: tipos de ingresso compráveis (catálogo público já existente), **categorias de participante** ativas com seus **campos**, e a **lista de afiliações**.

**200** →
```json
{ "data": {
  "event": { "slug": "seminario-2026", "name": "Seminário Internacional", "salesState": "open" },
  "ticketTypes": [ { "id": 1, "name": "Individual", "effectivePrice": "250.00", "isCouple": false, "soldOut": false } ],
  "categories": [
    { "key": "glmees", "label": "Irmão da GLMEES", "fields": [
      { "key": "loja", "label": "Loja", "type": "affiliation", "required": true },
      { "key": "cargo", "label": "Cargo na loja", "type": "conditional", "required": false, "config": { "question": "Possui cargo na loja?" } }
    ] },
    { "key": "outra_potencia", "label": "Irmão de outra potência", "fields": [
      { "key": "potencia", "label": "Potência", "type": "text", "required": true },
      { "key": "pais", "label": "País", "type": "country", "required": true },
      { "key": "cidade", "label": "Cidade", "type": "city", "required": true }
    ] }
  ],
  "affiliations": [ { "id": 10, "name": "Loja Exemplo nº 1" } ]
} }
```

## `POST /public/vouchers/validate`

Valida um voucher **sem** criar pedido (para a UI aplicar por inscrição). Não resgata.

**Body**: `{ "eventSlug": "seminario-2026", "code": "CTY-ABC123", "ticketTypeId": 1 }`
- **200** → `{ data: { valid: true, message: "Voucher aplicado com sucesso. Esta inscrição foi isenta de pagamento." } }`
- **200** (inválido) → `{ data: { valid: false, message: "Voucher inválido, expirado ou já utilizado. Verifique o código informado." } }`

## `POST /public/orders`

Cria o pedido (guest) na finalização. Cada item é uma inscrição; itens com `voucherCode` viram cortesia (pedido misto). Total é **recalculado no servidor** (transação com recontagem).

**Body**:
```json
{
  "eventSlug": "seminario-2026",
  "buyer": { "name": "Comprador", "email": "comprador@ex.com" },
  "items": [
    { "ticketTypeId": 1, "participantName": "Irmão 1", "participantEmail": "i1@ex.com",
      "categoryKey": "glmees", "fields": { "loja": "Loja Exemplo nº 1", "cargo": "Venerável" } },
    { "ticketTypeId": 1, "participantName": "Irmão 2", "participantEmail": "i2@ex.com",
      "categoryKey": "outra_potencia", "fields": { "potencia": "GOB", "pais": "Brasil", "cidade": "Vitória" },
      "voucherCode": "CTY-ABC123" }
  ]
}
```
- **201** → `{ data: { order: { code, status, totalAmount, tickets: [ { code, participantName, unitPrice, isCourtesy, status } ] }, payment: { required: true } } }`
  - `payment.required = false` quando total = 0 (pedido **gratuito**, já confirmado; ingressos emitidos + magic links).
- **422** → validação (campos obrigatórios da categoria, e-mail de participante ausente com >1 participante, afiliação fora da lista, tipo inválido).
- **409** → regra de negócio: esgotado, fora da janela de vendas, voucher inválido/já usado no envio, capacidade/lote insuficiente (recontagem).

## Pagamento (reutiliza o fluxo existente, exposto ao guest)

Após `POST /public/orders` com `payment.required = true`, o guest inicia o pagamento pelo `code` do pedido:

- `POST /public/orders/{order:code}/checkout/pix` → QR + copia-e-cola
- `POST /public/orders/{order:code}/checkout/card` → token do cartão + parcelas (tokenização no gateway; PAN nunca no backend)
- `GET /public/orders/{order:code}/payment-status` → status da cobrança

Aprovação passa pelo ponto único `RegisterPayment` (idempotente); ao confirmar, tickets pagos → `PAID`, e-mails de ingresso + magic links disparados.

**Autorização do guest**: o acesso ao pedido antes do login é feito pelo `code` (não sequencial) recém-criado na sessão do checkout; o back-office completo exige o magic link.
