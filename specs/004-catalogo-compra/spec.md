# Feature Specification: Catálogo Público e Compra

**Feature Branch**: `004-catalogo-compra`

**Created**: 2026-07-03

**Status**: Implemented

**Input**: User description: "004-catalogo-compra — a experiência pública do produto: landing page do evento renderizada a partir dos blocos configurados, catálogo com o lote vigente e preços efetivos, carrinho com múltiplos participantes (grupo/casal e camisa por pessoa), reserva com prazo de expiração, cortesias (regra automática e resgate de voucher), área do inscrito (meus pedidos e ingressos) e comprovante em PDF com QR code. O pagamento real fica na spec 005 — aqui o pedido nasce aguardando pagamento."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver o evento e o catálogo sem login (Priority: P1)

Qualquer visitante acessa a página pública do evento pelo endereço com o nome do
evento e vê a landing montada com os blocos configurados pelo administrador (capa,
texto, programação, palestrantes, perguntas frequentes, local e chamada), junto com
o catálogo de ingressos: tipos disponíveis, preço do lote vigente, e indicações de
esgotado ou inscrições encerradas — tudo sem precisar de conta.

**Why this priority**: é a vitrine; sem ela ninguém descobre nem começa a compra.
Funciona sozinha como página de divulgação mesmo antes do fluxo de compra.

**Independent Test**: abrir a página pública do evento do seed sem sessão e
conferir blocos na ordem configurada e catálogo com preços do lote vigente.

**Acceptance Scenarios**:

1. **Given** um evento publicado com blocos e catálogo, **When** um visitante
   anônimo acessa a página pelo endereço público, **Then** vê os blocos ativos na
   ordem definida e os tipos de ingresso com o preço efetivo do lote vigente.
2. **Given** blocos desativados pelo admin, **When** a página é exibida, **Then**
   eles não aparecem.
3. **Given** um evento em rascunho ou cancelado, **When** alguém acessa o endereço
   público, **Then** recebe "não encontrado" (rascunho) ou uma página informando o
   cancelamento — nunca o catálogo de venda.
4. **Given** inscrições fora da janela, sem lote vigente ou capacidade esgotada,
   **When** a página é exibida, **Then** o catálogo aparece como "inscrições
   encerradas"/"esgotado", sem botão de compra.
5. **Given** um tipo de ingresso esgotado (capacidade própria), **When** exibido,
   **Then** aparece marcado como esgotado e não pode ser adicionado ao carrinho.

---

### User Story 2 - Comprar ingressos para o grupo (Priority: P2)

Uma pessoa logada monta o carrinho escolhendo quantidades por tipo de ingresso e
preenche os dados de cada participante (nome, e-mail e documento opcionais; camisa
por pessoa quando o evento oferece; ingresso de casal com os dados do acompanhante
e camisa para cada um). Ao confirmar, nasce um pedido com prazo de reserva: as
vagas ficam garantidas por um tempo limitado enquanto o pagamento não acontece
(pagamento real chega na spec 005).

**Why this priority**: é o coração da conversão — transforma visitante em
inscrito; todas as regras duras de capacidade acontecem aqui.

**Independent Test**: logado, montar carrinho com 2 tipos (um casal), preencher
participantes e confirmar; verificar pedido aguardando pagamento com prazo, um
ingresso por participante com preço do momento e vagas/estoques decrementados.

**Acceptance Scenarios**:

1. **Given** um visitante não logado com carrinho montado, **When** tenta
   finalizar, **Then** é levado ao login/cadastro e retorna ao checkout com o
   carrinho preservado.
2. **Given** uma pessoa logada com carrinho válido, **When** confirma a compra,
   **Then** nasce um pedido "aguardando pagamento" com prazo de reserva visível,
   um ingresso por participante, cada um com o preço efetivo do momento congelado
   (mudanças futuras de lote/preço não afetam o pedido).
3. **Given** um ingresso de casal, **When** confirmado, **Then** ocupa 2 vagas e
   registra os dados e a camisa do titular e do acompanhante.
4. **Given** o evento exige camisa, **When** algum participante fica sem
   tamanho/modelo, **Then** a confirmação é recusada com erro no campo.
5. **Given** duas pessoas confirmando ao mesmo tempo as últimas vagas (ou último
   estoque de camisa, ou última unidade do lote), **When** ambas submetem,
   **Then** apenas uma conclui; a outra recebe recusa clara de esgotamento —
   nunca há venda além do limite.
6. **Given** inscrições encerradas ou evento não publicado, **When** alguém tenta
   confirmar uma compra, **Then** a operação é recusada como conflito.
7. **Given** um pedido criado, **When** consultado, **Then** o total é a soma dos
   ingressos e os dados do comprador ficam congelados no pedido.

---

### User Story 3 - Cortesias na compra (Priority: P3)

Quando o evento tem cortesia habilitada, o comprador ganha automaticamente Y
ingressos de cortesia a cada X pagos (respeitando o limite por conta), e pode
também resgatar um voucher de cortesia recebido do organizador — o código vira um
ingresso de cortesia confirmado na hora.

**Why this priority**: regra de negócio central do seminário (grupos grandes), mas
a compra funciona sem ela.

**Independent Test**: comprar X ingressos com a regra ativa e ver a cortesia
gerada; resgatar um voucher distribuído e ver o ingresso confirmado; repetir o
resgate e ver a recusa.

**Acceptance Scenarios**:

1. **Given** regra "a cada 10 pagos, 1 cortesia" ativa, **When** um pedido com 10
   ingressos pagáveis é criado, **Then** 1 ingresso de cortesia adicional nasce no
   pedido (valor zero, participante indicado pelo comprador).
2. **Given** o limite de cortesias por conta atingido em compras anteriores,
   **When** nova compra bateria a regra, **Then** a cortesia não é gerada — e as
   contagens consideram as compras anteriores mesmo em envios simultâneos.
3. **Given** um voucher distribuído válido, **When** o comprador o informa no
   checkout com os dados do participante, **Then** nasce um ingresso de cortesia
   confirmado e o voucher fica resgatado (vinculado ao ingresso).
4. **Given** um voucher já resgatado, inexistente ou de outro evento, **When**
   informado, **Then** o resgate é recusado com mensagem clara.

---

### User Story 4 - Minha área: pedidos, ingressos e comprovante (Priority: P4)

O inscrito vê seus pedidos (com situação e prazo de reserva) e seus ingressos —
tanto os que comprou quanto os emitidos no nome dele por outra pessoa. Para
ingressos confirmados, baixa o comprovante em PDF com QR code (que a portaria
lerá na spec 007).

**Why this priority**: fecha o ciclo pós-compra e é a base das specs 006/007;
depende da compra existir.

**Independent Test**: após uma compra, ver o pedido e os ingressos na área do
inscrito; baixar o PDF de um ingresso confirmado (cortesia) e conferir o QR; tentar
acessar pedido de outra pessoa e receber recusa.

**Acceptance Scenarios**:

1. **Given** uma pessoa logada com pedidos, **When** abre "meus pedidos", **Then**
   vê cada pedido com situação, total, prazo de reserva (quando aguardando) e os
   ingressos de cada um.
2. **Given** um ingresso emitido com o e-mail de uma pessoa com conta, **When**
   ela abre "meus ingressos", **Then** o ingresso aparece mesmo que a compra tenha
   sido de outra pessoa.
3. **Given** um ingresso confirmado (ex.: cortesia), **When** o dono baixa o
   comprovante, **Then** recebe um PDF com dados do evento/participante e QR code
   com o código público do ingresso.
4. **Given** um ingresso ainda aguardando pagamento, **When** o dono tenta o
   comprovante, **Then** é orientado a concluir o pagamento (sem PDF).
5. **Given** um pedido/ingresso de outra pessoa, **When** alguém tenta acessar
   pelo identificador, **Then** recebe recusa de permissão — sem vazar dados.

---

### User Story 5 - Reserva expira e libera as vagas (Priority: P5)

Pedidos aguardando pagamento que passam do prazo de reserva expiram
automaticamente: o pedido e seus ingressos são marcados como expirados e as vagas,
lotes e estoques de camisa voltam a ficar disponíveis para outras pessoas.

**Why this priority**: sem expiração, carrinhos abandonados travariam o evento;
roda em segundo plano e depende da compra existir.

**Independent Test**: criar pedido, avançar o relógio além do prazo, rodar a
rotina de expiração e verificar pedido/ingressos expirados e vagas liberadas.

**Acceptance Scenarios**:

1. **Given** um pedido aguardando pagamento com prazo vencido, **When** a rotina
   de expiração roda, **Then** pedido e ingressos ficam "expirados" e as
   contagens de vaga/lote/estoque voltam a disponibilizar os lugares.
2. **Given** um pedido dentro do prazo, **When** a rotina roda, **Then** nada
   muda.
3. **Given** um pedido expirado, **When** o comprador tenta usá-lo, **Then** é
   orientado a fazer nova compra (situação terminal).

---

### Edge Cases

- Lote vira (por data ou esgotamento) entre montar o carrinho e confirmar: vale o
  preço vigente **no momento da confirmação**, e a tela avisa se mudou.
- Ingresso de casal quando resta 1 vaga: recusado (casal ocupa 2).
- Cortesia automática e capacidade: a cortesia também ocupa vaga; se não couber, o
  pedido é recusado por esgotamento (nunca meio-pedido).
- Comprador é o participante (compra para si): dados pré-preenchidos com a conta.
- Participante sem conta no sistema: ingresso emitido normalmente (por e-mail);
  se criar conta depois com o mesmo e-mail, o ingresso aparece em "meus ingressos".
- Quantidade zero ou carrinho vazio: recusa de validação.
- Página pública de evento com janela futura: mostra "em breve" com a data de
  abertura das vendas.
- Voucher informado junto com carrinho vazio: permitido — resgate puro de voucher.
- PDF de ingresso cancelado/expirado: recusado (só confirmados/utilizáveis).
- Expiração concorrente com tentativa de pagamento: quem chegar primeiro vence; o
  fluxo de pagamento (005) revalidará a situação do pedido.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: A página pública do evento MUST ser acessível sem conta, pelo
  endereço com o nome público do evento, renderizando apenas blocos ativos na
  ordem configurada.
- **FR-002**: Eventos não publicados MUST responder "não encontrado" no endereço
  público; cancelados MUST informar o cancelamento sem catálogo de venda.
- **FR-003**: O catálogo MUST exibir tipos ativos com o preço efetivo do lote
  vigente e estados derivados (esgotado, inscrições encerradas, em breve) — sem
  nenhum estado editável paralelo.
- **FR-004**: A compra MUST exigir sessão apenas na finalização; o carrinho
  montado MUST sobreviver ao redirecionamento de login.
- **FR-005**: O checkout MUST coletar, por participante: nome (obrigatório),
  e-mail e documento (opcionais), camisa (modelo/tamanho) quando o evento
  oferece — obrigatória quando o evento exige; casal MUST coletar também
  acompanhante (nome e camisa própria).
- **FR-006**: A confirmação MUST validar, dentro de uma única transação com
  recontagem: evento publicado e vendável, janela, lote vigente com saldo,
  capacidade do evento e do tipo, estoque de camisa por tamanho — e MUST recusar
  como conflito qualquer estouro, sem jamais criar pedido parcial.
- **FR-007**: O pedido criado MUST nascer "aguardando pagamento" com prazo de
  reserva conforme a configuração do evento, dados do comprador congelados e um
  ingresso por participante com preço efetivo congelado no momento.
- **FR-008**: Ingressos MUST decrementar as disponibilidades derivadas (vaga,
  lote, estoque de camisa — casal conta 2 vagas e 2 camisas) imediatamente após a
  confirmação.
- **FR-009**: Com cortesia habilitada, o sistema MUST gerar automaticamente Y
  ingressos de cortesia a cada X pagáveis do pedido, respeitando o limite por
  conta considerando compras anteriores — com recontagem na mesma transação.
- **FR-010**: O resgate de voucher MUST aceitar apenas vouchers distribuídos do
  próprio evento, gerar ingresso de cortesia confirmado, vincular o voucher ao
  ingresso e marcá-lo resgatado — uma única vez.
- **FR-011**: "Meus pedidos" MUST listar apenas os pedidos do próprio usuário;
  "meus ingressos" MUST incluir também ingressos emitidos para o e-mail do
  usuário em compras de terceiros.
- **FR-012**: Acesso a pedido/ingresso de outra pessoa MUST ser recusado como
  falta de permissão.
- **FR-013**: O comprovante em PDF MUST estar disponível apenas para ingressos
  confirmados/utilizáveis, contendo evento, participante, tipo e QR code com o
  código público (nunca identificador sequencial).
- **FR-014**: Uma rotina periódica MUST expirar pedidos aguardando pagamento com
  prazo vencido, marcando pedido e ingressos como expirados e devolvendo as
  disponibilidades.
- **FR-015**: Pedidos/ingressos expirados MUST ser terminais (sem transição), e o
  histórico integral MUST ser preservado.
- **FR-016**: Toda recusa de regra de negócio MUST responder como conflito com
  mensagem clara em pt-BR; erros de preenchimento MUST apontar o campo.
- **FR-017**: A quantidade por pedido MUST ter um limite superior razoável
  (proteção contra abuso), configurável tecnicamente, com padrão de 20 ingressos.

### Key Entities

Todas já existem na fundação — esta spec cria os fluxos públicos:

- **Pedido**: nasce na compra; código público, comprador congelado, total, prazo
  de reserva, situação (aguardando → expirado nesta spec; pago na 005).
- **Ingresso**: um por participante; snapshot de preço/tipo/lote/camisa; código
  público (base do QR); cortesias nascem confirmadas.
- **Voucher de cortesia**: passa de distribuído a resgatado, vinculado ao
  ingresso gerado.
- **Bloco de landing / Tipo / Lote / Camisa**: consumidos como catálogo público
  (somente leitura aqui).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um visitante conclui a compra (landing → carrinho → participantes →
  pedido criado) em menos de 3 minutos, incluindo o login no caminho.
- **SC-002**: Em teste de compras concorrentes disputando as últimas vagas/lote/
  estoque, o limite **nunca** é ultrapassado (0 casos de venda além do limite) e
  os perdedores recebem recusa clara.
- **SC-003**: 100% dos ingressos carregam o preço efetivo do momento da compra;
  mudanças posteriores de catálogo não alteram nenhum pedido existente.
- **SC-004**: Cortesias automáticas e por voucher respeitam regra e limite por
  conta em 100% dos cenários de teste; nenhum voucher é resgatado duas vezes.
- **SC-005**: Pedidos vencidos expiram na primeira execução da rotina após o
  prazo, e as vagas voltam a ficar compráveis imediatamente.
- **SC-006**: 100% das tentativas de acesso a pedidos/ingressos de terceiros são
  recusadas; o comprovante PDF abre com QR legível para ingressos confirmados.
- **SC-007**: A suíte cobre as 5 user stories (incluindo o teste de concorrência)
  e passa integralmente; as suítes anteriores permanecem verdes.

## Assumptions

- Herança das specs 001–003: evento publicado com catálogo/landing (003), sessão
  do inscrito (002), derivações e snapshots (001) — nada é redefinido.
- **Pagamento real é a spec 005**: aqui o pedido permanece "aguardando pagamento"
  até expirar; a tela de checkout mostra o aviso "pagamento disponível em breve"
  no dev. Cortesias (valor zero) nascem confirmadas — por isso o comprovante PDF
  já é exercitável nesta spec.
- "Quem compra não dá a própria baixa" (constituição, III) é regra da spec 005 —
  nesta spec ninguém dá baixa.
- Carrinho é estado do navegador (persistido localmente até a confirmação); o
  servidor só conhece o pedido criado.
- Preço vale o do momento da **confirmação** (não o da montagem do carrinho),
  com aviso na tela se mudou — decisão de edge case.
- Limite padrão de 20 ingressos por pedido (FR-017) — ajustável por configuração
  técnica; o admin não gerencia isso por tela no MVP.
- O QR contém o código público do ingresso; a validação na portaria é spec 007.
- E-mails transacionais (confirmação de pedido) chegam na spec 005 junto do
  pagamento; nesta spec a confirmação é visual (tela + área do inscrito).
