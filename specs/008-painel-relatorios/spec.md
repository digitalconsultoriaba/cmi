# Feature Specification: Painel e Relatórios

**Feature Branch**: `008-painel-relatorios`

**Created**: 2026-07-04

**Status**: Implemented

**Input**: User description: "008-painel-relatorios — a visão gerencial que fecha o MVP: dashboard do evento com os números que a organização acompanha (previsto × confirmado, ocupação, camisas por modelo/tamanho, vendas por lote e por forma de pagamento, presenças); financeiro consolidado da tesouraria; relatórios exportáveis em planilha (.xlsx) com filtros de período; e trilha de auditoria de quem fez o quê e quando nas ações sensíveis."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Dashboard do evento (Priority: P1)

A organização abre o painel do evento e enxerga, numa tela só, a fotografia
atual: quantos ingressos válidos existem e quantas **pessoas** eles representam
frente à capacidade; a receita **prevista** (tudo que foi vendido e ainda pode
pagar) contra a **confirmada** (o que de fato entrou); a distribuição de
camisas por modelo e tamanho (o que mandar produzir); as vendas quebradas por
lote e por forma de pagamento; cortesias emitidas contra os limites; e o
termômetro da portaria (presentes/ausentes). Tudo derivado dos dados reais, no
momento da consulta.

**Why this priority**: é a pergunta diária da organização ("como estamos?") —
os dados já existem todos; sem essa visão a gestão vive de contagens manuais.

**Independent Test**: com o banco de demonstração, abrir o dashboard e conferir
cada número contra contagens diretas (ingressos, pessoas, receita, camisas).

**Acceptance Scenarios**:

1. **Given** um evento com vendas, cortesias e check-ins, **When** o dashboard
   abre, **Then** mostra pessoas confirmadas × capacidade, ingressos por
   situação, receita prevista × confirmada e presenças — todos batendo com os
   dados reais no instante da consulta.
2. **Given** ingressos de casal e camisas de tamanhos distintos para titular e
   acompanhante, **When** a grade de camisas é exibida, **Then** cada pessoa
   conta a sua camisa (casal = 2 camisas) por modelo e tamanho.
3. **Given** vendas em lotes e formas de pagamento diferentes, **When** as
   quebras são exibidas, **Then** cada lote mostra vendidos/limite e cada forma
   mostra quantidade e valor confirmado.
4. **Given** um estorno ou cancelamento ocorrido, **When** o dashboard é
   recarregado, **Then** os números refletem a mudança (nada fica em cache
   defasado que minta para a organização).
5. **Given** uma pessoa sem papel de organização, **When** tenta abrir o
   dashboard, **Then** o acesso é recusado.

---

### User Story 2 - Financeiro da tesouraria (Priority: P2)

A tesouraria consulta o consolidado financeiro: total confirmado por forma de
pagamento, pedidos pagos/pendentes/estornados com valores, estornos realizados
(quanto saiu e por quê), e a situação dos patrocínios (parcelas pagas × em
aberto, inadimplência). Pode filtrar por período (mês/ano ou intervalo de
datas) para fechar o caixa do mês.

**Why this priority**: é a prestação de contas — o papel de tesouraria existe
para isso; depende só de dados que já existem.

**Independent Test**: registrar pagamentos em formas e datas distintas, um
estorno e parcelas de patrocínio; conferir os totais do consolidado com e sem
filtro de período.

**Acceptance Scenarios**:

1. **Given** pagamentos confirmados em Pix, cartão e dinheiro, **When** o
   consolidado abre, **Then** cada forma mostra quantidade e soma, e o total
   geral bate com a soma das partes.
2. **Given** um filtro de período aplicado, **When** o consolidado recalcula,
   **Then** só entram pagamentos confirmados dentro do período — e o filtro
   vale igualmente para os estornos.
3. **Given** estornos parciais e totais realizados, **When** a seção de
   estornos é exibida, **Then** mostra quantidade e valor devolvido, separado
   do que permanece confirmado.
4. **Given** patrocínios com parcelas pagas e vencidas, **When** a seção de
   patrocínios abre, **Then** mostra recebido × a receber e destaca parcelas em
   atraso.
5. **Given** os papéis de tesouraria e administração, **When** acessam o
   financeiro, **Then** ambos entram; portaria e inscritos são recusados.

---

### User Story 3 - Relatórios exportáveis em planilha (Priority: P3)

A organização baixa planilhas (.xlsx) prontas para trabalhar fora do sistema:
a lista completa de inscritos (participante, acompanhante, tipo, lote, camisa
de cada pessoa, contato, situação, presença), o relatório financeiro
(pagamentos com forma/valor/data/pedido) e o de presenças. Os relatórios
respeitam os mesmos filtros de período do financeiro.

**Why this priority**: produção de camisas, credenciamento impresso e
prestação de contas externa acontecem FORA do sistema — a planilha é a ponte;
depende das visões das US1/US2 existirem.

**Independent Test**: exportar cada relatório com o banco de demonstração,
abrir a planilha e conferir linhas e colunas contra os dados.

**Acceptance Scenarios**:

1. **Given** inscritos confirmados (incluindo casais), **When** a planilha de
   inscritos é gerada, **Then** cada PESSOA é uma linha (acompanhante tem a
   própria linha com a própria camisa) com as colunas de conferência.
2. **Given** um filtro de período aplicado no financeiro, **When** exportado,
   **Then** a planilha traz exatamente as linhas visíveis no consolidado.
3. **Given** um download gerado, **When** aberto em editor de planilhas comum,
   **Then** abre sem erro, com cabeçalhos e uma linha por registro.
4. **Given** ingressos cancelados/transferidos, **When** a planilha de
   inscritos é gerada, **Then** aparecem apenas os que valem entrada (mesma
   régua da portaria), sem duplicar transferidos.

---

### User Story 4 - Trilha de auditoria (Priority: P4)

A administração consulta a trilha das ações sensíveis: quem registrou cada
baixa de pagamento, quem cancelou/estornou o quê, transferências, emissão de
cortesias, mudanças de configuração do evento e validações de portaria — cada
registro com autor, momento e o objeto afetado, com filtro por tipo de ação e
período. A trilha é somente leitura: nada nela pode ser alterado ou apagado
pela interface.

**Why this priority**: governança e resolução de disputas ("quem deu baixa
nisso?"); valiosa, mas o evento opera sem ela — é a dívida consciente da
fundação que esta spec quita.

**Independent Test**: executar uma baixa manual, um estorno e um check-in;
conferir que os três aparecem na trilha com autor e momento corretos.

**Acceptance Scenarios**:

1. **Given** ações sensíveis executadas (baixa manual, estorno, cortesia,
   check-in), **When** a trilha é consultada, **Then** cada uma aparece com
   autor, data/hora, tipo de ação e referência ao objeto afetado.
2. **Given** um filtro por tipo de ação ou período, **When** aplicado,
   **Then** a lista restringe corretamente.
3. **Given** o papel de tesouraria ou portaria, **When** tenta abrir a trilha,
   **Then** o acesso é recusado (somente administração).
4. **Given** qualquer registro da trilha, **When** exibido, **Then** não há
   ação de editar ou excluir — o histórico é imutável pela interface.

---

### Edge Cases

- Evento sem nenhuma venda: dashboard abre com zeros coerentes (não erra nem
  esconde seções).
- Pagamento confirmado e depois estornado dentro do mesmo período filtrado:
  aparece nas duas seções (confirmados e estornos) — o líquido é a diferença.
- Pedido pago com valor divergente registrado pela tesouraria (baixa manual
  com desconto): o financeiro soma o valor efetivamente recebido, não o
  nominal do pedido.
- Exportação com volume grande (milhares de linhas): o download conclui sem
  travar a navegação; não há limite artificial de linhas.
- Camisa não informada (dado legado ou cortesia emitida sem tamanho): entra na
  grade como "não informado" — a soma da grade sempre fecha com o total de
  pessoas.
- Fuso horário: filtros de período interpretam datas no fuso oficial do evento
  (Brasil), não em UTC cru — o "dia 30" do caixa é o dia 30 brasileiro.
- Ações executadas pelo próprio sistema (expiração automática de reserva,
  conciliação): aparecem na trilha atribuídas ao sistema, não a uma pessoa.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O dashboard MUST apresentar, derivado dos dados no momento da
  consulta: pessoas confirmadas × capacidade, ingressos por situação, receita
  prevista × confirmada, cortesias emitidas × limites e presenças
  (esperados/presentes/ausentes).
- **FR-002**: A grade de camisas MUST contar por PESSOA (titular e
  acompanhante, cada um com seu modelo/tamanho), incluindo a categoria "não
  informado", com totais que fecham com o número de pessoas confirmadas.
- **FR-003**: As quebras de vendas MUST incluir por lote (vendidos × limite ×
  receita) e por forma de pagamento (quantidade × valor confirmado).
- **FR-004**: O financeiro consolidado MUST somar pagamentos confirmados por
  forma, listar estornos (quantidade e valor devolvido) e a posição de
  patrocínios (recebido × a receber × em atraso), aceitando filtro por
  mês/ano ou intervalo de datas aplicado uniformemente a todas as seções.
- **FR-005**: Valores monetários MUST refletir o efetivamente recebido
  (baixas manuais com valor divergente contam pelo valor real; estornos
  parciais abatem apenas a parte devolvida).
- **FR-006**: Os relatórios MUST ser exportáveis em planilha (.xlsx):
  inscritos (uma linha por pessoa, com camisa, contato, tipo, lote, situação e
  presença), financeiro e presenças — respeitando os filtros ativos.
- **FR-007**: A planilha de inscritos MUST seguir a mesma régua de
  elegibilidade da portaria: cancelados, transferidos (o antigo) e não pagos
  ficam fora.
- **FR-008**: O sistema MUST registrar na trilha de auditoria as ações
  sensíveis — baixa/confirmação de pagamento, estorno, cancelamento (ingresso,
  pedido e evento), transferência, emissão/uso de cortesia, alterações de
  configuração do evento e check-in — com autor (pessoa ou sistema), momento e
  objeto afetado.
- **FR-009**: A trilha MUST ser consultável apenas pela administração, com
  filtros por tipo de ação e período, e MUST ser imutável pela interface
  (sem editar/excluir).
- **FR-010**: Acesso: dashboard e relatórios de inscritos/presenças para
  administração; financeiro (visão e export) para administração e tesouraria;
  trilha somente administração; demais papéis recusados.
- **FR-011**: Filtros de período MUST interpretar datas no fuso horário
  oficial do evento (Brasil).
- **FR-012**: Nenhum número do painel MUST ser armazenado como verdade — tudo
  é derivado e reflete estornos/cancelamentos imediatamente após ocorrerem.

### Key Entities

- **Registro de auditoria**: quem (pessoa ou sistema), quando, qual ação, e
  sobre qual objeto (pedido, ingresso, pagamento, cortesia, evento) — imutável.
- **Visões derivadas** (não armazenadas): fotografia do dashboard, consolidado
  financeiro e conteúdos de exportação — sempre calculados dos registros base.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A organização responde "quantas pessoas confirmadas e quanto já
  entrou de dinheiro" em menos de 10 segundos a partir do login, sem contar
  nada manualmente.
- **SC-002**: 100% dos números exibidos batem com contagens diretas dos
  registros base — inclusive imediatamente após um estorno ou cancelamento.
- **SC-003**: A grade de camisas fecha com o total de pessoas confirmadas em
  100% dos casos (nenhuma pessoa sem linha na produção).
- **SC-004**: As planilhas exportadas abrem em editores comuns sem erro e
  reproduzem exatamente as linhas filtradas na tela.
- **SC-005**: Toda ação sensível executada aparece na trilha de auditoria com
  autor e momento — zero ações sensíveis sem rastro após esta spec.
- **SC-006**: O fechamento financeiro de um mês (consolidado filtrado +
  export) é concluído pela tesouraria em menos de 2 minutos.

## Assumptions

- Um único evento ativo por vez (padrão do sistema) — o painel é "do evento";
  histórico multi-evento fica para a Fase 2.
- Formato de exportação exclusivamente .xlsx no MVP (CSV/PDF ficam para a
  Fase 2); gráficos visuais são desejáveis mas os números em si são o
  requisito.
- A trilha de auditoria passa a registrar a partir da entrada desta spec em
  produção — ações anteriores não são reconstruídas retroativamente (os
  campos de rastro pontuais já existentes, como operador do check-in e
  registrador da baixa, permanecem como fonte complementar).
- Atualização dos números por recarga/consulta (sem push em tempo real no
  MVP) — "tempo real" significa recalculado a cada consulta.
- Papéis e permissões existentes (administração, tesouraria, portaria,
  inscrito) são reutilizados; nenhum papel novo.
