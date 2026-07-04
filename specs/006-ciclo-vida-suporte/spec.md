# Feature Specification: Ciclo de Vida e Suporte

**Feature Branch**: `006-ciclo-vida-suporte`

**Created**: 2026-07-03

**Status**: Implemented

**Input**: User description: "006-ciclo-vida-suporte — o pós-venda: cancelamento pelo inscrito (pedido/ingresso) e cancelamento do evento inteiro em cascata, transferência de ingresso para outra pessoa por e-mail, estorno pela tesouraria (cartão pelo gateway; Pix/boleto operacional) e o canal de suporte entre inscrito e organização (casos com conversas). Telas do inscrito e da tesouraria."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Cancelar meu pedido ou ingresso (Priority: P1)

O inscrito cancela um pedido inteiro ou um ingresso específico pela própria conta
(quando o evento permite). A vaga volta imediatamente para venda. Se já houver
pagamento, o cancelamento abre automaticamente um pedido de reembolso para a
tesouraria, calculado pela política de reembolso do evento.

**Why this priority**: é o direito básico do consumidor e a operação de pós-venda
mais frequente; destrava vagas presas e reduz chamados manuais.

**Independent Test**: cancelar um ingresso pago dentro do prazo da política e
verificar: ingresso cancelado, vaga liberada, caso de reembolso aberto com o
valor correto; tentar cancelar fora das regras e ver a recusa.

**Acceptance Scenarios**:

1. **Given** um pedido aguardando pagamento, **When** o inscrito o cancela,
   **Then** pedido e ingressos ficam cancelados, cobranças ativas são
   invalidadas e as vagas voltam ao estoque — sem reembolso a tratar.
2. **Given** um ingresso pago até 7 dias antes do início do evento, **When**
   o titular ou o comprador cancela, **Then** o ingresso é cancelado, a vaga
   liberada, e um pedido de reembolso **integral (100%)** é aberto; nos últimos
   7 dias antes do evento não há reembolso (cancelamento exige confirmação
   explícita de "sem devolução").
3. **Given** o evento com cancelamento pelo inscrito desabilitado, **When** o
   inscrito tenta cancelar, **Then** é orientado a abrir um caso de suporte.
4. **Given** um ingresso já utilizado, transferido ou de pedido expirado,
   **When** alguém tenta cancelá-lo, **Then** a operação é recusada como
   conflito (situação terminal).
5. **Given** um cancelamento efetivado, **When** consultada a trilha, **Then**
   constam quem pediu, quem executou, quando e o motivo.

---

### User Story 2 - Transferir meu ingresso para outra pessoa (Priority: P2)

O titular de um ingresso confirmado transfere sua vaga para outra pessoa
informando nome e e-mail do novo participante (quando o evento permite). O
ingresso original fica marcado como transferido (histórico preservado) e um novo
ingresso ativo nasce para o destinatário — com QR próprio; o antigo deixa de
valer na portaria.

**Why this priority**: recurso muito usado em seminários (imprevistos de agenda);
evita revenda informal e mantém a lista de presença correta.

**Independent Test**: transferir um ingresso confirmado para um e-mail novo e
verificar: original transferido (QR inválido para check-in), novo ingresso
confirmado vinculado ao destinatário, aparecendo em "meus ingressos" dele.

**Acceptance Scenarios**:

1. **Given** um ingresso confirmado/pago e o evento permitindo transferência,
   **When** o titular (ou comprador) informa nome e e-mail do novo participante,
   **Then** o original vira "transferido", nasce um novo ingresso confirmado com
   código próprio para o destinatário, e os dois ficam vinculados na trilha.
2. **Given** o destinatário com conta no sistema (ou que crie depois com o mesmo
   e-mail), **When** abre "meus ingressos", **Then** o ingresso transferido
   aparece para ele, com comprovante disponível.
3. **Given** um ingresso reservado (não pago), cortesia resgatada de voucher, já
   utilizado ou já transferido, **When** alguém tenta transferir, **Then** a
   operação é recusada com o motivo claro.
4. **Given** o evento já iniciado ou com transferência desabilitada, **When**
   tentada a transferência, **Then** recusa como conflito.
5. **Given** uma transferência concluída, **When** o antigo QR é apresentado na
   portaria (spec 007), **Then** será recusado — só o novo vale.

---

### User Story 3 - Estorno pela tesouraria (Priority: P3)

A tesouraria trata os pedidos de reembolso: para pagamento em cartão, dispara o
estorno pelo provedor; para Pix/boleto, faz a devolução por fora (transferência)
e registra a comprovação. O pagamento fica estornado, o pedido/ingresso refletem
a devolução, o caso é fechado e o inscrito é avisado por e-mail — tudo com trilha
completa. Pagamentos sinalizados como pendência (pedido expirado que foi pago,
valor divergente, pagamento duplo — spec 005) são tratados pelo mesmo fluxo.

**Why this priority**: fecha o ciclo financeiro aberto pelos cancelamentos;
depende da US1 existir.

**Independent Test**: cancelar um ingresso pago (gera caso de reembolso), logar
como tesouraria, executar o estorno (cartão simulado e Pix operacional) e
verificar situações, trilha e e-mail.

**Acceptance Scenarios**:

1. **Given** um caso de reembolso aberto de pagamento em cartão, **When** a
   tesouraria aprova o estorno, **Then** o estorno é solicitado ao provedor, o
   pagamento fica "estornado" com evidência, o valor devolvido fica registrado
   no ingresso/pedido e o inscrito recebe e-mail.
2. **Given** um caso de reembolso de Pix/boleto, **When** a tesouraria registra
   a devolução feita (com comprovação/justificativa), **Then** idem — com origem
   "manual/operacional" na trilha.
3. **Given** um pagamento já estornado, **When** alguém tenta estornar de novo,
   **Then** recusa como conflito (situação terminal).
4. **Given** um operador da tesouraria que é o comprador do pedido, **When**
   tenta aprovar o próprio estorno, **Then** recusa de permissão (mesma regra da
   baixa: nunca no próprio pedido).
5. **Given** um estorno parcial (percentual da política), **When** executado,
   **Then** o valor devolvido registrado corresponde ao percentual, e a
   diferença permanece como receita do evento.

---

### User Story 4 - Suporte: falar com a organização (Priority: P4)

O inscrito abre um caso de suporte (dúvida, troca de camisa, pedido de reembolso
fora do fluxo, outros) a partir da conta ou de um pedido/ingresso específico, e
conversa com a organização por mensagens. Admin e tesouraria veem a fila,
respondem (com notas visíveis ou internas), encerram e reabrem casos. O inscrito
só vê os próprios casos e as mensagens públicas.

**Why this priority**: canal formal que absorve tudo que os fluxos automáticos
não cobrem; base para os cenários operacionais das US1–US3.

**Independent Test**: abrir um caso como inscrito, responder como admin (uma nota
pública e uma interna), verificar que o inscrito vê só a pública, encerrar e
reabrir.

**Acceptance Scenarios**:

1. **Given** uma pessoa logada, **When** abre um caso (tipo + assunto + mensagem,
   opcionalmente vinculado a pedido/ingresso), **Then** o caso nasce "aberto" e
   aparece na fila da organização.
2. **Given** um caso aberto, **When** admin/tesouraria responde com nota pública,
   **Then** o inscrito vê a resposta na conversa; notas internas nunca aparecem
   para o inscrito.
3. **Given** um caso resolvido, **When** encerrado pela organização, **Then**
   fica "finalizado"; o inscrito pode reabri-lo com nova mensagem.
4. **Given** um inscrito, **When** tenta acessar caso de outra pessoa, **Then**
   recusa de permissão.
5. **Given** um cancelamento com reembolso (US1), **When** efetivado, **Then** o
   caso de reembolso criado automaticamente aparece nesta mesma fila, vinculado
   ao ingresso.

---

### User Story 5 - Cancelar o evento inteiro (Priority: P5)

Quando o administrador cancela o evento (fluxo da spec 003), todos os pedidos e
ingressos vivos são cancelados em cascata, casos de reembolso são abertos para
todos os pagamentos confirmados, os inscritos recebem e-mail do cancelamento, e a
tesouraria ganha a fila completa de devoluções.

**Why this priority**: cenário raro porém crítico; reusa tudo das US1/US3 — por
isso vem por último.

**Independent Test**: com pedidos pagos e pendentes no evento, cancelar o evento
e verificar: tudo cancelado, casos de reembolso abertos só para os pagos,
e-mails enviados, portaria recusando qualquer QR (007).

**Acceptance Scenarios**:

1. **Given** um evento com pedidos pagos, pendentes e cancelados, **When** o
   admin cancela o evento, **Then** pedidos/ingressos vivos ficam cancelados
   (com motivo "evento cancelado"), cobranças ativas são invalidadas e os
   registros históricos permanecem intactos.
2. **Given** os pagamentos confirmados do evento, **When** o cancelamento roda,
   **Then** um caso de reembolso integral (100%, independente da política) é
   aberto por pedido pago, e a fila aparece para a tesouraria.
3. **Given** os compradores afetados, **When** o cancelamento conclui, **Then**
   recebem e-mail informando o cancelamento e o processo de devolução.
4. **Given** o evento cancelado, **When** alguém tenta comprar/pagar/transferir,
   **Then** todas as operações são recusadas (guardas existentes).

---

### Edge Cases

- Cancelamento de 1 ingresso de um pedido pago com vários: o reembolso é do
  valor daquele ingresso (snapshot); o pedido permanece pago com os demais.
- Cortesia automática ganha pela regra X→Y quando o ingresso pagador é
  cancelado: a cortesia vinculada ao pedido permanece (decisão simples do MVP —
  revisão na Fase 2 se virar abuso).
- Transferência de ingresso de casal: transfere o ingresso inteiro (titular +
  acompanhante informados de novo); não existe "meia transferência".
- Reembolso quando o pagamento foi baixa manual: sempre operacional (tesouraria
  devolve por fora e registra) — nunca via provedor.
- Caso reaberto mais de uma vez: permitido; o histórico da conversa é único e
  contínuo.
- Inscrito abre caso de reembolso manualmente (sem cancelar): a organização
  orienta pela conversa; o fluxo automático continua sendo o oficial.
- Evento cancelado com pedido pendente de pagamento: pedido cancelado; se um
  pagamento tardio chegar, cai na pendência derivada da 005 e vira devolução.
- Prazo da política vencido: o botão de cancelar informa que não há reembolso e
  pede confirmação explícita antes de cancelar (cancela sem devolução).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O inscrito MUST poder cancelar pedido inteiro ou ingresso
  individual apenas quando o evento permite (`allow_user_cancel`), sobre itens
  próprios (comprador ou titular), em situações não terminais.
- **FR-002**: Cancelamento MUST liberar as vagas/lotes/estoques imediatamente
  (recontagem transacional) e invalidar cobranças ativas do pedido.
- **FR-003**: Cancelamento de item pago MUST abrir automaticamente um caso de
  reembolso vinculado, com valor calculado pela **política definida**: 100% até
  7 dias antes do início do evento (piso legal: 100% sempre nos 7 dias após a
  compra); nos últimos 7 dias antes do evento não há reembolso e o cancelamento
  MUST exigir confirmação explícita de "sem devolução". Cancelamento do EVENTO
  devolve 100% independentemente do prazo.
- **FR-004**: Toda ação de cancelamento MUST registrar quem pediu, quem
  executou, quando e o motivo (trilha completa).
- **FR-005**: Transferência MUST estar disponível apenas para ingressos
  pagos/confirmados, evento permitindo (`allow_transfer`) e antes do início do
  evento; reservados, cortesias de voucher, usados e já transferidos MUST ser
  recusados.
- **FR-006**: A transferência MUST marcar o original como "transferido"
  (terminal, QR inválido), criar novo ingresso confirmado com código novo para o
  destinatário (nome/e-mail obrigatórios; camisa herdada) e vincular os dois
  registros nas duas direções.
- **FR-007**: O novo ingresso MUST aparecer em "meus ingressos" do destinatário
  (por conta ou e-mail — vínculo tardio da 004) com comprovante disponível.
- **FR-008**: O estorno MUST passar por um fluxo único da tesouraria: cartão →
  solicitação ao provedor pelo conector; Pix/boleto/manual → registro
  operacional com justificativa/comprovação obrigatória.
- **FR-009**: O estorno MUST marcar o pagamento como "estornado" (terminal) com
  evidência, registrar valor devolvido e momento no ingresso/pedido, encerrar o
  caso vinculado e enviar e-mail ao comprador.
- **FR-010**: Estorno repetido sobre pagamento estornado MUST ser recusado;
  operador que é o comprador do pedido MUST ser recusado por permissão.
- **FR-011**: Estorno parcial MUST registrar exatamente o valor devolvido; a
  diferença permanece contabilizada como receita.
- **FR-012**: O inscrito MUST poder abrir casos de suporte (tipo, assunto,
  mensagem; vínculo opcional a pedido/ingresso) e conversar por mensagens; MUST
  ver apenas os próprios casos e apenas notas públicas.
- **FR-013**: Admin e tesouraria MUST ver a fila completa de casos, responder
  com notas públicas ou internas, encerrar e reabrir; o inscrito MUST poder
  reabrir caso finalizado com nova mensagem.
- **FR-014**: Cancelamento do evento MUST cascatear: pedidos/ingressos vivos
  cancelados com motivo, cobranças invalidadas, casos de reembolso integral
  abertos para pagamentos confirmados e e-mail de aviso aos compradores — em
  processamento resiliente (falha em um item não interrompe os demais).
- **FR-015**: Todas as operações desta spec MUST respeitar as guardas
  existentes: situações terminais recusam transição (409), escopo de dono
  (403), papéis (403), envelope de erros em pt-BR.

### Key Entities

Todas já existem na fundação — esta spec movimenta:

- **Ingresso**: ganha os fluxos cancelar/transferir; campos de cancelamento,
  transferência (from/to) e reembolso já existem.
- **Pedido**: cancelamento em cascata dos seus ingressos; situação derivada dos
  pagamentos/estornos.
- **Pagamento**: transição pago → estornado com evidência (valor devolvido).
- **Caso de suporte + Notas**: o canal — tipo, situação (aberto/finalizado/
  reaberto), vínculo a pedido/ingresso, conversas com visibilidade por nota.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um inscrito cancela um ingresso em menos de 1 minuto pela própria
  conta; a vaga volta ao catálogo imediatamente (recompravel na sequência).
- **SC-002**: 100% dos cancelamentos de itens pagos geram caso de reembolso com
  o valor correto pela política; 0 reembolsos criados para itens não pagos.
- **SC-003**: Após uma transferência, o ingresso antigo nunca é aceito e o novo
  sempre é aceito (verificado nas guardas que a portaria da 007 usará); o
  destinatário vê o ingresso e o comprovante em 100% dos casos de teste.
- **SC-004**: 100% dos estornos ficam com trilha completa (origem, operador,
  evidência, valor); estorno duplicado e auto-estorno são recusados em 100% das
  tentativas.
- **SC-005**: No cancelamento de evento com N pedidos mistos, 100% dos vivos são
  cancelados, casos de reembolso são abertos exatamente para os pagos, e nenhum
  registro histórico é perdido.
- **SC-006**: O inscrito nunca vê casos de terceiros nem notas internas (0
  vazamentos nos testes de escopo).
- **SC-007**: A suíte cobre as 5 user stories e passa integralmente; as suítes
  anteriores permanecem verdes.

## Assumptions

- Herança: guardas terminais e trilha (001), sessão (002), flags do evento
  (003), compra/vagas/claim por e-mail (004), RegisterPayment/pendências (005).
- **Estorno de cartão** usa o conector da 005 (o fake simula a devolução); com
  gateway real, entra pelo mesmo contrato. **Pix/boleto** é sempre operacional
  no MVP (devolução por fora + registro) — API de devolução Pix automática fica
  para a Fase 2.
- Cancelamento de evento: os estornos NÃO são disparados automaticamente ao
  provedor em massa — a cascata abre a fila de casos e a tesouraria processa um
  a um (controle humano sobre dinheiro; volume do MVP comporta).
- E-mails do ciclo (cancelamento, transferência ao novo titular, estorno
  efetuado, evento cancelado) seguem o padrão da 005 (falha não bloqueia).
- Reabertura de caso é ilimitada no MVP; métricas de SLA/atendimento ficam para
  a 008/Fase 2.
- **Política de reembolso definida pelo organizador (decisão registrada em
  2026-07-03)**: devolução integral (100%) para cancelamentos até 7 dias antes
  do início do evento; sem reembolso nos últimos 7 dias. Pisos que valem sempre:
  100% nos 7 dias seguintes à compra (CDC art. 49) e 100% quando o EVENTO é
  cancelado. O prazo (7 dias) fica configurável tecnicamente para a Fase 2, sem
  tela de admin no MVP.
