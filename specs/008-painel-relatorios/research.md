# Research — 008-painel-relatorios

## Decisão 1 — Trilha de auditoria: `spatie/laravel-activitylog`

**Decision**: adotar o pacote `spatie/laravel-activitylog` (tabela
`activity_log` própria do pacote), com registro **explícito nos services** —
não auto-log por eventos de model.

**Rationale**: é exatamente a dívida registrada na 001 (research, Decisão 5:
"registrar na spec 008 a adoção do activity log"). O registro explícito nos
services captura a AÇÃO de negócio ("baixa manual", "estorno parcial",
"check-in") com o autor certo — auto-log de model capturaria UPDATEs anônimos
e ruído (recontagens de cache, timestamps). `causer` nulo = sistema (expiração,
conciliação), atendendo o edge case da spec. O pacote traz `log_name` (tipo da
ação), `subject` (morph para o objeto afetado) e `properties` (contexto JSON) —
tudo que o FR-008 pede, imutável por construção (nenhum endpoint de escrita).

**Alternativas consideradas**: tabela artesanal `audit_logs` (reinventa morphs
e helpers que o pacote dá pronto); auto-log via `LogsActivity` trait nos models
(ruído, autor impreciso em jobs); event sourcing (desproporcional ao MVP).

## Decisão 2 — Pontos de registro (o que loga onde)

**Decision**: instrumentar os services existentes — `RegisterPayment`
(baixa/confirmação), `RefundPayment` (estorno), `TicketLifecycleService`
(cancelamento e transferência de ingresso), `CancelEventCascade` (cancelamento
do evento), `CourtesyResolver`/`SponsorshipService` (emissão/uso de cortesia),
`EventConfigService` (alterações de configuração), `CheckinService` (check-in).
`log_name` padronizado por ação (ex.: `payment.registered`, `payment.refunded`,
`ticket.cancelled`, `ticket.transferred`, `ticket.checked_in`,
`courtesy.issued`, `courtesy.redeemed`, `event.updated`, `event.cancelled`,
`support.updated`).

**Rationale**: um ponto por regra de negócio, dentro da mesma transação da
ação — o log nasce e morre com a operação (rollback não deixa rastro órfão).
A lista cobre 1:1 o FR-008.

**Alternativas consideradas**: middleware HTTP genérico (loga requests, não
ações; perde contexto de domínio).

## Decisão 3 — Export .xlsx: `openspout/openspout`

**Decision**: `openspout/openspout` com escrita em streaming direto na
resposta HTTP (`StreamedResponse`), um writer por relatório em um
`ReportExportService`.

**Rationale**: pacote já fixado na constituição (stack, linha de utilitários).
Streaming não materializa a planilha em memória nem em disco — atende o edge
case de milhares de linhas sem limite artificial.

**Alternativas consideradas**: `maatwebsite/excel` (wrapper pesado do
PhpSpreadsheet, memória proporcional às linhas); CSV puro (spec pede .xlsx —
acentuação/encoding no Excel brasileiro é armadilha conhecida do CSV).

## Decisão 4 — Agregações derivadas em um `ReportService`

**Decision**: um `ReportService` no domínio concentra as consultas derivadas
(dashboard, financeiro, presenças) usando agregações SQL (`SUM`/`COUNT` com
joins) — reutilizando as derivações canônicas existentes
(`Event::ticketsSold()`, régua de elegibilidade da portaria, recontagens).

**Rationale**: princípio II da constituição — nada armazenado, tudo derivado
na consulta; um lugar só para as fórmulas evita divergência entre tela e
planilha (o export consome o MESMO service). Volume do MVP (centenas a poucos
milhares de ingressos) dispensa cache/materialização.

**Alternativas consideradas**: contadores materializados (viola princípio II e
o FR-012); views MySQL (indireção sem ganho neste volume).

## Decisão 5 — Grade de camisas por PESSOA

**Decision**: derivar a grade somando titular (`shirt_size_id`) e acompanhante
(`companion_shirt_size_id`) dos ingressos elegíveis, agrupado por
modelo/tamanho, com bucket "não informado" para nulos; total da grade
conciliado com pessoas confirmadas.

**Rationale**: mesma régua da recontagem de camisas da 003 (holder + companion
separados) — a spec exige que a grade FECHE com o total de pessoas (SC-003).

**Alternativas consideradas**: contar por ingresso (casal viraria 1 camisa —
errado).

## Decisão 6 — Filtros de período no fuso do evento

**Decision**: filtros `month/year` ou `from/to` interpretados em
`America/Sao_Paulo` (config `events.timezone`, novo) e convertidos para o
intervalo UTC correspondente antes da query; aplicados sobre `paid_at`
(pagamentos/estornos) e `used_at` (presenças).

**Rationale**: datas são armazenadas em UTC (convenção do projeto); "o dia 30
do caixa" é o dia 30 brasileiro (edge case da spec). Config nova em vez de
hardcode mantém o padrão de `config/events.php`.

**Alternativas consideradas**: filtrar em UTC cru (quebra o fechamento mensal
em até 3h nas bordas).

## Decisão 7 — Rotas e papéis (RBAC existente)

**Decision**: `GET /api/admin/dashboard`, `GET /api/admin/audit` e exports de
inscritos/presenças sob `require.role:admin`; `GET /api/treasury/finance` e
export financeiro sob `require.role:treasury,admin` (grupo `/treasury` já
existente). Exports como GET retornando o arquivo (Content-Disposition).

**Rationale**: espelha o FR-010 com os grupos de rota já estabelecidos nas
specs 003/005; GET permite baixar da própria SPA com a sessão Sanctum.

**Alternativas consideradas**: job assíncrono + link por e-mail para exports
(complexidade de fila/armazenamento sem necessidade no volume do MVP).

## Decisão 8 — Frontend: 3 entregas no chrome existente

**Decision**: página `Dashboard.jsx` (admin; vira a home do painel para
admin), seção Financeiro dentro de uma página `Financeiro.jsx`
(treasury+admin, ao lado da Tesouraria operacional) e página `Auditoria.jsx`
(admin). Downloads via link direto (mesma origem, cookie de sessão).

**Rationale**: reaproveita AdminLayout/RoleRoute/React Query como nas specs
003–007; separar Financeiro (leitura/fechamento) da Tesouraria (operação de
baixa) mantém cada tela com um propósito.

**Alternativas consideradas**: abas dentro de Tesouraria.jsx (mistura operação
com prestação de contas; telas já densas).
