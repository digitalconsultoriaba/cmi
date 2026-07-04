# Feature Specification: Check-in da Portaria

**Feature Branch**: `007-checkin-portaria`

**Created**: 2026-07-04

**Status**: Implemented

**Input**: User description: "007-checkin-portaria — a entrada do evento: validação de ingressos pelo QR code (ou digitação do código) marcando presença, com recusa clara de ingressos inválidos, já utilizados, cancelados, transferidos ou não pagos; ingresso de casal registra a entrada de duas pessoas; painel da portaria com leitor de QR pela câmera e lista de presentes/ausentes para conferência. Acesso restrito ao papel de portaria."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Validar o ingresso na entrada (Priority: P1)

A pessoa da portaria aponta o leitor para o QR do comprovante (ou digita o
código impresso) e recebe resposta imediata e inequívoca: **verde** com o nome
do participante (e do acompanhante, no casal) quando o ingresso vale — que fica
marcado como utilizado na hora — ou **vermelho** com o motivo exato quando não
vale: inexistente, já utilizado (com horário e quem validou), cancelado,
transferido (só o novo vale) ou ainda não pago.

**Why this priority**: é a função inteira da portaria — sem validação confiável
não há controle de entrada; todo o resto é apoio.

**Independent Test**: validar um ingresso confirmado (fica utilizado), tentar de
novo (recusado como já utilizado, com horário) e testar cada motivo de recusa.

**Acceptance Scenarios**:

1. **Given** um ingresso confirmado/pago/cortesia, **When** o código é validado,
   **Then** o ingresso fica "utilizado" com momento e operador registrados, e a
   tela mostra confirmação com nome do participante e tipo do ingresso.
2. **Given** um ingresso de casal, **When** validado, **Then** a confirmação
   exibe titular e acompanhante e a contagem de presentes soma **2 pessoas** em
   uma única validação.
3. **Given** um ingresso já utilizado, **When** validado de novo, **Then** é
   recusado informando quando e por quem foi utilizado.
4. **Given** um ingresso cancelado, transferido ou de pedido não pago
   (reservado/aguardando), **When** validado, **Then** é recusado com o motivo
   específico — no transferido, com a dica de que existe um ingresso novo válido.
5. **Given** um código inexistente ou digitado errado, **When** validado,
   **Then** recusa clara de "ingresso não encontrado" — sem vazar nenhum dado.
6. **Given** dois escaneamentos quase simultâneos do mesmo código (leitor
   rápido), **When** processados, **Then** apenas o primeiro vale; o segundo é
   recusado como já utilizado — nunca dupla entrada.
7. **Given** o evento cancelado, **When** qualquer código é validado, **Then**
   recusa informando o cancelamento do evento.

---

### User Story 2 - Painel da portaria com leitor de QR (Priority: P2)

Quem tem o papel de portaria abre o painel no celular ou notebook, ativa a
câmera e escaneia os QR codes em sequência — cada leitura dispara a validação e
mostra o resultado em tela cheia (verde/vermelho) por alguns segundos, pronta
para o próximo. Sem câmera (ou QR danificado), digita o código manualmente.
Quem não tem o papel não acessa.

**Why this priority**: é a interface que torna a US1 utilizável na velocidade de
uma fila real; depende da validação existir.

**Independent Test**: logar como portaria no painel, escanear um QR válido pela
câmera (verde), escanear de novo (vermelho "já utilizado") e validar um código
digitado; logar como inscrito e receber recusa de acesso.

**Acceptance Scenarios**:

1. **Given** uma pessoa com papel de portaria, **When** abre o painel de
   check-in, **Then** vê o leitor com câmera ativável e o campo de digitação
   manual; sem o papel → recusa de acesso.
2. **Given** o leitor ativo, **When** um QR é enquadrado, **Then** a validação
   dispara automaticamente e o resultado toma a tela (verde com nomes /
   vermelho com motivo), voltando ao leitor em seguida.
3. **Given** um QR ilegível ou comprovante impresso danificado, **When** o
   operador digita o código, **Then** a validação funciona identicamente.
4. **Given** leituras em sequência rápida, **When** o mesmo QR permanece
   enquadrado, **Then** o painel não dispara validações repetidas em rajada
   (proteção de re-leitura).
5. **Given** o painel aberto em um celular, **When** usado, **Then** leitor e
   resultados são utilizáveis em tela pequena (operação com uma mão).

---

### User Story 3 - Lista de presentes e ausentes (Priority: P3)

A portaria (e a organização) consulta a lista do evento: quem já entrou (com
horário) e quem ainda não chegou, com busca por nome/código e contadores no
topo — total esperado, presentes (contando acompanhantes) e ausentes. Serve
para conferência rápida ("essa pessoa já entrou?") e para acompanhar o fluxo.

**Why this priority**: apoio operacional à portaria; útil, mas a entrada
funciona sem ela.

**Independent Test**: após alguns check-ins, abrir a lista e conferir
contadores, presentes com horário e busca por nome.

**Acceptance Scenarios**:

1. **Given** ingressos válidos do evento, **When** a lista abre, **Then** mostra
   contadores (esperados, presentes em pessoas — casal conta 2 —, ausentes) e a
   relação com situação de cada um.
2. **Given** um nome buscado, **When** digitado, **Then** a lista filtra por
   participante, acompanhante ou código.
3. **Given** um presente, **When** listado, **Then** aparece o horário da
   entrada e quem validou.
4. **Given** ingressos cancelados/transferidos, **When** a lista é montada,
   **Then** não aparecem entre os esperados (só ingressos que valem entrada).

---

### Edge Cases

- Comprovante impresso × QR na tela do celular do inscrito: ambos carregam o
  mesmo código — indiferente para a validação.
- Ingresso confirmado de pedido que ficou "parcialmente pago": não vale entrada
  (recusa "pagamento pendente") — só situações plenamente confirmadas passam.
- Check-in antes do dia do evento: permitido (credenciamento antecipado é
  decisão operacional da portaria); evento cancelado recusa sempre.
- Acompanhante chega em momento diferente do titular: o ingresso de casal é uma
  entrada única — orientação operacional é entrarem juntos; exceções são
  resolvidas pela organização (fora do sistema no MVP).
- Validação equivocada (passou quem não devia): não há "desfazer" no MVP —
  utilizado é terminal; a ocorrência vira caso de suporte para a organização.
- Conexão instável na portaria: cada validação exige rede (modo offline é Fase
  2); a tela indica falha de rede sem marcar nada.
- Vários operadores de portaria simultâneos: seguro — a proteção contra dupla
  entrada vale entre dispositivos.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: A validação MUST aceitar o código público do ingresso (do QR ou
  digitado, com espaços/caixa normalizados) e responder em uma única operação:
  válido (marcando utilizado) ou recusado com motivo específico.
- **FR-002**: Apenas ingressos em situação plenamente confirmada (pago,
  confirmado, cortesia) de evento não cancelado MUST ser aceitos; reservado/
  aguardando → "pagamento pendente"; cancelado → "cancelado"; transferido →
  "transferido (existe ingresso novo)"; utilizado → "já utilizado" com momento
  e operador; inexistente → "não encontrado".
- **FR-003**: A marcação MUST registrar momento e operador, ser **atômica e à
  prova de corrida**: o mesmo código validado simultaneamente por dois
  dispositivos resulta em exatamente UMA entrada.
- **FR-004**: Ingresso utilizado MUST ser terminal — sem desfazer; a resposta da
  recusa MUST incluir os dados da utilização original.
- **FR-005**: A resposta de validação MUST trazer o que a portaria precisa ver:
  nome do participante, acompanhante (casal), tipo do ingresso e quantas
  pessoas a entrada representa (1 ou 2).
- **FR-006**: Todo o acesso (validação, lista) MUST exigir o papel de portaria;
  admin também MUST poder acessar (supervisão).
- **FR-007**: O painel MUST oferecer leitura de QR pela câmera do dispositivo e
  digitação manual como alternativa equivalente.
- **FR-008**: O painel MUST proteger contra validações repetidas em rajada do
  mesmo código (re-leitura do QR enquadrado) e MUST ser utilizável em celular.
- **FR-009**: A lista MUST separar presentes (com horário e operador) e
  ausentes, com busca por nome/acompanhante/código e contadores em pessoas
  (casal conta 2) — apenas ingressos que valem entrada contam como esperados.
- **FR-010**: A validação MUST responder rápido o suficiente para fila real
  (percepção imediata) e as recusas MUST usar mensagens em pt-BR sem vazar
  dados de terceiros.
- **FR-011**: Os dados de demonstração do ambiente de desenvolvimento MUST
  incluir um conjunto de inscritos confirmados (com casal e cortesia) pronto
  para exercitar o check-in.

### Key Entities

Nenhuma entidade nova — esta spec movimenta:

- **Ingresso**: transição confirmado/pago/cortesia → **utilizado**
  (`used_at`, `validated_by`); situação terminal.
- **Evento**: contadores derivados de presença (esperados/presentes/ausentes,
  em pessoas).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Uma validação completa (escanear → resposta na tela) leva menos de
  2 segundos em rede normal — compatível com fila real.
- **SC-002**: 100% dos ingressos não elegíveis (usado, cancelado, transferido,
  não pago, inexistente) são recusados com o motivo correto; 0 falsos aceites
  nos cenários de teste.
- **SC-003**: Em validações simultâneas do mesmo código, exatamente 1 entrada é
  registrada em 100% dos casos (0 duplas entradas).
- **SC-004**: Ingresso de casal validado soma exatamente 2 pessoas nos
  contadores de presença.
- **SC-005**: 100% das tentativas de acesso sem papel de portaria/admin são
  recusadas.
- **SC-006**: A suíte cobre as 3 user stories e passa integralmente; as suítes
  anteriores permanecem verdes.

## Assumptions

- Herança: código público no QR (004), transições terminais e trilha (001),
  transferido nunca vale (006), papéis (001/002).
- **Sem modo offline** no MVP (Fase 2 do produto, conforme ROADMAP); a portaria
  precisa de rede.
- **Sem desfazer** check-in no MVP: utilizado é terminal (constituição);
  ocorrências viram caso de suporte (006).
- Check-in permitido a qualquer momento com evento não cancelado
  (credenciamento antecipado é prática comum); janela restrita seria
  configuração da Fase 2.
- O leitor usa a câmera via navegador (sem aplicativo instalado); a escolha da
  tecnologia de leitura é decisão do plano técnico.
- Casal = entrada única de 2 pessoas juntas; controle de entrada separada do
  acompanhante fica fora do MVP (edge case registrado).
- Estatísticas históricas/relatórios de presença são a spec 008; aqui é a
  operação ao vivo.
