# Contrato — API de pagamento (005)

Envelope/erros da 001. Rotas do comprador exigem sessão + dono do pedido
(OrderPolicy da 004). Webhooks sem sessão, com verificação de origem.

## Checkout (comprador, dono do pedido)

| Método/rota | Regras | Respostas |
|---|---|---|
| `POST /orders/{code}/checkout/pix` | pedido pending, dentro do TTL, `allowPix`; expira cobranças anteriores | 201 payment · 403 · 409 (`sales_closed`/`terminal_status`/`method_disabled`) |
| `POST /orders/{code}/checkout/boleto` | idem, `allowBoleto` | 201 · 403 · 409 |
| `POST /orders/{code}/checkout/card` | `{ token, installments: 1..12 }`; `allowCard`; aprovação síncrona | 200 (pago) · 409 `card_declined` · 403 · 422 |
| `GET /orders/{code}/payment-status` | polling do dono (3s no front) | 200 `{ status, paidAt }` |

**PaymentResource** (retorno das criações):

```json
{
  "data": {
    "method": "pix", "status": "pending", "amount": "400.00",
    "pixQrCode": "000201...br.gov.bcb.pix...",     // copia-e-cola
    "pixQrCodeSvg": "<svg .../>",                   // gerado no servidor
    "boletoLine": null, "boletoBarcode": null, "boletoPdfUrl": null,
    "cardBrand": null, "cardLast4": null, "installments": null,
    "dueDate": "…", "paidAt": null
  }
}
```

## Webhooks (sem sessão)

| Método/rota | Regras |
|---|---|
| `POST /webhooks/sicoob` | header `X-Webhook-Secret` válido → grava webhook_events (dupe → 200 `ignored`) → **reconsulta a cobrança** → RegisterPayment; secret inválido → 401 (registrado `error`) |
| `POST /webhooks/card` | idem para o gateway de cartão |

Resposta 200 `{ data: { result: "ok"|"ignored" } }` — rápida; processamento
mínimo inline (reconsulta + baixa), sem redelivery storm.

## Tesouraria (`require.role:treasury`)

| Método/rota | Regras |
|---|---|
| `GET /treasury/receivables?status=&method=` | recebimentos com pedido, meio, origem da baixa e **pendências derivadas** (pago × pedido não-pago) destacadas |
| `POST /treasury/reconcile` | dispara a reconciliação agora; retorna `{ checked, settled, expired }` |
| `POST /treasury/orders/{code}/pay-manual` | `{ justification (obrig.), method?, paidAt?, amount? }` → RegisterPayment fonte `manual`; **comprador = operador → 403**; pedido já pago → 409 |

## Comandos

- `payments:reconcile` — agendado diário (04:00); mesmo código do disparo manual.
- `orders:expire` (004) — passa a cancelar cobrança ativa ao expirar (melhor
  esforço).

## Contrato de frontend

- `/pedido/{code}/pagar` (dono): abas pelos meios habilitados do evento —
  Pix (QR SVG + copia-e-cola com botão copiar + polling 3s → celebração),
  Boleto (linha digitável + copiar + QR pix híbrido), Cartão (formulário local
  que tokeniza SEM enviar número ao backend — cartões de teste no fake:
  `4242…` aprova, `4000…0002` recusa; parcelas 1..12).
- "Meus pedidos" (004) ganha botão "Pagar" nos pendentes → leva à página acima.
- Painel: item "Tesouraria" (papéis treasury/admin) — recebimentos + conciliar
  agora + baixa manual com justificativa.
