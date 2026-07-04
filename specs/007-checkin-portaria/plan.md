# Implementation Plan: Check-in da Portaria

**Branch**: `007-checkin-portaria` | **Date**: 2026-07-04 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/007-checkin-portaria/spec.md`

## Summary

A entrada do evento: `CheckinService` valida o código público com `lockForUpdate`
na linha do ticket (uma entrada exata mesmo com dois dispositivos no mesmo QR),
matriz completa de recusas com contexto (`already_used` devolve quando/quem;
`ticket_transferred` devolve o código novo), casal = 2 pessoas numa validação;
`GET /gate/attendance` com contadores derivados em pessoas e busca; painel
mobile-first no chrome existente (papel `gate` entra no `/painel`) com leitor de
câmera (`html5-qrcode`), debounce de re-leitura e resultado em tela cheia;
`SampleCheckinSeeder` popula a demo. **Nenhuma tabela nova.**

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12); JavaScript ES2022 (React 18)

**Primary Dependencies**: nova (front) — `html5-qrcode` (leitor de câmera
cross-browser, incl. Safari/iOS); backend sem novidades

**Storage**: MySQL 8 — `tickets.used_at`/`validated_by` da 001; **zero
migrations**

**Testing**: PHPUnit Feature em `app_test`; corrida coberta pela guarda
terminal sob lock (validações sequenciais do mesmo código)

**Target Platform**: painel usado em CELULAAR na porta do evento (mobile-first);
câmera exige localhost/HTTPS

**Project Type**: web application — API + SPA existentes

**Performance Goals**: validação < 2s ponta a ponta (SC-001); tickets
diferentes não se serializam (lock por linha)

**Constraints**: `used` terminal sem desfazer; recusa nunca altera estado;
mensagens pt-BR com contexto; papéis gate+admin

**Scale/Scope**: 2 endpoints, 1 tela (2 abas), 1 seeder dev, 0 migrations,
3 user stories

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação |
|---|---|
| I. Standalone, RBAC | ✅ `require.role:gate,admin`; terceiro papel no chrome do painel (padrão da 005). |
| II. Estado derivado + corrida | ✅ Presenças 100% derivadas (pessoas via join, mesmo cálculo da 004); marcação atômica sob lock de linha. |
| III. Ponto único de baixa | ✅ N/A — nenhum dinheiro; check-in não toca pagamentos. |
| IV. Segurança | ✅ Valida pelo código público (nunca id); recusa não vaza dados de terceiros (contexto mínimo); sem credencial nova. |
| V. Histórico | ✅ `used` terminal com momento+operador; sem desfazer (ocorrência → suporte da 006); recusas não alteram nada. |
| VI. Specs por área | ✅ Consome guardas das 004/006 (código, transferido); estatísticas históricas delegadas à 008. |
| Stack e convenções | ✅ `html5-qrcode` é utilitário de câmera do front — stack intacta. |

**Resultado**: PASS (pré-Phase 0 e pós-Phase 1). Sem entradas em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/007-checkin-portaria/
├── plan.md              # Este arquivo
├── research.md          # 7 decisões
├── data-model.md        # 0 tabelas — matriz de validação + derivações
├── quickstart.md        # validação por user story
├── contracts/gate-api.md
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks (próximo passo)
```

### Source Code (repository root)

```text
app/
├── Domain/Events/Services/CheckinService.php   # checkIn(code, operator) — lock + matriz
├── Http/Controllers/Api/Gate/GateController.php # checkin + attendance
database/seeders/SampleCheckinSeeder.php         # ~30 confirmados p/ demo (dev)
routes/api.php                                   # /gate/* (require.role:gate,admin)
tests/Feature/Gate/{CheckinTest,AttendanceTest}.php
frontend/src/
├── admin/pages/Checkin.jsx                      # abas Leitor (html5-qrcode) + Presenças
├── admin/AdminLayout.jsx                        # + item Check-in (gate, admin)
├── auth/… (RoleRoute já aceita roles=[])        # /painel ganha o papel gate
└── App.jsx                                      # rota /painel/checkin; PainelHome p/ gate
```

**Structure Decision**: service no domínio (mesma casa das demais regras);
controller isolado em `Api/Gate`; uma tela com duas abas (operação de um
dispositivo só).

## Fases de implementação (visão para /speckit-tasks)

1. **Setup**: `npm install html5-qrcode`; rotas `/gate/*`.
2. **US1 — validação**: CheckinService (matriz + lock) + GateController@checkin;
   testes da matriz completa + corrida + trilha.
3. **US2 — painel**: Checkin.jsx (leitor + manual + resultado em tela cheia +
   debounce), papel gate no /painel, menu e PainelHome; validação manual.
4. **US3 — presenças**: attendance (contadores em pessoas + busca) + aba
   Presenças; testes.
5. **Seeder**: SampleCheckinSeeder + DatabaseSeeder (dev).
6. **Polish**: quickstart manual, suítes anteriores, ROADMAP/status.

## Complexity Tracking

Sem violações constitucionais a justificar.
