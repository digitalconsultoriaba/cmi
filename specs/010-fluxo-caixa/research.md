# Research — 010-fluxo-caixa

## Decisão 1 — Espelho de ingressos/patrocínios: observers idempotentes (não segunda fonte de verdade)

**Decision**: cada pedido de ingresso e cada parcela de patrocínio espelham
**um** `financial_entry` (conta a receber) via **observers** (`OrderObserver`,
`SponsorshipInstallmentObserver`) que fazem **upsert por `(source_type,
source_id)`** — chave única que garante zero duplicidade. O status da conta
espelhada é derivado do estado do pedido/parcela (pendente→em aberto,
pago→recebido, cancelado→cancelado, reembolsado→estorno). Entradas de origem
`ticket`/`sponsorship` são **somente leitura** na UI (geridas pela
sincronização); as manuais são totalmente editáveis.

**Rationale**: respeita os princípios II (estado derivado) e III (ponto único
de baixa): o dinheiro do ingresso continua sendo baixado só pelo
`RegisterPayment` (005); a conta a receber é **projeção** sincronizada, nunca
uma segunda contabilização. Observer roda na mesma transação da mudança de
status → consistente e sem corrida. Cortesias (`is_courtesy`) são ignoradas
(não geram receita).

**Alternativas consideradas**: instrumentar cada service com chamadas
explícitas (como o activity() da 008) — mais verboso e fácil de esquecer um
ponto; projeção 100% on-read (sem linhas) — impede filtrar/relatar junto com as
manuais e não guarda histórico.

## Decisão 2 — Status derivado + `settled_amount` como cache recontável

**Decision**: o `financial_entry` guarda `amount` (original) e um cache
`settled_amount` (soma das baixas), recontado sob lock a cada baixa/estorno
(padrão `sold_count` da 004). O **status** é derivado: cancelado (se
`cancelled_at`); recebido/pago (se `settled_amount == amount`); parcial (se
0 < settled < amount); vencido (se `due_date < hoje` e não quitado/cancelado);
senão em aberto.

**Rationale**: princípio II — situação nunca é coluna editável; "vencido" é
puramente temporal. O cache torna listagem/filtro por status eficiente sem
recalcular somas a cada linha; a fonte de verdade são as `financial_settlements`.

**Alternativas consideradas**: status como coluna manual (viola II, permite
mentira); somar baixas a cada leitura (custa em listas grandes).

## Decisão 3 — Baixa (pagamento/recebimento) como registro próprio, sob transação

**Decision**: cada baixa é uma `financial_settlement` (entry_id, valor, data,
forma, observação, anexo opcional, autor). Total ou parcial = uma ou várias
settlements. `FinancialEntryService::settle()` grava a baixa e reconta
`settled_amount` numa `DB::transaction` com `lockForUpdate` na linha da entry
(evita corrida de baixas simultâneas). Baixa nunca ultrapassa o saldo (piso
zero); conta cancelada não recebe baixa.

**Rationale**: princípio II (recontagem sob lock) e V (histórico). Espelha o
`RegisterPayment` sem reusá-lo (domínio diferente — contas do módulo, não
pedidos).

## Decisão 4 — Parcelamento e recorrência: geração explícita

**Decision**: parcelamento gera N `financial_entries` irmãs
(`installment_group` UUID, `installment_number`/`installment_total`), soma
fecha o total (resto na última), vencimentos pela frequência — cada uma com
baixa própria (pagar uma não mexe nas outras). Recorrência guarda a config em
`financial_recurrences` (frequência, início, término/limite) e um comando
`financial:generate-recurrences` materializa os próximos lançamentos até o
limite (padrão dos comandos agendados da 005, ex.: `payments:reconcile`).

**Rationale**: cada parcela precisa de vencimento/status/baixa próprios;
gerar sob demanda evita loop infinito de recorrência sem término.

## Decisão 5 — Histórico via `spatie/laravel-activitylog` (já adotado na 008)

**Decision**: as movimentações (criação, edição, baixa total/parcial,
cancelamento, estorno, anexo incluído/removido, mudança de situação) usam o
`activity()` com `log_name` `financial.*`, `subject` = a entry, `causer` = o
autor, dentro das transações. Edição de conta já baixada exige justificativa,
gravada em `properties`.

**Rationale**: reaproveita a trilha imutável da 008 (nenhuma tabela de
histórico nova); "sem alteração silenciosa" vira uma guarda + log obrigatório.

## Decisão 6 — Anexos no disco público, validados

**Decision**: `financial_attachments` (entry_id, path, kind, original_name,
uploaded_by) com arquivo no disco `public` (padrão de banner/avatar). Upload
valida tipo (pdf/imagem) e tamanho; ver/baixar/remover conforme papel.

**Rationale**: mesmo mecanismo de storage já usado; sem dependência nova.

## Decisão 7 — Cadastros de apoio e formas de pagamento como tabelas

**Decision**: `financial_categories` (direction income|expense, name,
is_active), `financial_people` (kind, name, document, phone, whatsapp, email,
notes), `financial_payment_methods` (lookup seedado: Pix, cartão crédito/débito,
boleto, dinheiro, transferência, depósito, permuta, cortesia, outro). Categoria
com uso **não** é excluída — só inativada (guarda no destroy).

**Rationale**: relatórios por categoria/pessoa/forma exigem entidades reais;
segue o padrão de lookups seeded do projeto.

## Decisão 8 — Exportação: openspout (.xlsx) e dompdf (PDF), já disponíveis

**Decision**: relatórios exportam .xlsx (openspout, streaming — padrão da 008)
e PDF (barryvdh/laravel-dompdf, já usado no comprovante da 004); CSV opcional
derivado do mesmo dado. Export respeita os filtros da tela (mesmo service da
prévia).

**Rationale**: ambas as libs já estão no projeto; nada novo.

## Decisão 9 — Papéis e navegação: reuso de admin+treasury

**Decision**: rotas sob `require.role:admin,treasury`; item "Financeiro" no
menu principal para admin e financeiro (treasury). A visão por evento é o
módulo filtrado por `event_id` (não uma aba nova no evento). Telas 008/009
permanecem intactas (coexistência).

**Rationale**: decisão do usuário (sem papéis novos); menor atrito com o RBAC
da fundação.

## Decisão 10 — Frontend: novo módulo com gráficos (ApexCharts) e padrões da 009

**Decision**: seção Financeiro no `AdminLayout` (admin+treasury) com abas
Dashboard, Contas a Pagar, Contas a Receber, Categorias, Fornecedores/Clientes,
Formas de Pagamento, Relatórios; tela interna do lançamento com histórico e
ações. Reaproveita DonutChart/AreaChart, cards, tabelas e modais já criados na
009.

**Rationale**: consistência visual e reuso; nada de tela nova do zero.
