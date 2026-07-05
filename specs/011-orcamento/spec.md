# Feature Specification: Aba Orçamento / Previsão Financeira do Evento

**Feature Branch**: `011-orcamento`

**Created**: 2026-07-04

**Status**: Draft

**Input**: User description: "011-orcamento — Criar aba Orçamento dentro do evento: planejamento, previsão de custos, simulação de receitas (ingressos/patrocínios), preço de ingresso, ponto de equilíbrio, investimento próprio, comparativo orçado×realizado e geração de financeiro real. Não substitui o módulo Financeiro. Orçamento é individual por evento."

## Visão geral

A aba **Orçamento** vive **dentro da tela do evento** (segunda camada de abas, ao lado de Financeiro) e é uma ferramenta de **planejamento e simulação** — não registra dinheiro real. Ela responde: *quanto o evento custa, quanto arrecadar com ingressos, quanto buscar em patrocínio, qual o preço mínimo do ingresso, quantos pagantes para fechar a conta e quanto de investimento próprio será necessário*. Cada orçamento é **exclusivo de um evento**.

O Financeiro (spec 010) continua sendo a verdade do que aconteceu (lançamentos, baixas, comprovantes). O Orçamento **alimenta** o Financeiro: um item de custo previsto pode virar **conta a pagar** e um patrocínio previsto pode virar **conta a receber**, sem duplicidade.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Montar a planilha de custos e ver o resultado previsto (Priority: P1)

O organizador abre a aba Orçamento de um evento, cadastra os itens de custo previstos (descrição, categoria, quantidade, valor unitário, status) e imediatamente vê, no topo, o **custo total previsto**, a **receita total prevista**, o **resultado previsto** (superávit/déficit) e o **investimento próprio necessário**.

**Why this priority**: É o núcleo do valor — sem a planilha de custos e o resumo, a aba não decide viabilidade. Entrega um MVP útil mesmo sem simulações avançadas.

**Independent Test**: Cadastrar 3 itens de custo (um deles cancelado) num evento e verificar que o custo total previsto soma apenas os itens ativos e que o resultado previsto = receita total prevista − custo total previsto.

**Acceptance Scenarios**:

1. **Given** um evento sem orçamento, **When** o organizador cadastra um item de custo com quantidade 2 e valor unitário R$ 1.000,00, **Then** o valor total do item é R$ 2.000,00 e entra no custo total previsto.
2. **Given** um item de custo com status "Cancelado", **When** o resumo é calculado, **Then** o item cancelado **não** entra no custo total previsto.
3. **Given** custo total previsto R$ 250.000,00 e receita total prevista R$ 200.000,00, **When** o resumo é exibido, **Then** mostra "déficit previsto" e "investimento próprio necessário: R$ 50.000,00".
4. **Given** um item informado só pelo valor total (sem quantidade/unitário), **When** salvo, **Then** o sistema aceita e usa o valor total informado.
5. **Given** um usuário sem papel de acesso ao painel do evento, **When** tenta abrir a aba Orçamento, **Then** recebe 403.

---

### User Story 2 - Simular receitas: ingressos por lote, patrocínios e participantes (Priority: P2)

O organizador cadastra lotes de ingresso previstos (nome, valor, quantidade prevista, pagantes estimados), cotas de patrocínio previstas (nome, valor, quantidade, status) e a estimativa de participantes (pagantes, cortesias, convidados, equipe, palestrantes). O resumo passa a mostrar **receita prevista com ingressos**, **receita prevista com patrocínios**, **ticket médio previsto**, **custo por participante**, **custo por pagante** e **ponto de equilíbrio**.

**Why this priority**: Completa o modelo de receita e os indicadores de decisão (preço e ponto de equilíbrio), tornando o orçamento acionável.

**Independent Test**: Cadastrar três lotes (200×R$250, 200×R$300, 200×R$350) e verificar receita prevista com ingressos = R$ 180.000,00; com custo R$ 250.000,00 e patrocínio previsto R$ 100.000,00 e ticket médio R$ 300,00, o ponto de equilíbrio exibido é ~500 pagantes.

**Acceptance Scenarios**:

1. **Given** um lote com valor R$ 250,00 e quantidade prevista 200, **When** salvo, **Then** a receita prevista do lote é R$ 50.000,00.
2. **Given** patrocínios com status "Perdido" e "Cancelado", **When** os totais são calculados, **Then** eles não entram no "patrocínio confirmado" e o sistema mostra dois totais (previsto total × confirmado).
3. **Given** custo total previsto R$ 250.000,00, patrocínio previsto R$ 100.000,00 e ticket médio R$ 300,00, **When** o ponto de equilíbrio é calculado, **Then** exibe ~500 pagantes com a frase explicativa.
4. **Given** 400 pagantes previstos e 100 cortesias, **When** a receita de ingressos é calculada, **Then** somente os pagantes influenciam a receita; cortesias/convidados/equipe/palestrantes não geram receita, mas contam no total geral de participantes para o custo por participante.
5. **Given** total geral de 500 participantes e custo previsto R$ 250.000,00, **When** os indicadores são exibidos, **Then** custo médio por participante = R$ 500,00.

---

### User Story 3 - Transformar previsão em financeiro real (Priority: P3)

Cada item de custo previsto tem a ação **Gerar conta a pagar** e cada patrocínio previsto (não perdido/cancelado) tem **Gerar conta a receber**. A ação cria o lançamento no Financeiro do evento (spec 010) com descrição, categoria e valor preenchidos, e marca a linha do orçamento como "conta gerada", impedindo duplicidade.

**Why this priority**: É a ponte entre planejamento e execução; sem ela o orçamento fica isolado. Depende do módulo Financeiro já existente.

**Independent Test**: Num item "Sonorização — R$ 26.000,00", clicar "Gerar conta a pagar", verificar que surge uma conta a pagar vinculada ao evento no Financeiro e que o item fica marcado como "conta a pagar gerada"; clicar de novo retorna aviso de duplicidade (não cria segunda conta).

**Acceptance Scenarios**:

1. **Given** um item de custo previsto sem conta gerada, **When** o usuário clica "Gerar conta a pagar", **Then** o sistema cria uma conta a pagar no Financeiro do evento com valor/descrição/categoria do item e marca o item como "conta a pagar gerada".
2. **Given** um item já com conta a pagar gerada, **When** o usuário tenta gerar de novo, **Then** o sistema recusa com aviso de que já existe conta vinculada (nenhuma conta nova é criada).
3. **Given** um patrocínio previsto com status "Confirmado", **When** o usuário clica "Gerar conta a receber", **Then** cria a conta a receber no Financeiro do evento e marca o patrocínio como "conta a receber gerada".
4. **Given** um patrocínio com status "Perdido" ou "Cancelado", **When** o usuário abre as ações, **Then** a ação "Gerar conta a receber" fica indisponível.
5. **Given** um item de custo que já gerou conta a pagar, **When** o item é excluído no orçamento, **Then** a conta a pagar já criada no Financeiro é preservada (o histórico não some) e o vínculo é registrado.

---

### User Story 4 - Comparar orçado × realizado (Priority: P4)

A aba mostra um comparativo entre o planejado e o real do evento: custo previsto × despesas reais pagas, receita prevista × receitas recebidas, patrocínio previsto × recebido, ingressos previstos × vendidos, e resultado previsto × realizado — com o percentual de atingimento da meta de ingressos.

**Why this priority**: Fecha o ciclo de acompanhamento durante a execução do evento. Depende de dados reais do Financeiro (010) e das vendas (004/005).

**Independent Test**: Com meta de 500 ingressos e 320 vendidos, verificar atingimento de 64% e a diferença entre receita prevista e recebida calculada corretamente.

**Acceptance Scenarios**:

1. **Given** meta prevista de 500 ingressos e 320 vendidos reais, **When** o comparativo é exibido, **Then** mostra 64% de atingimento.
2. **Given** despesa prevista R$ 250.000,00 e despesa paga real R$ 230.000,00, **When** o comparativo é exibido, **Then** classifica o evento como "abaixo do orçamento" (gastou menos que o previsto).
3. **Given** patrocínio previsto R$ 200.000,00 e recebido real R$ 120.000,00, **When** o comparativo é exibido, **Then** mostra a diferença de R$ 80.000,00 ainda a captar.
4. **Given** um evento sem nenhum lançamento real, **When** o comparativo é aberto, **Then** exibe zeros coerentes no lado "realizado" sem erro.

---

### User Story 5 - Simuladores e alertas de viabilidade (Priority: P5)

O organizador usa ferramentas "e se": três cenários (Conservador/Realista/Otimista) com pagantes, ticket médio, patrocínio e custo próprios; um simulador de preço mínimo do ingresso; e uma margem de segurança percentual sobre o custo. Alertas automáticos avisam quando há déficit, meta não coberta, itens/patrocínios ainda não convertidos em financeiro, etc.

**Why this priority**: Agrega poder de decisão, mas não é pré-requisito para o orçamento básico funcionar.

**Independent Test**: Informar custo R$ 250.000,00, patrocínio R$ 100.000,00, outras receitas R$ 0,00 e 500 pagantes no simulador de preço e verificar valor mínimo do ingresso = R$ 300,00, com a frase explicativa.

**Acceptance Scenarios**:

1. **Given** custo R$ 250.000,00, patrocínio R$ 100.000,00 e 500 pagantes, **When** o simulador de preço roda, **Then** sugere ingresso mínimo de R$ 300,00.
2. **Given** margem de segurança de 10% sobre custo R$ 250.000,00, **When** aplicada, **Then** o custo com margem exibido é R$ 275.000,00, sem alterar o custo base cadastrado.
3. **Given** três cenários preenchidos, **When** comparados, **Then** o sistema indica qual(is) cenário(s) fecham o orçamento (receita ≥ custo).
4. **Given** custo previsto maior que receita prevista, **When** a aba carrega, **Then** um alerta em vermelho informa "Faltam R$ X para fechar o orçamento".
5. **Given** existem itens de custo previstos ainda não convertidos em conta a pagar, **When** a aba carrega, **Then** um alerta informativo lista essa pendência.

---

### User Story 6 - Exportar e visualizar o orçamento (Priority: P6)

O organizador exporta o orçamento (resumo, itens, ingressos, patrocínios, resultado e comparativo) em PDF e Excel, e visualiza gráficos simples (custos por categoria, receitas previstas por tipo, orçado × realizado, participação dos patrocínios).

**Why this priority**: Facilita apresentação a terceiros; complementar ao núcleo.

**Independent Test**: Exportar o orçamento de um evento com itens e lotes e verificar que o arquivo gerado contém o resumo e as seções previstas, respeitando os dados atuais.

**Acceptance Scenarios**:

1. **Given** um orçamento com itens e lotes, **When** o usuário exporta em Excel, **Then** o arquivo contém as seções resumo, itens de custo, ingressos, patrocínios e resultado.
2. **Given** itens de custo em várias categorias, **When** o gráfico "custos por categoria" é exibido, **Then** cada categoria aparece com seu total previsto.

---

### Edge Cases

- **Divisão por zero**: quando pagantes previstos = 0 ou total de participantes = 0, ticket médio / custo por participante / valor mínimo de ingresso devem exibir "—" (indefinido), nunca erro.
- **Valores inválidos**: item/lote/patrocínio com valor zero ou negativo é recusado (dinheiro sempre positivo, duas casas).
- **Ponto de equilíbrio sem ticket médio**: se não há lotes/ticket médio, o cálculo exibe "informe ao menos um lote" em vez de dividir por zero.
- **Superávit**: quando receita total prevista ≥ custo previsto, o investimento próprio necessário é R$ 0,00 e o resumo mostra "superávit/positivo" ou "ponto de equilíbrio" (quando iguais).
- **Item convertido depois excluído/cancelado**: excluir/cancelar o item no orçamento não apaga a conta a pagar já gerada no Financeiro; a conta segue a política própria do Financeiro (cancelamento com motivo).
- **Patrocínio previsto ≠ patrocínio real (spec de patrocínios)**: o patrocínio *previsto* do orçamento é planejamento; não se confunde com o patrocínio real do evento. O comparativo puxa o real; a geração de conta a receber usa o previsto.
- **Evento cancelado**: o orçamento continua legível (histórico preservado), mas edição pode ser bloqueada conforme a política do evento.

## Requirements *(mandatory)*

### Functional Requirements

**Acesso e escopo**

- **FR-001**: O sistema DEVE exibir a aba "Orçamento" dentro da tela do evento, ao lado da aba Financeiro, e cada orçamento DEVE pertencer a exatamente um evento.
- **FR-002**: O sistema DEVE restringir a aba Orçamento a papéis autorizados: `admin` (acesso total: ver, criar, editar, excluir, gerar financeiro, exportar) e `treasury`/financeiro (ver, editar e gerar contas a pagar/receber). Papéis `gate` e `attendee` NÃO acessam a aba (403).
- **FR-003**: O orçamento NÃO DEVE misturar-se com lançamentos financeiros reais; ele representa apenas previsão/simulação.

**Itens de custo previstos**

- **FR-004**: Usuários autorizados DEVEM poder cadastrar, editar, excluir e **duplicar** itens de custo, cada um com descrição, categoria, quantidade, valor unitário, valor total, observação, status e fornecedor previsto (opcional).
- **FR-005**: O sistema DEVE calcular o valor total do item como quantidade × valor unitário; se apenas o valor total for informado, DEVE aceitá-lo como total do item.
- **FR-006**: Cada item DEVE ter um status entre: Previsto, Cotado, Aprovado, Contratado, Cancelado. Itens **Cancelados** NÃO entram no custo total previsto.
- **FR-007**: O sistema DEVE oferecer um conjunto de categorias de custo (Espaço, Hospedagem, Alimentação, Bebidas, Som e iluminação, Infraestrutura, Gráfica, Comunicação, Marketing, Palestrantes, Transporte, Logística, Brindes, Cerimonial, Equipe, Segurança, Fotografia e filmagem, Taxas, Outros) e permitir agrupar/relatar custos por categoria.

**Ingressos, patrocínios e participantes previstos**

- **FR-008**: Usuários DEVEM poder cadastrar lotes de ingresso previstos (nome, valor do ingresso, quantidade prevista, pagantes estimados, observação); a receita prevista do lote = valor × quantidade prevista.
- **FR-009**: Usuários DEVEM poder cadastrar cotas de patrocínio previstas (nome, valor, quantidade, status, observação); status entre Previsto, Em negociação, Confirmado, Recebido, Perdido, Cancelado. Cotas **Perdidas/Canceladas** não entram no patrocínio confirmado.
- **FR-010**: O sistema DEVE exibir dois totais de patrocínio: **previsto total** e **confirmado** (Confirmado/Recebido).
- **FR-011**: Usuários DEVEM poder informar a estimativa de participantes segmentada em pagantes, cortesias, convidados, equipe e palestrantes, além do total geral previsto. Somente pagantes influenciam a receita de ingressos; todos os segmentos contam no custo por participante.

**Cálculos e resumo**

- **FR-012**: O sistema DEVE calcular e exibir, no resumo do topo: custo total previsto, receita prevista com ingressos, receita prevista com patrocínios, receita total prevista, resultado previsto, valor que falta captar, investimento próprio necessário, custo médio por participante, receita média por participante (ticket médio) e ponto de equilíbrio.
- **FR-013**: Receita total prevista = receita de ingressos + receita de patrocínios + outras receitas previstas. Resultado previsto = receita total prevista − custo total previsto.
- **FR-014**: Investimento próprio necessário = max(0, custo total previsto − receita total prevista).
- **FR-015**: Ponto de equilíbrio (pagantes) = (custo total previsto − patrocínio previsto − outras receitas) ÷ ticket médio, arredondado para cima; o sistema DEVE apresentar o resultado em linguagem clara ("Para pagar o evento, será necessário vender ~N ingressos com ticket médio de R$ X").
- **FR-016**: O sistema DEVE classificar o resultado como superávit previsto (receita > custo), ponto de equilíbrio (receita = custo) ou déficit previsto (receita < custo) e, no déficit, exibir "Faltam R$ X para fechar o orçamento".
- **FR-017**: Quando divisores forem zero (pagantes ou total de participantes), os indicadores dependentes DEVEM exibir estado indefinido ("—") sem erro.

**Simuladores**

- **FR-018**: O sistema DEVE oferecer um simulador de preço mínimo do ingresso: valor mínimo = (custo total previsto − patrocínio previsto − outras receitas) ÷ pagantes, exibindo a frase explicativa; opcionalmente considerar uma margem desejada.
- **FR-019**: O sistema DEVE permitir três cenários nomeados (Conservador, Realista, Otimista), cada um com pagantes, ticket médio, patrocínio previsto, custo previsto e outras receitas próprios, e indicar qual(is) fecham o orçamento.
- **FR-020**: O sistema DEVE permitir informar uma margem de segurança percentual sobre o custo e exibir o custo com margem, **sem** alterar o custo base cadastrado (simulação com e sem margem).

**Conversão em financeiro**

- **FR-021**: Cada item de custo previsto DEVE oferecer "Gerar conta a pagar", criando uma conta a pagar no Financeiro vinculada ao evento (descrição, categoria e valor preenchidos) e marcando o item como "conta a pagar gerada".
- **FR-022**: Cada patrocínio previsto não perdido/cancelado DEVE oferecer "Gerar conta a receber", criando uma conta a receber no Financeiro vinculada ao evento e marcando o patrocínio como "conta a receber gerada".
- **FR-023**: O sistema DEVE impedir duplicidade: uma linha já convertida não gera novo lançamento e retorna aviso de que já existe conta vinculada.
- **FR-024**: A conversão NÃO DEVE ser obrigatória nem automática — apenas sob ação explícita do usuário.
- **FR-025**: Excluir/cancelar uma linha do orçamento que já gerou lançamento NÃO DEVE apagar o lançamento no Financeiro (histórico preservado).

**Comparativo, alertas e visualização**

- **FR-026**: O sistema DEVE exibir comparativo orçado × realizado puxando dados reais do Financeiro e das vendas: custo previsto × despesa paga, receita prevista × recebida, patrocínio previsto × recebido, ingressos previstos × vendidos, resultado previsto × realizado, e o percentual de atingimento da meta de ingressos.
- **FR-027**: O sistema DEVE exibir alertas automáticos (déficit, valor faltante, meta de venda abaixo, patrocínio confirmado insuficiente, custo por participante acima do ticket médio, dependência de investimento próprio, ausência de margem de segurança, itens/patrocínios ainda não convertidos em financeiro).
- **FR-028**: O sistema DEVE usar indicadores visuais claros (verde=positivo, amarelo=atenção, vermelho=déficit, azul=informativo) e gráficos simples (custos por categoria, receitas previstas por tipo, orçado × realizado, participação dos patrocínios).
- **FR-029**: O sistema DEVE permitir exportar o orçamento (resumo, itens, ingressos, patrocínios, resultado e comparativo) nos formatos já padronizados na plataforma (PDF e Excel), respeitando os dados atuais.

**Integridade e histórico**

- **FR-030**: O sistema NÃO DEVE permitir valores zerados ou negativos em itens, lotes e patrocínios; dinheiro sempre positivo com duas casas decimais.
- **FR-031**: Toda criação/edição/exclusão no orçamento DEVE registrar autor e data/hora e preservar histórico (soft delete), conforme a constituição.
- **FR-032**: Cortesias previstas NUNCA DEVEM ser contabilizadas como receita.

### Key Entities *(include if feature involves data)*

- **Orçamento do Evento (BudgetPlan)**: cabeçalho do orçamento de um evento (1:1 com o evento). Guarda observações gerais, estimativas de participantes por segmento (pagantes, cortesias, convidados, equipe, palestrantes), outras receitas previstas e a margem de segurança. Deriva todos os totais a partir das linhas filhas.
- **Item de Custo Previsto (BudgetCostItem)**: descrição, categoria, quantidade, valor unitário, valor total, status (previsto/cotado/aprovado/contratado/cancelado), fornecedor previsto, observação e vínculo opcional ao lançamento financeiro gerado (para evitar duplicidade).
- **Lote de Ingresso Previsto (BudgetTicketLot)**: nome, valor do ingresso, quantidade prevista, pagantes estimados, observação; receita prevista derivada.
- **Cota de Patrocínio Prevista (BudgetSponsorship)**: nome da cota, valor, quantidade, status, observação e vínculo opcional ao lançamento a receber gerado.
- **Cenário (BudgetScenario)**: presets nomeados (Conservador/Realista/Otimista) com pagantes, ticket médio, patrocínio, custo e outras receitas para comparação what-if.
- **Categoria de Custo**: rótulo de agrupamento dos itens de custo (lista padrão editável).

Relacionamentos: um evento tem um BudgetPlan; um BudgetPlan tem N BudgetCostItem, N BudgetTicketLot, N BudgetSponsorship e até 3 BudgetScenario. Itens e patrocínios podem referenciar um lançamento do módulo Financeiro (spec 010) quando convertidos.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Com os dados de custo, ingressos e patrocínios cadastrados, o organizador consegue ver custo total, receita total, resultado previsto e investimento próprio necessário em uma única tela, sem sair da aba.
- **SC-002**: O resultado previsto e o investimento próprio batem exatamente com a fórmula (receita total − custo total; e max(0, custo − receita)) em 100% dos casos de teste.
- **SC-003**: O ponto de equilíbrio e o preço mínimo de ingresso são exibidos com no máximo 1 unidade monetária/1 pagante de arredondamento em relação ao cálculo manual dos exemplos da especificação.
- **SC-004**: Converter um item em conta a pagar cria exatamente 1 lançamento no Financeiro e uma segunda tentativa cria 0 lançamentos adicionais (duplicidade bloqueada) em 100% dos casos.
- **SC-005**: O comparativo orçado × realizado reflete as vendas e lançamentos reais do evento e calcula o percentual de atingimento da meta de ingressos corretamente.
- **SC-006**: Itens de custo cancelados e cortesias previstas nunca alteram, respectivamente, o custo total previsto e a receita prevista (0 casos de vazamento nos testes).
- **SC-007**: Nenhuma operação de cálculo lança erro quando divisores são zero — os indicadores dependentes exibem estado indefinido.

## Assumptions

- **Papéis existentes**: a plataforma tem 4 papéis (`admin`, `treasury`, `gate`, `attendee`) por decisão constitucional. Os perfis "Organizador do Evento" e "Consulta" citados na descrição são mapeados para os papéis existentes: `admin` = acesso total; `treasury` (financeiro) = ver/editar orçamento e gerar contas; demais papéis não acessam. Não serão criados novos papéis.
- **Escopo por evento**: o orçamento é individual por evento (1 BudgetPlan por evento), coerente com o painel do evento já existente.
- **Dados de simulação são independentes dos reais**: os lotes de ingresso e cotas de patrocínio *previstos* do orçamento são dados de planejamento próprios, distintos dos tipos/lotes de ingresso reais (spec 004) e do patrocínio real (spec de patrocínios). O comparativo é que cruza previsão com o real.
- **Integração com o Financeiro (spec 010)**: a geração de conta a pagar/receber usa o módulo Financeiro já existente, criando lançamentos vinculados ao evento. O comparativo lê despesas pagas, receitas recebidas e patrocínio recebido do Financeiro, e ingressos vendidos das vendas reais (specs 004/005).
- **Exportação**: reutiliza o padrão da plataforma (Excel via openspout, PDF via dompdf); CSV não é adicionado se não houver padrão prévio para a tela.
- **Cenários persistidos**: os três cenários são salvos por evento (sobrevivem ao reload); o simulador de preço e a margem de segurança podem ser recalculados a cada uso sem persistir necessariamente.
- **Moeda/idioma**: valores em BRL DECIMAL(10,2); identificadores em inglês, UI/mensagens em pt-BR; datas em UTC no banco.
- **Estado derivado**: todos os totais (custo total, receitas, resultado, ponto de equilíbrio, investimento próprio, % atingimento) são calculados na leitura, nunca armazenados como colunas editáveis, conforme a constituição.
