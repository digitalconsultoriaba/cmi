# Data Model — 009-refatoracao-telas

**Nenhuma tabela nova, nenhuma coluna nova.** Esta spec é reorganização de
apresentação + derivações de leitura sobre o modelo das specs 001–008
(princípio II). Estoque de camisas por tamanho, parcelas de patrocínio,
`used_at`/`validated_by`, trilha — tudo já existe.

## Derivações novas (no `ReportService`, calculadas na consulta)

### Painel do módulo — `overview(?from, ?to)`
```
eventos          = COUNT(events)
publicados       = COUNT(events status=published)
proximos         = COUNT(events starts_at >= hoje, não cancelado)
inscritosAtivos  = Σ pessoas dos ingressos elegíveis de todos os eventos
receitaConfirmada= Σ payments paid − estornos parciais         (reuso 008)
receitaPrevista  = confirmada + Σ pedidos em aberto
patrocinioPago   = Σ parcelas de patrocínio pagas
reembolsosAbertos= COUNT(support_cases type=refund abertos)
eventsByStatus   = COUNT por situação de EVENTO (rosca)
inscriptionsByMonth = série mensal de ingressos elegíveis (curva)
filtro: ?event= (um evento) e ?from/?to (período, fuso do evento)
```

### Painel do evento — `dashboard(Event)` (estende o da 008)
```
já existe (008): people, revenue, shirts, byLot, byMethod, cortesias, ticketsByStatus
novo: byTicketType = COUNT + Σ preço por tipo de ingresso (substitui "por loja")
      inscriptionsByMonth(Event) = curva do evento
contadores da operação (cards do protótipo, todos derivados):
  capacidade, inscritosTotal, pagosConfirmados, cortesias, presentes,
  aguardandoPgto, cancelados, reembolsados,
  valorPrevisto, valorConfirmado, aReceber, patrocinioPago
```

### Inscritos — `attendeesList(Event, filtros)`
```
linhas = ingressos do evento (todas as situações), com:
  participante (+ badge casal + acompanhante), tipo, camisa (titular/acomp.),
  valor, situação, data da compra, situação do pagamento
filtros: search (nome), status (situação), type (tipo de ingresso), from/to
SEM coluna Loja (FR-014)
```

### Check-in — `attendancePayload(Event, search)`
```
reaproveita o cálculo do GateController.attendance, ESCOPADO ao evento:
  comprados/presentes/ausentes (em pessoas; casal = 2), % presença,
  lista com participante/situação/registro (usedAt + validatedBy)
donut de presença = presentes × ausentes
```

### Relatórios — `reportPreview(Event, type, filtros)`
```
type ∈ { inscritos, financeiro, presencas, camisas }
retorna: colunas + linhas (limitadas p/ preview, com "mostrando N de M") + total
o MESMO shape alimenta o export .xlsx (reuso ReportExportService, escopado)
filtros: type de ingresso, ano/mês OU de/até, busca
```

### Camisas — sem endpoint novo
```
já disponível: GET /admin/events/{event}/shirt-models retorna modelos com
  tamanhos { stock_quantity, sold_count } → disponível = stock − sold
  (null = ilimitado). A tela apenas exibe/soma. Relatório de camisas entra
  como type do reportPreview/export.
```

## Invariantes (verificáveis em teste)

1. `overview` e `dashboard(Event)` batem com contagens diretas dos registros
   (inclusive após venda/estorno/check-in) — nada em cache.
2. `inscriptionsByMonth` soma, sobre todos os meses, o total de pessoas
   elegíveis do recorte (a curva fecha com o card de inscritos).
3. Presença manual pela lista produz exatamente o mesmo efeito e trilha de um
   check-in por código (1 entrada, `ticket.checked_in`, casal = 2).
4. `attendeesList` nunca expõe coluna Loja; recortes "por loja" viram "por
   tipo de ingresso".
5. Prévia de relatório e export .xlsx do mesmo recorte trazem as mesmas linhas.
6. Disponível de camisa = estoque − vendidas; estoque nulo = ilimitado (sem
   "disponível negativo").
7. Endpoints da 008 (`/admin/dashboard`, `/gate/attendance`, exports globais)
   continuam respondendo (sem regressão — suítes 007/008 verdes).
