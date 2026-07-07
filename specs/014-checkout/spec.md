# Feature Specification: Checkout do Seminário Internacional (multi-inscrição, guest, voucher por participante)

**Feature Branch**: `014-checkout`

**Created**: 2026-07-07

**Status**: Draft

**Input**: Fluxo de checkout iniciado pelo botão "Inscreva-se" no site do evento, permitindo inscrever um ou mais participantes no mesmo carrinho, com dados por participante conforme a categoria, aplicação de voucher de gratuidade por inscrição, revisão do carrinho e finalização com pagamento ou confirmação gratuita.

## Visão geral

O checkout permite que um visitante (sem login) inscreva **um ou mais participantes** no evento num único carrinho, informando os dados de cada um conforme a **categoria de participante** escolhida, aplique **voucher de gratuidade por inscrição**, revise o carrinho e finalize — **pagando** (quando há valor) ou **confirmando gratuidade** (quando o total é zero). Cada inscrição vira um ingresso individual vinculado ao evento, ao pedido e ao participante; os ingressos são enviados por e-mail e o comprador ganha acesso a um back-office com todos os ingressos, além de cada participante acessar o seu.

**Extensão, não redefinição (constituição VI)**: esta spec **estende** o fluxo de compra já existente (pedido→ingressos com snapshot, gateways de pagamento, vouchers de cortesia, área do inscrito das specs 002/004/006/011) adicionando: checkout **guest** (sem login), **categorias de participante com campos configuráveis**, **voucher aplicado por participante dentro de um pedido misto**, tela de **revisão do carrinho** e **entrega de ingresso por participante**. Nada do fluxo existente é reescrito.

**Standalone (constituição I)**: as categorias, os campos por categoria (ex.: "Loja", "Potência", "País", "Cidade", "Cargo") e a **lista de afiliações** ("lojas") são **configuração/dados**, não conceitos de domínio no código. O código trabalha com "categorias de participante" e "campos de inscrição" genéricos; os rótulos maçônicos (GLMEES, Loja, Potência, Cargo) vivem como configuração/conteúdo. Nenhuma entidade de Grande Loja/loja/irmão entra no código.

## Clarifications

### Session 2026-07-07

- Q: Cada inscrição usa um valor único do seminário ou o participante escolhe entre vários tipos de ingresso? → A: O participante **escolhe o tipo de ingresso** por inscrição (o valor vem do tipo/lote vigente).
- Q: Como cada participante acessa "o seu" ingresso? → A: Cada participante tem **conta própria** (acesso passwordless por link) vinculada ao seu e-mail, com área própria vendo apenas o seu ingresso.
- Q: Como o comprador guest ganha acesso ao back-office após finalizar? → A: Por **link mágico passwordless** (e-mail com link de acesso, sem senha).
- Q: Quais códigos de voucher podem ser aplicados no checkout? → A: **Qualquer código ativo/elegível do evento** (disponível ou distribuído), desde que válido/elegível e não usado.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Inscrever participantes e pagar (guest) (Priority: P1)

Um visitante clica em **Inscreva-se** no site do evento e vai ao checkout. Escolhe a **categoria** do primeiro participante, preenche os campos daquela categoria, adiciona ao carrinho e pode **adicionar outros participantes**. Informa o **e-mail** de cada participante e o e-mail do comprador. Numa **tela de revisão**, confere as inscrições (podendo remover), clica **Pagar agora** e conclui o pagamento. Os ingressos são emitidos, cada participante recebe o seu por e-mail e o comprador ganha acesso ao back-office com todos os ingressos.

**Why this priority**: É o caminho feliz principal (inscrição paga multi-participante como guest) — sem ele não há produto. Entrega valor completo sozinho.

**Independent Test**: Sem estar logado, abrir o checkout, adicionar 2 participantes de categorias diferentes, revisar, pagar com o gateway de teste e confirmar: 2 ingressos "pagos", e-mails enviados (comprador + participantes) e acesso ao back-office do comprador.

**Acceptance Scenarios**:

1. **Given** um visitante não logado no checkout do evento, **When** escolhe uma categoria, o **tipo de ingresso** e preenche os campos obrigatórios, **Then** o participante entra no carrinho como uma inscrição com o **valor do tipo/lote vigente**.
2. **Given** um carrinho com 1 participante, **When** clica "Adicionar outro irmão" e cadastra um segundo (categoria e campos próprios), **Then** o **resumo financeiro** recalcula automaticamente (subtotal = soma das inscrições).
3. **Given** participantes no carrinho, **When** vai para a **tela de revisão**, **Then** vê um card por participante (nome, categoria, afiliação, valor, voucher se houver) com ação de **remover** (como retirar do carrinho), e o total recalcula ao remover.
4. **Given** total > R$ 0,00 e e-mails informados, **When** clica **Pagar agora** e o pagamento é aprovado, **Then** cada inscrição fica **paga**, o pedido fica **pago integralmente**, e cada participante recebe seu ingresso por e-mail.
5. **Given** o pagamento foi aprovado, **When** o comprador acessa o back-office (pelo link/acesso recebido), **Then** vê **todos** os ingressos do pedido; cada participante, pelo seu acesso, vê **apenas o seu**.
6. **Given** um campo obrigatório da categoria não preenchido, **When** tenta adicionar ao carrinho, **Then** o sistema recusa com mensagem clara e não adiciona.
7. **Given** o pagamento falha ou é abandonado, **When** o fluxo termina sem aprovação, **Then** o pedido permanece **aguardando pagamento** (pré-inscrição pendente) e nenhuma inscrição é confirmada.

---

### User Story 2 - Voucher de gratuidade por participante (Priority: P2)

No carrinho com vários participantes, o comprador aplica um **voucher de gratuidade** a uma inscrição específica. Aquela inscrição passa a **R$ 0,00**; as demais seguem com valor. O total a pagar reflete apenas as inscrições não isentas, num **único pedido misto**.

**Why this priority**: Diferencial central pedido — voucher por participante (não no carrinho inteiro), gerando pedido misto pago+gratuito. Depende de US1.

**Independent Test**: Carrinho com 3 participantes; aplicar voucher válido no 2º; conferir que o 2º fica R$ 0,00, total = 2 × valor, e após pagar o 2º fica "gratuito por voucher" e os outros "pagos" no mesmo pedido.

**Acceptance Scenarios**:

1. **Given** uma inscrição no carrinho, **When** aplica um voucher **válido** para ela, **Then** exibe "Voucher aplicado com sucesso. Esta inscrição foi isenta de pagamento." e o valor daquela inscrição vira **R$ 0,00**.
2. **Given** um voucher **inválido/expirado/já utilizado/não elegível** à categoria/tipo, **When** aplica, **Then** exibe "Voucher inválido, expirado ou já utilizado. Verifique o código informado." e o valor da inscrição **permanece**.
3. **Given** um voucher aplicado a uma inscrição, **When** o comprador **remove o voucher**, **Then** a inscrição volta ao valor normal e o total recalcula.
4. **Given** um pedido misto (algumas pagas, uma gratuita) após pagamento aprovado, **Then** as pagas ficam **pagas** e a isenta fica **gratuita por voucher**; o pedido fica **pago parcialmente por voucher**/**pago integralmente** conforme o caso.
5. **Given** um voucher de uso único já vinculado a uma inscrição do carrinho, **When** tenta aplicá-lo a outra inscrição, **Then** o sistema recusa (mesmo voucher não serve duas inscrições, salvo vouchers com múltiplos usos).
6. **Given** um voucher aplicado no carrinho e a inscrição é **removida**, **Then** o voucher é liberado (volta a poder ser aplicado).

---

### User Story 3 - Checkout 100% gratuito (Priority: P3)

Quando **todos** os participantes têm voucher aplicado (ou gratuidade autorizada), o total é **R$ 0,00**. O sistema **não** leva ao pagamento: finaliza direto e confirma as inscrições como **gratuitas por voucher**.

**Why this priority**: Fecha o caso de borda financeiro (total zero) sem passar por gateway. Depende de US2.

**Independent Test**: Carrinho com 2 participantes, ambos com voucher válido; total = R$ 0,00; botão vira "Confirmar inscrição gratuita"; ao confirmar, 2 ingressos "gratuitos por voucher" emitidos, sem etapa de pagamento.

**Acceptance Scenarios**:

1. **Given** todas as inscrições do carrinho com voucher válido (total = R$ 0,00), **When** revisa, **Then** o botão de finalização é **"Confirmar inscrição gratuita"** (não "Pagar agora").
2. **Given** total = R$ 0,00, **When** confirma, **Then** o sistema finaliza **sem** ir ao pagamento e marca todos como **gratuito por voucher**; o pedido fica **gratuito**.
3. **Given** um dos vouchers ficou inválido no momento da confirmação, **When** confirma, **Then** o sistema recusa a finalização gratuita daquela inscrição e orienta a corrigir (sem confirmar inscrições indevidas).

---

### User Story 4 - Categorias de participante e campos configuráveis (Priority: P4)

Um administrador configura, por evento, as **categorias de participante** (ex.: "Irmão da GLMEES" e "Irmão de outra potência") e os **campos** de cada uma (incluindo campos condicionais como "Possui cargo?" → "Cargo"), além de uma **lista de afiliações** ("lojas") usada no campo de seleção/autocomplete. O checkout renderiza os campos conforme a categoria escolhida.

**Why this priority**: Sustenta o standalone (rótulos maçônicos como config) e alimenta US1. É configuração de bastidor; o checkout pode nascer com a config padrão do seminário.

**Independent Test**: Configurar duas categorias com campos distintos e uma lista de afiliações; no checkout, ao escolher cada categoria, ver o formulário mudar dinamicamente e o select de afiliação carregar a lista cadastrada.

**Acceptance Scenarios**:

1. **Given** duas categorias configuradas, **When** o participante escolhe uma, **Then** o formulário exibe **os campos daquela categoria** (ex.: GLMEES → Loja/Nome/Cargo?; outra potência → Potência/País/Cidade/Nome/Cargo?).
2. **Given** um campo condicional "Possui cargo?", **When** marca "Sim", **Then** o campo "Cargo" aparece e é capturado; marcando "Não", não aparece.
3. **Given** um campo de **afiliação** (lista), **When** o participante digita, **Then** o sistema sugere itens da **lista cadastrada** (select/autocomplete).
4. **Given** os dados do participante, **When** a inscrição é criada, **Then** os valores dos campos ficam **snapshotados** na inscrição (não mudam se a config/lista mudar depois).
5. **Given** categorias/campos, **When** aparecem nas telas, **Then** os **rótulos** são os configurados (pt-BR), sem que o código dependa de conceitos maçônicos.

---

### User Story 5 - Acesso pós-compra (comprador e participantes) (Priority: P5)

Após a finalização, o **comprador** obtém acesso a um back-office com **todos** os ingressos do pedido; **cada participante** recebe seu ingresso por e-mail e acessa **apenas o seu**. É possível reenviar/baixar o ingresso.

**Why this priority**: Entrega o pós-compra pedido ("disparar o ingresso para o e-mail e dar acesso ao back-office"). Depende de US1.

**Independent Test**: Após um pedido pago com 3 participantes (e-mails distintos), confirmar: comprador vê 3 ingressos; cada participante, pelo seu acesso, vê 1; reenvio de ingresso funciona.

**Acceptance Scenarios**:

1. **Given** um pedido finalizado por guest, **When** conclui, **Then** o sistema **cria/vincula** uma conta de comprador ao e-mail informado e envia um **acesso** ao back-office (sem exigir cadastro prévio).
2. **Given** vários participantes com e-mail, **When** o pedido é confirmado, **Then** cada participante recebe **seu** ingresso (com QR/código) no e-mail informado.
3. **Given** o comprador no back-office, **When** abre o pedido, **Then** vê **todos** os ingressos; **Given** um participante no seu acesso, **Then** vê **apenas o seu**.
4. **Given** um ingresso emitido, **When** o comprador/participante solicita **reenvio**, **Then** o ingresso é reenviado ao e-mail correspondente.
5. **Given** o mesmo e-mail já é de uma conta existente, **When** finaliza, **Then** o pedido é **vinculado** à conta existente (sem duplicar conta).

---

### Edge Cases

- **Voucher**: inexistente, inativo, de outro evento, já utilizado, fora da validade, ou não elegível ao tipo/categoria → recusa com a mensagem padrão; a inscrição mantém o valor.
- **Mesmo voucher em duas inscrições**: recusado, salvo vouchers com múltiplos usos (respeita a quantidade de usos).
- **Remover participante**: recalcula o total; libera o voucher que estava naquela inscrição; carrinho vazio desabilita a finalização.
- **Recalculo em tempo real**: adicionar, remover ou aplicar/remover voucher recalcula subtotal, descontos e total imediatamente.
- **Abandono do checkout**: os dados podem ficar como **pré-inscrição pendente** (pedido aguardando pagamento) e expiram conforme a política de reserva do evento; vagas/lote não ficam presos além do prazo.
- **Pagamento falho**: pedido permanece **aguardando pagamento**; o comprador pode retomar.
- **Concorrência**: aplicação de voucher, contagem de vaga/lote e criação das inscrições acontecem com recontagem transacional (dois pedidos não consomem a mesma vaga/voucher/lote).
- **E-mail obrigatório para múltiplos participantes**: quando há mais de um participante, o e-mail de cada participante é **obrigatório** (para entrega do ingresso).
- **E-mail do comprador**: obrigatório para gerar o acesso ao back-office.
- **Categoria sem campos configurados**: usa um conjunto mínimo (ao menos nome + e-mail) e não quebra o checkout.
- **Total zero por gratuidade autorizada** (sem voucher, ex.: cortesia automática): finaliza como gratuito, sem pagamento.
- **Esgotado / fora da janela de vendas**: o checkout recusa novas inscrições quando o evento está esgotado ou fora do período de vendas.

## Requirements *(mandatory)*

### Functional Requirements

**Início e carrinho (US1)**

- **FR-001**: O checkout DEVE iniciar ao clicar em **Inscreva-se** no site do evento, sem exigir login (**guest**).
- **FR-002**: O checkout DEVE permitir adicionar **N participantes** ao mesmo carrinho; cada participante é **uma inscrição individual**. O participante **escolhe o tipo de ingresso** da inscrição e o valor é o do **tipo/lote vigente** (pode variar por tipo). Quando o evento tem um único tipo, ele é pré-selecionado.
- **FR-003**: Para cada participante, o checkout DEVE primeiro pedir a **categoria** e então exibir **os campos daquela categoria** (formulário dinâmico), incluindo campos condicionais (ex.: "Possui cargo?" → "Cargo").
- **FR-004**: O botão **"Adicionar outro irmão"** (rótulo configurável) DEVE reabrir o formulário de categoria/campos e, ao concluir, **recalcular** o resumo financeiro.
- **FR-005**: Cada inscrição no carrinho DEVE permitir **editar dados**, **remover**, **aplicar/remover voucher** e **ver o valor individual**.
- **FR-006**: Antes de finalizar, o checkout DEVE apresentar uma **tela de revisão** (cards por participante) com ação de **remover** por inscrição e o **total** recalculado.

**Resumo financeiro (US1/US2)**

- **FR-007**: O checkout DEVE exibir um **resumo em tempo real**: quantidade de inscrições, valor unitário, subtotal, descontos por voucher e **total a pagar**, recalculado a cada adição/remoção/voucher.

**Voucher por participante (US2/US3)**

- **FR-008**: O voucher DEVE ser aplicado **por inscrição** (não ao carrinho inteiro); voucher válido zera o valor **daquela** inscrição (R$ 0,00), num **pedido misto**.
- **FR-009**: O sistema DEVE **validar** o voucher: existe, **ativo/elegível** (código **disponível ou distribuído** do evento), pertence a este evento, não utilizado, dentro da validade (se houver) e elegível ao tipo/categoria; exibindo a mensagem de sucesso ou de erro padrão.
- **FR-010**: O **mesmo voucher** NÃO DEVE ser usado em mais de uma inscrição, salvo vouchers configurados com **múltiplos usos** (respeitando a quantidade).
- **FR-011**: O voucher e a contagem de vaga/lote DEVEM ser resolvidos com **recontagem transacional** ao finalizar (proteção contra corrida).

**Finalização (US1/US3)**

- **FR-012**: Se o total **> R$ 0,00**, a finalização DEVE seguir para o **pagamento** (reutilizando o fluxo/gateway existente); ingressos pagos ficam **pagos** após aprovação.
- **FR-013**: Se o total **= R$ 0,00** (todos com voucher/gratuidade), a finalização DEVE **não** ir ao pagamento e confirmar as inscrições como **gratuito por voucher**; o botão passa a "Confirmar inscrição gratuita".
- **FR-014**: Se o pagamento **falhar/for abandonado**, o pedido DEVE permanecer **aguardando pagamento** (pré-inscrição), sem confirmar inscrições.

**Status (US1/US2)**

- **FR-015**: Cada inscrição DEVE ter status próprio: **aguardando pagamento**, **pago**, **gratuito por voucher**, **cancelado**.
- **FR-016**: O pedido DEVE ter status consolidado: **aguardando pagamento**, **pago parcialmente por voucher**, **pago integralmente**, **gratuito**, **cancelado**.

**Dados, e-mails e acesso (US1/US5)**

- **FR-017**: O checkout DEVE **salvar os dados dos participantes** já na criação do pedido (status aguardando pagamento), antes do pagamento.
- **FR-018**: O e-mail do **comprador** é obrigatório; quando há **mais de um participante**, o e-mail **de cada participante** é obrigatório (para entrega do ingresso).
- **FR-019**: Após a confirmação, o sistema DEVE **enviar o ingresso** (com QR/código) ao e-mail de cada participante.
- **FR-020**: Após a finalização por guest, o sistema DEVE **criar/vincular** uma conta ao e-mail do comprador e conceder acesso ao back-office por **link mágico passwordless** (e-mail com link, sem senha; sem exigir cadastro prévio); e-mail já existente é **vinculado** (sem duplicar).
- **FR-021**: Cada **participante** com e-mail DEVE ter **conta própria** (acesso passwordless por link) vinculada ao seu e-mail, acessando **apenas o seu** ingresso; o **comprador** acessa **todos** os ingressos do pedido; ingressos podem ser **reenviados**. E-mail de participante já existente é **vinculado** (sem duplicar conta).

**Configuração de categorias/campos (US4)**

- **FR-022**: O administrador DEVE poder configurar, por evento, as **categorias de participante** e os **campos** de cada uma (incluindo condicionais e um campo do tipo **lista de afiliações**), com **rótulos** em pt-BR.
- **FR-023**: O sistema DEVE oferecer uma **lista de afiliações** ("lojas") gerenciável, usada como fonte do campo de seleção/autocomplete no checkout.
- **FR-024**: Os valores dos campos preenchidos DEVEM ser **snapshotados** na inscrição no momento da compra (imutáveis a mudanças posteriores de config/lista).
- **FR-025 (standalone)**: O modelo de código DEVE ser genérico ("categorias de participante", "campos de inscrição", "afiliações"); os termos maçônicos (GLMEES, Loja, Potência, Cargo) DEVEM existir apenas como **configuração/dados/rótulos**, sem conceitos maçônicos no código.

**Integração e governança (transversal)**

- **FR-026**: A feature DEVE **reutilizar** o pedido/ingresso com snapshot, os gateways de pagamento, o módulo de **voucher de cortesia** e a área do inscrito já existentes, **sem redefinir** o que outras specs entregaram.
- **FR-027**: Cada inscrição DEVE ficar vinculada a **evento + pedido + participante**; nada é apagado fisicamente (soft delete + histórico), e cada ação registra quem/quando.

### Key Entities *(include if data involved)*

- **Pedido (Order)** *(existente, estendido)*: agrega N inscrições; guarda comprador (nome/e-mail), total (derivado), status consolidado, vínculo com evento; passa a suportar **criação por guest** e **conta criada/vinculada** ao finalizar.
- **Inscrição (Ticket)** *(existente, estendido)*: um ingresso por participante, com **tipo de ingresso escolhido** e **snapshot** de valor (do tipo/lote vigente) e dos **campos da categoria**; status próprio (aguardando/pago/gratuito por voucher/cancelado); e-mail do participante; código/QR público; **conta própria** do participante (passwordless) vinculada ao e-mail.
- **Categoria de participante** *(novo — config por evento)*: define o conjunto de campos exibidos no checkout (com condicionais). Rótulos configuráveis.
- **Campo de inscrição** *(novo — config)*: definição genérica de um campo de uma categoria (tipo: texto, seleção-de-afiliação, país, cidade, condicional "possui X" → campo). Snapshotado por inscrição.
- **Afiliação ("loja")** *(novo — lista gerenciável)*: item de uma lista usada no campo de seleção/autocomplete; rótulo configurável; sem semântica maçônica no código.
- **Voucher de gratuidade** *(existente — cortesia)*: código aplicável **por inscrição**; validado (evento, ativo, validade, uso, elegibilidade); zera o valor da inscrição; uso único (ou múltiplo se configurado).
- **Pagamento** *(existente)*: cobrança do total > 0 via gateway; baixa idempotente; guest paga pelo gateway (não baixa o próprio pedido manualmente).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um visitante **sem login** consegue inscrever 2+ participantes de categorias diferentes, revisar, pagar e receber os ingressos por e-mail, obtendo acesso ao back-office — em uma única sessão.
- **SC-002**: O **resumo financeiro** está sempre correto: em 100% dos testes, subtotal/descontos/total refletem adições, remoções e vouchers imediatamente.
- **SC-003**: Voucher aplicado **por participante** produz **pedido misto** correto (isenta só a inscrição alvo); voucher inválido nunca isenta e sempre mostra a mensagem de erro.
- **SC-004**: Total **R$ 0,00** finaliza **sem** passar por pagamento e marca as inscrições como gratuitas por voucher; total > 0 exige pagamento aprovado para confirmar.
- **SC-005**: Cada participante com e-mail recebe **o seu** ingresso; o comprador vê **todos**; cada participante vê **apenas o seu**; reenvio funciona.
- **SC-006**: Nenhuma inscrição é confirmada sem pagamento aprovado (quando há valor) ou sem voucher válido (quando gratuita); pagamentos nunca são baixados em duplicidade.
- **SC-007**: Os termos maçônicos aparecem apenas como **configuração/rótulos**; o código não introduz conceitos de loja/potência/irmão (verificável na revisão).
- **SC-008**: Nada é apagado fisicamente; abandono vira pré-inscrição pendente que expira conforme a política; todo o histórico é preservado.

## Assumptions

- **Extensão do existente**: reutiliza `Order`/`Ticket` (snapshot, status), `Payment`/gateways (Sicoob/cartão), `CourtesyVoucher` (resgate) e a área do inscrito (specs 002/004/006/011). Ajuste principal: **permitir criação de pedido por guest** e **voucher por item em pedido misto** (hoje o resgate de voucher cria um pedido gratuito separado — passa a poder isentar uma inscrição dentro de um pedido com outras pagas).
- **Guest checkout + conta**: o comprador não precisa estar logado; ao finalizar, cria-se/vincula-se uma conta ao e-mail do comprador e concede-se acesso (link/senha) ao back-office. E-mail já cadastrado é vinculado.
- **Standalone (Princípio I)**: categorias, campos e afiliações são **config/dados**; o código permanece genérico. Os campos maçônicos pré-existentes no `User` (comprador) não são alterados por esta spec; a lista de "lojas" é uma **nova lista gerenciável** (não havia tabela de lojas). Rótulo corrigido: **GLMEES** (não "GLMS").
- **Voucher = cortesia**: usa o módulo de voucher de cortesia existente; "aplicar voucher" resgata um código elegível e transforma a inscrição em gratuita (uso único, salvo múltiplos usos configurados).
- **Preço/lote**: valor da inscrição derivado do tipo/lote vigente (snapshot em `unit_price`); dinheiro em DECIMAL, datas em UTC; UI/mensagens em pt-BR.
- **Segurança/pagamento (Princípios III/IV)**: baixa idempotente via ponto único; PAN/CVV nunca no backend (tokenização no gateway); comprador não dá a própria baixa manual.
- **LGPD**: coleta-se o mínimo necessário (e-mail para entrega do ingresso; documento opcional); dados sensíveis não são logados.
- **Escopo do evento**: aplicado ao Seminário Internacional; o modelo genérico permite reuso por outros eventos. Multi-evento segue o padrão do produto (evento é tabela).
- **Não escopo**: novo gateway/meios de pagamento; reescrita do carrinho/checkout existente; construtor visual de formulários avançado (as categorias/campos seguem um catálogo simples: texto, seleção-de-afiliação, país, cidade, condicional "possui X"); domínio maçônico no código.
