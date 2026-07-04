# Feature Specification: Pagamento

**Feature Branch**: `005-pagamento`

**Created**: 2026-07-03

**Status**: Implemented

**Input**: User description: "005-pagamento — o pagamento real dos pedidos: Pix (QR + copia-e-cola com confirmação automática), boleto híbrido (linha digitável + QR Pix na mesma cobrança), cartão com tokenização no navegador, ponto único de baixa idempotente, notificações do provedor com deduplicação e reconsulta, reconciliação diária de segurança, baixa manual de contingência pela tesouraria (quem compra não dá a própria baixa) e e-mails transacionais. Checkout no site e painel da tesouraria."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Pagar com Pix e confirmar na hora (Priority: P1)

Com o pedido criado (spec 004), o comprador escolhe Pix e recebe o QR code e o
código copia-e-cola. Ao pagar no aplicativo do banco, a confirmação chega em
segundos: a tela do pedido atualiza sozinha, os ingressos ficam confirmados e o
comprador recebe um e-mail — os comprovantes (spec 004) são liberados.

**Why this priority**: Pix é o meio dominante no público-alvo e o único com
confirmação instantânea — é o caminho feliz do produto e o MVP do pagamento.

**Independent Test**: com o provedor simulado, gerar a cobrança Pix de um pedido,
simular a notificação de pagamento e ver pedido pago, ingressos confirmados,
tela atualizada e e-mail enviado.

**Acceptance Scenarios**:

1. **Given** um pedido aguardando pagamento, **When** o comprador escolhe Pix,
   **Then** recebe QR code e copia-e-cola com validade alinhada ao prazo da
   reserva, e o registro da cobrança fica vinculado ao pedido.
2. **Given** a cobrança paga no banco, **When** a notificação do provedor chega,
   **Then** o pedido fica "pago", os ingressos "confirmados", e a tela do
   comprador reflete isso sem recarregar (acompanhamento automático).
3. **Given** o pagamento confirmado, **When** processado, **Then** o comprador
   recebe e-mail de confirmação com os ingressos.
4. **Given** um pedido que expira antes do pagamento, **When** o comprador tenta
   gerar a cobrança, **Then** é orientado a fazer novo pedido (conflito).
5. **Given** alguém sem sessão ou dono de outro pedido, **When** tenta gerar
   cobrança de um pedido alheio, **Then** recebe recusa de permissão.

---

### User Story 2 - Baixa única e confiável, aconteça o que acontecer (Priority: P2)

O organizador confia que **todo** pagamento vira baixa exatamente **uma vez** —
mesmo com notificações duplicadas, fora de ordem, forjadas ou perdidas. Toda
notificação é registrada, verificada e reconferida junto ao provedor antes de
baixar; e uma varredura diária concilia os pedidos pendentes diretamente com o
provedor, garantindo a baixa mesmo quando a notificação nunca chegou.

**Why this priority**: é o coração financeiro (princípio inegociável da
plataforma); sem ele, Pix/boleto/cartão não são confiáveis. A documentação de
notificações do provedor bancário é reconhecidamente incompleta — a varredura
diária não é opcional.

**Independent Test**: enviar a mesma notificação duas vezes (uma baixa só);
enviar notificação com assinatura inválida (rejeitada e registrada); apagar a
notificação e rodar a reconciliação (pedido baixado mesmo assim).

**Acceptance Scenarios**:

1. **Given** a mesma notificação de pagamento entregue duas vezes, **When**
   processadas, **Then** a baixa acontece uma única vez (o valor nunca conta em
   dobro) e a duplicata fica registrada como ignorada.
2. **Given** uma notificação com assinatura/origem inválida, **When** recebida,
   **Then** é rejeitada sem efeito e fica registrada para auditoria.
3. **Given** uma notificação válida, **When** processada, **Then** a situação da
   cobrança é reconferida junto ao provedor antes da baixa (a notificação nunca
   é a única fonte de verdade).
4. **Given** um pagamento feito cuja notificação se perdeu, **When** a
   reconciliação diária roda, **Then** o pedido é baixado normalmente.
5. **Given** um pagamento confirmado de pedido que já expirou, **When**
   processado, **Then** o pagamento fica registrado como recebido, o pedido
   permanece expirado e a tesouraria é sinalizada para tratar (estorno na spec
   006) — vagas possivelmente revendidas nunca são duplicadas.
6. **Given** qualquer baixa (notificação, reconciliação ou manual), **When**
   consultada a trilha, **Then** consta origem, momento e evidência bruta da
   confirmação.

---

### User Story 3 - Pagar com boleto híbrido (Priority: P3)

O comprador escolhe boleto e recebe uma cobrança híbrida: linha digitável para
pagar como boleto ou QR Pix embutido para pagar na hora — com PDF/e-mail para
guardar. O vencimento respeita o prazo da reserva; a compensação (quando paga
como boleto) confirma nos dias seguintes pela notificação ou reconciliação.

**Why this priority**: alternativa importante para o público sem Pix à mão, mas
depende da fundação de confirmação (US2) e complementa o Pix.

**Independent Test**: gerar a cobrança híbrida de um pedido (linha digitável +
QR), simular a liquidação e ver o pedido confirmado; e-mail com o boleto enviado.

**Acceptance Scenarios**:

1. **Given** um pedido aguardando pagamento, **When** o comprador escolhe boleto,
   **Then** recebe linha digitável e QR Pix da mesma cobrança, com vencimento
   compatível com o prazo da reserva, e um e-mail com os dados.
2. **Given** o boleto liquidado, **When** a confirmação chega (notificação ou
   reconciliação), **Then** pedido pago e ingressos confirmados.
3. **Given** um pagamento com valor divergente do pedido, **When** registrado,
   **Then** o pedido fica "parcialmente pago" e a tesouraria é sinalizada para
   resolver — nunca confirmação automática por valor errado.

---

### User Story 4 - Pagar com cartão sem expor os dados (Priority: P4)

O comprador paga com cartão de crédito (à vista ou parcelado) direto na tela de
checkout. Os dados do cartão são capturados de forma segura no próprio navegador
pelo provedor de pagamento — o número completo **nunca** passa pela plataforma —
e a aprovação confirma o pedido na hora; recusas retornam mensagem clara com a
chance de tentar de novo dentro do prazo da reserva.

**Why this priority**: completa o tripé de meios de pagamento; depende da
escolha comercial do provedor de cartão (pendência externa) — por isso nasce
sobre um conector substituível, validado com provedor simulado.

**Independent Test**: com o provedor simulado, tokenizar um cartão de teste,
aprovar (pedido confirmado na hora) e recusar (mensagem clara, pedido continua
aguardando).

**Acceptance Scenarios**:

1. **Given** o checkout de cartão, **When** o comprador preenche os dados,
   **Then** a captura acontece pelo componente seguro do provedor e a plataforma
   recebe apenas um código de uso único — nunca o número do cartão.
2. **Given** uma transação aprovada, **When** processada, **Then** pedido pago,
   ingressos confirmados e e-mail enviado — na mesma tela, em segundos.
3. **Given** uma transação recusada, **When** retornada, **Then** o comprador vê
   orientação clara e pode tentar novamente enquanto a reserva vale.
4. **Given** um pedido com parcelamento, **When** aprovado, **Then** o número de
   parcelas fica registrado no pagamento (a cobrança das parcelas é do emissor
   do cartão; para o evento o pedido está pago).

---

### User Story 5 - Tesouraria: recebimentos, conciliação e contingência (Priority: P5)

Quem tem o papel de tesouraria acompanha os recebimentos (por meio, situação e
período), dispara a conciliação com o provedor sob demanda, e — em contingência
(ex.: pagamento comprovado por outros meios) — registra baixa manual com
justificativa e comprovação. **Quem compra nunca dá baixa no próprio pedido**,
nem mesmo tendo papel de tesouraria.

**Why this priority**: operação financeira do dia a dia; depende de existirem
pagamentos (US1–US4).

**Independent Test**: logar como tesouraria, ver recebimentos, rodar a
conciliação manualmente, registrar uma baixa manual com justificativa e conferir
a trilha; tentar baixar o próprio pedido e receber recusa.

**Acceptance Scenarios**:

1. **Given** o papel de tesouraria, **When** abre os recebimentos, **Then** vê
   os pagamentos com meio, situação, valor, pedido e origem da baixa —
   filtráveis por situação e meio; sem o papel, recusa de acesso.
2. **Given** pedidos pendentes com cobrança, **When** a tesouraria dispara a
   conciliação, **Then** os pagos junto ao provedor são baixados e o resultado é
   apresentado.
3. **Given** um pagamento comprovado fora do sistema, **When** a tesouraria
   registra baixa manual com justificativa, **Then** o pedido confirma com a
   trilha completa (quem, quando, por quê).
4. **Given** um pedido cujo comprador é o próprio operador da tesouraria,
   **When** ele tenta a baixa manual, **Then** a operação é recusada por
   permissão — outra pessoa da tesouraria precisa fazê-lo.
5. **Given** um pedido já pago, **When** alguém tenta baixa manual de novo,
   **Then** recusa como conflito.

---

### Edge Cases

- Notificações fora de ordem (confirmação chega antes do registro da cobrança
  terminar): a reconsulta ao provedor resolve — a baixa só acontece com a
  cobrança confirmada na fonte.
- Comprador gera Pix, desiste e gera boleto: cobranças anteriores do pedido são
  canceladas/invalidadas — só uma cobrança ativa por pedido por vez.
- Pagamento duplo real (pagou o Pix e o boleto da troca): segunda confirmação é
  registrada, pedido já pago não muda, tesouraria sinalizada (estorno na 006).
- Cobrança Pix expira sem pagamento: comprador pode gerar nova cobrança dentro
  do prazo da reserva.
- Reserva expira com cobrança ativa: cobrança é cancelada junto ao provedor na
  expiração (melhor esforço) — pagamento tardio cai no cenário US2-5.
- Meio de pagamento desabilitado no evento (flags da 003): opção não aparece e a
  tentativa direta é recusada.
- Pedido de total zero (cortesia/voucher da 004): não passa por aqui — já nasce
  pago.
- Falha temporária do provedor ao criar cobrança: erro claro ao comprador com
  nova tentativa; nada fica meio-criado.
- Renovação/expiração do certificado bancário: falhas de autenticação com o
  provedor ficam registradas e visíveis para o admin (monitoramento na 008).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O comprador MUST poder escolher o meio (Pix, boleto, cartão) entre
  os habilitados no evento, apenas para pedidos próprios aguardando pagamento e
  dentro do prazo de reserva; fora disso → conflito/permissão.
- **FR-002**: Pix MUST gerar cobrança com QR code e copia-e-cola, validade
  alinhada ao prazo da reserva, registrada e vinculada ao pedido.
- **FR-003**: Boleto MUST ser híbrido (linha digitável + QR Pix da mesma
  cobrança), com vencimento compatível com a reserva e e-mail com os dados.
- **FR-004**: Cartão MUST usar captura segura no navegador do comprador (a
  plataforma recebe apenas código de uso único — nunca número/validade/código de
  segurança), com aprovação síncrona, recusa com mensagem clara e parcelamento
  registrado (1 a 12).
- **FR-005**: Apenas UMA cobrança ativa por pedido: gerar nova cobrança
  invalida/cancela a anterior.
- **FR-006**: Toda confirmação de pagamento — notificação, reconciliação ou
  baixa manual — MUST passar por um ponto único de baixa, idempotente: o mesmo
  evento externo nunca baixa duas vezes.
- **FR-007**: Notificações do provedor MUST ser registradas na chegada (payload
  bruto), deduplicadas, verificadas quanto à origem/assinatura, e a situação da
  cobrança MUST ser reconferida no provedor antes de qualquer baixa.
- **FR-008**: Notificações inválidas/desconhecidas MUST ser rejeitadas sem
  efeito e mantidas registradas para auditoria.
- **FR-009**: Uma reconciliação diária automática MUST varrer pedidos pendentes
  com cobrança e baixar os confirmados no provedor; a tesouraria MUST poder
  disparar a mesma varredura sob demanda.
- **FR-010**: A baixa MUST confirmar pedido e ingressos (pendentes → pagos/
  confirmados) e disparar o e-mail de confirmação — uma única vez.
- **FR-011**: Pagamento com valor divergente MUST marcar o pedido como
  parcialmente pago e sinalizar a tesouraria — nunca confirmar por valor errado.
- **FR-012**: Pagamento confirmado de pedido expirado MUST ser registrado sem
  reativar o pedido, com sinalização à tesouraria (tratamento/estorno na spec
  006).
- **FR-013**: Baixa manual MUST exigir papel de tesouraria, justificativa e
  identificação de quem registrou; **o comprador do pedido nunca pode dar a
  própria baixa**, mesmo com o papel — recusa de permissão.
- **FR-014**: O painel da tesouraria MUST listar recebimentos com meio,
  situação, valor, pedido, origem da baixa e filtros; acesso restrito ao papel.
- **FR-015**: E-mails transacionais MUST ser enviados: cobrança de boleto
  emitida (com os dados) e pagamento confirmado (com os ingressos); falha de
  e-mail nunca bloqueia a baixa.
- **FR-016**: Credenciais do provedor (certificado, chaves) MUST viver fora do
  código e do navegador; nenhum dado sensível de cartão MUST ser armazenado ou
  registrado em log.
- **FR-017**: O acompanhamento do pagamento na tela do comprador MUST atualizar
  automaticamente quando a confirmação chegar (sem recarregar a página).
- **FR-018**: Provedores MUST ficar atrás de um conector substituível: trocar de
  provedor de cartão ou banco não pode exigir reescrever o fluxo de pagamento.
- **FR-019**: Toda a movimentação MUST manter trilha completa (quem/quando/
  origem/evidência bruta) e valores com precisão de centavos.

### Key Entities

Todas já existem na fundação — esta spec as movimenta de verdade:

- **Pagamento**: cobrança vinculada ao pedido — meio, provedor, identificador
  externo, situação, dados de exibição (QR, linha digitável), parcelas, momento
  do pagamento, quem registrou (se manual), evidência bruta; idempotência por
  provedor + identificador externo.
- **Evento de notificação (webhook)**: registro bruto de cada notificação
  recebida — origem, identificador, payload, resultado do processamento
  (ok/ignorada/erro) — dedupe e auditoria.
- **Pedido / Ingresso**: transições pendente → pago / reservado → confirmado
  acontecem exclusivamente pelo ponto único de baixa.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Pagamento Pix confirmado no provedor reflete no pedido (pago +
  ingressos confirmados + tela atualizada) em menos de 30 segundos.
- **SC-002**: Em todos os cenários de teste de duplicidade (notificação repetida,
  reconciliação após notificação, baixa manual sobre pago), a baixa ocorre
  exatamente **uma vez** — 0 confirmações duplicadas.
- **SC-003**: Com a notificação perdida, 100% dos pagamentos confirmados no
  provedor são baixados pela reconciliação seguinte.
- **SC-004**: 100% das notificações com origem/assinatura inválida são rejeitadas
  sem efeito e registradas.
- **SC-005**: Nenhum dado sensível de cartão aparece em banco, logs ou respostas
  em nenhum cenário de teste (verificação automatizada).
- **SC-006**: 100% das tentativas de baixa manual pelo próprio comprador são
  recusadas; baixas manuais legítimas ficam com trilha completa.
- **SC-007**: O comprador completa o checkout Pix (escolher meio → ver QR) em
  menos de 15 segundos após o pedido criado.
- **SC-008**: A suíte cobre as 5 user stories com provedores simulados e passa
  integralmente; as suítes anteriores permanecem verdes.

## Assumptions

- Herança: pedidos com reserva TTL (004), papéis e RBAC (001/002/003), evento
  com flags de meios de pagamento (003).
- **Provedores em modo simulado/sandbox nesta spec**: banco (Pix/boleto) via
  sandbox e/ou simulador de notificações; cartão atrás do conector substituível
  com provedor simulado. Os bloqueadores externos (certificado bancário,
  escolha Cielo/Rede, domínio HTTPS) são necessários para produção, não para
  esta entrega — o desenho não muda com a escolha (conector).
- Validação ponta a ponta com o banco real (sandbox oficial) fica registrada no
  guia de validação como etapa manual dependente das credenciais.
- Parcelamento no cartão: 1 a 12 vezes, sem juros da plataforma (valor total
  igual) — repasse de juros é decisão comercial futura.
- Estorno/reembolso é a spec 006; cancelamento de evento com devolução idem.
- Pagamento em dinheiro/presencial não existe no MVP — a baixa manual de
  contingência cobre exceções comprovadas.
- O acompanhamento automático da tela usa verificação periódica (polling) — 
  tempo real via push fica para a Fase 2.
- Relatórios financeiros consolidados são a spec 008; aqui a tesouraria tem a
  lista operacional de recebimentos.
