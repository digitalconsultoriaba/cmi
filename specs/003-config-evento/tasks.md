# Tasks: Configuração do Evento (Admin)

**Input**: Design documents from `/specs/003-config-evento/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md,
contracts/admin-api.md, quickstart.md — e as specs 001/002 mergeadas.

**Tests**: INCLUÍDOS — exigência da constituição; uploads com `Storage::fake()`.

**Organization**: agrupado por user story; **nenhuma migration nova**.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizável (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1–US6 (mapeia para spec.md)

## Path Conventions

Laravel na raiz + SPA em `frontend/`. Controllers admin em
`app/Http/Controllers/Api/Admin/`; testes em `tests/Feature/Admin/`; telas em
`frontend/src/admin/`.

---

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Instalar `@tabler/core` no frontend (`npm install @tabler/core
      --prefix frontend`) e importar o CSS em `frontend/src/main.jsx` (apenas
      para as rotas do painel — import estático é aceitável no MVP)
- [X] T002 [P] Adicionar `php artisan storage:link` ao alvo `install` do
      `Makefile` (e executá-lo no ambiente atual)
- [X] T003 [P] Criar `frontend/src/lib/money.js` com `parseMoney` (aceita
      "1.234,56" → "1234.56") e `formatMoney` (string decimal → "R$ 1.234,56")

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: casca de rotas, guarda de papel no front e layout do painel —
bloqueiam todas as US.

**⚠️ CRITICAL**: nenhuma US começa antes desta fase terminar.

- [X] T004 Criar grupo de rotas `/api/admin` em `routes/api.php`
      (`auth:sanctum` + `require.role:admin`): events (index/show/update/publish/
      cancel/banner), event-types, e recursos aninhados em
      `events/{event}` — ticket-types (+reorder), lots (+reorder), shirt-models,
      shirt-models/{model}/sizes, landing-blocks (+reorder), courtesy-vouchers
      (+generate/distribute), sponsorships (+installments/{n}/pay) — apontando
      para os controllers das fases seguintes
- [X] T005 [P] Criar `frontend/src/auth/RoleRoute.jsx`: exige papel no
      `user.roles` (me da 002); sem papel → página 403 amigável embutida;
      compõe com ProtectedRoute
- [X] T006 [P] Criar layout do painel em `frontend/src/admin/AdminLayout.jsx`
      (Tabler: sidebar Evento · Tipos & Lotes · Camisas · Landing · Cortesias ·
      Patrocínios + header com usuário/sair) e registrar rotas `/painel/*` em
      `frontend/src/App.jsx` sob `RoleRoute role="admin"`
- [X] T007 [P] Feature test de RBAC do painel em
      `tests/Feature/Admin/AdminRbacTest.php`: amostra de rotas admin → anônimo
      401, attendee 403, admin 200/404 (nunca 403)

**Checkpoint**: `/painel` navegável (menus vazios) e rotas protegidas.

---

## Phase 3: User Story 1 - Configurar e publicar o evento (Priority: P1) 🎯 MVP

**Goal**: edição completa do evento, publish com requisitos mínimos, cancel com
guarda terminal, banner por upload.

**Independent Test**: quickstart.md §US1.

### Tests for User Story 1

- [X] T008 [P] [US1] Feature test em
      `tests/Feature/Admin/EventConfigTest.php`: update de configuração persiste
      (flags, janela, cortesia); publish sem tipo ativo → 409 com `missing[]`;
      publish válido → published + auditoria; cancel exige motivo (422) e
      registra autor/momento; update/publish/cancel em cancelado → 409; banner
      jpeg ok (Storage::fake, retorna bannerUrl), PDF/6MB → 422

### Implementation for User Story 1

- [X] T009 [P] [US1] Criar
      `app/Domain/Events/Services/EventConfigService.php`: `publish(Event)` —
      valida nome/starts_at/event_type/≥1 tipo ativo, lança `DomainRuleViolation`
      com lista `missing` no payload do erro, transiciona via `transitionTo`;
      `cancel(Event, string $reason)` — grava cancelled_at/by/reason em transação
- [X] T010 [P] [US1] Criar `app/Http/Requests/Admin/UpdateEventRequest.php`
      (todos os campos FR-002, datas coerentes, dinheiro ≥ 0) e
      `BannerRequest.php` (image jpeg/png/webp, max 5120 KB)
- [X] T011 [US1] Criar `app/Http/Resources/Admin/EventResource.php` (config
      completa + derivações salesOpen/available/soldOut + bannerUrl) e
      `app/Http/Controllers/Api/Admin/EventController.php`
      (index/show/update/publish/cancel/banner — banner grava no disco public e
      remove o anterior)
- [X] T012 [US1] Criar tela `frontend/src/admin/pages/Evento.jsx`: formulário de
      configuração (seções: dados, janela, comportamento, cortesia), upload de
      banner com preview, botões publicar/cancelar (com motivo) exibindo
      `missing[]`/409 amigavelmente

**Checkpoint**: US1 completa — evento configurável e publicável pelo painel.

---

## Phase 4: User Story 2 - Tipos de ingresso e lotes (Priority: P2)

**Goal**: catálogo com guardas de venda e vigência/preço efetivo visíveis.

**Independent Test**: quickstart.md §US2.

### Tests for User Story 2

- [X] T013 [P] [US2] Feature test em
      `tests/Feature/Admin/TicketCatalogTest.php`: CRUD de tipos (ordenar,
      ativar); delete de tipo/lote com tickets vivos → 409 (desativar ok);
      capacity < vendido → 409; CRUD de lotes; resource de lote traz isCurrent/
      effectivePrice iguais às derivações da fundação; reorder persiste

### Implementation for User Story 2

- [X] T014 [P] [US2] Adicionar guardas de domínio: `TicketType::hasSales()`,
      `TicketLot::hasSales()` (tickets vivos via COUNTS_CAPACITY) em
      `app/Domain/Events/Models/` + validação de capacidade ≥ vendido
- [X] T015 [US2] Criar `app/Http/Requests/Admin/TicketTypeRequest.php` e
      `TicketLotRequest.php` (money/datas/escopo) +
      `app/Http/Resources/Admin/TicketTypeResource.php` (available/soldOut) e
      `TicketLotResource.php` (isCurrent/soldOut/effectivePrice)
- [X] T016 [US2] Criar `app/Http/Controllers/Api/Admin/TicketTypeController.php`
      e `TicketLotController.php` (CRUD + reorder transacional + guardas 409)
- [X] T017 [US2] Criar tela `frontend/src/admin/pages/TiposLotes.jsx`: tabelas de
      tipos e lotes (badge de vigente/esgotado, preço efetivo por tipo), forms
      modais com money mask, reordenação (subir/descer), desativar/excluir com
      tratamento de 409

**Checkpoint**: catálogo completo e protegido.

---

## Phase 5: User Story 3 - Camisas com estoque (Priority: P3)

**Goal**: modelos/tamanhos com estoque ≥ vendido e esgotamento visível.

**Independent Test**: quickstart.md §US3.

### Tests for User Story 3

- [X] T018 [P] [US3] Feature test em `tests/Feature/Admin/ShirtTest.php`: CRUD
      hierárquico; estoque < sold_count → 409; delete de tamanho com vendas →
      409; resource traz soldOut/sold_count

### Implementation for User Story 3

- [X] T019 [US3] Adicionar guarda `EventShirtSize::hasSales()` + criar
      `app/Http/Requests/Admin/ShirtModelRequest.php`/`ShirtSizeRequest.php`
      (estoque ≥ vendido) e controllers
      `app/Http/Controllers/Api/Admin/ShirtModelController.php`/
      `ShirtSizeController.php` (+ resources com soldOut)
- [X] T020 [US3] Criar tela `frontend/src/admin/pages/Camisas.jsx`: modelos com
      tamanhos aninhados, estoque/vendido/esgotado, forms e tratamento de 409

**Checkpoint**: camisas completas.

---

## Phase 6: User Story 4 - Editor da landing por blocos (Priority: P4)

**Goal**: blocos dos 7 tipos com payload validado, reordenação e visibilidade.

**Independent Test**: quickstart.md §US4.

### Tests for User Story 4

- [X] T021 [P] [US4] Feature test em
      `tests/Feature/Admin/LandingBlockTest.php`: cria bloco de cada tipo;
      payload inválido por tipo (hero sem title, faq sem items) → 422; reorder
      em massa persiste; desativar mantém no banco; delete soft

### Implementation for User Story 4

- [X] T022 [US4] Criar `app/Http/Requests/Admin/LandingBlockRequest.php`
      (mapa de regras por tipo — research Decisão 5) e
      `app/Http/Controllers/Api/Admin/LandingBlockController.php`
      (CRUD + reorder transacional)
- [X] T023 [US4] Criar tela `frontend/src/admin/pages/Landing.jsx`: lista
      ordenável de blocos com editor por tipo (campos específicos), toggle de
      visibilidade, adicionar/remover

**Checkpoint**: landing editável (renderização pública fica na 004).

---

## Phase 7: User Story 5 - Cortesias: regra e vouchers (Priority: P5)

**Goal**: regra X→Y no evento + geração/distribuição/listagem de vouchers.

**Independent Test**: quickstart.md §US5.

### Tests for User Story 5

- [X] T024 [P] [US5] Feature test em `tests/Feature/Admin/VoucherTest.php`:
      gerar N (códigos únicos CTY- não sequenciais, status available);
      quantity 0 ou > 500 → 422; distribute registra autor/momento/nota;
      distribute de distribuído → 409; voucher redeemed intocável → 409;
      listagem filtra por status

### Implementation for User Story 5

- [X] T025 [US5] Criar
      `app/Http/Controllers/Api/Admin/CourtesyVoucherController.php`
      (index com filtro, generate em lote, distribute via transitionTo) +
      `app/Http/Requests/Admin/GenerateVouchersRequest.php` (1..500) + resource
- [X] T026 [US5] Criar tela `frontend/src/admin/pages/Cortesias.jsx`: regra X→Y
      (edita campos do evento), gerar em lote, tabela com filtro por situação e
      ação de distribuir (com nota)

**Checkpoint**: cortesias completas (resgate na 004).

---

## Phase 8: User Story 6 - Patrocínios e parcelas (Priority: P6)

**Goal**: patrocínio com parcelas geradas, baixa auditada e status recalculado.

**Independent Test**: quickstart.md §US6.

### Tests for User Story 6

- [X] T027 [P] [US6] Feature test em `tests/Feature/Admin/SponsorshipTest.php`:
      criação gera N parcelas somando o total (resto na última); baixa registra
      valor/data/forma/autor e recalcula status (pending→partial→paid); parcela
      paga → 409; cancelamento preserva parcelas; installmentsCount 0 → 422

### Implementation for User Story 6

- [X] T028 [US6] Criar
      `app/Domain/Events/Services/SponsorshipService.php`
      (`createWithInstallments`, `payInstallment` — transação + 
      `Sponsorship::recalculateStatus()`) e adicionar `recalculateStatus()` em
      `app/Domain/Events/Models/Sponsorship.php`
- [X] T029 [US6] Criar `app/Http/Controllers/Api/Admin/SponsorshipController.php`
      (CRUD + pay) + `app/Http/Requests/Admin/SponsorshipRequest.php`/
      `PayInstallmentRequest.php` + resource com parcelas aninhadas
- [X] T030 [US6] Criar tela `frontend/src/admin/pages/Patrocinios.jsx`: lista com
      status geral, form de criação (parcelas), expandir parcelas e registrar
      baixa

**Checkpoint**: todas as US completas.

---

## Phase 9: Polish & Cross-Cutting Concerns

- [X] T031 Criar `app/Http/Controllers/Api/Admin/EventTypeController.php`
      (CRUD do lookup com 409 quando em uso) + teste em
      `tests/Feature/Admin/EventTypeTest.php` + select de tipo na tela Evento
- [X] T032 Executar `specs/003-config-evento/quickstart.md` de ponta a ponta
      (make fresh + suíte + fluxos manuais no painel) e corrigir o que falhar
- [X] T033 [P] Varredura: suítes 001/002 verdes; nenhuma coluna/estado derivado
      novo; auditoria em amostra de ações; build do frontend ok
- [X] T034 Atualizar `ROADMAP.md` (003 ✅) e `specs/003-config-evento/spec.md`
      (Status: Draft → Implemented)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** → **Phase 2 (Foundational)** → US1…US6
- **US1 (Phase 3)**: primeira — EventResource/telas servem de padrão às demais
- **US2–US6**: independentes entre si após a Fase 2 (todas usam o AdminLayout);
  ordem sugerida por prioridade, mas US3/US4/US5/US6 podem correr em paralelo
- **Phase 9 (Polish)**: por último

### Key task-level dependencies

- T004 (rotas) referencia todos os controllers — casca primeiro, preencher por
  fase
- T009/T010 antes de T011; T014/T015 antes de T016; T028 antes de T029
- Telas (T012/T017/T020/T023/T026/T030) dependem do AdminLayout (T006) e dos
  endpoints da própria US
- Testes de cada US antes da implementação correspondente (devem falhar primeiro)

### Parallel Opportunities

- Setup: T001 ∥ T002 ∥ T003
- Foundational: T005 ∥ T006 ∥ T007 (após T004)
- US1: T008 ∥ T009 ∥ T010
- Após a Fase 2: US2 ∥ US3 ∥ US4 ∥ US5 ∥ US6 (times separados); solo: por
  prioridade
- Todos os testes de US (T008/T013/T018/T021/T024/T027) são [P] entre si

## Parallel Example: pós-Foundational

```bash
# Times separados podem atacar stories inteiras em paralelo:
Task: "US2 completa (T013–T017)"
Task: "US4 completa (T021–T023)"
Task: "US5 completa (T024–T026)"
```

## Implementation Strategy

**MVP first**: Fases 1–3 (US1) entregam o painel com o evento configurável e
publicável — já demonstrável. Depois o catálogo (US2, que destrava a 004) e as
demais por prioridade. Parar em cada checkpoint; merge na `main` só com a suíte
inteira verde e quickstart validado.
