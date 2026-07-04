# Feature Specification: Refatoração das Telas (identidade CMI/GLMEES e navegação por abas)

**Feature Branch**: `009-refatoracao-telas`

**Created**: 2026-07-04

**Status**: Implemented

**Input**: User description: "Reorganizar o ambiente administrativo seguindo o
protótipo aprovado (14 referências em `referencias/`): identidade visual da
CMI/GLMEES com sidebar azul e logo; navegação em duas camadas de abas — o
módulo 'Eventos e Ingressos' (Painel consolidado, Eventos, Atendimentos,
Tipos) e, dentro de um evento, as abas Painel/Inscritos/Ingressos/Camisas/
Cortesias/Patrocínio/Relatórios/Check-in/Trilha; painéis com gráficos
(rosca de situação e curva de inscrições por mês); check-in com validação
QR/código, gráfico de presença e lista com registro de presença manual; tela
de camisas mostrando estoque por tamanho; relatórios com pré-visualização em
tabela antes do export. Não quero tema escuro — tema claro com o azul da
marca."

## Clarifications

### Session 2026-07-04

- Q: A sidebar deve replicar o menu do sistema anfitrião? → A: Não. Sidebar
  azul com a logo CMI/GLMEES como identidade, mas só "Eventos e Ingressos"
  navega; esta plataforma é standalone.
- Q: Multi-loja (Eventos das lojas, coluna Loja, ranking por loja) entra? →
  A: Fora do escopo. Reorganização visual sobre o modelo atual; onde a
  referência recortava por loja, recortar por tipo de ingresso.
- Q: Quais telas implementar de fato além da casca? → A: Painéis com gráficos
  (módulo e evento), check-in com presença manual, camisas com estoque na
  tela, relatórios com preview. As demais abas reorganizam telas existentes.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Navegação por abas com a identidade da marca (Priority: P1)

A organização entra no ambiente e reconhece a plataforma como sua: uma
**sidebar azul com a logomarca da CMI/GLMEES**, tema claro. Todo o trabalho de
eventos acontece num módulo único, **"Eventos e Ingressos"**, organizado em
abas: um **Painel** consolidado, a **lista de Eventos** (com "Novo evento"),
**Atendimentos** e **Tipos**. Ao abrir um evento, a pessoa entra numa segunda
camada de abas — Painel, Inscritos, Ingressos, Camisas, Cortesias, Patrocínio,
Relatórios, Check-in e Trilha — sempre com um cabeçalho fixo do evento
(voltar, nome, situação e as ações Editar/Banner/Cancelar). Nada de tela preta;
nada de menu de outro sistema.

**Why this priority**: é a espinha dorsal — sem a estrutura de duas camadas e a
identidade, nenhuma das outras telas tem onde morar. Entrega sozinha a
transformação de aparência e organização que o protótipo pede.

**Independent Test**: entrar como organização, ver a sidebar azul com a logo,
navegar pelas abas do módulo, abrir um evento da lista e circular pelas abas do
evento — tudo em tema claro, sem itens de menu que não levam a lugar nenhum
dentro desta plataforma.

**Acceptance Scenarios**:

1. **Given** um administrador logado, **When** abre o ambiente, **Then** vê a
   sidebar azul com a logomarca e o módulo "Eventos e Ingressos" com as abas
   Painel/Eventos/Atendimentos/Tipos, em tema claro.
2. **Given** a aba Eventos, **When** aberta, **Then** lista os eventos
   existentes (nome, tipo, data, situação) com acesso a cada um e ação de criar
   um novo evento.
3. **Given** a lista de eventos, **When** um evento é aberto, **Then** aparece
   o cabeçalho do evento (voltar, nome, badge de situação, Editar/Banner/
   Cancelar) e a segunda camada de abas do evento.
4. **Given** as abas do evento, **When** o usuário troca de aba, **Then** o
   cabeçalho do evento permanece e o conteúdo troca sem sair do evento.
5. **Given** a identidade da marca, **When** qualquer tela do ambiente é
   exibida, **Then** o tema é claro com o azul da marca — nunca tema escuro
   forçado.
6. **Given** os papéis existentes, **When** acessam o ambiente, **Then** cada
   um vê apenas as abas do seu escopo (tesouraria, portaria e administração
   como já definido), sem itens que não pode usar.

---

### User Story 2 - Painéis com gráficos (Priority: P2)

A organização acompanha a operação por gráficos, não só números. O **Painel do
módulo** consolida todos os eventos: cards de contagem (eventos, publicados,
próximos, inscritos ativos, receita confirmada e prevista, patrocínio pago,
reembolsos em aberto), uma **rosca de eventos por situação** e uma **curva de
inscrições por mês** — com filtro por evento e por período. O **Painel do
evento** faz o mesmo recorte para um evento só: os contadores da operação
(capacidade, inscritos, pagos, cortesias, presentes, aguardando, cancelados,
reembolsados; valores previsto/confirmado/a receber/patrocínio) com uma rosca
de situação dos ingressos e a distribuição por tipo de ingresso.

**Why this priority**: é a leitura gerencial que o protótipo destaca; depende
da casca de navegação existir para ter onde aparecer.

**Independent Test**: abrir o Painel do módulo e conferir cada card e gráfico
contra os dados; abrir um evento e conferir o Painel do evento; aplicar o
filtro de período e ver os números e a curva reagirem.

**Acceptance Scenarios**:

1. **Given** eventos com vendas, **When** o Painel do módulo abre, **Then**
   mostra os cards consolidados, a rosca de eventos por situação e a curva de
   inscrições por mês, batendo com os dados.
2. **Given** um filtro de evento ou período, **When** aplicado, **Then** os
   cards e os gráficos recalculam para o recorte.
3. **Given** um evento aberto, **When** o Painel do evento é exibido, **Then**
   mostra os contadores da operação e os valores financeiros do evento, com a
   rosca de situação dos ingressos.
4. **Given** vendas em tipos de ingresso diferentes, **When** o painel do
   evento é exibido, **Then** a distribuição por tipo de ingresso aparece (no
   lugar do recorte por loja, fora de escopo).
5. **Given** um evento sem inscrições, **When** os painéis abrem, **Then** os
   gráficos aparecem vazios de forma coerente, sem erro.

---

### User Story 3 - Check-in operacional com presença manual (Priority: P3)

A portaria trabalha numa tela pensada para a fila: de um lado a **validação de
entrada** (campo de código + "Ler QR" pela câmera + "Validar ingresso"), do
outro uma **rosca de presença** (presentes × ausentes); embaixo, cards de
comprados/presentes/ausentes/% de presença e a **lista de participantes** com
busca por nome, onde cada linha ausente tem um botão **"Registrar presença"**
para marcar manualmente quem esqueceu o comprovante. Presentes aparecem
destacados, com horário e quem validou.

**Why this priority**: torna a portaria completa (QR + digitação + presença
manual) na organização visual do protótipo; o motor de validação já existe.

**Independent Test**: validar um código (vira presente na lista e na rosca),
registrar presença manual de um ausente pela lista, buscar por nome e conferir
os contadores e a % de presença.

**Acceptance Scenarios**:

1. **Given** a aba Check-in, **When** aberta, **Then** mostra a validação de
   entrada, a rosca de presença, os cards (comprados/presentes/ausentes/%) e a
   lista de participantes.
2. **Given** um ausente na lista, **When** a portaria usa "Registrar presença",
   **Then** ele passa a presente com horário e operador, e os contadores e a
   rosca atualizam — pela mesma regra de uma entrada válida (casal conta 2).
3. **Given** um nome buscado, **When** digitado, **Then** a lista filtra por
   participante.
4. **Given** um já presente, **When** exibido, **Then** aparece destacado com
   o horário e quem validou, sem opção de registrar de novo.

---

### User Story 4 - Camisas com estoque na tela (Priority: P4)

Quem organiza a produção de camisas vê e controla o **estoque por tamanho** na
própria tela: cada modelo (Masculina, Feminina, Infantil…) mostra um resumo
(estoque total, vendidas, disponível) e a grade de tamanhos com estoque,
vendidas e disponível por linha; dá para **adicionar um tamanho com seu
estoque** ali mesmo (em branco = ilimitado) e baixar o relatório por modelo ou
o geral.

**Why this priority**: fecha a gestão de camisas (o estoque já existe no
modelo, mas não aparecia); é o que falta para a produção trabalhar pela tela.

**Independent Test**: abrir Camisas, conferir estoque/vendidas/disponível por
tamanho contra os dados, adicionar um tamanho novo e vê-lo na grade com o
disponível calculado.

**Acceptance Scenarios**:

1. **Given** modelos com tamanhos e vendas, **When** a aba Camisas abre,
   **Then** cada modelo mostra o resumo (total/vendidas/disponível) e a grade
   por tamanho com estoque/vendidas/disponível.
2. **Given** um tamanho novo com estoque informado, **When** adicionado,
   **Then** entra na grade e o disponível reflete estoque menos vendidas.
3. **Given** um tamanho com estoque em branco, **When** exibido, **Then**
   aparece como ilimitado (sem trava de disponível).
4. **Given** os relatórios de camisas, **When** solicitados, **Then** é
   possível baixar o de um modelo e o geral (todas as camisas).

---

### User Story 5 - Relatórios com pré-visualização (Priority: P5)

Antes de exportar, a organização **vê o relatório na tela**: escolhe o tipo
(inscritos, financeiro, presenças…), aplica filtros (tipo de ingresso, ano/mês
ou intervalo de datas, busca por nome) e a prévia aparece em tabela com o total
de linhas; satisfeita, **exporta em .xlsx** com exatamente o mesmo recorte.

**Why this priority**: dá confiança antes do download e evita exportações no
escuro; consome as visões que já existem.

**Independent Test**: escolher um relatório, aplicar um filtro, conferir a
prévia e o total de linhas, exportar e verificar que a planilha traz as mesmas
linhas.

**Acceptance Scenarios**:

1. **Given** a aba Relatórios, **When** um tipo é escolhido e filtros
   aplicados, **Then** a prévia mostra as linhas correspondentes e o total.
2. **Given** uma prévia filtrada, **When** exportada, **Then** a planilha
   .xlsx reproduz exatamente as linhas da prévia.
3. **Given** um filtro sem resultados, **When** aplicado, **Then** a prévia
   informa "0 linhas" sem erro, e o export gera a planilha só com cabeçalhos.

---

### Edge Cases

- Evento único × vários eventos: a lista e o Painel do módulo funcionam com 1
  ou com N eventos; abrir um evento sempre leva às abas daquele evento.
- Tema do navegador em modo escuro: a interface permanece no tema claro da
  marca (o protótipo não usa dark forçado).
- Sidebar em tela estreita (tablet): colapsa mantendo o acesso ao módulo.
- Registro de presença manual de ingresso não elegível (não pago/cancelado):
  recusado com o mesmo motivo da validação por código — a lista não burla a
  régua da portaria.
- Camisa sem estoque definido (ilimitado) misturada com tamanhos com estoque:
  o resumo do modelo soma corretamente e não mostra "disponível negativo".
- Preview de relatório muito grande: a prévia limita a exibição (com aviso de
  "mostrando N de M") mas o export traz tudo — nenhuma exportação truncada em
  silêncio.
- Itens do menu anfitrião: exibidos como identidade, mas não navegáveis dentro
  desta plataforma — não podem parecer quebrados nem levar a telas vazias.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O ambiente administrativo MUST usar a identidade da marca —
  sidebar azul com a logomarca CMI/GLMEES e tema claro — sem tema escuro
  forçado.
- **FR-002**: A navegação MUST ser em duas camadas: um módulo "Eventos e
  Ingressos" com abas (Painel, Eventos, Atendimentos, Tipos) e, dentro de um
  evento, abas (Painel, Inscritos, Ingressos, Camisas, Cortesias, Patrocínio,
  Relatórios, Check-in, Trilha).
- **FR-003**: A aba Eventos MUST listar os eventos (nome, tipo, data,
  situação) e permitir criar um novo e abrir cada um; abrir um evento MUST
  exibir um cabeçalho fixo (voltar, nome, situação, Editar/Banner/Cancelar) e
  a segunda camada de abas.
- **FR-004**: Editar e criar evento MUST acontecer em modal, cobrindo os campos
  do protótipo (dados, janela de vendas, capacidade/limite, público, modo de
  preço, regras/toggles, gratuidade X→Y, observações).
- **FR-005**: O Painel do módulo MUST consolidar todos os eventos (cards de
  contagem e financeiros, rosca de eventos por situação, curva de inscrições
  por mês) com filtro por evento e por período.
- **FR-006**: O Painel do evento MUST mostrar os contadores da operação e os
  valores financeiros do evento, com rosca de situação dos ingressos e a
  distribuição por tipo de ingresso.
- **FR-007**: Os gráficos MUST ser derivados dos dados no momento da consulta
  (nada armazenado) e refletir mudanças (vendas, estornos, check-ins) na
  recarga.
- **FR-008**: A aba Check-in MUST reunir validação por código e por QR
  (câmera), rosca de presença, cards (comprados/presentes/ausentes/% presença)
  e a lista de participantes com busca.
- **FR-009**: A lista de check-in MUST permitir registrar presença manual por
  linha, seguindo a MESMA régua da validação por código (só elegíveis; casal
  conta 2 pessoas; registra horário e operador; presença é terminal).
- **FR-010**: A aba Camisas MUST exibir, por modelo, o resumo
  (estoque total, vendidas, disponível) e a grade por tamanho (estoque,
  vendidas, disponível), permitir adicionar tamanho com estoque (em branco =
  ilimitado) e baixar relatório por modelo e o geral.
- **FR-011**: A aba Relatórios MUST oferecer seleção de tipo, filtros (tipo de
  ingresso, ano/mês ou intervalo, busca) e uma prévia em tabela com o total de
  linhas, antes do export .xlsx com o mesmo recorte.
- **FR-012**: As abas Inscritos, Ingressos, Cortesias, Patrocínio e Trilha
  MUST apresentar as funções já existentes reorganizadas no novo layout, sem
  perda de capacidade (incluindo o modal de novo patrocínio com vencimentos
  "1ª + 30 em 30" ou personalizado).
- **FR-013**: O acesso por papel MUST ser preservado — cada papel vê apenas as
  abas/ações do seu escopo; itens do menu anfitrião aparecem como identidade
  mas não são navegáveis nesta plataforma.
- **FR-014**: Recortes que no protótipo eram por loja (ranking, coluna,
  "Inscrições por Loja", "Eventos das lojas") MUST ser omitidos ou
  substituídos por recorte por tipo de ingresso — sem introduzir modelo de
  lojas.

### Key Entities

- **Série de inscrições por mês** (derivada): contagem de ingressos elegíveis
  agrupada por mês de compra, para a curva — consolidada (módulo) e por evento.
- **Distribuição por tipo de ingresso** (derivada): contagem/valor por tipo,
  substituindo o recorte por loja do protótipo.
- Demais dados são os já existentes (eventos, ingressos, camisas com estoque,
  cortesias, patrocínios, pagamentos, trilha).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A organização identifica a plataforma como CMI/GLMEES à primeira
  vista — sidebar azul com a logo e tema claro em 100% das telas do ambiente.
- **SC-002**: Qualquer tarefa de evento é alcançável em no máximo 2 níveis de
  navegação (aba do módulo → aba do evento), sem menus de outro sistema no
  caminho.
- **SC-003**: 100% dos números dos painéis e gráficos batem com contagens
  diretas dos registros, inclusive após venda/estorno/check-in na recarga.
- **SC-004**: A portaria registra presença manual de um ausente pela lista em
  menos de 5 segundos, e a % de presença atualiza na mesma tela.
- **SC-005**: A grade de camisas mostra disponível correto (estoque − vendidas)
  em 100% dos tamanhos, e "ilimitado" quando sem estoque definido.
- **SC-006**: A prévia de relatório reproduz exatamente as linhas exportadas em
  .xlsx (mesmo recorte), sem divergência.
- **SC-007**: Nenhuma regressão funcional — todas as capacidades das specs
  001–008 continuam acessíveis pelo novo layout (suíte verde).

## Assumptions

- Reaproveita o backend das specs 001–008; as únicas adições de dados são
  derivações novas (série mensal, distribuição por tipo) — sem novas tabelas.
- Um contexto de organização por vez (sem multi-loja/multi-tenant); a lista de
  eventos pode conter vários eventos, mas o trabalho é sempre "dentro de um
  evento".
- Biblioteca de gráficos no frontend (rosca e linha) é decisão de
  implementação; os números em si são o requisito, não a lib.
- Identidade visual: `public/logo.png` (brasões CMI + GLMEES) e a paleta azul
  da marca; tema claro fixo no ambiente administrativo.
- "Atendimentos" no módulo mapeia para a fila de suporte já existente; "Tipos"
  para os tipos de evento já existentes.
- Presença manual usa o mesmo ponto de check-in (mesma régua e trilha) — não é
  um caminho paralelo que burla validações.
