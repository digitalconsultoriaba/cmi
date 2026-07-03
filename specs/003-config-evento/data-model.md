# Data Model — 003-config-evento

**Nenhuma tabela nova** — a fundação (spec 001) criou todo o schema. Esta spec
define regras de escrita, validações e transições dos fluxos de gestão.

## Tabelas movimentadas

| Tabela | Operações nesta spec |
|---|---|
| `events` | update de configuração; publish/cancel (status); banner_path |
| `event_types` | CRUD admin (soft delete; sem exclusão quando em uso) |
| `ticket_types` | CRUD + ordenação + ativação; guardas de venda |
| `ticket_lots` | CRUD + ordenação + ativação; guardas de venda |
| `event_shirt_models` / `event_shirt_sizes` | CRUD hierárquico; estoque ≥ vendido |
| `landing_blocks` | CRUD + reorder em massa + ativação; payload validado por tipo |
| `courtesy_vouchers` | geração em lote; distribute (ciclo só avança); listagem |
| `sponsorships` / `sponsorship_installments` | criação com parcelas; baixa por parcela; status recalculado |

## Regras de escrita (validações de negócio → 409)

1. **Publicar** exige: `name`, `starts_at`, `event_type_id` e ≥ 1 `ticket_type`
   ativo (não deletado). Recusa lista os itens faltantes.
2. **Cancelar** exige `cancel_reason`; grava `cancelled_at`/`cancelled_by`.
   Evento em status terminal (cancelled/finished) rejeita update/publish/cancel
   (guarda `transitionTo` da 001).
3. **Exclusão protegida**: `ticket_types`, `ticket_lots` e `event_shirt_sizes`
   com tickets vivos vinculados (COUNTS_CAPACITY da 001) não podem ser excluídos —
   apenas desativados. `event_types` vinculados a eventos não podem ser excluídos.
4. **Capacidade**: `events.total_capacity` e `ticket_types.capacity` não podem
   ficar abaixo da contagem de tickets vivos correspondente.
5. **Estoque**: `event_shirt_sizes.stock_quantity` não pode ficar abaixo de
   `sold_count` (null = ilimitado, sempre válido).
6. **Voucher**: criação sempre em `available`; `distribute` só de `available`
   (transição via ciclo da 001); `redeemed` é intocável aqui (resgate na 004).
7. **Parcela de patrocínio**: baixa exige parcela `pending`; grava
   `paid_at`/`paid_amount`/`method`/`registered_by`; parcela `paid` rejeita nova
   baixa. `installments_count` ≥ 1; soma das parcelas geradas = `total_amount`
   (divisão igual, resto na última).
8. **Todo update** passa pela auditoria automática da fundação
   (created_by/updated_by/soft delete) — nenhuma exclusão física.

## Transições de estado

```
Evento:      draft ──publish──► published ──cancel──► cancelled (terminal)
             draft ──cancel──► cancelled            published ──(spec 008)──► finished
             cancelled/finished: rejeitam update/publish/cancel (409)

Voucher:     available ──distribute──► distributed ──(spec 004)──► redeemed
             (retroceder: 409)

Patrocínio:  status = f(parcelas) — recalculado em transação:
             nenhuma paga → pending · algumas → partial · todas → paid
             cancelamento manual → cancelled (preserva parcelas)
Parcela:     pending ──pay──► paid (repetir: 409)
```

## Derivações consumidas (fundação — nunca recalcular no front)

- Lote vigente e preço efetivo: `Event::currentLot()`, `TicketLot::effectivePrice()`
- Esgotamentos: `TicketLot::soldOut()`, `EventShirtSize::soldOut()`,
  `TicketType::available()`
- As telas exibem esses valores vindos da API (serializados nos resources).

## Validações de entrada (422)

- Dinheiro: decimal ≥ 0 com 2 casas (front normaliza vírgula → ponto).
- Datas: `ends_at ≥ starts_at`; janelas de lote com `ends_at ≥ starts_at`.
- Banner: imagem jpeg/png/webp ≤ 5 MB.
- Bloco de landing: `type` ∈ tipos suportados; payload por tipo — hero: `title`
  obrigatório; text: `body`; schedule/speakers/faq: `items` array não vazio;
  location: `address`; cta: `label`.
- Voucher: `quantity` 1–500 por geração.
