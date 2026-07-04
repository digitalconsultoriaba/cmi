# Feature Specification: Módulo Financeiro — Contas a Pagar e Receber

**Feature Branch**: `010-fluxo-caixa`

**Created**: 2026-07-04

**Status**: Implemented

**Input**: User description: "Criação do módulo financeiro central (contas a
pagar e a receber). Financeiro centralizado no sistema, com cada lançamento
podendo estar vinculado a um evento; o evento funciona como centro de
resultado e a aba Financeiro do evento apenas apresenta os lançamentos
filtrados daquele evento. Contempla lançamentos manuais e integrações,
parcelamento, recorrência, baixa total/parcial, anexos, categorias,
fornecedores/clientes, formas de pagamento, dashboard geral e por evento,
relatórios com exportação, histórico de movimentações e permissões."

## Clarifications

### Session 2026-07-04

- Q: Vendas de ingresso e patrocínios geram conta a receber automaticamente? →
  A: **Ambos**. Cada pedido de ingresso e cada patrocínio (ou parcela) espelha
  uma conta a receber, **sincronizada** com o pagamento já existente (ponto
  único de baixa da 005): pendente → em aberto, pago → recebido, cancelado →
  cancela, reembolsado → estorno. **Um** lançamento por pedido/patrocínio, sem
  duplicidade. Cortesias não geram receita.
- Q: Usa os papéis atuais ou cria papéis novos? → A: **Reutilizar** os atuais —
  admin (tudo) e tesouraria/financeiro (criar, baixar, cancelar, relatórios).
  Sem papéis novos.
- Q: Substitui ou coexiste com as telas financeiras existentes (008/009)? →
  A: **Coexistir**. O novo módulo Financeiro fica no menu principal, à parte; a
  aba Financeiro do evento atual (pedidos + baixa da 009) e o consolidado da
  008 permanecem inalterados. A visão por evento do novo módulo é o próprio
  módulo central **filtrado por evento** (centro de resultado), sem criar outra
  aba conflitante no evento.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Lançar e acompanhar contas a pagar e a receber (Priority: P1)

A pessoa do financeiro registra, num **módulo central** de Financeiro, todas as
entradas (contas a receber) e saídas (contas a pagar) da organização. Cada
lançamento tem descrição, valor, categoria, vencimento, forma de pagamento,
origem e — **opcionalmente** — um evento e uma pessoa (fornecedor/cliente)
vinculados. Lançamentos sem evento são administrativos/gerais (ex.: hospedagem
do sistema). A situação de cada conta é clara e atual: em aberto, vencida (só
pela passagem do vencimento), paga/recebida, parcial ou cancelada.

**Why this priority**: é a espinha dorsal — sem registrar e situar os
lançamentos não existe módulo financeiro; tudo o mais (baixa, dashboard,
relatórios) depende disso.

**Independent Test**: criar uma conta a pagar (com e sem evento) e uma conta a
receber; conferir os campos, o valor em reais e a situação inicial "em aberto";
deixar uma vencer e ver a situação virar "vencida".

**Acceptance Scenarios**:

1. **Given** os dados de uma despesa (descrição, valor, categoria,
   vencimento), **When** o lançamento é criado, **Then** nasce como conta a
   pagar "em aberto", com valor positivo em reais e origem registrada.
2. **Given** uma conta com vencimento passado e ainda não baixada, **When** a
   listagem é exibida, **Then** a situação aparece como "vencida" (derivada da
   data, não de uma edição manual).
3. **Given** um lançamento vinculado a um evento, **When** criado, **Then** o
   evento fica registrado; **Given** um lançamento administrativo, **When**
   criado sem evento, **Then** é aceito como "Administrativo / Geral".
4. **Given** valor zero ou negativo, **When** se tenta salvar, **Then** o
   sistema recusa (todo valor deve ser positivo, duas casas decimais).
5. **Given** uma pessoa sem permissão financeira, **When** acessa o módulo,
   **Then** não vê valores, saldos nem relatórios.

---

### User Story 2 - Dar baixa (pagamento/recebimento), total ou parcial (Priority: P2)

O financeiro registra a **baixa** de uma conta: para conta a pagar é o
pagamento; para conta a receber é o recebimento. A baixa informa data, valor,
forma de pagamento e, quando houver, observação e comprovante. O sistema aceita
**baixa parcial**, mostrando sempre valor original, valor já pago/recebido e
saldo restante, e atualiza a situação automaticamente (paga/recebida quando
quita, parcial quando falta). Toda baixa entra no histórico de movimentações.

**Why this priority**: é a operação diária do caixa e o que torna os saldos
"realizados" verdadeiros; sem baixa, o módulo é só uma agenda de contas.

**Independent Test**: baixar parcialmente uma conta a receber (situação vira
"recebido parcialmente" com saldo correto), depois quitar o restante (vira
"recebido"); conferir o histórico das duas movimentações.

**Acceptance Scenarios**:

1. **Given** uma conta a pagar em aberto, **When** o valor total é pago com
   data e forma, **Then** a situação vira "pago" e o saldo restante fica zero.
2. **Given** uma conta a receber, **When** parte do valor é recebida, **Then**
   a situação vira "recebido parcialmente" e o saldo restante = original −
   recebido.
3. **Given** várias baixas parciais, **When** a soma atinge o valor original,
   **Then** a situação passa a "pago/recebido" automaticamente.
4. **Given** qualquer baixa, **When** registrada, **Then** entra no histórico
   com usuário, data/hora, tipo de ação e valores.
5. **Given** um valor já recebido/pago, **When** alguém tenta editá-lo,
   **Then** o sistema exige justificativa e registra a alteração no histórico
   (sem alteração silenciosa).

---

### User Story 3 - Evento como centro de resultado (Priority: P3)

No módulo Financeiro central, ao **filtrar por um evento** (ou abrir a visão
"por evento"), a gestão vê **apenas** os lançamentos daquele evento e os seus
indicadores — receita prevista/recebida/pendente, despesa prevista/paga/
pendente, saldo previsto, saldo realizado e **resultado final** — além das
contas a pagar/receber, vencidos e em aberto do evento. Ao criar um lançamento
nesse contexto, o evento já vem **preenchido**. As telas financeiras existentes
do evento (pedidos + baixa da 009) e o consolidado da 008 **permanecem
inalterados** — o novo módulo coexiste, não os substitui.

**Why this priority**: transforma cada evento num centro de resultado (lucro/
prejuízo por evento), que é o objetivo gerencial central; depende dos
lançamentos (US1) e da baixa (US2).

**Independent Test**: lançar receitas e despesas vinculadas a um evento, dar
algumas baixas, abrir a aba Financeiro do evento e conferir cada indicador e o
resultado; criar um lançamento pela aba e ver o evento já preenchido.

**Acceptance Scenarios**:

1. **Given** lançamentos de um evento, **When** o módulo é filtrado por esse
   evento, **Then** só aparecem os daquele evento, com os indicadores próprios.
2. **Given** receitas e despesas do evento, **When** os indicadores são
   calculados, **Then** saldo previsto = receita prevista − despesa prevista;
   saldo realizado = receita realizada − despesa realizada; resultado = total
   recebido − total pago do evento.
3. **Given** "nova conta a receber" no contexto de um evento, **When** clicado,
   **Then** o formulário abre com o evento já vinculado.
4. **Given** lançamentos cancelados do evento, **When** os indicadores são
   calculados, **Then** os cancelados não entram em nenhum saldo.
5. **Given** as telas financeiras antigas do evento (008/009), **When** o novo
   módulo é usado, **Then** elas continuam funcionando sem alteração.

---

### User Story 4 - Dashboard financeiro geral (Priority: P4)

A gestão abre o **Dashboard Financeiro** e vê, em cards, a saúde do caixa: a
receber e recebido no mês, a pagar e pago no mês, contas vencidas (a pagar e a
receber), saldo previsto e realizado, resultado do mês, eventos com melhor
resultado e eventos no prejuízo, e os próximos vencimentos. Filtra por período,
evento, tipo, categoria, situação e forma de pagamento.

**Why this priority**: é a leitura executiva que justifica o módulo; consome os
lançamentos e baixas já existentes.

**Independent Test**: com lançamentos e baixas variados, abrir o dashboard e
conferir cada card contra os dados; aplicar filtros e ver os números reagirem.

**Acceptance Scenarios**:

1. **Given** lançamentos do mês, **When** o dashboard abre, **Then** os cards
   (a receber/recebido/a pagar/pago no mês, vencidos, saldos, resultado)
   batem com os registros.
2. **Given** vários eventos com resultados diferentes, **When** o dashboard é
   exibido, **Then** destaca os de melhor resultado e os no prejuízo.
3. **Given** um filtro (período/evento/categoria/situação/forma), **When**
   aplicado, **Then** todos os indicadores recalculam para o recorte.
4. **Given** contas vencidas, **When** exibidas, **Then** aparecem agrupadas
   (vencidas hoje, próximos 7 dias, há mais de 30 dias) com alerta visual.

---

### User Story 5 - Cadastros de apoio: categorias, pessoas e formas de pagamento (Priority: P5)

O financeiro mantém os cadastros que organizam os lançamentos: **categorias
financeiras** (de receita e de despesa, ativáveis/inativáveis, nunca excluídas
se já usadas), **fornecedores/clientes** (pessoas financeiras: fornecedor,
cliente, patrocinador, participante, prestador…) e a lista de **formas de
pagamento**. Esses cadastros alimentam os campos, filtros e relatórios.

**Why this priority**: dá consistência e permite os relatórios por categoria/
pessoa/forma; os lançamentos funcionam sem eles, mas ficam pobres.

**Independent Test**: criar categorias e um fornecedor, vincular a um
lançamento, inativar uma categoria já usada (permanece nos lançamentos, some
das novas opções) e tentar excluí-la (recusado).

**Acceptance Scenarios**:

1. **Given** uma categoria em uso, **When** se tenta excluí-la, **Then** o
   sistema recusa e permite apenas inativar.
2. **Given** um fornecedor cadastrado, **When** vinculado a uma conta a pagar,
   **Then** aparece na listagem e nos relatórios por pessoa.
3. **Given** uma forma de pagamento, **When** usada numa baixa, **Then** consta
   no histórico e nos filtros/relatórios.

---

### User Story 6 - Parcelamento e recorrência (Priority: P6)

O financeiro cria lançamentos **parcelados** (valor total dividido em N
parcelas, cada uma com vencimento, situação e baixa próprios — pagar uma não
mexe nas outras) e lançamentos **recorrentes** (mensal/semanal/anual, com data
inicial, término opcional ou número de repetições). O sistema gera as parcelas
e as recorrências automaticamente.

**Why this priority**: cobre despesas reais (buffet em 3x, mensalidade do
sistema); valioso, mas o módulo opera com lançamentos avulsos antes disso.

**Independent Test**: criar uma despesa de R$ 12.000 em 3 parcelas → 3 contas
de R$ 4.000 com vencimentos mensais; pagar a 1ª e conferir que as outras
seguem em aberto; criar uma recorrência mensal e ver os lançamentos gerados.

**Acceptance Scenarios**:

1. **Given** um total e uma quantidade de parcelas, **When** o parcelamento é
   criado, **Then** N parcelas são geradas com a soma exata do total e
   vencimentos conforme a frequência.
2. **Given** um parcelamento, **When** uma parcela é paga, **Then** as demais
   não mudam de situação.
3. **Given** uma recorrência mensal com término, **When** configurada,
   **Then** os lançamentos são gerados para cada período até o término/limite.

---

### User Story 7 - Comprovantes, cancelamento, estorno e histórico (Priority: P7)

Cada lançamento guarda **anexos** (comprovante, nota fiscal, recibo, contrato,
boleto…), com visualizar/baixar/remover conforme permissão. O financeiro pode
**cancelar** um lançamento (com motivo — some dos saldos, permanece no
histórico e só aparece com filtro de cancelados) e registrar **estorno** de um
valor já pago/recebido (com motivo, data e valor, atualizando o saldo). Todo
lançamento tem **histórico** completo de movimentações (criação, edição,
baixa total/parcial, cancelamento, anexos, mudança de situação) com autor e
data/hora.

**Why this priority**: governança e rastreabilidade do dinheiro; importante,
mas o núcleo funciona sem anexos/estorno no primeiro momento.

**Independent Test**: anexar um comprovante a uma conta paga; cancelar um
lançamento com motivo (sai dos saldos, fica no histórico); estornar um
recebimento e ver o saldo do evento atualizar.

**Acceptance Scenarios**:

1. **Given** um lançamento, **When** um arquivo é anexado, **Then** fica
   disponível para ver/baixar/remover conforme a permissão.
2. **Given** um lançamento cancelado com motivo, **When** os saldos são
   calculados, **Then** ele não entra em previsto nem realizado, mas continua
   no histórico.
3. **Given** um valor já recebido, **When** um estorno é registrado com motivo
   e valor, **Then** o saldo é atualizado e a movimentação fica no histórico.

---

### User Story 8 - Relatórios financeiros e exportação (Priority: P8)

A gestão gera relatórios financeiros (geral, por evento, contas a pagar, contas
a receber, por categoria, por pessoa/fornecedor/patrocinador, por forma de
pagamento, receitas de ingressos, patrocínios, despesas do evento, saldo
previsto × realizado), filtra por período e **exporta** respeitando os filtros
da tela.

**Why this priority**: fecha o ciclo de prestação de contas; consome os dados
já consolidados nas histórias anteriores.

**Independent Test**: gerar o relatório por evento de um período, conferir as
linhas na tela e exportar; conferir que o arquivo traz exatamente o recorte.

**Acceptance Scenarios**:

1. **Given** um relatório e um período, **When** gerado, **Then** mostra as
   linhas correspondentes e os totais.
2. **Given** um relatório filtrado, **When** exportado, **Then** o arquivo
   reproduz exatamente as linhas visíveis.

---

### Edge Cases

- Lançamento sem evento (administrativo) entra no geral, mas nunca no resultado
  de um evento específico.
- Cortesias **não** geram receita financeira — no máximo aparecem como
  contagem no financeiro do evento, nunca como valor recebido.
- Baixa parcial que ultrapassaria o valor original é recusada (saldo nunca
  negativo).
- Conta cancelada não pode receber baixa nem estorno.
- Categoria/pessoa inativada permanece nos lançamentos históricos; só some das
  novas opções.
- Fuso: vencimentos e datas de baixa interpretados no fuso oficial (Brasil).
- Duplicidade: uma mesma origem (ex.: um pedido de ingresso) nunca gera dois
  lançamentos financeiros.
- Recorrência sem término definido: gera até um limite seguro e continua sob
  demanda, sem loop infinito.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O sistema MUST possuir **um** módulo financeiro central com
  lançamentos de dois tipos — **Conta a Pagar** (saída) e **Conta a Receber**
  (entrada) — cada um com: tipo, descrição, valor, categoria, vencimento,
  situação, origem, forma de pagamento, evento (opcional), pessoa (opcional) e
  observações.
- **FR-002**: O vínculo com evento MUST ser **opcional**; lançamentos sem
  evento são administrativos/gerais. Nenhum lançamento pode ser obrigado a ter
  evento.
- **FR-003**: A situação MUST ser derivada e coerente — contas a pagar: em
  aberto, vencido (vencimento passado sem quitação), pago, pago parcialmente,
  cancelado; contas a receber: em aberto, vencido, recebido, recebido
  parcialmente, cancelado.
- **FR-004**: Valores MUST ser positivos, em reais com duas casas decimais; o
  sistema recusa zero ou negativo.
- **FR-005**: O sistema MUST permitir baixa (pagamento/recebimento) total ou
  parcial, informando data, valor, forma de pagamento e opcionalmente
  observação e comprovante; e MUST exibir valor original, valor pago/recebido e
  saldo restante, atualizando a situação automaticamente.
- **FR-006**: O sistema MUST manter, por lançamento, um histórico de
  movimentações (criação, edição, baixa total/parcial, cancelamento, anexos,
  mudança de situação) com autor, data/hora, tipo de ação e descrição.
- **FR-007**: Editar um lançamento **já pago/recebido** MUST exigir
  justificativa e registrar a alteração no histórico (proibida alteração
  silenciosa de valores baixados).
- **FR-008**: Cancelar um lançamento MUST exigir motivo; cancelados não entram
  em saldo previsto nem realizado, permanecem no histórico e só aparecem quando
  o filtro incluir cancelados.
- **FR-009**: O sistema MUST permitir estorno de valor já pago/recebido, com
  motivo, data e valor, atualizando o saldo e mantendo histórico.
- **FR-010**: O sistema MUST permitir lançamentos parcelados (gera N parcelas
  cuja soma fecha o total, cada uma com vencimento/situação/baixa próprios;
  pagar uma não altera as demais) e recorrentes (mensal/semanal/anual, com data
  inicial, término opcional ou número de repetições).
- **FR-011**: O sistema MUST permitir anexos por lançamento (comprovante, nota,
  recibo, contrato, boleto, outro), com ver/baixar/remover conforme permissão.
- **FR-012**: O sistema MUST manter cadastros de **categorias financeiras** (de
  receita e de despesa; criar/editar/ativar/inativar; **não** excluir
  definitivamente quando já houver lançamentos), **pessoas financeiras**
  (fornecedor, cliente, patrocinador, participante, prestador, outro) e a lista
  de **formas de pagamento**.
- **FR-013**: O módulo central MUST oferecer uma **visão por evento** (filtro)
  que exibe apenas os lançamentos daquele evento, seus indicadores e botões
  para criar conta a pagar/receber já vinculadas ao evento. As telas
  financeiras existentes do evento (pedidos + baixa da 009) e o consolidado da
  008 MUST permanecer inalterados (coexistência, sem financeiro paralelo novo).
- **FR-014**: O sistema MUST calcular: receita prevista (contas a receber não
  canceladas), receita realizada (efetivamente recebido), despesa prevista
  (contas a pagar não canceladas), despesa realizada (efetivamente pago), saldo
  previsto (receita prevista − despesa prevista), saldo realizado (receita
  realizada − despesa realizada) e resultado do evento (recebido − pago do
  evento).
- **FR-015**: O Dashboard Financeiro geral MUST exibir os indicadores do mês (a
  receber/recebido/a pagar/pago), vencidos (a pagar e a receber), saldos,
  resultado, eventos com melhor resultado e no prejuízo, e próximos
  vencimentos; com filtros por período, evento, tipo, categoria, situação e
  forma de pagamento.
- **FR-016**: As telas de contas a pagar e a receber MUST oferecer filtros por
  período, vencimento, data de baixa, situação, evento, categoria, pessoa,
  forma de pagamento, origem, valor e texto livre; e ações de visualizar,
  editar, dar baixa, anexar, cancelar e duplicar.
- **FR-017**: O sistema MUST destacar visualmente contas vencidas e agrupar os
  vencimentos (hoje, próximos 7 dias, há mais de 30 dias).
- **FR-018**: O sistema MUST oferecer os relatórios financeiros listados
  (geral, por evento, a pagar, a receber, por categoria, por pessoa, por forma,
  ingressos, patrocínios, despesas do evento, previsto × realizado) com filtro
  por período e exportação que respeita os filtros da tela.
- **FR-019**: O acesso MUST usar os papéis atuais — **admin** (tudo) e
  **tesouraria/financeiro** (criar, editar, baixar, cancelar, relatórios); sem
  papéis novos. Quem não tem permissão financeira não vê valores, saldos nem
  relatórios; a visão financeira por evento só aparece para quem pode vê-la.
- **FR-020**: Cada **pedido de ingresso** e cada **patrocínio** (ou parcela)
  MUST espelhar exatamente **uma** conta a receber, **sincronizada** com o
  pagamento já existente (ponto único de baixa da 005, sem segunda fonte de
  verdade do dinheiro): pedido/parcela pendente → em aberto; pago → recebido;
  cancelado → cancelado; reembolsado → estorno. O sistema MUST evitar
  duplicidade (nunca dois lançamentos para a mesma origem). **Cortesias MUST
  não gerar receita** (no máximo contagem informativa).
- **FR-021**: O módulo MUST **coexistir** com as telas financeiras existentes —
  vive no menu principal ("Financeiro"); o consolidado da 008 e a aba
  Financeiro do evento (pedidos + baixa da 009) permanecem inalterados.

### Key Entities

- **Lançamento financeiro**: um a pagar ou a receber — tipo, descrição, valor
  original, categoria, vencimento, situação (derivada), origem, forma de
  pagamento, evento (opcional), pessoa (opcional), observações, saldo restante,
  anexos, movimentações.
- **Baixa/movimentação**: pagamento ou recebimento (total/parcial) de um
  lançamento — data, valor, forma de pagamento, observação, comprovante, autor.
- **Parcela**: uma fração de um lançamento parcelado, com vencimento, situação
  e baixa próprios.
- **Categoria financeira**: de receita ou de despesa; ativa/inativa.
- **Pessoa financeira** (fornecedor/cliente): fornecedor, cliente,
  patrocinador, participante, prestador, outro.
- **Forma de pagamento**: Pix, cartão, boleto, dinheiro, transferência,
  depósito, permuta, cortesia, outro.
- **Anexo**: arquivo vinculado a um lançamento (comprovante, nota, recibo…).
- **Evento** (existente): centro de resultado ao qual os lançamentos podem se
  vincular.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A gestão descobre o resultado (lucro/prejuízo) de qualquer evento
  em menos de 10 segundos a partir do módulo, sem somar nada manualmente.
- **SC-002**: 100% dos saldos (previsto, realizado, resultado) batem com os
  lançamentos e baixas registrados, e cancelados nunca entram nos saldos.
- **SC-003**: Uma baixa (total ou parcial) é registrada em menos de 30
  segundos, com a situação e o saldo restante atualizados na mesma tela.
- **SC-004**: Nenhum lançamento com valor zero/negativo é aceito; nenhuma
  categoria com uso é excluída; nenhuma cortesia entra como receita — em 100%
  dos casos.
- **SC-005**: Toda alteração de valor em conta baixada exige justificativa e
  fica no histórico — zero alterações silenciosas.
- **SC-006**: O fechamento financeiro de um mês (dashboard filtrado + export) é
  concluído em menos de 3 minutos.
- **SC-007**: Uma mesma venda/patrocínio nunca produz dois lançamentos
  financeiros (zero duplicidade).

## Assumptions

- O módulo reaproveita o cadastro de **eventos**, **patrocínios** e
  **pagamentos** já existentes (specs 003/005/006/008/009); os lançamentos
  novos vivem no financeiro central e se vinculam a eventos quando aplicável.
- Datas e vencimentos no fuso oficial do evento (Brasil), como no restante do
  sistema.
- Exportação de relatórios reaproveita os formatos já usados no sistema (.xlsx
  como padrão; PDF/CSV conforme decisão de plano) — o requisito é o conteúdo
  filtrado, não o formato específico.
- Contas financeiras (bancos) são um campo opcional simples na baixa; a
  conciliação bancária completa fica para a Fase 2.
- Valores monetários seguem DECIMAL com duas casas, moeda brasileira.
- O módulo é acessível a partir do menu principal ("Financeiro") e, por
  evento, pela aba "Financeiro do evento".
