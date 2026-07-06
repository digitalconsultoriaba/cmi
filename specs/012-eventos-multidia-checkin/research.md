# Research — Eventos multi-dia e check-in por dia (spec 012)

Decisões técnicas de implementação e como estender o que já existe (specs 003/007/008). Sem `NEEDS CLARIFICATION` pendente (a spec resolveu o produto; aqui ficam as escolhas de execução).

## D1 — Modelo de dias: tabela `event_days` (autoritativa)

- **Decisão**: cada evento tem 1..3 `event_days` (número do dia, data, horário início/fim opcionais, rótulo opcional, colunas de finalização/bloqueio). A "duração" é a **contagem de dias** (não uma coluna redundante em `events`).
- **Rationale**: os dias precisam de data/horário/rótulo próprios e de situação/finalização individuais — impossível manter só numa coluna do evento. Contagem como duração evita divergência entre "duration_days" e o nº real de dias.
- **Alternativas rejeitadas**: (a) guardar as datas dos dias num JSON no evento — perde histórico/finalização por dia e consultas de presença por dia; (b) coluna `duration_days` + dias — duas fontes de verdade.

## D2 — Presença: tabela `ticket_day_checkins`, única por (ingresso, dia)

- **Decisão**: cada presença é uma linha `(event_id, event_day_id, ticket_id, checked_in_at, operator_id, origin, note)`, **única por (ticket_id, event_day_id)**. Unicidade garantida **no serviço sob `lockForUpdate`** (mesmo padrão do `CheckinService` atual), pois soft delete impede um índice único simples.
- **Rationale**: presença é por dia; o registro carrega operador/origem/observação exigidos pela spec. Lock no serviço mantém "uma entrada por leitura" sem corrida, como hoje.
- **Alternativas rejeitadas**: reusar `tickets.used_at` para vários dias — impossível (é um único carimbo). Índice único físico com soft delete — MySQL não tem índice parcial; unicidade fica no serviço.

## D3 — Compatibilidade com 1 dia (não quebrar o atual)

- **Decisão**: no check-in, sempre cria a linha em `ticket_day_checkins`. **Se o evento tem 1 dia**, também espelha no ingresso o comportamento atual: `used_at`, `validated_by` e transição para status `used`. **Se multi-dia**, o ingresso **não** vira `used` global (permanece pago/confirmado/cortesia), para poder ser lido nos demais dias; a presença vive só nos day-checkins.
- **Rationale**: preserva 100% do comportamento de 1 dia (comprovante "já utilizado", contagem, telas antigas) e habilita multi-dia sem `used` global.
- **Impacto no `CheckinService`**: novo método `checkInDay(code, eventDay, operator, origin, note)`. O antigo `checkIn(code, operator)` passa a resolver o dia (para 1-dia: o único dia; genérico exige dia). Recusas atuais (cancelado/estornado/transferido/não pago/evento cancelado) permanecem; some a recusa global "already_used" — vira "já tem check-in **neste dia**".

## D4 — Situação do dia: derivada + colunas de ação auditada

- **Decisão**: `event_days` guarda `finalized_at`/`finalized_by`, `blocked_at`/`blocked_by`, `reopened_at`/`reopened_by`/`reopen_reason` (auditoria de ações). A **situação exibida** é derivada: `finished` se `finalized_at`; senão `blocked` se `blocked_at`; senão `in_progress` se há check-ins; senão `open`.
- **Rationale**: constituição II — situação derivada; colunas só para as ações auditáveis (análogo a `cancelled_at`, princípio V). A reabertura limpa `finalized_at` e grava o ciclo no log/colunas de reabertura.
- **Alternativas rejeitadas**: coluna `status` editável — violaria II e arriscaria divergência.

## D5 — Guardas de finalização/bloqueio no check-in

- **Decisão**: `checkInDay` recusa quando o dia está **finalizado** (`finalized_at` não nulo) ou **bloqueado** (`blocked_at` não nulo) → `DomainRuleViolation` (409) com tipo próprio (`day_finished` / `day_blocked`). Exclusão/edição de check-in de dia finalizado também recusa.
- **Rationale**: FR-014/FR-019. Mantém integridade do fechamento.

## D6 — Permissões (RBAC de 4 papéis)

- **Decisão**: check-in e finalizar dia → `gate`/`admin` (grupo da portaria + painel). Reabrir dia → **só `admin`** (middleware `require.role:admin`), com justificativa obrigatória. Gerir dias/duração (cadastro) → `admin`/`treasury` (grupo do painel do evento).
- **Rationale**: mapeia o "perfil autorizado" da spec aos papéis existentes; sem papel novo (constituição I).

## D7 — Endpoints (estender portaria + painel)

- **Decisão**:
  - **Portaria** (`/gate`): `GET /gate/events` passa a incluir os dias (com situação e contagem); `POST /gate/checkin` recebe `day` (event_day_id) + `origin`; `GET /gate/attendance?event=&day=` escopa por dia.
  - **Painel do evento** (`/admin/events/{event}`): `GET /days`, `PUT /days` (upsert duração+datas), `POST /days/{day}/finalize`, `POST /days/{day}/reopen` (só admin), `POST /days/{day}/block` (opcional); `GET /attendance?day=`; `GET /attendance-report` (por dia + consolidado).
- **Rationale**: reusa os pontos de entrada já existentes das specs 007/008/009; convenções de erro/roles preservadas.

## D8 — Criação do Dia 1 e migração

- **Decisão**: ao **criar** um evento, cria automaticamente o Dia 1 a partir de `starts_at` (data) + horários (`starts_at`/`ends_at`). Na **migração**, um backfill cria 1 `event_day` para cada evento existente. Ao editar a duração para 2/3, o `EventDayService` faz upsert dos dias (adiciona/edita/remove), **recusando** remover dia com check-ins (FR-005).
- **Rationale**: garante SC-001 (todos viram 1 dia) e o padrão "sempre há pelo menos 1 dia".
- **Onde criar o Dia 1**: `EventObserver@created` (ou no serviço de criação de evento) — idempotente (só cria se não houver dias).

## D9 — Relatórios de presença por dia (estende spec 008)

- **Decisão**: `AttendanceReportService` deriva, a partir de `ticket_day_checkins`: por dia (presentes/ausentes/percentual) e consolidado (presentes em **todos** os dias, **parcial**, **nenhum**); e o **detalhe individual** por ingresso com o check-in de cada dia (sim/não, data, hora, operador). A aba Relatórios ganha o tipo "presenças por dia"; export xlsx/pdf reusa openspout/dompdf.
- **Rationale**: FR-021..FR-024. Fonte única (day-checkins) para prévia e planilha.

## D10 — Frontend

- **Decisão**: `EventoModal` ganha o seletor de **duração** (1/2/3) e, para 2/3, os campos de **data/horário/rótulo por dia**. As telas de check-in (`CheckinEvento` admin e `Checkin` portaria) ganham **cards/abas de dia** (número, data, situação, nº de check-ins, botão operar) + **Finalizar dia** e (admin) **Reabrir**; o dia de hoje vem destacado; o dia selecionado fica sempre visível. Componente `DiasEvento` reutilizável entre as duas telas.
- **Rationale**: FR-008/FR-020, experiência da seção 11 da descrição; segue os padrões de UI já usados (cards Tabler, modais, react-query).

## D11 — Validação e integridade

- **Decisão**: FormRequests para upsert de dias (1–3 dias; cada dia com data obrigatória e distinta; horários opcionais `HH:MM`; rótulo ≤ 60) e para reabertura (justificativa obrigatória ≤ 500). Erros: 422 validação, 409 regra (duplicidade por dia, dia finalizado/bloqueado, remover dia com presença), 403 papel/escopo.
- **Rationale**: alinha ao padrão de erros de domínio do projeto.
