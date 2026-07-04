# Tasks: Check-in da Portaria

**Input**: Design documents from `/specs/007-checkin-portaria/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md,
contracts/gate-api.md, quickstart.md — e as specs 001–006 mergeadas.

**Tests**: INCLUÍDOS — exigência da constituição; a matriz de validação inteira
vira teste.

**Organization**: agrupado por user story; **nenhuma migration nova**.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizável (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1–US3 (mapeia para spec.md)

## Path Conventions

Service em `app/Domain/Events/Services/`; controller em
`app/Http/Controllers/Api/Gate/`; testes em `tests/Feature/Gate/`.

---

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Instalar `html5-qrcode` no frontend (`npm install html5-qrcode
      --prefix frontend`)
- [X] T002 [P] Adicionar rotas em `routes/api.php`: grupo `/gate` com
      `auth:sanctum` + `require.role:gate,admin` — `POST /gate/checkin` e
      `GET /gate/attendance` (controller da fase seguinte)

---

## Phase 2: User Story 1 - Validar o ingresso na entrada (Priority: P1) 🎯 MVP

**Goal**: matriz de validação completa, atômica, com trilha e contexto nas
recusas.

**Independent Test**: quickstart.md §US1.

### Tests for User Story 1

- [X] T003 [P] [US1] Feature test em `tests/Feature/Gate/CheckinTest.php`
      cobrindo a matriz do data-model: confirmado/pago/cortesia → 200 marca
      used com used_at/validated_by e payload (participantName, seats); casal →
      seats=2 com companionName; código com espaços/minúsculas normalizado;
      used → 409 already_used com usedAt/validatedBy nos errors; cancelado →
      409 ticket_cancelled; transferido → 409 ticket_transferred com
      transferredToCode; reservado → 409 not_paid; inexistente → 404; evento
      cancelado → 409 event_cancelled; dupla validação → exatamente 1 entrada;
      recusa nunca altera estado; attendee/anônimo → 403/401; admin → 200

### Implementation for User Story 1

- [X] T004 [US1] Criar `app/Domain/Events/Services/CheckinService.php`:
      `checkIn(string $code, User $operator)` — normalização, transação com
      `lockForUpdate` na linha do ticket, matriz de guardas (types e contexto
      do data-model via DomainRuleViolation com errors), marcação
      used_at/validated_by + transitionTo(used)
- [X] T005 [US1] Criar `app/Http/Controllers/Api/Gate/GateController.php`
      método `checkin` (valida `{code}` obrigatório, retorna payload de sucesso
      do contrato)

**Checkpoint**: validação completa e blindada — MVP da portaria.

---

## Phase 3: User Story 2 - Painel com leitor de QR (Priority: P2)

**Goal**: leitor de câmera + digitação manual, resultado em tela cheia,
mobile-first, papel gate no painel.

**Independent Test**: quickstart.md §US2.

### Implementation for User Story 2

- [X] T006 [US2] Criar `frontend/src/admin/pages/Checkin.jsx` — aba **Leitor**:
      html5-qrcode (start/stop da câmera), campo de digitação manual
      equivalente, resultado em tela cheia (~2,5s; verde: nomes/tipo/"2
      pessoas" — vermelho: motivo + contexto usedAt/validatedBy/
      transferredToCode), debounce de 5s por código repetido, aviso de falha de
      rede, layout mobile-first
- [X] T007 [US2] Integrar o papel gate ao painel: `frontend/src/App.jsx`
      (RoleRoute do /painel ganha 'gate'; rota `/painel/checkin` para
      gate+admin; `PainelHome` cai no Checkin para gate-only) e
      `frontend/src/admin/AdminLayout.jsx` (item "Check-in" roles gate+admin)

**Checkpoint**: portaria operável no navegador/celular.

---

## Phase 4: User Story 3 - Lista de presentes e ausentes (Priority: P3)

**Goal**: contadores em pessoas + lista com busca.

**Independent Test**: quickstart.md §US3.

### Tests for User Story 3

- [X] T008 [P] [US3] Feature test em `tests/Feature/Gate/AttendanceTest.php`:
      contadores em PESSOAS (casal used soma 2 em presentes; esperados somam
      assentos dos elegíveis); cancelado/transferido/reservado-expirado fora
      dos esperados; presentes com usedAt/validatedBy; busca por participante,
      acompanhante e código; attendee → 403

### Implementation for User Story 3

- [X] T009 [US3] Adicionar `attendance` ao
      `app/Http/Controllers/Api/Gate/GateController.php` (elegíveis + somas de
      assentos + busca LIKE, shape do contrato) e a aba **Presenças** em
      `frontend/src/admin/pages/Checkin.jsx` (contadores grandes, lista com
      situação/horário/operador, campo de busca, botão atualizar)

**Checkpoint**: todas as US completas.

---

## Phase 5: Polish & Cross-Cutting Concerns

- [X] T010 Criar `database/seeders/SampleCheckinSeeder.php` (dev): ~30
      ingressos confirmados no evento demo via pedidos pagos (individuais + 2
      casais + 2 cortesias; ~5 já used com validated_by = portaria dev) e
      registrar no `database/seeders/DatabaseSeeder.php` (fora de produção,
      após o SampleEventSeeder)
- [X] T011 Executar `specs/007-checkin-portaria/quickstart.md` de ponta a ponta
      (suíte + fluxo manual: digitar código do seed → verde; repetir →
      vermelho com horário; presenças com busca) e corrigir o que falhar
- [X] T012 [P] Varredura: suítes 001–006 verdes; build do frontend ok; nenhuma
      coluna nova
- [X] T013 Atualizar `ROADMAP.md` (007 ✅) e
      `specs/007-checkin-portaria/spec.md` (Status: Draft → Implemented)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** → US1 → US2/US3 → Polish
- **US1**: primeira — service e endpoint que tudo consome
- **US2** e **US3**: independentes entre si após a US1 (US3 toca o mesmo
  controller/página — sequenciar T009 após T005/T006)
- **Polish**: por último

### Key task-level dependencies

- T004 (service) antes de T005; T005 antes de T009 (mesmo controller)
- T006 antes de T009 (mesma página Checkin.jsx)
- T003/T008 (testes) antes das implementações correspondentes

### Parallel Opportunities

- Setup: T001 ∥ T002
- T003 ∥ T004 (teste primeiro); T008 em paralelo com US2
- T012 ∥ T013 no Polish

## Parallel Example: pós-US1

```bash
Task: "US2 painel (T006–T007)"
Task: "T008 teste de presenças"
```

## Implementation Strategy

**MVP first**: Fases 1–2 (US1) entregam a validação completa testável via API.
US2 dá a interface da fila; US3 o apoio; o seeder fecha a demo. Merge na
`main` só com a suíte inteira verde e o fluxo manual do leitor validado.
