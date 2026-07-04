# Contrato — Ciclo de vida e suporte (006)

Envelope/erros da 001; sessão da 002; códigos públicos nas URLs.

## Inscrito — cancelamento e transferência

| Método/rota | Regras | Respostas |
|---|---|---|
| `POST /tickets/{code}/cancel` | `{ reason?, confirm_no_refund? }` — dono; flag do evento; não terminal; política 0 sem confirmação → 409 `refund_confirmation_required` | 200 (ticket + caso criado se pago) · 403 · 409 |
| `POST /orders/{code}/cancel` | idem, pedido inteiro | 200 · 403 · 409 |
| `POST /tickets/{code}/transfer` | `{ participant_name!, participant_email!, participant_document? }` — pago/confirmado; flag; antes do início; não-voucher | 201 (novo TicketResource) · 403 · 409 (`not_transferable`) |

**TicketResource (004) ganha**: `cancellable`, `transferable`,
`refundPreview` (valor que a política devolve agora — null se não pago),
`transferredToCode`/`transferredFromCode`.

## Inscrito — suporte

| Método/rota | Regras |
|---|---|
| `GET /support-cases` | meus casos (status, tipo, assunto, atualizado em) |
| `POST /support-cases` | `{ type: question\|shirt_change\|refund\|other, subject!, message!, orderCode?, ticketCode? }` → open |
| `GET /support-cases/{id}` | meu caso + notas **públicas** apenas |
| `POST /support-cases/{id}/notes` | `{ message! }` — nota from_attendee; caso finished → reabre (reopened) |

## Organização — fila de suporte (`require.role:admin,treasury`)

| Método/rota | Regras |
|---|---|
| `GET /admin/support-cases?status=&type=` | fila completa com vínculos (pedido/ingresso/comprador) |
| `GET /admin/support-cases/{id}` | caso + TODAS as notas (públicas e internas) |
| `POST /admin/support-cases/{id}/notes` | `{ message!, visible_to_attendee: bool }` |
| `POST /admin/support-cases/{id}/finish` · `/reopen` | transições open⇄finished/reopened |

## Tesouraria — estornos (`require.role:treasury`)

| Método/rota | Regras |
|---|---|
| `GET /treasury/refunds` | fila: cases type refund open/reopened, com valor, pagamento e meio |
| `POST /treasury/refunds/{case}/execute` | `{ justification! (min 10), amount? }` — cartão → `refundCharge` no conector; demais → operacional; **operador = comprador → 403**; payment não-paid/caso fechado → 409 |

## Emenda ao contrato do gateway (005)

`PaymentGatewayContract` ganha `refundCharge(Payment, string $amount): RefundResult`
(`{ refunded: bool, externalId?, raw }`). FakeCardGateway estorna no banco
simulado; FakePixGateway/SicoobGateway lançam `RefundNotSupported` → fluxo
operacional. Registrado como emenda em `specs/005-pagamento/contracts/gateway-contract.md`.

## Cascata do cancelamento de evento

`EventConfigService::cancel` (003) passa a disparar `CancelEventCascade`:
pedidos vivos cancelados (motivo "Evento cancelado"), cobranças invalidadas,
caso refund 100% por pedido pago, `EventCancelledPtBr` por comprador —
try/catch por pedido (resiliente), log de falhas.

## E-mails (pt-BR, falha nunca bloqueia)

`TicketCancelledPtBr` · `TicketTransferredPtBr` (ao novo titular) ·
`RefundCompletedPtBr` · `EventCancelledPtBr`.

## Contrato de frontend

- `MeusIngressos`: botões condicionais (cancellable/transferable) com modais —
  cancelar mostra `refundPreview` ou confirmação "sem devolução"; transferir
  pede nome/e-mail.
- `MeusPedidos`: cancelar pedido.
- `/minha-conta/suporte`: lista + conversa + novo caso + reabrir.
- Painel: `Suporte` (fila, admin+treasury) e seção **Estornos** na Tesouraria
  (executar com justificativa).
