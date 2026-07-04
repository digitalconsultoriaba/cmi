# Data Model — 010-fluxo-caixa

**Novas tabelas** (maior adição de esquema desde a 001 — módulo novo). Dinheiro
DECIMAL(10,2); datas UTC; soft delete + `created_by`/`updated_by` nas tabelas de
negócio (constituição). Histórico via `activity_log` (008) — sem tabela nova.

## financial_categories
`id, direction (income|expense), name, is_active (bool), sort, audit`
- Uso: categoriza receitas e despesas. Não excluir se houver `financial_entries`
  vinculados → só `is_active=false` (409 no destroy com uso).

## financial_people  (fornecedores / clientes)
`id, kind (supplier|customer|sponsor|participant|provider|other), name,
document (CPF/CNPJ), phone, whatsapp, email, notes, is_active, audit`
- Vinculável a contas a pagar/receber.

## financial_payment_methods  (lookup seedado)
`id, slug, name, is_active, sort`
- Seed: pix, credit_card, debit_card, boleto, cash, transfer, deposit, barter,
  courtesy, other. Aparece em baixa, filtros e relatórios.

## financial_entries  (o lançamento — a pagar OU a receber)
```
id
direction         payable | receivable            (a pagar = saída / a receber = entrada)
description
amount            DECIMAL(10,2)  > 0               (valor original)
settled_amount    DECIMAL(10,2)  default 0         (cache recontável = Σ baixas líquidas)
category_id       → financial_categories (nullable)
payment_method_id → financial_payment_methods (nullable, previsto)
event_id          → events (NULLABLE = administrativo/geral)
person_id         → financial_people (nullable)
due_date          DATE
origin            manual|ticket|sponsorship|inscription|event_expense|
                  admin_expense|admin_income|adjustment|other
source_type       nullable  (Order | SponsorshipInstallment — espelho)
source_id         nullable
installment_group nullable (UUID) · installment_number · installment_total
recurrence_id     → financial_recurrences (nullable)
cancelled_at      nullable · cancel_reason
notes
audit (created_by/updated_by) · timestamps · softDeletes
UNIQUE (source_type, source_id)   -- zero duplicidade do espelho (FR-020)
```
**Status derivado** (nunca coluna): cancelled (cancelled_at) → recebido/pago
(settled == amount) → parcial (0 < settled < amount) → vencido (due_date < hoje
e não quitado/cancelado) → em aberto.

## financial_settlements  (baixas / movimentações de dinheiro)
`id, entry_id → financial_entries, amount DECIMAL(10,2) > 0, kind (payment|
receipt|reversal), settled_on DATE, payment_method_id → …, bank_account
(nullable string), note, created_by, timestamps`
- Baixa total = uma settlement quitando; parcial = várias. Estorno = kind
  `reversal` (valor negativo no líquido). `settled_amount` da entry = Σ
  (payment/receipt) − Σ reversal, recontado sob lock.

## financial_attachments
`id, entry_id → financial_entries, path, kind (receipt|invoice|contract|boleto|
other), original_name, uploaded_by, timestamps`
- Disco `public`. Ver/baixar/remover conforme papel.

## financial_recurrences
`id, direction, description, amount, category_id, person_id, event_id (nullable),
payment_method_id, frequency (weekly|monthly|yearly), starts_on, ends_on
(nullable), max_occurrences (nullable), last_generated_on, is_active, audit`
- Comando `financial:generate-recurrences` materializa os próximos
  `financial_entries` até o término/limite (sem loop infinito).

## Derivações (calculadas na consulta — princípio II)

```
saldoRestante(entry)   = amount − settled_amount          (piso 0)
receitaPrevista(escopo)= Σ amount das receivable não canceladas
receitaRealizada       = Σ settled_amount das receivable não canceladas
despesaPrevista        = Σ amount das payable não canceladas
despesaRealizada       = Σ settled_amount das payable não canceladas
saldoPrevisto          = receitaPrevista − despesaPrevista
saldoRealizado         = receitaRealizada − despesaRealizada
resultadoEvento(ev)    = receitaRealizada(ev) − despesaRealizada(ev)
escopo: tudo | por evento (event_id) | por período (due_date/settled_on) | ...
vencidos: due_date < hoje E status ∈ {em aberto, parcial}
```

## Espelho automático (observers, upsert idempotente)

```
OrderObserver (saved):  se pedido não-cortesia → upsert financial_entry
  direction=receivable, origin=ticket, source=(Order,id), event_id=evento,
  amount=total do pedido, settled_amount conforme pago; status espelha o pedido
  (pending→em aberto, paid→recebido, cancelled→cancelado, refunded→estorno)
SponsorshipInstallmentObserver (saved): upsert por parcela
  direction=receivable, origin=sponsorship, source=(SponsorshipInstallment,id),
  amount=valor da parcela, settled conforme paga
Espelhadas = read-only na UI (geridas pela sincronização). Manuais = editáveis.
```

## Invariantes (verificáveis em teste)

1. Nenhum lançamento com `amount <= 0`.
2. `settled_amount` nunca ultrapassa `amount`; saldo nunca negativo.
3. Cancelado não entra em nenhum saldo; some da listagem salvo filtro de
   cancelados; permanece no histórico.
4. Uma mesma origem (Order/SponsorshipInstallment) nunca gera dois entries
   (UNIQUE source).
5. Cortesia nunca vira receita.
6. Editar entry já baixada exige justificativa e gera log; sem alteração
   silenciosa.
7. Categoria/pessoa com uso não é excluída — só inativada.
8. Parcelas: Σ das parcelas = total; baixar uma não altera as outras.
9. Toda baixa/estorno recontam `settled_amount` sob lock (sem corrida).
```
