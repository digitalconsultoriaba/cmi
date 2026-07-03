# Contrato — Derivações de domínio (fundação)

Princípio constitucional II: estado operacional é **sempre calculado**; nenhuma
coluna editável equivalente pode existir. Este contrato fixa as definições que as
specs 003–008 consomem. Toda alteração aqui é emenda de contrato.

## Definições

**Tickets vivos** (ocupam vaga/estoque): status ∈ {reserved, awaiting_payment, paid,
confirmed, courtesy}. Exclui: cancelled, refunded, transferred, used*, expired.
(*used ocupou vaga mas o evento já consumiu; para disponibilidade de venda conta-se
como vivo até o evento — decisão: **used conta como vivo** para capacidade, pois a
vaga foi consumida de fato. Excluir used apenas de contagens de "aguardando".)

**Disponível por tipo** = `ticket_type.capacity − COUNT(tickets vivos do tipo)`;
`capacity` null = ilimitado (disponível = null, nunca esgota por tipo).

**Disponível do evento** = `event.total_capacity − COUNT(tickets vivos do evento)`;
null = ilimitado.

**Lote vigente** (por tipo de ingresso, ou global quando `ticket_type_id` null):
elegível = `is_active` ∧ (starts_at null ∨ agora ≥ starts_at) ∧ (ends_at null ∨
agora ≤ ends_at) ∧ (quantity null ∨ sold_count < quantity). Vigente = primeiro
elegível por `sort` ASC, id ASC (determinístico). Lote específico do tipo tem
precedência sobre lote global.

**Preço efetivo** = `lote_vigente.price_override ?? ticket_type.price`.

**Inscrições abertas** (`salesOpen`) = status = published ∧ (sales_start_at null ∨
agora ≥ sales_start_at) ∧ (sales_end_at null ∨ agora ≤ sales_end_at) ∧ existe lote
vigente ∧ (disponível do evento é null ∨ > 0).

**Esgotado** (evento) = disponível do evento ≤ 0 (quando capacidade definida).

**Camisa esgotada** = `stock_quantity` não nulo ∧ `sold_count ≥ stock_quantity`.

**Previsto × confirmado** = SUM(unit_price) dos tickets agrupada por situação
(previsto = vivos; confirmado = paid, confirmed, courtesy, used).

## Regras de implementação

- `sold_count` (lotes, tamanhos de camisa) é **cache recalculável**: fonte de verdade
  é a contagem de tickets vivos; método `recount()` recalcula dentro da mesma
  transação de escrita. Specs 004+ DEVEM incrementar/recontar dentro de
  `DB::transaction` (proteção contra race).
- Transições de status passam por `transitionTo()`; status terminal lança
  `DomainRuleViolation` → 409. Terminais: ticket {cancelled, refunded, transferred,
  used}; order {cancelled, expired, refunded}; payment {refunded, chargeback};
  voucher só avança (available → distributed → redeemed).

## Verificação (testes desta spec)

Cenários mínimos, todos sem editar campo de status manualmente:
1. Evento publicado + janela aberta + lote com saldo + vagas → `salesOpen` true.
2. Janela encerrada OU sem lote elegível OU capacidade atingida → `salesOpen` false.
3. Lote 1 esgota por quantidade → lote vigente passa ao 2; preço efetivo muda;
   lote 1 expira por data → idem.
4. Lotes com janelas sobrepostas → vigente é o de menor `sort` (determinístico).
5. `price_override` null → preço efetivo = preço do tipo.
6. Camisa com estoque 10 e 10 vendidos → esgotada; estoque null → nunca esgota.
7. `transitionTo` sobre ticket `used` → `DomainRuleViolation`.
