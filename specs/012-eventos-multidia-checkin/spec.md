# Feature Specification: Eventos com 1, 2 ou 3 dias e check-in por dia

**Feature Branch**: `012-eventos-multidia-checkin`

**Created**: 2026-07-06

**Status**: Draft

**Input**: User description: "Ajustar o módulo de Eventos/Ingressos para permitir eventos de 1, 2 ou 3 dias, com check-in registrado individualmente por dia; eventos existentes viram 1 dia automaticamente; finalização e reabertura de dias; relatórios de presença por dia e consolidados; tudo com histórico e log."

## Visão geral

Hoje o check-in é único por ingresso (uma leitura marca o ingresso como "utilizado"). Eventos de vários dias (seminários, congressos, formações) precisam de **presença por dia**: o mesmo ingresso é lido em cada dia e cada leitura registra a presença **apenas no dia selecionado**. O evento passa a ter uma **duração de 1, 2 ou 3 dias**, cada dia com sua data (e opcionalmente horário e rótulo). Operadores fazem check-in escolhendo o dia; ao encerrar a operação de um dia ele é **finalizado** (congelado); um dia finalizado só pode ser **reaberto** por administrador, com justificativa registrada. Relatórios mostram presença por dia e consolidada.

**Compatibilidade**: todo evento existente e todo evento novo nascem como **1 dia**, preservando exatamente o comportamento atual. Nada quebra para quem não usa multi-dia.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configurar a duração do evento e os dias (Priority: P1)

O organizador, ao cadastrar/editar um evento, escolhe a **duração** (1, 2 ou 3 dias). Em "1 dia" mantém a data principal única (como hoje). Em "2 dias" ou "3 dias", informa a **data de cada dia** e, opcionalmente, horário de início/término e um rótulo (ex.: Abertura, Palestras, Encerramento).

**Why this priority**: É a base — sem os dias configurados, o check-in por dia e os relatórios por dia não existem. Entrega valor imediato (evento multi-dia cadastrável) e é pré-requisito das demais histórias.

**Independent Test**: Criar um evento novo (vem "1 dia") e confirmar 1 dia igual à data principal; editar para "2 dias", informar as duas datas e verificar que dois dias ficam registrados; um evento pré-existente aparece como "1 dia" sem alteração manual.

**Acceptance Scenarios**:

1. **Given** um evento novo, **When** o organizador salva sem mexer na duração, **Then** o evento fica com **1 dia** cuja data é a data principal do evento (comportamento atual preservado).
2. **Given** um evento, **When** o organizador muda a duração para "2 dias" e informa Data do Dia 1 e Data do Dia 2, **Then** o sistema registra 2 dias com essas datas.
3. **Given** duração "3 dias", **When** o organizador tenta salvar sem informar a data de algum dia, **Then** o sistema recusa e exige as datas obrigatórias dos dias adicionais.
4. **Given** um evento de 2 dias com check-ins registrados no Dia 2, **When** o organizador tenta reduzir para "1 dia", **Then** o sistema recusa enquanto houver presença registrada em dias que seriam removidos (evita perder histórico).
5. **Given** todos os eventos já existentes no sistema, **When** a mudança entra no ar, **Then** cada um passa a ter exatamente 1 dia correspondente à sua data, sem intervenção manual.

---

### User Story 2 - Check-in do participante por dia (Priority: P2)

O operador de portaria abre o check-in de um evento, **escolhe o dia** em que está operando (o dia de hoje vem destacado), e valida a entrada por leitura de QR ou busca do participante. A presença é registrada **somente no dia selecionado**. Se o participante já tem check-in naquele dia, o sistema avisa e mostra quando e por quem foi feito.

**Why this priority**: É o coração operacional da feature — o registro de presença correto por dia. Depende de US1 (dias configurados).

**Independent Test**: Num evento de 2 dias, ler o mesmo ingresso no Dia 1 (registra presença no Dia 1) e depois no Dia 2 (registra presença no Dia 2); ler de novo no Dia 1 retorna "já possui check-in neste dia" com data/hora/operador do registro anterior.

**Acceptance Scenarios**:

1. **Given** um evento de 2 dias com o Dia 1 selecionado, **When** o operador lê um ingresso válido ainda sem presença no Dia 1, **Then** o sistema registra a presença no Dia 1 (data/hora, operador e origem "QR Code") e confirma na tela.
2. **Given** o mesmo ingresso já com check-in no Dia 1, **When** o operador o lê de novo com o Dia 1 selecionado, **Then** o sistema informa "Participante já possui check-in registrado neste dia" e mostra data, hora e usuário do check-in anterior — **sem** criar novo registro.
3. **Given** o Dia 2 selecionado, **When** o operador lê o mesmo ingresso (que só tem presença no Dia 1), **Then** o sistema registra a presença no Dia 2 normalmente (a presença de um dia não confirma a do outro).
4. **Given** um ingresso cancelado/estornado (inapto), **When** o operador o lê em qualquer dia, **Then** o sistema recusa a entrada informando o motivo.
5. **Given** um participante buscado por nome/código, **When** o operador registra a presença manualmente, **Then** o check-in é gravado com origem "busca manual" e o operador responsável.
6. **Given** um evento de 1 dia, **When** o operador faz check-in, **Then** o comportamento é idêntico ao atual (presença única no único dia).

---

### User Story 3 - Finalizar o dia (congelar a operação) (Priority: P3)

Ao encerrar a operação de um dia, o operador/organizador aciona **"Finalizar Dia N"**. O dia finalizado não aceita novos check-ins, exclusões ou alterações; o histórico fica bloqueado para conferência. Os dias posteriores ainda abertos seguem operando normalmente.

**Why this priority**: Garante integridade do fechamento diário. Depende de US2.

**Independent Test**: Num evento de 2 dias, finalizar o Dia 1 e verificar que novas leituras no Dia 1 são recusadas ("dia finalizado"), enquanto o Dia 2 continua aceitando check-in.

**Acceptance Scenarios**:

1. **Given** o Dia 1 com check-ins, **When** o organizador finaliza o Dia 1, **Then** o Dia 1 fica com situação "Finalizado" e novas leituras/alterações nesse dia são recusadas.
2. **Given** o Dia 1 finalizado num evento de 2 dias, **When** o operador seleciona o Dia 2, **Then** consegue registrar check-in normalmente no Dia 2.
3. **Given** um dia finalizado, **When** alguém tenta excluir ou alterar um check-in daquele dia, **Then** o sistema recusa a operação.
4. **Given** a finalização de um dia, **When** ela ocorre, **Then** fica registrado no histórico quem finalizou e quando.

---

### User Story 4 - Reabrir um dia finalizado (restrito) (Priority: P4)

Por segurança, um dia finalizado só pode ser **reaberto por administrador**, mediante **justificativa obrigatória**. A reabertura fica registrada no histórico (quem, quando, qual dia, justificativa). Após o ajuste, o dia pode ser finalizado novamente.

**Why this priority**: Exceção controlada para corrigir erros; não bloqueia o fluxo principal. Depende de US3.

**Independent Test**: Um administrador reabre o Dia 1 com justificativa, faz um ajuste de presença e finaliza de novo; um operador comum (não admin) não consegue reabrir.

**Acceptance Scenarios**:

1. **Given** um dia finalizado, **When** um administrador solicita reabertura sem justificativa, **Then** o sistema recusa e exige a justificativa.
2. **Given** um administrador com justificativa, **When** ele reabre o dia, **Then** o dia volta a "Aberto/Em andamento", aceita check-in de novo, e o histórico grava quem/quando/qual dia/justificativa.
3. **Given** um operador sem permissão administrativa, **When** ele tenta reabrir um dia, **Then** o sistema nega a ação.
4. **Given** um dia reaberto e reajustado, **When** o administrador finaliza de novo, **Then** o dia volta a "Finalizado" e o histórico preserva os dois ciclos.

---

### User Story 5 - Relatórios de presença por dia e consolidado (Priority: P5)

Organizadores acompanham a presença por dia e consolidada: totais e percentuais de presentes/ausentes por dia, quem esteve em todos os dias, quem esteve parcialmente e quem não compareceu. No detalhe individual, cada participante mostra o check-in de cada dia (Sim/Não, data, hora, operador).

**Why this priority**: Fecha o ciclo de acompanhamento; consome os dados das histórias anteriores.

**Independent Test**: Num evento de 2 dias com presenças mistas, conferir os totais por dia, o número de "presentes em todos os dias" e o detalhe individual com o check-in de cada dia.

**Acceptance Scenarios**:

1. **Given** um evento de 3 dias com presenças, **When** o organizador abre o relatório, **Then** vê total de inscritos e, por dia, presentes, ausentes e percentual de presença.
2. **Given** o mesmo evento, **When** o organizador consulta os recortes consolidados, **Then** vê quantos estiveram presentes em **todos** os dias, quantos parcialmente e quantos não compareceram em **nenhum** dia.
3. **Given** um participante, **When** o organizador abre o detalhe individual, **Then** vê, para cada dia, Check-in Sim/Não com data, hora e operador.
4. **Given** um evento de 1 dia, **When** o relatório é aberto, **Then** ele mostra a presença do único dia (equivalente ao relatório de presença atual).

---

### Edge Cases

- **Reduzir duração com presença registrada**: recusar remoção de dias que já têm check-in (preserva histórico); permitir só quando o(s) dia(s) a remover não têm presença.
- **Datas de dias fora de ordem/duplicadas**: cada dia deve ter data distinta; ordem dos dias segue a numeração (Dia 1, 2, 3).
- **Check-in em dia bloqueado/não liberado**: recusar com mensagem clara (dia bloqueado).
- **Check-in em dia finalizado**: recusar ("dia finalizado — reabra para alterar").
- **Ingresso inapto** (cancelado/estornado): recusar a entrada em qualquer dia.
- **Cortesia/casal**: seguem a mesma régua do check-in atual (casal conta como as pessoas do ingresso); a presença é por ingresso, por dia.
- **Operar no dia errado**: a tela deve deixar o dia selecionado sempre visível/óbvio para reduzir erro humano; o dia de hoje vem destacado, mas a seleção de outro dia é permitida (respeitando bloqueios/finalização).
- **Transferência de ingresso**: o ingresso válido (após transferência) é o que registra presença; o antigo não.

## Requirements *(mandatory)*

### Functional Requirements

**Configuração de duração e dias (US1)**

- **FR-001**: O cadastro/edição de evento DEVE ter o campo "Duração do evento" com opções **1, 2 ou 3 dias**, padrão **1 dia**.
- **FR-002**: Em "1 dia", o evento mantém o comportamento atual, com o único dia correspondente à data principal do evento.
- **FR-003**: Em "2 ou 3 dias", o sistema DEVE exigir a **data de cada dia** e permitir, opcionalmente, **horário de início**, **horário de término** e **rótulo** por dia (ex.: Abertura, Palestras, Encerramento).
- **FR-004**: Cada dia DEVE ter data distinta; os dias são numerados sequencialmente (Dia 1, 2, 3) na ordem das datas.
- **FR-005**: O sistema NÃO DEVE permitir reduzir a duração removendo dias que já possuem check-in registrado.
- **FR-006**: Todos os eventos **já existentes** DEVEM passar a ter automaticamente **1 dia** (data = data principal), sem intervenção manual e sem alterar seu funcionamento.

**Check-in por dia (US2)**

- **FR-007**: O check-in DEVE ser registrado **por dia do evento** (não mais apenas por evento). A presença de um dia NÃO confirma a presença de outro dia.
- **FR-008**: Na tela de check-in, o operador DEVE **selecionar o dia** antes de validar; o dia cuja data é a de hoje DEVE vir **destacado**, permitindo seleção manual dos demais (respeitando bloqueios/finalização).
- **FR-009**: Ao validar (QR ou busca), o sistema DEVE verificar: ingresso válido para o evento; se o participante **já tem check-in naquele dia**; se o dia está **liberado**; se o dia foi **finalizado**; se o ingresso está **apto** (não cancelado/estornado).
- **FR-010**: Se ainda não houver check-in no dia selecionado, o sistema DEVE registrar a presença com **data/hora, operador responsável e origem** (QR Code, busca manual ou ajuste administrativo) e observação opcional.
- **FR-011**: Se já houver check-in no dia, o sistema DEVE informar **"Participante já possui check-in registrado neste dia"** e mostrar **data, hora e usuário** do check-in anterior, sem criar novo registro.
- **FR-012**: O mesmo ingresso/QR DEVE poder ser lido em cada dia do evento; cada leitura gera presença **apenas** no dia selecionado.
- **FR-013**: Para eventos de **1 dia**, o check-in DEVE se comportar exatamente como hoje (presença única no único dia).

**Finalização e reabertura (US3, US4)**

- **FR-014**: Cada dia DEVE ter uma ação **"Finalizar Dia N"**. Após finalizado, o dia NÃO aceita novo check-in, exclusão ou alteração; o histórico fica bloqueado para conferência.
- **FR-015**: Finalizar um dia NÃO DEVE afetar os demais dias — dias posteriores ainda abertos continuam operando.
- **FR-016**: A **reabertura** de um dia finalizado DEVE ser restrita a **administrador** e exigir **justificativa obrigatória**.
- **FR-017**: Toda finalização e reabertura DEVE gravar no histórico **quem, quando, qual dia** e (na reabertura) a **justificativa**.
- **FR-018**: Após reabertura e ajuste, o dia DEVE poder ser **finalizado novamente**, preservando o histórico dos ciclos.

**Status dos dias (US3)**

- **FR-019**: Cada dia DEVE ter uma situação operacional **derivada/controlada**: **Aberto** (disponível, sem check-ins), **Em andamento** (já há check-ins), **Finalizado** (encerrado, sem alterações) e **Bloqueado** (não liberado / regra administrativa).
- **FR-020**: A tela DEVE exibir, por dia, a **situação**, a **data** e a **quantidade de check-ins**, com ação para operar aquele dia.

**Relatórios (US5)**

- **FR-021**: Os relatórios de presença DEVEM considerar os dias **separadamente**: total de inscritos; presentes, ausentes e **percentual de presença por dia**.
- **FR-022**: Os relatórios DEVEM apresentar recortes consolidados: presentes em **todos** os dias, presentes **parcialmente** e ausentes em **nenhum** dia (não compareceram).
- **FR-023**: O detalhe individual do participante DEVE mostrar, para **cada dia**, Check-in Sim/Não com **data, hora e operador**, além de nome, tipo de ingresso e status do ingresso.
- **FR-024**: Para eventos de 1 dia, os relatórios DEVEM equivaler aos relatórios de presença atuais.

**Histórico e integridade (transversal)**

- **FR-025**: Todo check-in, finalização e reabertura DEVE gerar **histórico/registro de auditoria** (quem/quando/o quê). Nada é apagado fisicamente.
- **FR-026**: O check-in DEVE registrar sua **origem** (QR Code, busca manual, ajuste administrativo) e permitir **observação** opcional.

### Key Entities *(include if feature involves data)*

- **Dia do Evento**: representa um dia operacional do evento (número do dia, data, opcionalmente horário de início/término e rótulo). Pertence a um evento (1 a 3 por evento). Guarda a **situação** (aberto/em andamento/finalizado/bloqueado) e os dados de finalização/reabertura (quem/quando/justificativa).
- **Check-in por Dia**: presença de um ingresso num dia específico. Contém: evento, dia do evento, ingresso/inscrição, participante, data/hora do check-in, usuário responsável, origem (QR Code, busca manual, ajuste administrativo), status e observação opcional. É **único por (ingresso, dia)**.
- **Evento** (existente): passa a ter uma **duração** (1–3) e a relação com seus Dias do Evento; a data principal continua existindo (equivale ao Dia 1).
- **Ingresso** (existente): permanece válido para todos os dias do evento; sua aptidão (pago/confirmado/cortesia vs. cancelado/estornado) governa a entrada.

Relacionamentos: Evento → 1..3 Dias do Evento; (Ingresso × Dia do Evento) → 0..1 Check-in por Dia.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% dos eventos existentes passam a ter exatamente 1 dia após a mudança, sem alteração no comportamento de check-in de 1 dia (nenhuma regressão).
- **SC-002**: Num evento de 2 ou 3 dias, o mesmo ingresso pode registrar presença em cada dia, e cada leitura afeta **somente** o dia selecionado (verificável: presença no Dia 1 não marca o Dia 2).
- **SC-003**: Uma segunda leitura no mesmo dia nunca cria um segundo registro e sempre informa data/hora/operador do check-in anterior (0 duplicatas por (ingresso, dia)).
- **SC-004**: Após finalizar um dia, nenhuma nova leitura, exclusão ou alteração é aceita naquele dia, enquanto os dias posteriores seguem operando.
- **SC-005**: Apenas administradores reabrem dias finalizados, sempre com justificativa; toda finalização/reabertura fica registrada no histórico (quem/quando/qual dia/justificativa).
- **SC-006**: Os relatórios mostram presentes/ausentes/percentual por dia e os consolidados (todos os dias, parcial, nenhum) de forma consistente com os check-ins registrados; o detalhe individual mostra o check-in de cada dia com data/hora/operador.
- **SC-007**: O operador identifica sem ambiguidade qual dia está operando (o dia selecionado fica sempre visível), reduzindo check-in no dia errado.

## Assumptions

- **Papéis (RBAC de 4 papéis)**: o "perfil autorizado" a **reabrir** dias mapeia para **admin**. **Finalizar** um dia pode ser feito por quem opera o check-in (**gate**) e por **admin**. O check-in em si segue a permissão atual da portaria (gate/admin). Nenhum papel novo é criado.
- **Limite de duração**: 1 a 3 dias (conforme a descrição); não há suporte a 4+ nesta spec.
- **Bloqueado**: estado opcional aplicável administrativamente; por padrão os dias nascem "Aberto" e o dia de hoje é destacado. Não é obrigatório bloquear dias futuros — o operador pode registrar presença antecipada num dia aberto (a menos que bloqueado/finalizado).
- **Busca do participante**: por nome, e-mail ou código do ingresso; CPF/documento é usado quando disponível no cadastro (nem todo participante tem documento informado).
- **Compatibilidade do "utilizado" atual**: para eventos de 1 dia, o check-in do Dia 1 equivale ao "utilizado" de hoje; o comprovante/ingresso e as telas existentes continuam refletindo essa presença sem mudança perceptível.
- **Origem do check-in**: valores previstos — QR Code, busca manual e ajuste administrativo.
- **Datas/horários**: datas dos dias no fuso do evento; armazenamento em UTC (convenção do projeto).
- **Não escopo**: emissão de crachá por dia, catraca física e regras de acesso por faixa de horário não fazem parte desta spec.
