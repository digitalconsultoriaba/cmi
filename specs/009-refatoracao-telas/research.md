# Research — 009-refatoracao-telas

## Decisão 1 — Biblioteca de gráficos: `react-apexcharts` + `apexcharts`

**Decision**: adotar `apexcharts` com o wrapper `react-apexcharts` para a rosca
(situação) e a curva de área (inscrições por mês).

**Rationale**: o próprio dashboard do Tabler (tema já usado no projeto,
`@tabler/core`) usa ApexCharts — a rosca e a área com preenchimento das
referências são exatamente o visual padrão da lib, então batemos o protótipo
com o mínimo de CSS. Cores alinhadas às variáveis do Tabler
(`--tblr-primary`, success, danger, warning).

**Alternativas consideradas**: `recharts` (bom, mas o preenchimento de área e a
rosca ficariam com aparência diferente da referência); Chart.js (canvas, menos
integrado ao Tabler); SVG à mão (reinventaria a roda).

## Decisão 2 — Identidade visual: sidebar azul + logo, tema claro fixo

**Decision**: `navbar-vertical` do Tabler com fundo **azul da marca** (variável
`--tblr-primary` sobrescrita para o azul CMI/GLMEES), logo `public/logo.png` no
topo, `data-bs-theme="light"` fixo no ambiente administrativo. O menu lista só
"Eventos e Ingressos" como item navegável; um agrupamento visual pode exibir
rótulos do sistema anfitrião como identidade, **sem rota** (não navegam).

**Rationale**: FR-001/FR-013 — identidade da marca sem tema escuro e sem imitar
navegação de outro sistema. A logo precisa ser servível pela SPA: copiar para
`frontend/public/logo.png` (dev via Vite) — em produção o build entra no
`public/` do Laravel.

**Alternativas consideradas**: tema escuro alternável (recusado pelo usuário);
replicar todo o menu anfitrião (fora de escopo, standalone).

## Decisão 3 — Navegação em duas camadas com React Router aninhado

**Decision**: rota `/painel` → módulo "Eventos e Ingressos" com **abas** (Painel,
Eventos, Atendimentos, Tipos) via `NavLink`; a aba Eventos lista e leva a
`/painel/eventos/:eventId` → **segunda camada** de abas do evento (Painel,
Inscritos, Ingressos, Camisas, Cortesias, Patrocínio, Relatórios, Check-in,
Trilha) com um cabeçalho fixo do evento (Outlet aninhado).

**Rationale**: FR-002/FR-003 — o Router já é a ferramenta (v7 no projeto);
rotas aninhadas dão o cabeçalho fixo + troca de conteúdo sem sair do evento.
Reaproveita `RoleRoute` para o escopo por papel (FR-013).

**Alternativas consideradas**: abas por estado local sem rota (perde deep-link e
voltar do navegador); uma SPA de rota única (não escala às 9 abas).

## Decisão 4 — Endpoints escopados por evento (leitura), reuso do domínio

**Decision**: adicionar endpoints **somente-leitura por evento** consumindo um
`ReportService` estendido — sem tabelas novas, sem tocar regras de escrita:
- `GET /admin/overview` — painel do módulo (consolidado de todos os eventos).
- `GET /admin/events/{event}/dashboard` — painel do evento.
- `GET /admin/events/{event}/attendees` — lista de inscritos (busca/filtros).
- `GET /admin/events/{event}/attendance` — lista de check-in + contadores.
- `GET /admin/events/{event}/reports/preview` — prévia de relatório.
- `GET /admin/events/{event}/reports/{type}.xlsx` — export escopado por evento.
Os endpoints single-event da 008 (`/admin/dashboard`, `/gate/attendance`,
`/admin/reports/*.xlsx`) **permanecem** para não quebrar as suítes 007/008.

**Rationale**: as referências mostram tudo dentro de um evento específico; o
app pode ter vários eventos (a lista existe). Escopar por evento é correção
natural. Manter os antigos evita regressão (SC-007). Derivações novas no
`ReportService`: `overview()`, `inscriptionsByMonth()`, `byTicketType()`,
`attendeesList()`, `attendancePayload(Event)`, `reportPreview()`.

**Alternativas consideradas**: reescopar os endpoints da 008 (quebraria testes
007/008 sem ganho); um controller gigante (preferi um `EventPanelController`
enxuto + `ReportService`).

## Decisão 5 — Presença manual reusa o ponto único de check-in

**Decision**: o botão "Registrar presença" da lista chama o **mesmo**
`POST /gate/checkin` `{code}` — nenhuma via paralela. A mesma régua (só
elegíveis, casal = 2, terminal), a mesma trilha (`ticket.checked_in` da 008) e
o mesmo lock por linha.

**Rationale**: FR-009 — presença manual não pode burlar a validação; o
`CheckinService` já faz tudo. `require.role:gate,admin` já cobre o admin na
tela do evento.

**Alternativas consideradas**: endpoint novo de presença (duplicaria regra e
trilha, risco de divergência).

## Decisão 6 — Séries temporais em SQL agrupado por mês (fuso do evento)

**Decision**: `inscriptionsByMonth` agrupa ingressos elegíveis por mês da
**compra** (`created_at` convertido ao fuso do evento), retornando série
contígua (meses sem venda = 0) para a curva. `byTicketType` agrupa por tipo.

**Rationale**: FR-005/FR-007 — derivado na consulta; fuso do evento coerente
com a 008 (`events.timezone`). Série contígua evita curva "buraco".

**Alternativas consideradas**: agrupar por `paid_at` (curva de caixa, não de
inscrição); janela fixa de 12 meses (a referência mostra ~12 meses — adoto
janela pela faixa real dos dados, com no mínimo os últimos 12 meses).

## Decisão 7 — Multi-loja fora: recorte por tipo de ingresso

**Decision**: onde a referência mostrava recorte por loja ("Inscrições por
Loja", ranking, coluna Loja, "Eventos das lojas"), usar **tipo de ingresso** —
a coluna Loja sai da tabela de inscritos, o gráfico "por loja" vira "por tipo
de ingresso", e a aba "Eventos das lojas" não existe.

**Rationale**: FR-014 — não há modelo de lojas nesta plataforma; introduzir
seria mudança estrutural (fora de escopo, decisão do usuário).

**Alternativas consideradas**: introduzir entidade Loja (rejeitado no
/speckit-specify).

## Decisão 8 — Reorganizar telas existentes sem reescrever a lógica

**Decision**: as abas Inscritos, Ingressos, Cortesias, Patrocínio, Trilha,
Camisas e Landing reaproveitam os componentes/telas já existentes
(`TiposLotes`, `Camisas`, `Cortesias`, `Patrocinios`, `Auditoria`,
`SuporteFila`), reembalados no novo layout de abas do evento e apontando para
os endpoints escopados por evento. Editar/Criar evento em **modal** (FR-004).

**Rationale**: FR-012/SC-007 — zero regressão; o valor é a reorganização e a
identidade, não reescrever regra que já funciona e é testada.

**Alternativas consideradas**: reescrever cada tela do zero (risco alto, sem
ganho funcional).
