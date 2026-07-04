# Research — 006-ciclo-vida-suporte

Decisões técnicas do pós-venda. Base: specs 001–005 e a política de reembolso
definida pelo organizador (integral até 7 dias antes do evento).

---

## Decisão 1: `RefundPolicy` como classe pura de cálculo

**Decisão**: `RefundPolicy::refundableAmount(Ticket): string` centraliza a
política em um único lugar testável:
- 100% se `now ≤ event.starts_at − 7 dias` (config `events.refund_full_until_days`);
- 100% se `now ≤ order.created_at + 7 dias` (piso CDC, config
  `events.refund_purchase_grace_days`);
- senão `0.00` (cancelar exige `confirm_no_refund`).
Cancelamento de EVENTO ignora a política: sempre 100% (`refundableForEventCancellation`).

**Rationale**: FR-003; valores em config (sem tela no MVP) — a Fase 2 muda
número, não código.

---

## Decisão 2: `TicketLifecycleService` — cancelar/transferir com o mesmo lock da compra

**Decisão**: cancelamentos e transferências rodam em `DB::transaction` com
`lockForUpdate` no evento (mesmo mutex do purchase da 004):
- `cancelTicket(Ticket, actor, ?reason, bool $confirmNoRefund)`: guardas
  (flag do evento, dono, não terminal) → ticket `cancelled`
  (cancel_requested_by/cancelled_by/motivo) → recount de lote/estoque → se pago
  e política > 0, abre caso de reembolso; se política = 0, exige confirmação.
- `cancelOrder(Order, …)`: cancela tickets vivos um a um + order `cancelled` +
  `expirePendingPayments` (005); pedido pago vira UM caso de reembolso do
  `amountPaid`.
- `transferTicket(Ticket, actor, participant[])`: guardas (pago/confirmado,
  flag, antes do início, não voucher-resgatado) → original `transferred` →
  novo ticket clonando snapshot (tipo/lote/preço/camisa) com participante novo,
  `status confirmed`, vínculos from/to nas duas pontas → e-mail ao destinatário.
  Vagas: neutro (transferred sai da contagem, o novo entra).

**Rationale**: princípio II (recontagem sob lock); a transferência preserva o
histórico como manda o princípio V.

**Detecção de voucher**: ingresso com `CourtesyVoucher.redeemed_ticket_id`
apontando para ele não transfere (FR-005) — consulta direta, sem coluna nova.

---

## Decisão 3: Caso de reembolso = SupportCase type `refund` (tabela existente)

**Decisão**: o "pedido de reembolso" é um `support_case` `type=refund` com
`refund_amount` calculado, vinculado ao ticket (ou ao pedido, no cancelamento
total) e ao usuário; nasce `open` com uma nota automática descrevendo origem e
política aplicada. A fila da tesouraria é a listagem desses casos.

**Rationale**: o schema da 001 foi desenhado exatamente para isso (base:
"support_cases substitui refund_cases"); nenhuma tabela nova.

---

## Decisão 4: Estorno — emenda ao contrato do gateway + registro no ticket

**Decisão**:
1. **Emenda ao `PaymentGatewayContract` (005)**: novo método
   `refundCharge(Payment, string $amount): RefundResult` — FakeCardGateway
   implementa (estorna no "banco simulado"); FakePixGateway/Sicoob lançam
   `RefundNotSupported` → força o fluxo operacional (devolução Pix automática é
   Fase 2). Emenda registrada no contrato da 005.
2. **`RefundPayment` service** (tesouraria): guardas (caso aberto, pagamento
   `paid`, operador ≠ comprador — mesma regra da baixa); cartão → via provedor;
   demais → operacional com justificativa obrigatória. Efeitos: registra
   `refunded_at`/`refund_amount` no(s) ticket(s) do caso; payment
   `transitionTo(refunded)` **apenas quando a devolução cobre o valor total do
   payment** (estorno parcial mantém `paid` — o dinheiro restante é receita);
   caso `finished` com nota automática; e-mail `RefundCompletedPtBr`.

**Rationale**: FR-008–FR-011; `payment_statuses.refunded` é terminal (001) —
parcial não pode transicionar, então o registro parcial vive nos campos do
ticket (dados existentes) e na trilha.

---

## Decisão 5: Cascata do cancelamento de evento — síncrona e resiliente

**Decisão**: `CancelEventCascade::run(Event)` chamado pelo
`EventConfigService::cancel` (003): itera pedidos vivos com try/catch por
pedido (falha não interrompe — FR-014): pendentes → cancelados + cobranças
invalidadas; pagos → cancelados + caso de reembolso **100%** por pedido +
e-mail `EventCancelledPtBr`. Execução síncrona no request (volume MVP: centenas
de pedidos ≪ timeout); registro em log de cada falha.

**Rationale**: Assumption da spec (fila humana, sem estorno em massa
automático); job assíncrono seria otimização prematura no single-event.

---

## Decisão 6: Suporte — controllers finos sobre o schema pronto

**Decisão**:
- Inscrito: CRUD dos próprios casos (`SupportCasePolicy`), notas com
  `from_attendee=true` e **apenas** notas `visible_to_attendee` no payload;
  reabrir caso finalizado = nova nota + status `reopened`.
- Organização (`require.role:admin,treasury`): fila com filtro por
  status/tipo, notas públicas ou internas, finalizar/reabrir.
- Transições: `open → finished ⇄ reopened` (reabertura ilimitada — Assumption).

**Rationale**: FR-012/FR-013; visibilidade filtrada na serialização (nota
interna nunca sai no JSON do inscrito — SC-006).

---

## Decisão 7: E-mails do ciclo — 4 notificações pt-BR, padrão da 005

**Decisão**: `TicketCancelledPtBr` (confirmação + info de reembolso),
`TicketTransferredPtBr` (ao NOVO titular, com link dos ingressos),
`RefundCompletedPtBr` (devolução efetuada) e `EventCancelledPtBr` (aviso geral
por comprador). Síncronas com try/catch (falha nunca bloqueia).

---

## Decisão 8: Front — ações nos ingressos + suporte no chrome do inscrito + filas no painel

**Decisão**:
- `MeusIngressos`: botões Cancelar (modal com valor do reembolso calculado pela
  API — ou aviso "sem devolução" com confirmação) e Transferir (modal
  nome/e-mail) conforme elegibilidade vinda do resource
  (`cancellable`/`transferable`/`refundPreview`).
- `MeusPedidos`: cancelar pedido pendente/pago.
- `/minha-conta/suporte`: lista + conversa (nova mensagem, reabrir).
- Painel: `Suporte.jsx` (fila admin+treasury, responder público/interno,
  finalizar) e `Tesouraria.jsx` ganha a seção **Estornos** (casos refund
  abertos → executar com justificativa).

**Rationale**: elegibilidade calculada no servidor (derivação — o front nunca
reimplementa política); reuso do chrome existente.

---

## Riscos / notas

- **Estorno parcial × payment terminal**: documentado na Decisão 4 — o valor
  devolvido vive no ticket; conciliação contábil fina é a 008.
- **Transferência herda camisa**: mantém modelo/tamanho (estoque neutro); troca
  de tamanho é caso de suporte (`shirt_change`).
- **Cortesia X→Y órfã** (pagador cancelado): mantida por decisão da spec;
  revisar na Fase 2 se virar abuso.
- Zero migrations e zero dependências novas — sexta spec consecutiva sobre o
  schema da fundação.
