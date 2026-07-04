# Data Model — 006-ciclo-vida-suporte

**Nenhuma tabela nova** — campos de cancelamento/transferência/reembolso
(tickets), casos e notas (support_*) existem desde a 001.

## Transições movimentadas

```
Ticket:  reserved/awaiting/paid/confirmed/courtesy ──cancelar──► cancelled
         paid/confirmed ──transferir──► transferred (+ novo ticket confirmed)
         (cancelled/transferred/used: terminais — recusam tudo, 409)

Order:   pending/paid/partially_paid ──cancelar──► cancelled
         (cascata: tickets vivos cancelam junto; cobranças pendentes expiram)

Payment: paid ──estorno TOTAL──► refunded (terminal)
         paid ──estorno PARCIAL──► permanece paid (valor devolvido registrado
                                    nos tickets; diferença = receita)

SupportCase: (nasce) open ──finalizar──► finished ──reabrir──► reopened ──► finished…
```

## Regras de negócio (409/403)

| Operação | Guardas |
|---|---|
| Cancelar ticket/pedido | `allow_user_cancel`; dono (comprador ou titular); situação não terminal; política = 0 → exige `confirm_no_refund=true` |
| Transferir | `allow_transfer`; status ∈ {paid, confirmed}; antes de `event.starts_at`; NÃO resgatado de voucher; destinatário com nome+e-mail |
| Estornar | caso refund `open`; payment `paid`; operador ≠ comprador do pedido (403); repetido → 409 |
| Suporte | inscrito só vê os próprios casos e notas públicas (403 fora do escopo) |

## Política de reembolso (RefundPolicy — decisão do organizador)

```
refundableAmount(ticket):
  se now ≤ order.created_at + 7 dias        → 100% (piso CDC)
  senão se now ≤ event.starts_at − 7 dias   → 100% (política)
  senão                                     → 0.00 (cancela só com confirmação)

Cancelamento de EVENTO → 100% sempre, por pedido pago (amountPaid).
```

Configs: `events.refund_full_until_days = 7`,
`events.refund_purchase_grace_days = 7`.

## Efeitos por fluxo

**Cancelar ticket pago** (transação + lock do evento):
ticket → cancelled (requested_by/cancelled_by/reason) → recount lote/camisas →
caso refund `open` (refund_amount = política, vínculo ticket+order+user, nota
automática) → e-mail.

**Cancelar pedido**: idem para cada ticket vivo + order → cancelled +
`expirePendingPayments` + (se pago) UM caso refund do `amountPaid`.

**Transferir**: original → transferred (+`transferred_to_ticket_id`); novo
ticket: mesmo type/lot/`unit_price` (snapshot preservado)/camisas, participante
novo, `status confirmed`, `transferred_from_ticket_id`; claim por e-mail da 004
funciona para o destinatário; e-mail ao novo titular.

**Estornar (tesouraria)**: cartão → `refundCharge` no conector (emenda 005);
demais → operacional com justificativa; tickets do caso ganham
`refunded_at`/`refund_amount`; payment → refunded SE devolução total; caso →
finished com nota automática; e-mail ao comprador.

**Cancelar evento (cascata resiliente)**: por pedido vivo — try/catch
individual: cancela tickets+pedido (motivo "Evento cancelado"), invalida
cobranças; pago → caso refund 100%; e-mail por comprador.

## Consultas derivadas

- **Elegibilidade** no TicketResource: `cancellable`, `transferable`,
  `refundPreview` (valor da política agora) — calculadas no servidor, nunca no
  front.
- **Fila de estornos** = support_cases type refund status open/reopened.
- **Fila de suporte** = todos os cases, filtro status/tipo.

## Invariantes (verificáveis em teste)

1. Cancelamento nunca deixa contagem de vaga/lote/estoque inconsistente
   (recount na mesma transação).
2. Ticket transferido nunca volta a contar vaga nem passa na portaria; o par
   from/to é navegável nos dois sentidos.
3. Caso de reembolso existe ⇔ havia pagamento; valor sempre = política do
   momento do cancelamento (congelado no caso).
4. Payment refunded ⇔ devolução total; parcial mantém paid com registro nos
   tickets.
5. Nota interna jamais aparece em resposta para o inscrito.
6. Cascata de evento: nenhum pedido vivo sobra; nenhum registro é apagado.
