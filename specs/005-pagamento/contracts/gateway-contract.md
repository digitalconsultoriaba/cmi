# Contrato — Gateway de pagamento (005)

Interface `App\Domain\Events\Payments\PaymentGatewayContract` — princípio IV da
constituição: trocar provedor sem reescrever o fluxo.

## Interface

| Método | Entrada | Saída | Obrigações |
|---|---|---|---|
| `createPixCharge(Order)` | pedido pending | `ChargeData{ externalId, pixCopiaECola, expiresAt }` | expiração ≤ reserved_until |
| `createBoletoCharge(Order)` | idem | `ChargeData{ externalId, boletoLine, boletoBarcode, pdfUrl?, pixCopiaECola? }` | híbrido quando o provedor suportar |
| `chargeCard(Order, token, installments)` | token opaco (nunca PAN) | `CardResult{ approved, externalId?, brand?, last4?, declineReason? }` | síncrono |
| `getChargeStatus(Payment)` | payment com externalId | `ChargeStatus{ state: paid\|pending\|expired\|cancelled, paidAmount?, paidAt?, raw }` | fonte de verdade da baixa |
| `cancelCharge(Payment)` | payment pending | void | melhor esforço; falha não propaga |

## Drivers

| Driver | Config | Comportamento |
|---|---|---|
| `SicoobGateway` | `payments.pix_driver=sicoob` | via `SicoobClient` (OAuth2 client_credentials + mTLS; PUT `/cob/{txid}`, boleto `"hibrido": true`, GET cobrança, cancel). Credenciais só por env (`SICOOB_CLIENT_ID`, `SICOOB_CERT_PATH`, `SICOOB_CERT_KEY_PATH`, `SICOOB_SANDBOX`, `SICOOB_WEBHOOK_SECRET`) |
| `FakePixGateway` | `payments.pix_driver=fake` (default dev/teste) | gera externalId/copia-e-cola determinísticos; `getChargeStatus` responde de um "banco simulado" controlável em teste (marcar cobrança como paga) |
| `FakeCardGateway` | `payments.card_driver=fake` (default) | `tok_ok_*` → aprovado (brand/last4 fake); `tok_declined_*` → recusado com motivo; nunca aceita nada parecido com PAN (guarda: token com 13+ dígitos seguidos → exceção) |

## Regras transversais

- Nenhum driver recebe, armazena ou loga PAN/CVV/validade (SC-005 varre).
- Todo retorno carrega `raw` (evidência bruta) para `payments.raw_response`.
- Falha de comunicação ao CRIAR cobrança → erro claro 502-like no envelope
  (`type: gateway_error`), nada persistido pela metade.
- `RegisterPayment` é o ÚNICO consumidor de `ChargeStatus.paid` — drivers nunca
  tocam em orders/tickets.

## Simulação em testes

- `FakePixGateway::settle(externalId, ?amount)` marca a cobrança paga no banco
  simulado — os testes de webhook/reconciliação usam isso como "pagou no banco".
- O simulador de webhook dos testes envia o POST assinado com o secret de teste.
