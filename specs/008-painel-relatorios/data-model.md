# Data Model — 008-painel-relatorios

**Uma tabela nova** — `activity_log` (migration do pacote
`spatie/laravel-activitylog`). Todo o resto é **derivado na consulta**
(princípio II; FR-012).

## activity_log (do pacote)

| Campo | Uso aqui |
|---|---|
| `log_name` | tipo da ação (`payment.registered`, `payment.refunded`, `ticket.cancelled`, `ticket.transferred`, `ticket.checked_in`, `courtesy.issued`, `courtesy.redeemed`, `event.updated`, `event.cancelled`, `support.updated`) |
| `description` | frase pt-BR legível ("Baixa manual de R$ 350,00 no pedido ORD-…") |
| `subject_type` / `subject_id` | morph para o objeto afetado (Payment, Ticket, Order, CourtesyVoucher, Event, SupportCase) |
| `causer_type` / `causer_id` | autor; **NULL = sistema** (expiração, conciliação) |
| `properties` | contexto JSON (valores, códigos públicos, de/para) |
| `created_at` | momento (UTC) |

**Imutável**: nenhum endpoint de escrita/edição/exclusão; registros nascem
dentro da transação da ação de negócio (rollback ⇒ sem rastro órfão).

## Config nova

- `events.timezone` = `America/Sao_Paulo` — fuso oficial do evento para
  filtros de período (FR-011).

## Derivações (fórmulas canônicas — servem tela E planilha)

```
elegíveis        = tickets do evento, status ∈ {paid, confirmed, courtesy, used}
                   (mesma régua da portaria — FR-007)
pessoas(t)       = GREATEST(seats_per_ticket, is_couple ? 2 : 1)   [da 004]

DASHBOARD
  pessoasConfirmadas = Σ pessoas(elegíveis)            × capacidade do evento
  ingressosPorStatus = COUNT por status (todos, incl. cancelados p/ contexto)
  receitaConfirmada  = Σ payments paid (efetivamente recebido)
                       − Σ ticket_refund_amounts (estornos parciais/totais)
  receitaPrevista    = receitaConfirmada + Σ orders em aberto (reserved/awaiting)
  grade de camisas   = por modelo×tamanho: Σ titulares (shirt_size_id)
                       + Σ acompanhantes (companion_shirt_size_id) dos elegíveis;
                       NULL → "não informado"; Σ grade ≡ pessoasConfirmadas (SC-003)
  por lote           = vendidos × limite × receita (Σ preço dos elegíveis do lote)
  por forma          = payments paid: COUNT × Σ amount por method
  cortesias          = emitidas × limite (config), usadas
  presenças          = esperados/presentes/ausentes (mesmo cálculo da 007)

FINANCEIRO (filtro de período sobre paid_at, no fuso do evento → UTC)
  porForma     = payments paid no período: COUNT × Σ amount por method
  totalGeral   = Σ porForma (bate por construção)
  estornos     = tickets com refund no período: COUNT × Σ valor devolvido
  pendentes    = orders reserved/awaiting_payment (fotografia, sem filtro)
  patrocínios  = installments: recebido (paid) × a receber (pending)
                 × em atraso (pending com due_date < hoje)

EXPORTS (.xlsx — MESMO service das telas, mesmos filtros)
  inscritos  = 1 linha por PESSOA (titular e acompanhante separados):
               nome, papel (titular|acompanhante), e-mail/telefone (titular),
               tipo, lote, camisa (modelo+tamanho), situação, presença (used_at)
  financeiro = 1 linha por pagamento pago: pedido, comprador, forma, valor,
               data (fuso do evento), registrado por; seção de estornos
  presenças  = 1 linha por ingresso elegível: código, nomes, pessoas,
               situação, used_at, validado por
```

## Filtro de período

```
entrada: month+year OU from+to (datas no fuso America/Sao_Paulo)
→ [início, fim) convertido para UTC → WHERE paid_at/used_at no intervalo
aplicado uniformemente a confirmados E estornos (FR-004)
```

## Invariantes (verificáveis em teste)

1. Σ grade de camisas = pessoas confirmadas (incluindo "não informado").
2. Total geral do financeiro = Σ das formas; líquido = confirmado − estornado.
3. Baixa manual com valor divergente conta pelo valor RECEBIDO (payment.amount).
4. Pagamento pago e estornado no mesmo período aparece nas duas seções.
5. Planilha reproduz exatamente as linhas da consulta filtrada (mesmo service).
6. Toda ação sensível executada gera exatamente 1 registro de auditoria, com
   causer correto (pessoa ou NULL=sistema), dentro da transação.
7. Dashboard reflete estorno/cancelamento imediatamente (nenhum cache).
```
