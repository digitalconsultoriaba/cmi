# Data Model — 005-pagamento

**Nenhuma tabela nova** — `payments` e `webhook_events` (001) já têm todas as
colunas. Esta spec define a máquina de estados da baixa.

## Máquina de estados

```
Payment:  (nasce) pending ──RegisterPayment──► paid (terminal p/ baixa; refund na 006)
          pending ──nova cobrança/expiração──► expired
          pending ──recusa de cartão──► failed (pedido segue pending; pode tentar de novo)

Order:    pending ──RegisterPayment (valor exato)──► paid
          pending ──RegisterPayment (valor divergente)──► partially_paid (+ pendência derivada)
          expired/cancelled + pagamento recebido → INTOCADO (payment paid registrado; pendência)

Ticket:   reserved ──RegisterPayment (caminho feliz)──► confirmed
```

## Fluxo da baixa (RegisterPayment — único caminho)

```
DB::transaction:
├─ lockForUpdate(payment)
├─ payment já paid? → retorna (idempotente — SC-002)
├─ registra evidência: paid_at, raw_response (payload bruto), registered_by (manual)
├─ valor recebido ≠ order.total? → payment paid + order partially_paid; FIM
├─ order terminal (expired/cancelled)? → payment paid; order intocado; FIM
└─ payment paid → order paid → tickets vivos confirmed → (pós-commit) e-mail
```

Origens da baixa (`evidence.source`): `webhook` | `reconciliation` | `manual` |
`gateway` (cartão síncrono). Todas gravam a mesma estrutura de evidência.

## Dedupe e idempotência (estrutural)

| Camada | Mecanismo |
|---|---|
| Webhook repetido | unique `(provider, external_id)` em webhook_events → 200 `ignored` |
| Cobrança repetida | unique `(provider, provider_charge_id)` em payments |
| Baixa repetida | guarda "já paid" sob `lockForUpdate` |
| Webhook forjado | segredo no header + reconsulta obrigatória ao provedor |

## Regras de criação de cobrança

1. Só o comprador, pedido `pending`, dentro de `reserved_until`, meio habilitado
   no evento (flags da 003) — senão 403/409.
2. Nova cobrança expira as `pending` anteriores do pedido (+ `cancelCharge`
   melhor esforço) — uma ativa por vez.
3. Pix: expiração = segundos até `reserved_until`; boleto: vencimento no dia de
   `reserved_until`; cartão: cobrança síncrona (sem estado intermediário).
4. Campos por meio: pix → `pix_qrcode` (copia-e-cola) + `pix_qrcode_image`
   (SVG); boleto → `boleto_line`, `boleto_barcode`, `boleto_pdf_url?` + pix da
   cobrança híbrida; cartão → `card_brand`, `card_last4`, `installments`.

## Consultas derivadas (sem colunas novas)

- **Pendências da tesouraria**: payments `paid` cujo order não está `paid`
  (expirado, cancelado ou parcial) — destaque na listagem de recebimentos.
- **payment-status do comprador**: `{ status: order.status, paidAt: payment.paid_at }`.

## Invariantes (verificáveis em teste)

1. Um evento externo (webhook/reconciliação/manual repetidos) nunca produz duas
   baixas nem soma valor duas vezes.
2. Nenhum registro contém PAN/CVV/validade — em nenhuma coluna, log ou payload.
3. Todo payment `paid` tem evidência bruta e origem registradas.
4. Order `paid` ⇔ existe payment `paid` com valor exato (ou baixa manual com
   justificativa).
5. Tickets só passam a `confirmed` via RegisterPayment (nunca por fora).
