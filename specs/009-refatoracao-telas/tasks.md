# Tasks: Refatoração das Telas (identidade e navegação por abas)

**Input**: Design documents from `/specs/009-refatoracao-telas/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md,
contracts/panel-api.md, quickstart.md, referencias/ (14 imagens) — e as specs
001–008 mergeadas.

**Tests**: INCLUÍDOS para o backend novo (endpoints de leitura, invariantes,
não-regressão) — exigência da constituição. Frontend validado por build +
quickstart manual contra as 14 referências.

**Organization**: por user story (spec.md). **Nenhuma migration/coluna nova.**
Endpoints da 008 permanecem (sem regressão).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizável (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1–US5 (mapeia para spec.md)

## Path Conventions

Backend: services em `app/Domain/Events/Services/`, controllers em
`app/Http/Controllers/Api/Admin/`, testes em `tests/Feature/Panel/`. Frontend
em `frontend/src/admin/`.

---

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Instalar gráficos no frontend (`npm install apexcharts
      react-apexcharts --prefix frontend`) e copiar a logo para a SPA (`cp
      public/logo.png frontend/public/logo.png`)
- [X] T002 [P] Tema da marca: azul em `--tblr-primary` e tema claro fixo
      (`data-bs-theme="light"`) — criar `frontend/src/theme/brand.css`
      (importado no `frontend/src/main.jsx`) com o azul CMI/GLMEES
- [X] T003 [P] Componentes de gráfico reutilizáveis
      `frontend/src/admin/components/DonutChart.jsx` e `AreaChart.jsx`
      (wrappers finos de react-apexcharts, cores do tema)
- [X] T004 Registrar as rotas de leitura escopadas por evento em
      `routes/api.php` (grupo `/admin`, `require.role:admin`): `GET /overview`;
      `GET /events/{event}/dashboard`, `/attendees`, `/attendance`,
      `/reports/preview`, `/reports/{type}.xlsx` (controllers das fases
      seguintes) — **sem remover** as rotas da 008

---

## Phase 2: User Story 1 - Casca + identidade (Priority: P1) 🎯 MVP

**Goal**: sidebar azul com logo, tema claro, navegação em 2 camadas de abas
(módulo → evento) com cabeçalho fixo; lista de eventos + criar/editar em modal;
abas que só reorganizam telas existentes.

**Independent Test**: quickstart.md §US1.

### Implementation for User Story 1

- [X] T005 [US1] Refazer `frontend/src/admin/AdminLayout.jsx`: `navbar-vertical`
      **azul** com a logo no topo; único item navegável "Eventos e Ingressos"
      (agrupamento anfitrião opcional como rótulo não-navegável); tema claro
- [X] T006 [US1] Módulo com abas: `frontend/src/admin/eventos/ModuloLayout.jsx`
      (pretítulo "Eventos" + título "Eventos e Ingressos" + abas Painel/Eventos/
      Atendimentos/Tipos via NavLink) e rotas aninhadas em
      `frontend/src/App.jsx` (`/painel` → módulo; Atendimentos→SuporteFila,
      Tipos→tipos de evento já existentes)
- [X] T007 [US1] `frontend/src/admin/eventos/ListaEventos.jsx`: tabela de
      eventos (nome/tipo/data/situação) consumindo `GET /admin/events`, com
      "Novo evento"; abrir um evento navega para `/painel/eventos/:id`
- [X] T008 [US1] `frontend/src/admin/eventos/EventoLayout.jsx`: cabeçalho fixo
      do evento (← Voltar, nome, badge de situação, Editar/Banner/Cancelar) +
      2ª camada de abas (Painel/Inscritos/Ingressos/Camisas/Cortesias/
      Patrocínio/Relatórios/Check-in/Trilha) com Outlet aninhado; rotas em
      `frontend/src/App.jsx`
- [X] T009 [US1] `frontend/src/admin/components/EventoModal.jsx`: criar/editar
      evento em modal (dados, janela de vendas, capacidade/limite, público,
      modo de preço, toggles de regras, gratuidade X→Y, observações) — reusa os
      endpoints `POST/PUT /admin/events` e `EventConfigService`
- [X] T010 [P] [US1] Reembalar nas abas do evento as telas que já existem, sem
      reescrever lógica: `Ingressos`←TiposLotes, `Cortesias`←Cortesias,
      `Patrocinio`←Patrocinios (com modal "1ª + 30 em 30"|Personalizado),
      `Trilha`←Auditoria, `Inscritos` (nova tabela, ver US-lista) — em
      `frontend/src/admin/eventos/abas/`

**Checkpoint**: ambiente com a identidade da marca e navegação em 2 camadas —
MVP visual do protótipo.

---

## Phase 3: User Story 2 - Painéis com gráficos (Priority: P2)

**Goal**: painel do módulo (consolidado) e do evento, com cards + rosca + curva
mensal; recorte por tipo de ingresso (não por loja).

**Independent Test**: quickstart.md §US2.

### Tests for User Story 2

- [X] T011 [P] [US2] Feature test `tests/Feature/Panel/OverviewTest.php`:
      `GET /admin/overview` — cards (eventos/publicados/próximos/inscritos
      ativos/receita confirmada/prevista/patrocínio pago/reembolsos) batendo
      com os dados; `eventsByStatus`; `inscriptionsByMonth` (série contígua,
      soma = inscritos); filtro `event`/`from`/`to`; treasury/gate → 403,
      anônimo → 401 (primeiro)
- [X] T012 [P] [US2] Feature test `tests/Feature/Panel/EventDashboardTest.php`:
      `GET /admin/events/{event}/dashboard` — counters + financial batendo;
      `byTicketType` (no lugar de por loja); `ticketsByStatus`; evento sem
      vendas → zeros/gráficos vazios; reflete estorno/check-in; RBAC

### Implementation for User Story 2

- [X] T013 [US2] Estender `app/Domain/Events/Services/ReportService.php`:
      `overview(?int $eventId, ?Carbon $from, ?Carbon $to)`,
      `inscriptionsByMonth(?Event, $from, $to)` (série contígua, fuso do
      evento) e `byTicketType(Event)`; `dashboard(Event)` ganha `counters`,
      `financial`, `byTicketType`, `inscriptionsByMonth`
- [X] T014 [US2] Criar `app/Http/Controllers/Api/Admin/OverviewController.php`
      (`show`) e `app/Http/Controllers/Api/Admin/EventPanelController.php`
      (`dashboard`) — shape do contrato, validação dos filtros
- [X] T015 [US2] `frontend/src/admin/eventos/PainelModulo.jsx` (cards + rosca
      eventos por situação + curva inscrições/mês, filtro evento+período) e a
      aba `frontend/src/admin/eventos/abas/PainelEvento.jsx` (contadores +
      financeiro + rosca situação dos ingressos + por tipo) usando
      DonutChart/AreaChart

**Checkpoint**: leitura gerencial por gráficos no módulo e no evento.

---

## Phase 4: User Story 3 - Check-in + presença manual (Priority: P3)

**Goal**: aba Check-in com validação QR/código, donut de presença, cards e
lista com "Registrar presença" manual por linha (reusa o ponto único).

**Independent Test**: quickstart.md §US3.

### Tests for User Story 3

- [X] T016 [P] [US3] Feature test
      `tests/Feature/Panel/EventAttendanceTest.php`:
      `GET /admin/events/{event}/attendance` escopado (contadores em pessoas,
      casal = 2, % presença, donut); presença manual pela lista =
      `POST /gate/checkin` gera 1 entrada + trilha `ticket.checked_in`; não
      elegível recusa com o mesmo motivo; busca por nome; RBAC

### Implementation for User Story 3

- [X] T017 [US3] Adicionar `attendancePayload(Event, ?string $search)` ao
      `ReportService.php` (escopado ao evento, reaproveitando o cálculo do
      GateController) e `attendance` ao `EventPanelController.php`
- [X] T018 [US3] Aba `frontend/src/admin/eventos/abas/Checkin.jsx`: validação
      (código + Ler QR via html5-qrcode já instalado + Validar), DonutChart de
      presença, 4 cards (comprados/presentes/ausentes/%), lista com busca e
      botão "Registrar presença" por linha ausente chamando
      `POST /gate/checkin`; presentes destacados com horário/operador

**Checkpoint**: portaria completa (QR + código + presença manual) no layout do
protótipo.

---

## Phase 5: User Story 4 - Camisas com estoque (Priority: P4)

**Goal**: estoque por tamanho na tela (total/vendidas/disponível), add inline,
relatório por modelo e geral.

**Independent Test**: quickstart.md §US4.

### Tests for User Story 4

- [X] T019 [P] [US4] Feature test `tests/Feature/Panel/ShirtStockTest.php`:
      `GET /admin/events/{event}/shirt-models` retorna, por tamanho,
      `stock`/`sold`/`available` (= estoque − vendidas); estoque nulo =
      ilimitado (sem disponível negativo); somatório do modelo fecha

### Implementation for User Story 4

- [X] T020 [US4] Garantir que o payload de `ShirtModelController@index`
      (ou um resource) exponha por tamanho `stockQuantity`, `soldCount` e
      `available` derivado; ajustar se faltar (sem coluna nova)
- [X] T021 [US4] Aba `frontend/src/admin/eventos/abas/Camisas.jsx`
      (reembala/atualiza a tela existente): por modelo, resumo
      total/vendidas/disponível + grade por tamanho (estoque/vendidas/
      disponível), add tamanho inline (branco = ilimitado), botões Relatório
      (modelo) e Relatório geral

**Checkpoint**: produção de camisas gerenciável pela tela com estoque visível.

---

## Phase 6: User Story 5 - Relatórios com preview (Priority: P5)

**Goal**: seletor de relatório + filtros + prévia em tabela + export .xlsx do
mesmo recorte.

**Independent Test**: quickstart.md §US5.

### Tests for User Story 5

- [X] T022 [P] [US5] Feature test `tests/Feature/Panel/ReportPreviewTest.php`:
      `GET /admin/events/{event}/reports/preview?type=` (inscritos/financeiro/
      presencas/camisas) — colunas+linhas+total; filtros (tipo, ano/mês ou
      de/até, busca); `type` inválido → 422; filtro vazio → 0 linhas;
      export `/reports/{type}.xlsx` do MESMO recorte traz as mesmas linhas
      (abrir binário com o reader do openspout); RBAC

### Implementation for User Story 5

- [X] T023 [US5] Adicionar `reportPreview(Event, string $type, array $filtros)`
      ao `ReportService.php` (colunas+linhas+total+shown, limitando exibição
      sem truncar dados) e o export escopado por evento ao
      `ReportExportService.php` (inscritos/financeiro/presencas/camisas,
      reusando os writers da 008)
- [X] T024 [US5] Adicionar `reportsPreview` e `reportsExport` ao
      `EventPanelController.php` (validação de `type` e filtros; export retorna
      o arquivo com `Content-Disposition`)
- [X] T025 [US5] Aba `frontend/src/admin/eventos/abas/Relatorios.jsx`: seletor
      de tipo + filtros (tipo de ingresso, ano/mês ou de/até, busca) + prévia
      em tabela com total ("mostrando N de M") + botão Exportar .xlsx do mesmo
      recorte

**Checkpoint**: todas as US completas — protótipo entregue.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T026 Executar `specs/009-refatoracao-telas/quickstart.md` de ponta a
      ponta contra as 14 referências (identidade azul + logo; 2 camadas de
      abas; painéis com gráficos; check-in com presença manual; camisas com
      estoque; relatórios com preview) e corrigir divergências visuais
- [X] T027 [P] Não-regressão: suítes 001–008 verdes (endpoints antigos
      intactos); build do frontend ok; nenhuma tabela/coluna nova; `.env` e
      segredos fora do VCS
- [X] T028 Atualizar `ROADMAP.md` (009 ✅) e
      `specs/009-refatoracao-telas/spec.md` (Status: Draft → Implemented)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (P1)** → US1 → US2 → US3 → US4 → US5 → Polish
- **US1 primeiro** (P1, casca): sem a navegação em 2 camadas, as demais abas
  não têm onde morar — é o MVP
- **US2–US5** consomem o `ReportService`/`EventPanelController`; cada uma toca a
  sua aba dentro do EventoLayout (US1)

### Key task-level dependencies

- T001–T004 (setup) antes de tudo; T003 (gráficos) antes de T015/T018
- T004 (rotas) antes dos controllers (T014/T017/T024)
- T013 (ReportService) antes de T014; estende-se em T017 e T023 (mesmo arquivo
  — sequenciar)
- T008 (EventoLayout) antes das abas (T015/T018/T021/T025)
- Testes [P] (T011/T012/T016/T019/T022) antes das implementações da sua US

### Parallel Opportunities

- Setup: T002 ∥ T003 (após T001)
- T010 (reembalar telas existentes) ∥ T015 (painéis) — arquivos distintos
- Cada teste [P] em paralelo com o início da sua fase (teste primeiro no back)
- T027 ∥ T028 no Polish

## Parallel Example: US2

```bash
Task: "T011 OverviewTest (teste do painel do módulo)"
Task: "T012 EventDashboardTest (teste do painel do evento)"
# depois, sequencial no ReportService: T013 → T014 → T015
```

## Implementation Strategy

**MVP = US1** (casca + identidade): entrega sozinha a transformação visual e a
navegação do protótipo. US2–US5 preenchem as abas com valor incremental,
sempre reusando o backend das 001–008 e adicionando só derivações de leitura.
Merge na `main` apenas com a suíte inteira verde (incluindo não-regressão
007/008) e os fluxos manuais das 14 referências conferidos.
