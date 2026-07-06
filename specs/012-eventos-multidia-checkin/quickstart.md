# Quickstart — Eventos multi-dia e check-in por dia (spec 012)

Guia de validação end-to-end. Detalhes em [data-model.md](./data-model.md) e [contracts/multiday-api.md](./contracts/multiday-api.md).

## Pré-requisitos

- Dev no ar: `make up`, API `docker compose --profile dev up -d api` (:8000), Vite `npm run dev` (:5173).
- Migrations aplicadas: `docker compose run --rm php php artisan migrate` (cria `event_days`, `ticket_day_checkins` e faz o backfill de 1 dia dos eventos existentes).
- Usuários: `admin@dev.local` (admin) e `portaria@dev.local` (gate) — senha `password`.

## Fluxo manual (UI)

1. **Compatibilidade 1 dia**: abrir um evento existente → a duração aparece como **1 dia** (data = data principal). Fazer check-in de um ingresso na portaria continua funcionando igual (marca presença; ingresso vira "utilizado").
2. **Configurar 2 dias**: em Editar evento, mudar **Duração** para "2 dias", informar Data do Dia 1 e Dia 2 (e rótulos opcionais) → salvar. Tentar salvar sem a data do Dia 2 → recusa (422).
3. **Check-in por dia (portaria)**: em `/painel/checkin` → escolher o evento → aparecem os **cards de dia** (Dia 1/Dia 2 com data e situação); o dia de hoje vem destacado. Selecionar **Dia 1**, ler/validar um ingresso → presença no Dia 1. Ler o mesmo ingresso de novo no Dia 1 → aviso "Participante já possui check-in registrado neste dia" com data/hora/operador. Selecionar **Dia 2** e ler o mesmo ingresso → presença no Dia 2 (independente).
4. **Finalizar dia**: finalizar o **Dia 1** → novas leituras no Dia 1 são recusadas ("dia finalizado"); o **Dia 2** segue aceitando.
5. **Reabrir dia (admin)**: como `portaria@dev.local`, tentar reabrir o Dia 1 → negado (403). Como `admin@dev.local`, reabrir com **justificativa** → Dia 1 volta a aceitar; histórico registra quem/quando/justificativa. Finalizar de novo.
6. **Relatórios**: na aba Relatórios do evento → presença **por dia** (presentes/ausentes/%); consolidado (todos os dias / parcial / nenhum); detalhe individual com o check-in de cada dia. Exportar xlsx/pdf.

## Verificações automatizadas (Feature tests, `app_test`)

Rodar: `docker compose run --rm php php artisan test --filter=Multiday`

- **EventDaysTest**
  - evento novo nasce com 1 dia = data principal; migração cria 1 dia para eventos existentes (SC-001).
  - upsert de 2/3 dias com datas obrigatórias/distintas; recusa remover dia com check-ins (409 `day_has_checkins`).
- **DayCheckinTest**
  - presença registrada só no dia selecionado; mesmo ingresso marca Dia 1 e Dia 2 independentemente (SC-002).
  - 2ª leitura no mesmo dia → 409 `already_checked_in_day` com data/hora/operador, sem duplicar (SC-003).
  - ingresso inapto (cancelado/estornado/transferido/não pago) recusado; evento cancelado recusado.
  - evento de 1 dia: check-in espelha `used_at`/`validated_by`/status `used` (compat).
- **DayFinalizeReopenTest**
  - finalizar bloqueia novo check-in/edição/exclusão naquele dia; dias posteriores seguem (SC-004).
  - reabrir só admin + justificativa obrigatória; log grava quem/quando/dia/justificativa (SC-005).
- **AttendanceReportTest**
  - por dia (presentes/ausentes/%), consolidado (todos/parcial/nenhum) e individual por dia coerentes com os check-ins (SC-006).

## Critérios de aceite (mapeados à spec)

- SC-001: migração + criação sempre com 1 dia; sem regressão no check-in de 1 dia.
- SC-002/SC-003: presença por dia isolada; sem duplicata por (ingresso, dia).
- SC-004/SC-005: finalização congela; reabertura restrita e auditada.
- SC-006: relatórios por dia + consolidado + individual.
- SC-007: dia selecionado sempre visível na tela de check-in.
