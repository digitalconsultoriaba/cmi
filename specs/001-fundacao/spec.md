# Feature Specification: Fundação da Plataforma de Eventos

**Feature Branch**: `001-fundacao`

**Created**: 2026-07-03

**Status**: Implemented

**Input**: User description: "Fundação da Plataforma de Eventos (spec 001-fundacao): scaffold do projeto, extração do domínio de eventos do módulo 061 sem acoplamentos com a maçonaria, migrations e models de fundação com estado derivado, RBAC com 4 papéis, seeders e tooling de desenvolvimento. Não inclui telas de usuário final, fluxo de compra nem pagamento (specs 002+)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ambiente de desenvolvimento reproduzível (Priority: P1)

Uma pessoa desenvolvedora clona o repositório em uma máquina limpa e, com poucos
comandos padronizados, sobe o ambiente completo (aplicação, banco de dados, fila),
aplica a estrutura de dados do zero e executa a suíte de testes com resultado verde —
sem depender de configuração manual ou de segredos não documentados.

**Why this priority**: todas as demais specs (002–008) dependem deste alicerce; sem
ambiente reproduzível não há como desenvolver, testar ou demonstrar nada.

**Independent Test**: em um checkout limpo, executar os comandos documentados de
subida, migração e teste; o ambiente fica operacional e os testes passam sem
intervenção manual além do previsto no guia.

**Acceptance Scenarios**:

1. **Given** um checkout limpo do repositório e as ferramentas pré-requisito
   instaladas, **When** a pessoa executa o comando padronizado de subida do ambiente,
   **Then** aplicação, banco e demais serviços ficam disponíveis para desenvolvimento.
2. **Given** o ambiente de desenvolvimento no ar com banco vazio, **When** executa o
   comando padronizado de migração, **Then** toda a estrutura de dados é criada do
   zero, sem erros e sem passos manuais.
3. **Given** a estrutura de dados aplicada, **When** executa o comando padronizado de
   testes, **Then** a suíte roda em banco de teste isolado e termina verde.
4. **Given** o repositório público/compartilhado, **When** qualquer arquivo versionado
   é inspecionado, **Then** nenhum segredo real (senha, chave, certificado) está
   presente — apenas placeholders no arquivo de exemplo de configuração.

---

### User Story 2 - Domínio de eventos independente e com histórico (Priority: P2)

O organizador do seminário precisa que a plataforma nasça com o modelo de dados
completo do negócio — evento, tipos de ingresso, lotes, camisas, pedidos, ingressos,
pagamentos, cortesias, patrocínios e suporte — desmembrado do sistema de origem
(módulo 061) e **sem nenhum conceito da organização de origem** (lojas, membros,
donos polimórficos, matriz de módulos), preservando histórico de tudo o que acontece.

**Why this priority**: é o coração do produto; as specs seguintes apenas constroem
fluxos sobre estas entidades. Erros aqui exigem retrabalho em cascata.

**Independent Test**: criar registros de cada entidade via camada de dados (sem telas),
verificar relacionamentos, exclusão reversível e trilha de quem criou/alterou; varrer
o código confirmando ausência dos conceitos proibidos.

**Acceptance Scenarios**:

1. **Given** a estrutura de dados aplicada, **When** se cria um evento com tipos de
   ingresso, lotes, modelos/tamanhos de camisa e blocos de landing, **Then** todos os
   registros se relacionam corretamente conforme o modelo de dados de referência.
2. **Given** qualquer registro de negócio, **When** ele é excluído, **Then** a exclusão
   é reversível (o registro permanece recuperável) e a trilha de quem criou/alterou é
   preservada.
3. **Given** o código-fonte do projeto, **When** se busca por conceitos da organização
   de origem (loja, membro, dono polimórfico, guarda de acesso do 061, matriz de
   módulos, integrações 054/055, limite de vagas por loja), **Then** nenhuma
   ocorrência existe.
4. **Given** um pedido com ingressos, **When** consultado, **Then** cada ingresso
   carrega a fotografia (snapshot) de preço e configuração do momento da compra,
   independente de alterações futuras no catálogo.

---

### User Story 3 - Situação do evento sempre derivada, nunca editada (Priority: P3)

O organizador consulta a situação do evento — inscrições abertas ou fechadas, lote
vigente, vagas disponíveis, esgotamento de ingressos e de camisas — e essa situação é
sempre **calculada** a partir dos dados reais (janelas de venda, contagens, estoques),
nunca um campo editável que possa divergir da realidade.

**Why this priority**: princípio constitucional (II) que previne toda uma classe de
bugs de inconsistência; precisa nascer correto porque as specs 003/004 dependem
dessas derivações.

**Independent Test**: montar cenários de dados (janela de venda aberta/fechada, lote
esgotado, estoque de camisa zerado) e verificar que as respostas derivadas refletem
cada cenário sem qualquer atualização manual de status.

**Acceptance Scenarios**:

1. **Given** um evento publicado com janela de vendas vigente, lote ativo com
   quantidade disponível e vagas livres, **When** se consulta "inscrições abertas",
   **Then** a resposta é positiva.
2. **Given** o mesmo evento com a janela de vendas encerrada OU sem lote vigente OU
   sem vagas, **When** se consulta "inscrições abertas", **Then** a resposta é
   negativa — sem que nenhum campo de status tenha sido editado.
3. **Given** dois lotes configurados com janelas e quantidades distintas, **When** o
   primeiro esgota ou expira, **Then** o lote vigente passa a ser o seguinte e o preço
   efetivo reflete o novo lote.
4. **Given** um tamanho de camisa com estoque definido, **When** a quantidade vendida
   atinge o estoque, **Then** o tamanho consta como esgotado.

---

### User Story 4 - Papéis de acesso e dados de demonstração (Priority: P4)

O organizador conta com quatro papéis de acesso — administrador, tesouraria, portaria
e inscrito — podendo uma mesma pessoa acumular papéis. O ambiente de desenvolvimento
nasce com dados de demonstração (papéis, usuário administrador, listas de domínio e um
evento de exemplo) para que as próximas specs tenham base navegável desde o primeiro dia.

**Why this priority**: destrava o trabalho das specs 002+ (que protegem rotas por
papel) e dá base demonstrável, mas não entrega valor final sozinho.

**Independent Test**: popular o banco com os dados de demonstração e verificar que os
papéis existem, que um usuário pode ter mais de um papel e que rotas de gestão recusam
quem não tem o papel exigido.

**Acceptance Scenarios**:

1. **Given** o banco populado, **When** se consultam os papéis, **Then** existem
   exatamente administrador, tesouraria, portaria e inscrito, e um usuário pode
   acumular mais de um.
2. **Given** uma rota de gestão protegida por papel, **When** um usuário sem o papel
   exigido a acessa, **Then** recebe recusa por falta de permissão (sem vazar detalhes).
3. **Given** o comando de dados de demonstração executado, **When** se inspeciona o
   banco, **Then** existem listas de domínio completas (situações de evento, pedido,
   ingresso e pagamento; tipos de evento), um usuário administrador de desenvolvimento
   e um evento de exemplo com tipos de ingresso, lotes e camisas.

---

### Edge Cases

- Migração executada duas vezes seguidas: a segunda execução não falha nem duplica
  estrutura (rollback/re-run limpos).
- Usuário sem nenhum papel: tratado como sem acesso às áreas de gestão; cadastro pelo
  site receberá o papel inscrito por padrão (fluxo na spec 002).
- Lote sem tipo de ingresso associado: aplica-se ao evento todo; o cálculo de preço
  efetivo usa a sobreposição de preço do lote quando existir, senão o preço do tipo.
- Duas configurações de lote com janelas sobrepostas: o lote vigente é resolvido por
  regra determinística (ordem definida), nunca ambíguo.
- Estoque de camisa nulo: significa ilimitado, nunca "zero".
- Registros em situação terminal (cancelado/reembolsado/usado/expirado): tentativas de
  transição são rejeitadas como conflito de regra de negócio.
- Exclusão reversível de um evento com filhos (tipos, lotes, pedidos): nada é apagado
  fisicamente; consultas padrão deixam de listar, histórico permanece íntegro.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O projeto MUST nascer como produto autônomo, sem nenhum conceito da
  organização de origem: dono polimórfico, guarda de acesso do 061, matriz de módulos,
  loja/membro, limite de vagas por loja e integrações 054/055 não podem existir no
  código nem na estrutura de dados.
- **FR-002**: O material em `base/` MUST ser tratado como referência de extração
  (modelo de dados, regras), nunca copiado com acoplamentos.
- **FR-003**: A estrutura de dados MUST contemplar: contas de usuário e papéis;
  listas de domínio (situações de evento, pedido, ingresso e pagamento; tipos de
  evento); evento e sua configuração (blocos de landing, tipos de ingresso, lotes,
  modelos e tamanhos de camisa); pedidos e ingressos; pagamentos e eventos de webhook;
  vouchers de cortesia; patrocínios e parcelas; casos de suporte e notas — conforme o
  modelo de dados de referência.
- **FR-004**: Toda tabela de negócio MUST ter exclusão reversível (soft delete) e
  trilha de auditoria (quem criou/alterou), exceto listas de domínio e junções puras.
- **FR-005**: Cada ingresso MUST guardar fotografia (snapshot) de preço unitário e
  configuração (nome, camisa, lote) do momento da compra.
- **FR-006**: Identificadores expostos publicamente (pedido, ingresso, voucher) MUST
  ser códigos não sequenciais e únicos; ids sequenciais internos nunca aparecem em
  URL pública ou QR.
- **FR-007**: Valores monetários MUST usar precisão decimal fixa (10,2) e datas MUST
  ser armazenadas em UTC.
- **FR-008**: A situação operacional do evento — inscrições abertas, lote vigente,
  vagas disponíveis, esgotado, camisa esgotada — MUST ser sempre derivada dos dados
  (janelas, contagens, estoques), nunca persistida como campo editável.
- **FR-009**: O lote vigente MUST ser resolvido de forma determinística: ativo, dentro
  da janela, não esgotado, respeitando a ordem definida; o preço efetivo MUST ser a
  sobreposição do lote quando existir, senão o preço do tipo de ingresso.
- **FR-010**: O sistema MUST oferecer exatamente quatro papéis de acesso —
  administrador, tesouraria, portaria e inscrito — com possibilidade de acúmulo por
  usuário.
- **FR-011**: Rotas de gestão MUST ser protegidas por verificação de papel; acesso sem
  o papel exigido MUST ser recusado com resposta de "sem permissão" (403), e violação
  de regra de negócio MUST responder como conflito (409).
- **FR-012**: Contas de usuário MUST suportar cadastro com e sem senha local (conta
  criada via login social terá senha ausente) — o fluxo de autenticação em si é da
  spec 002.
- **FR-013**: O ambiente MUST ser populável com dados de demonstração: papéis, listas
  de domínio completas, usuário administrador de desenvolvimento e um evento de
  exemplo com tipos de ingresso, lotes, camisas e blocos de landing.
- **FR-014**: O repositório MUST fornecer comandos padronizados e documentados para
  subir o ambiente, aplicar a estrutura de dados e rodar os testes, funcionando em
  máquina limpa.
- **FR-015**: Nenhum segredo real MUST ser versionado; o arquivo de exemplo de
  configuração MUST conter apenas placeholders.
- **FR-016**: Migrations MUST ser aditivas e re-executáveis do zero (instalação limpa
  sempre funciona); renomeações destrutivas exigem justificativa em spec.
- **FR-017**: Registros em situação terminal (cancelado, reembolsado, usado, expirado)
  MUST rejeitar novas transições de situação como conflito de regra de negócio.
- **FR-018**: O código e identificadores MUST estar em inglês; mensagens voltadas a
  pessoas e documentação MUST estar em pt-BR; respostas da API MUST seguir o envelope
  `{ data }` com chaves em camelCase.
- **FR-019**: A suíte de testes da spec MUST cobrir: criação/relacionamento das
  entidades, exclusão reversível com auditoria, derivações (cenários de aberto/fechado,
  lote vigente, esgotado, camisa esgotada), proteção por papel (403) e recusa de
  transição terminal (409).

### Key Entities

- **Usuário**: pessoa com conta na plataforma (nome, e-mail único, documento e
  telefone opcionais, vínculo com login social opcional); pode acumular papéis.
- **Papel**: perfil de acesso seeded — administrador, tesouraria, portaria, inscrito;
  relação N:N com usuário.
- **Listas de domínio**: situações de evento (rascunho/publicado/cancelado/encerrado),
  de pedido, de ingresso e de pagamento; tipos de evento — todas seeded.
- **Evento**: o seminário; nome, slug público único, datas, local, capacidade,
  janela de vendas, TTL de reserva, modo de precificação, flags de comportamento
  (formas de pagamento aceitas, camisa, transferência, cancelamento, cortesia),
  regra de cortesia, dados de cancelamento. Único no MVP, mas modelado como coleção.
- **Bloco de landing**: seção configurável da página pública do evento (hero, texto,
  programação, palestrantes, FAQ, local, chamada), com ordem e conteúdo próprio.
- **Tipo de ingresso**: categoria vendável (nome, preço, capacidade, assentos por
  ingresso, casal, inclui camisa/kit, cortesia, público-alvo).
- **Lote**: janela/quantidade de venda com possível sobreposição de preço; pertence ao
  evento e opcionalmente a um tipo de ingresso.
- **Modelo e Tamanho de camisa**: hierarquia por evento (tamanho pertence a modelo),
  com estoque opcional por tamanho (nulo = ilimitado).
- **Pedido**: agrupador da compra; código público, comprador, fotografia dos dados do
  comprador, valor total, situação, prazo de reserva.
- **Ingresso**: um por participante; fotografia de preço/configuração, dados do
  participante (com ou sem conta), camisa escolhida, situação, código público (base do
  QR), campos de uso/cancelamento/reembolso/transferência.
- **Pagamento**: cobrança vinculada ao pedido (método, provedor, identificador da
  cobrança no provedor, situação, metadados por método); idempotência por provedor +
  identificador externo.
- **Evento de webhook**: registro bruto de notificação externa para auditoria e
  deduplicação.
- **Voucher de cortesia**: código único com ciclo disponível → distribuído → resgatado.
- **Patrocínio e Parcela**: apoio financeiro de empresa com parcelas e baixa
  individual.
- **Caso de suporte e Nota**: canal inscrito ↔ organização, com notas visíveis ou não
  ao inscrito.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Em uma máquina limpa com os pré-requisitos instalados, uma pessoa
  desenvolvedora sobe o ambiente, aplica a estrutura de dados e roda a suíte de testes
  com no máximo 3 comandos documentados, em menos de 15 minutos.
- **SC-002**: A instalação do zero (banco vazio → estrutura completa + dados de
  demonstração) conclui sem erro e sem passo manual em 100% das execuções.
- **SC-003**: A suíte de testes da fundação passa integralmente (0 falhas) cobrindo os
  cenários das quatro user stories.
- **SC-004**: Varredura do código-fonte por termos do sistema de origem (loja, membro,
  dono polimórfico, guarda 061, matriz 047, 054/055, limite por loja) retorna 0
  ocorrências.
- **SC-005**: 100% das tabelas de negócio têm exclusão reversível e trilha de
  quem criou/alterou; 100% das listas de domínio estão seeded com os valores do
  modelo de referência.
- **SC-006**: Nenhum segredo real em arquivo versionado (verificação de placeholders
  no exemplo de configuração passa em revisão).
- **SC-007**: Consultas de situação derivada (inscrições abertas, lote vigente,
  esgotado, camisa esgotada) respondem corretamente em todos os cenários de teste
  definidos, sem existir campo editável equivalente.

## Assumptions

- A stack é a fixada pela constituição (Laravel 11/PHP 8.3+, MySQL 8, Redis, React 18
  + Vite, Sanctum SPA por cookie) e não é decisão desta spec — a spec descreve
  capacidades; o plano técnico detalha o uso da stack.
- O escopo é single-event no MVP, porém eventos são modelados como coleção para
  permitir multi-evento na Fase 2 sem reescrita (constituição, princípio I).
- Esta spec entrega fundação técnica: **não** inclui telas de usuário final, fluxos de
  autenticação (spec 002), configuração administrativa via interface (spec 003),
  compra (spec 004) nem pagamento (spec 005). O frontend nesta spec limita-se ao
  scaffold funcional (aplicação React montada e servida em dev).
- A recontagem transacional de vagas/lote/estoque em compras concorrentes
  (constituição, princípio II) tem sua estrutura preparada aqui (contagens deriváveis),
  mas o fluxo de reserva/compra que a exercita é da spec 004.
- O evento de exemplo dos dados de demonstração evolui nas specs seguintes conforme
  novas entidades ganham fluxo (regra do roadmap).
- Docker é o caminho padrão de desenvolvimento; execução nativa é possível mas não
  suportada oficialmente pelo guia.
- Nomes de tabelas seguem o modelo de dados de referência em `base/data-model.md`
  (renomeando `event_*` do sistema de origem para `orders`/`tickets`/`payments` etc.).
