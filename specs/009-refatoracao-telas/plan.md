# Implementation Plan: Refatoração das Telas (identidade e navegação por abas)

**Branch**: `009-refatoracao-telas` | **Date**: 2026-07-04 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/009-refatoracao-telas/spec.md`

## Summary

Reorganização do ambiente administrativo seguindo o protótipo aprovado (14
referências): identidade **CMI/GLMEES** (sidebar azul + logo, tema claro fixo) e
navegação em **duas camadas de abas** (módulo "Eventos e Ingressos" → evento),
com React Router aninhado e cabeçalho fixo do evento. Painéis com **gráficos**
(rosca + curva mensal) via ApexCharts; check-in com presença manual pela lista
(reusa o ponto único de check-in); camisas mostrando estoque por tamanho
(dado já existente); relatórios com **preview** antes do export. Backend:
apenas endpoints **somente-leitura escopados por evento** + derivações novas no
`ReportService` — **zero tabelas, zero colunas, zero regressão** (endpoints da
008 permanecem). Multi-loja fora: recortes "por loja" viram "por tipo de
ingresso".

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12); JavaScript ES2022 (React 18)

**Primary Dependencies**: nova (frontend) — `apexcharts` + `react-apexcharts`
(rosca e curva do protótipo, alinhadas ao Tabler); backend sem novas
dependências

**Storage**: MySQL 8 — **nenhuma migration**; tudo derivado na consulta

**Testing**: PHPUnit Feature em `app_test` (endpoints de painel escopados por
evento, invariantes do data-model, não-regressão 007/008); build do frontend

**Target Platform**: navegador desktop (organização); check-in também em
celular (herda a 007)

**Project Type**: web application — API + SPA existentes

**Performance Goals**: painel < 10s do login à resposta (herda SC da 008);
gráficos derivados na consulta; preview limita exibição sem truncar o export

**Constraints**: tema claro fixo (sem dark); identidade da marca; sem modelo de
lojas (FR-014); presença manual não burla a régua da portaria (FR-009); sem
regressão das capacidades 001–008 (SC-007)

**Scale/Scope**: ~6 endpoints de leitura novos, 1 lib de gráfico, reorganização
de ~12 telas em 2 camadas de abas, 5 user stories, 0 migrations

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação |
|---|---|
| I. Standalone, RBAC | ✅ Reforçado: identidade própria, sem navegação do sistema anfitrião; `RoleRoute`/`require.role` preservam o escopo por papel; nenhum papel novo. |
| II. Estado derivado + corrida | ✅ Todos os painéis/gráficos derivados na consulta; nenhuma coluna/contador novo; leituras puras. |
| III. Ponto único de baixa | ✅ N/A financeiro; presença manual reusa o `CheckinService` (ponto único), não cria via paralela. |
| IV. Segurança | ✅ Só leitura atrás de papel; nenhum dado sensível novo; logo é asset público. |
| V. Histórico | ✅ Presença manual escreve a MESMA trilha (`ticket.checked_in`) da 008; nada apagado. |
| VI. Specs por área | ⚠️→✅ É spec de **apresentação transversal** (consome 001–008 sem alterar regra). Justificada: só reorganiza UI e adiciona derivações de leitura; não duplica nem reescreve domínio. Sem violação material. |
| Stack e convenções | ✅ ApexCharts é utilitário de gráfico do front (Tabler já o usa); API mantém `{ data }` camelCase e erros padrão. |

**Resultado**: PASS (pré-Phase 0 e pós-Phase 1). Sem entradas em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/009-refatoracao-telas/
├── plan.md              # Este arquivo
├── research.md          # 8 decisões
├── data-model.md        # 0 tabelas — derivações de leitura
├── quickstart.md        # validação por user story + 14 referências
├── contracts/panel-api.md
├── referencias/         # 14 imagens + INDEX.md (contrato visual)
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks (próximo passo)
```

### Source Code (repository root)

```text
app/
├── Domain/Events/Services/ReportService.php        # + overview, dashboard(Event),
│                                                    #   inscriptionsByMonth, byTicketType,
│                                                    #   attendeesList, attendancePayload,
│                                                    #   reportPreview (derivações)
├── Domain/Events/Services/ReportExportService.php   # + export escopado por evento + camisas
├── Http/Controllers/Api/Admin/
│   ├── OverviewController.php                        # GET /admin/overview
│   └── EventPanelController.php                      # dashboard/attendees/attendance/
│                                                     #   reports.preview/reports.{type}.xlsx (por evento)
routes/api.php                                        # rotas de leitura escopadas por evento
tests/Feature/Panel/{OverviewTest,EventDashboardTest,
      AttendeesTest,EventAttendanceTest,ReportPreviewTest}.php
frontend/
├── public/logo.png                                  # copiada de public/logo.png (servível pela SPA)
├── src/theme/                                        # azul da marca (--tblr-primary), tema claro
├── src/admin/AdminLayout.jsx                         # sidebar azul + logo + item único navegável
├── src/admin/eventos/                                # módulo: TabsModulo (Painel/Eventos/Atend./Tipos)
│   ├── PainelModulo.jsx                              # cards + rosca + curva (ApexCharts)
│   ├── ListaEventos.jsx                              # listagem + Novo evento (modal)
│   └── EventoLayout.jsx                              # cabeçalho fixo + 2ª camada de abas
├── src/admin/eventos/abas/                           # Painel, Inscritos, Ingressos, Camisas,
│   │                                                 #   Cortesias, Patrocinio, Relatorios,
│   │                                                 #   Checkin, Trilha (reembala telas existentes)
├── src/admin/components/{DonutChart,AreaChart,EventoModal}.jsx
└── src/App.jsx                                       # rotas aninhadas /painel → evento/:id
```

**Structure Decision**: derivações concentradas no `ReportService` (tela e
export nunca divergem — herança da 008); um `EventPanelController` enxuto para
o escopo por evento; frontend em duas camadas de abas com Router aninhado,
reembrulhando as telas existentes (sem reescrever regra).

## Fases de implementação (visão para /speckit-tasks)

1. **Setup**: `npm i apexcharts react-apexcharts`; copiar logo p/
   `frontend/public`; tema azul (variáveis Tabler) + tema claro fixo; rotas de
   leitura escopadas por evento em `routes/api.php`.
2. **US1 — casca + identidade** (P1, MVP): AdminLayout azul com logo e item
   único; Router aninhado (módulo → evento) com cabeçalho fixo; ListaEventos +
   EventoModal (criar/editar); reembalar as abas que só reorganizam telas
   existentes (Ingressos, Cortesias, Patrocínio, Trilha, Camisas, Inscritos).
3. **US2 — painéis com gráficos**: `overview` + `dashboard(Event)` +
   `inscriptionsByMonth`/`byTicketType` no `ReportService`; controllers;
   PainelModulo + Painel do evento com DonutChart/AreaChart; testes.
4. **US3 — check-in + presença manual**: `attendancePayload(Event)` +
   endpoint; aba Check-in (validação + donut + cards + lista com "Registrar
   presença" via `/gate/checkin`); testes (mesma régua/trilha).
5. **US4 — camisas com estoque**: aba Camisas exibindo estoque/vendidas/
   disponível por tamanho + add inline + relatório; testes de disponível.
6. **US5 — relatórios com preview**: `reportPreview` + export escopado
   (inscritos/financeiro/presencas/camisas); aba Relatórios (seletor + filtros
   + prévia + export); testes prévia≡export.
7. **Polish**: quickstart das 14 referências, não-regressão 007/008, build do
   front, ROADMAP/status.

## Complexity Tracking

Sem violações constitucionais a justificar (o item VI é apresentação
transversal, não duplicação de domínio — ver Constitution Check).
