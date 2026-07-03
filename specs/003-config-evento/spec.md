# Feature Specification: Configuração do Evento (Admin)

**Feature Branch**: `003-config-evento`

**Created**: 2026-07-03

**Status**: Implemented

**Input**: User description: "003-config-evento — painel administrativo do evento: dados e publicação do evento (com banner e cancelamento), tipos de ingresso, lotes de venda, camisas com estoque, regra de cortesia e vouchers, patrocínios com parcelas, e editor da página pública por blocos. Telas admin correspondentes, acessíveis apenas ao papel de administração."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configurar e publicar o evento (Priority: P1)

O administrador acessa o painel (restrito ao papel de administração), preenche os
dados do evento — nome, descrição, tipo, datas, local, capacidade, janela de vendas,
banner, regras de participação e as opções de comportamento (formas de pagamento
aceitas, escolha de camisa, transferência, cancelamento pelo inscrito, cortesia) —
e o publica quando estiver pronto. Pode também cancelar o evento, informando o
motivo, com todo o histórico preservado.

**Why this priority**: sem evento configurado e publicado não existe venda (spec
004); é a peça central de que tudo depende.

**Independent Test**: logar como admin, editar os dados do evento, publicar e
verificar que a situação mudou; cancelar e verificar que registro/motivo/autor
ficam gravados e que nada é apagado.

**Acceptance Scenarios**:

1. **Given** um usuário com papel de administração, **When** acessa o painel do
   evento, **Then** vê e edita todos os dados de configuração; um usuário sem o
   papel recebe recusa de acesso.
2. **Given** um evento em rascunho com dados mínimos completos (nome, data de
   início, tipo, ao menos um tipo de ingresso ativo), **When** o admin publica,
   **Then** a situação passa a "publicado" e fica registrado quem publicou.
3. **Given** um evento sem os dados mínimos (ex.: nenhum tipo de ingresso ativo),
   **When** o admin tenta publicar, **Then** a publicação é recusada com a lista
   do que falta.
4. **Given** um evento publicado, **When** o admin cancela informando o motivo,
   **Then** a situação passa a "cancelado", com autor/momento/motivo registrados,
   e nenhum dado é apagado.
5. **Given** um evento cancelado ou encerrado, **When** o admin tenta alterar a
   configuração ou republicar, **Then** a alteração é recusada como conflito de
   regra de negócio.
6. **Given** uma imagem de banner válida, **When** o admin envia, **Then** ela
   substitui a anterior; formatos inválidos ou arquivos grandes demais são
   recusados com mensagem clara.

---

### User Story 2 - Tipos de ingresso e lotes (Priority: P2)

O administrador cadastra os tipos de ingresso (individual, casal, cortesia, etc.)
com preço, capacidade e características (assentos por ingresso, inclui camisa/kit,
público-alvo), e organiza os lotes de venda — janelas de data, quantidade e preço
promocional — vendo claramente qual lote está vigente e qual preço efetivo o
comprador pagará.

**Why this priority**: é o catálogo que a venda (spec 004) consome; sem tipos e
lotes o evento publicado não tem o que vender.

**Independent Test**: criar tipos e lotes, conferir vigência e preço efetivo
calculados; tentar excluir um tipo com ingressos vendidos e ver a recusa.

**Acceptance Scenarios**:

1. **Given** o evento configurado, **When** o admin cria um tipo de ingresso com
   preço e características, **Then** ele aparece na lista, ordenável e podendo ser
   ativado/desativado.
2. **Given** tipos cadastrados, **When** o admin cria lotes com janela, quantidade
   e preço promocional (geral ou por tipo), **Then** a tela indica o lote vigente
   e o preço efetivo resultante de cada tipo.
3. **Given** um tipo de ingresso com vendas registradas, **When** o admin tenta
   excluí-lo, **Then** a exclusão é recusada como conflito (desativar continua
   possível).
4. **Given** um lote com vendas registradas, **When** o admin tenta excluí-lo,
   **Then** idem — recusa com orientação a desativar.
5. **Given** um tipo com capacidade definida, **When** o admin tenta reduzir a
   capacidade para menos do que o já vendido, **Then** a alteração é recusada.
6. **Given** preço, **When** informado com valor negativo ou malformado, **Then**
   erro de validação campo a campo.

---

### User Story 3 - Camisas com estoque (Priority: P3)

O administrador cadastra os modelos de camisa do evento (ex.: unissex, baby look)
e seus tamanhos, com estoque por tamanho (ou ilimitado), acompanhando o vendido e
o esgotamento de cada um.

**Why this priority**: alimenta a escolha de camisa na compra (spec 004); depende
apenas do evento existir.

**Independent Test**: criar modelos e tamanhos com e sem estoque, ver contadores
de vendido/esgotado; tentar definir estoque abaixo do já vendido e ver a recusa.

**Acceptance Scenarios**:

1. **Given** o evento, **When** o admin cria modelos e tamanhos com estoque
   definido ou ilimitado, **Then** aparecem organizados por modelo, com vendido e
   situação (disponível/esgotado) visíveis.
2. **Given** um tamanho com unidades vendidas, **When** o admin tenta definir
   estoque menor que o vendido, **Then** a alteração é recusada.
3. **Given** um tamanho com vendas, **When** o admin tenta excluí-lo, **Then**
   recusa como conflito; desativar continua possível.

---

### User Story 4 - Editor da página pública por blocos (Priority: P4)

O administrador monta a página pública do evento combinando blocos — capa (hero),
texto, programação, palestrantes, perguntas frequentes, local e chamada para
inscrição — podendo reordenar, ativar/desativar e editar o conteúdo de cada bloco
sem depender de ninguém técnico. A renderização pública acontece na spec 004.

**Why this priority**: é a vitrine do produto, mas a venda funciona mesmo com uma
landing mínima — por isso vem após o catálogo.

**Independent Test**: criar/editar/reordenar/desativar blocos de cada tipo e
verificar a persistência de conteúdo e ordem.

**Acceptance Scenarios**:

1. **Given** o evento, **When** o admin adiciona blocos de qualquer um dos tipos
   suportados com seu conteúdo, **Then** eles são salvos com a ordem definida.
2. **Given** blocos existentes, **When** o admin reordena ou desativa um bloco,
   **Then** a nova ordem/visibilidade é persistida.
3. **Given** um bloco com conteúdo obrigatório faltando (ex.: capa sem título),
   **When** salvo, **Then** erro de validação indicando o campo.

---

### User Story 5 - Cortesias: regra e vouchers (Priority: P5)

O administrador define a regra de cortesia do evento (a cada X ingressos pagos, Y
cortesias, com limite por conta) e gerencia vouchers de cortesia: gera códigos em
lote, marca a distribuição (para quem/quando) e acompanha a situação de cada um
(disponível, distribuído, resgatado). O resgate pelo comprador acontece na spec 004.

**Why this priority**: recurso de negócio importante, mas o evento vende sem ele.

**Independent Test**: configurar a regra, gerar vouchers, distribuir alguns e
conferir a listagem por situação; verificar que códigos são únicos e não
sequenciais.

**Acceptance Scenarios**:

1. **Given** o evento com cortesia habilitada, **When** o admin define a regra
   X→Y e o limite por conta, **Then** os valores ficam salvos e visíveis.
2. **Given** o painel de cortesias, **When** o admin gera N vouchers, **Then** N
   códigos únicos e não sequenciais são criados na situação "disponível".
3. **Given** um voucher disponível, **When** o admin o marca como distribuído
   (com anotação de destinatário), **Then** a situação avança e ficam registrados
   autor e momento.
4. **Given** um voucher resgatado, **When** o admin tenta voltá-lo para
   disponível, **Then** a mudança é recusada (o ciclo só avança).

---

### User Story 6 - Patrocínios e parcelas (Priority: P6)

O administrador registra os patrocínios do evento (empresa, contato, valor total,
forma de pagamento, número de parcelas), e dá baixa nas parcelas conforme os
pagamentos acontecem — com valor, data e quem registrou — acompanhando a situação
geral (pendente, parcial, pago).

**Why this priority**: gestão financeira complementar; não bloqueia a venda de
ingressos.

**Independent Test**: criar patrocínio parcelado, dar baixa em parcelas e ver a
situação geral evoluir de pendente → parcial → pago, com trilha de quem registrou.

**Acceptance Scenarios**:

1. **Given** o painel de patrocínios, **When** o admin cria um patrocínio com
   valor total e N parcelas, **Then** as parcelas são geradas numeradas com seus
   valores.
2. **Given** parcelas pendentes, **When** o admin registra o pagamento de uma
   (valor/data/forma), **Then** a parcela fica paga com autor registrado e a
   situação geral do patrocínio é recalculada (parcial ou pago).
3. **Given** uma parcela já paga, **When** o admin tenta registrar pagamento de
   novo, **Then** a operação é recusada como conflito.
4. **Given** um patrocínio cancelado, **When** consultado, **Then** o registro e
   as parcelas permanecem visíveis no histórico.

---

### Edge Cases

- Evento único do MVP: o painel gerencia o evento existente; não há criação de
  segundo evento nesta fase (estrutura multi-evento fica pronta, tela não).
- Publicar com janela de vendas já encerrada: permitido com aviso (o evento fica
  publicado, mas "inscrições abertas" continuará negativo — estado derivado).
- Troca de banner: a imagem anterior deixa de ser usada, sem quebrar a página.
- Lotes com janelas sobrepostas: aceito; a vigência segue a ordem definida
  (regra determinística da fundação) e a tela mostra qual vale.
- Bloco de landing duplicado do mesmo tipo (ex.: dois blocos de texto): permitido;
  a ordem define a apresentação.
- Voucher gerado em excesso: pode ser mantido "disponível" indefinidamente; não há
  expiração de voucher nesta fase.
- Duas pessoas admin editando ao mesmo tempo: vale a última gravação; sem trava
  otimista nesta fase (registrado como limitação consciente).
- Valores monetários: sempre duas casas decimais; entradas com vírgula são aceitas
  nas telas e normalizadas.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Todas as áreas desta spec MUST ser acessíveis apenas a usuários com
  papel de administração; sem o papel → recusa de permissão.
- **FR-002**: O admin MUST poder editar os dados do evento: nome, descrição, tipo,
  datas de início/fim, local (com link de mapa), capacidade total, janela de
  vendas, tempo de reserva, regras de participação, notas internas, modo de
  precificação e as opções de comportamento (formas de pagamento, camisa
  obrigatória/opcional, kit, transferência, cancelamento pelo inscrito, pedido de
  reembolso, cortesia).
- **FR-003**: O admin MUST poder enviar/substituir o banner do evento; apenas
  imagens (formatos comuns) até um limite de tamanho, com erro claro fora disso.
- **FR-004**: Publicar MUST exigir dados mínimos — nome, data de início, tipo de
  evento e ao menos um tipo de ingresso ativo — e responder com a lista do que
  falta quando recusado.
- **FR-005**: Cancelar o evento MUST exigir motivo e registrar autor e momento;
  eventos cancelados/encerrados MUST rejeitar alterações e republicação como
  conflito de regra de negócio.
- **FR-006**: O admin MUST poder gerenciar os tipos de evento da lista de domínio
  (criar, renomear, ativar/desativar), sem excluir fisicamente os em uso.
- **FR-007**: O admin MUST poder criar/editar/ordenar/ativar tipos de ingresso com
  nome, preço (≥ 0, duas casas), capacidade opcional, assentos por ingresso,
  casal, inclui camisa/kit, cortesia e público-alvo.
- **FR-008**: Tipos de ingresso e lotes com vendas registradas MUST ser protegidos
  contra exclusão (recusa como conflito); desativação MUST permanecer possível.
- **FR-009**: Capacidade (do evento ou de tipo) MUST não poder ser reduzida abaixo
  do já vendido.
- **FR-010**: O admin MUST poder criar/editar/ordenar lotes — nome, janela de
  datas, quantidade, preço promocional, escopo (evento todo ou um tipo) — e a
  interface MUST indicar o lote vigente e o preço efetivo por tipo.
- **FR-011**: O admin MUST poder gerenciar modelos e tamanhos de camisa com
  estoque por tamanho (ou ilimitado); a interface MUST mostrar vendido e
  esgotamento; estoque MUST não poder ficar abaixo do vendido; tamanhos com
  vendas não podem ser excluídos.
- **FR-012**: O admin MUST poder montar a página pública com blocos dos tipos
  suportados (capa, texto, programação, palestrantes, FAQ, local, chamada),
  com conteúdo validado por tipo, reordenação e ativação/desativação.
- **FR-013**: O admin MUST poder definir a regra de cortesia (a cada X pagos → Y
  cortesias; limite por conta) quando a cortesia estiver habilitada no evento.
- **FR-014**: O admin MUST poder gerar vouchers de cortesia em lote (códigos
  únicos, não sequenciais), marcar distribuição com anotação de destinatário e
  listar por situação; o ciclo do voucher MUST só avançar (disponível →
  distribuído → resgatado).
- **FR-015**: O admin MUST poder registrar patrocínios (empresa, contato, valor
  total, forma, parcelas) com parcelas numeradas geradas; dar baixa em parcela
  MUST registrar valor/data/forma/autor e recalcular a situação geral (pendente/
  parcial/pago); parcela paga MUST rejeitar nova baixa.
- **FR-016**: Toda ação administrativa desta spec MUST manter a trilha da
  fundação (quem criou/alterou; nada é apagado fisicamente).
- **FR-017**: As telas MUST apresentar erros de validação campo a campo e
  mensagens em pt-BR, seguindo o padrão de erros da plataforma.
- **FR-018**: As listas do painel (tipos, lotes, tamanhos, vouchers, patrocínios)
  MUST refletir os estados derivados da fundação (vigente, esgotado, disponível) —
  nunca campos editáveis equivalentes.

### Key Entities

Todas já existem na fundação (spec 001) — esta spec cria os fluxos de gestão:

- **Evento**: alvo central da configuração; ganha publicação, cancelamento e banner.
- **Tipo de evento**: lista de domínio gerenciável (seminário, congresso, …).
- **Tipo de ingresso / Lote**: catálogo de venda com vigência e preço efetivo
  derivados.
- **Modelo / Tamanho de camisa**: hierarquia com estoque e vendido por tamanho.
- **Bloco de landing**: seção da página pública com tipo, ordem, visibilidade e
  conteúdo próprio.
- **Voucher de cortesia**: código único com ciclo disponível → distribuído →
  resgatado.
- **Patrocínio / Parcela**: apoio financeiro com baixa por parcela e situação
  geral derivada.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um administrador configura um evento completo do zero (dados +
  banner + 3 tipos + 2 lotes + camisas + landing com os 7 blocos) e o publica em
  menos de 30 minutos, sem ajuda técnica.
- **SC-002**: 100% das tentativas de acesso ao painel sem papel de administração
  são recusadas.
- **SC-003**: 100% das operações destrutivas indevidas (excluir tipo/lote/tamanho
  com vendas, republicar cancelado, reduzir capacidade/estoque abaixo do vendido,
  baixar parcela paga, retroceder voucher) são recusadas como conflito, com
  mensagem clara.
- **SC-004**: O lote vigente e o preço efetivo mostrados no painel coincidem com a
  regra da fundação em 100% dos cenários de teste (virada por data, quantidade e
  ordem).
- **SC-005**: Vouchers gerados têm códigos únicos e não sequenciais em 100% dos
  casos; a listagem por situação reflete o ciclo corretamente.
- **SC-006**: A suíte de testes cobre os cenários das 6 user stories e passa
  integralmente; as suítes anteriores (fundação e autenticação) permanecem verdes.
- **SC-007**: Todas as ações administrativas ficam com trilha de autor/momento
  verificável em 100% dos casos amostrados.

## Assumptions

- Base técnica herdada: RBAC e middleware de papel (001), sessão/login (002),
  envelope de API e shape de erros (001) — nada é redefinido.
- Single-event: o painel gerencia o evento existente (o do seed, no dev); telas de
  criação/listagem de múltiplos eventos ficam para a Fase 2 do produto.
- O painel administrativo usa o tema Tabler (`template/`) como referência visual;
  fidelidade pixel-perfect não é critério — clareza e consistência são.
- A renderização pública da landing e o resgate de voucher pelo comprador são da
  spec 004; aqui só a gestão.
- A baixa de parcelas de patrocínio é gestão administrativa direta (dinheiro fora
  do gateway); o ponto único de baixa de pagamentos de pedidos (constituição, III)
  não se aplica a patrocínio e chega na spec 005.
- Sem trava otimista de edição concorrente nesta fase (última gravação vale) —
  registrado como limitação consciente.
- Banner: formatos JPEG/PNG/WebP até 5 MB (padrão da indústria); armazenamento
  local no dev.
- Sem edição de e-mails transacionais, relatórios ou dashboard aqui (spec 008).
