# Data Model — Aba Orçamento (spec 011)

Todas as tabelas estendem `BaseModel` (soft delete + `created_by`/`updated_by` via `TracksAuditors`). Dinheiro em `DECIMAL(10,2)`. Nenhum total derivado é coluna. Código em inglês; rótulos de UI em pt-BR.

## Tabela `budget_plans`

Cabeçalho do orçamento — **1:1 com `events`** (criado sob demanda no primeiro acesso).

| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| event_id | FK events | **unique** (um orçamento por evento) |
| expected_paying | int | pagantes previstos (default 0) |
| expected_courtesy | int | cortesias previstas (default 0) |
| expected_guests | int | convidados (default 0) |
| expected_staff | int | equipe (default 0) |
| expected_speakers | int | palestrantes (default 0) |
| other_revenue | decimal(10,2) | outras receitas previstas (default 0) |
| safety_margin_pct | decimal(5,2) nullable | margem de segurança % (ex.: 10.00) |
| notes | text nullable | observações gerais |
| created_by / updated_by / timestamps / deleted_at | | auditoria |

Relacionamentos: `belongsTo Event`; `hasMany` costItems, ticketLots, sponsorships, scenarios.

**Total geral de participantes** (derivado) = paying + courtesy + guests + staff + speakers.

## Tabela `budget_cost_items`

Itens de custo previstos (a "planilha").

| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| budget_plan_id | FK budget_plans | |
| description | string | obrigatório |
| category | string | uma das categorias padrão (D2/FR-007) |
| quantity | decimal(10,2) nullable | |
| unit_price | decimal(10,2) nullable | |
| total_amount | decimal(10,2) | **valor efetivo do item** (ver regra) |
| supplier_name | string nullable | fornecedor previsto |
| status | string | `planned`\|`quoted`\|`approved`\|`contracted`\|`cancelled` |
| notes | text nullable | |
| financial_entry_id | FK financial_entries nullable | preenchido ao gerar conta a pagar (bloqueia duplicidade) |
| auditoria | | soft delete + created_by/updated_by |

**Regra de valor (FR-005)**: se `quantity` e `unit_price` informados → `total_amount = quantity × unit_price`; se só `total_amount` informado → usa-o. `total_amount` sempre persistido (positivo, 2 casas). Itens `cancelled` **não** somam no custo total (FR-006).

Status: constantes em `BudgetCostItemStatus`. Sem transições rígidas (o usuário pode reclassificar livremente); apenas `cancelled` afeta o cálculo.

## Tabela `budget_ticket_lots`

Lotes de ingresso **previstos** (planejamento — distintos dos lotes reais da spec 004).

| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| budget_plan_id | FK budget_plans | |
| name | string | ex.: "Primeiro lote" |
| unit_price | decimal(10,2) | valor do ingresso (positivo) |
| expected_quantity | int | quantidade prevista de ingressos |
| expected_paying | int nullable | pagantes estimados (default = expected_quantity) |
| notes | text nullable | |
| auditoria | | |

**Receita prevista do lote** (derivada) = `unit_price × expected_quantity`.

## Tabela `budget_sponsorships`

Cotas de patrocínio **previstas**.

| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| budget_plan_id | FK budget_plans | |
| name | string | ex.: "Patrocínio Master" |
| unit_value | decimal(10,2) | valor da cota (positivo) |
| quantity | int | nº de cotas (default 1) |
| status | string | `planned`\|`negotiating`\|`confirmed`\|`received`\|`lost`\|`cancelled` |
| notes | text nullable | |
| financial_entry_id | FK financial_entries nullable | preenchido ao gerar conta a receber |
| auditoria | | |

**Receita prevista da cota** (derivada) = `unit_value × quantity`.
- **Previsto total** = soma de todas as cotas exceto `lost`/`cancelled`.
- **Confirmado** = soma das cotas em `confirmed`/`received`.
- Ação "gerar conta a receber" indisponível para `lost`/`cancelled` (FR-022).

## Tabela `budget_scenarios`

Cenários what-if persistidos (até 3 por plano).

| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| budget_plan_id | FK budget_plans | |
| key | string | `conservative`\|`realistic`\|`optimistic` (unique por plano) |
| paying | int | pagantes do cenário |
| avg_ticket | decimal(10,2) | ticket médio do cenário |
| sponsorship | decimal(10,2) | patrocínio previsto do cenário |
| cost | decimal(10,2) | custo previsto do cenário |
| other_revenue | decimal(10,2) | outras receitas do cenário |
| auditoria | | |

**Fecha o orçamento?** (derivado) = `(paying × avg_ticket) + sponsorship + other_revenue ≥ cost`.

## Cálculos derivados (serviço `BudgetCalculator`)

Nenhum destes é coluna — todos calculados na leitura (constituição II).

| Indicador | Fórmula |
|---|---|
| Custo total previsto | Σ `total_amount` dos itens **não** `cancelled` |
| Receita prevista ingressos | Σ (`unit_price × expected_quantity`) dos lotes |
| Receita prevista patrocínios | patrocínio **previsto total** (exceto lost/cancelled) |
| Receita total prevista | ingressos + patrocínios + `other_revenue` |
| Resultado previsto | receita total prevista − custo total previsto |
| Investimento próprio | max(0, custo total − receita total) |
| Valor que falta captar | mesmo que investimento próprio (quando déficit) |
| Ticket médio previsto | receita ingressos ÷ `expected_paying` (null se 0) |
| Custo médio/participante | custo total ÷ total geral de participantes (null se 0) |
| Custo/pagante | custo total ÷ `expected_paying` (null se 0) |
| Ponto de equilíbrio (pagantes) | ⌈(custo total − patrocínio previsto − other_revenue) ÷ ticket médio⌉ (null se ticket médio ausente) |
| Custo com margem | custo total × (1 + `safety_margin_pct`/100) |
| Classificação | superávit (receita>custo) \| equilíbrio (=) \| déficit (<) |

**Comparativo orçado × realizado** (serviço + `FinancialReportService::eventResult` + vendas reais):

| Par | Orçado | Realizado |
|---|---|---|
| Despesa | custo total previsto | despesa paga (settlements payable do evento) |
| Receita | receita total prevista | receita recebida (settlements receivable) |
| Patrocínio | patrocínio previsto | patrocínio recebido (Financeiro do evento) |
| Ingressos (qtd) | Σ `expected_quantity` | ingressos vendidos reais do evento |
| Resultado | resultado previsto | resultado realizado (recebido − pago) |
| % atingimento | — | vendidos ÷ meta prevista × 100 |

## Validação (FormRequests → 422)

- `total_amount`, `unit_price`, `unit_value` **> 0** (2 casas); quantidades inteiras ≥ 0.
- `status` (item/patrocínio) restrito às constantes do enum.
- `category` do item dentro da lista padrão.
- `safety_margin_pct` entre 0 e 100.
- `scenario.key` ∈ {conservative, realistic, optimistic} e único por plano.

## Regras de negócio (→ 409 `DomainRuleViolation`)

- Gerar conta a pagar/receber de uma linha **já convertida** (`financial_entry_id` presente) → `already_converted`.
- Gerar conta a receber de patrocínio `lost`/`cancelled` → `invalid_sponsorship_status`.

## Escopo/papel (→ 403)

- Endpoints sob `require.role:admin,treasury`; `gate`/`attendee` bloqueados.
