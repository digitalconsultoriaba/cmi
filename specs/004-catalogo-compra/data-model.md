# Data Model — 004-catalogo-compra

**Nenhuma tabela nova** — schema da fundação (001). Esta spec define o fluxo de
escrita da compra e as transições que ela movimenta.

## Fluxo de escrita da compra (uma transação, lock no evento)

```
lockForUpdate(evento)
├─ validar: publicado ∧ salesOpen (janela + lote vigente + vagas)     → 409
├─ por item do carrinho:
│  ├─ tipo ativo do evento; lote vigente do tipo (preço efetivo)      → 409
│  ├─ recontar capacidade (evento e tipo) incluindo casal ×2          → 409 sold_out
│  └─ recontar estoque por tamanho (titular + acompanhante)           → 409 sold_out
├─ CourtesyResolver: cortesias automáticas (regra X→Y, limite/conta,
│  recontagem de cortesias vivas do comprador; ocupam vaga)           → 409 se não couber
├─ criar order: code ORD-…, buyer congelado (nome/email/documento),
│  total = Σ preços efetivos, reserved_until = now + TTL do evento,
│  status = pending (ou paid se total = 0)
├─ criar tickets: 1/participante — snapshot (unit_price efetivo, tipo,
│  lote, camisa, acompanhante), code TCK-…,
│  status = reserved (pagáveis) | courtesy (cortesias)
│  └─ total = 0 → tickets confirmed/courtesy direto
└─ recountSold() nos lotes e tamanhos afetados (cache coerente)
```

**Voucher** (pode vir junto do carrinho ou sozinho): na mesma transação, mas em
**pedido separado** (research, Decisão 2): voucher `lockForUpdate` → deve estar
`distributed` e ser do evento → cria order própria (total 0 → paid) com 1 ticket
`courtesy` confirmado → voucher `transitionTo('redeemed')` + `redeemed_ticket_id`.

## Transições movimentadas

```
Order:  (nasce) pending ──expira──► expired (terminal)
        (nasce) paid  — quando total = 0 (cortesias/voucher/evento gratuito)
        pending ──(spec 005)──► paid / partially_paid

Ticket: (nasce) reserved — pagável aguardando
        (nasce) courtesy — cortesia confirmada
        reserved ──expiração──► cancelled (cancel_reason = "Reserva expirada")

Voucher: distributed ──resgate──► redeemed (+ redeemed_ticket_id)
         (available não é resgatável: só vouchers distribuídos — FR-010)
```

## Regras de validação (409, tipo indicado)

| Regra | type |
|---|---|
| Evento não vendável (não publicado/janela/sem lote/lotado) | `sales_closed` |
| Capacidade do evento/tipo insuficiente (incl. casal ×2 e cortesias) | `sold_out` |
| Lote vigente sem saldo para a quantidade | `sold_out` |
| Estoque do tamanho insuficiente (titular + acompanhante) | `sold_out` |
| Voucher inexistente/de outro evento/não distribuído/já resgatado | `invalid_voucher` |
| Pedido expirado sendo usado | `terminal_status` |

Validações de entrada (422): itens 1..20 no total (config `events.max_tickets_per_order`),
participante com nome obrigatório, camisa obrigatória quando `requires_shirt`,
casal exige acompanhante, tamanho/modelo pertencem ao evento e coerentes entre si.

## Consultas do inscrito

- **Meus pedidos**: `orders.buyer_user_id = eu`, com tickets aninhados.
- **Meus ingressos**: `tickets.participant_user_id = eu` **ou**
  `participant_email = meu e-mail` (claim preguiçoso preenche o user_id no
  primeiro acesso — research, Decisão 6).
- **Comprovante**: ticket com status ∈ {confirmed, courtesy, paid, used} e
  requisitante ∈ {participante, comprador}.

## Catálogo público (somente leitura)

`GET /public/events/{slug}` compõe: evento publicado (404 se rascunho; payload
mínimo com aviso se cancelado), blocos ativos ordenados, tipos ativos com preço
efetivo do lote vigente + esgotamentos, e o estado agregado
(`salesOpen` | `soonAt` janela futura | `closed` | `soldOut`) — todas as
derivações vêm da fundação, nada recalculado.

## Invariantes (verificáveis em teste)

1. Nunca existem mais tickets vivos que `total_capacity`/`capacity`/`quantity`/
   `stock_quantity` (casal contando 2) — mesmo sob contenção.
2. `unit_price` do ticket nunca muda após a criação (snapshot).
3. Todo voucher `redeemed` tem exatamente 1 ticket vinculado, e nunca volta.
4. Pedido `expired` não tem tickets vivos, e as disponibilidades refletem a
   liberação imediatamente.
5. Pedido com `total_amount = 0` nunca fica `pending`.
