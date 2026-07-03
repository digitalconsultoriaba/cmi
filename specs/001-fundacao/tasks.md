# Tasks: Fundação da Plataforma de Eventos

**Input**: Design documents from `/specs/001-fundacao/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: INCLUÍDOS — exigidos por FR-019 e pela constituição (feature tests em MySQL
de teste bloqueiam merge).

**Organization**: agrupado por user story; cada fase é um incremento testável.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizável (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1–US4 (mapeia para spec.md)

## Path Conventions

Laravel na **raiz do repositório** + SPA em `frontend/` (plan.md, Structure Decision).
Domínio em `app/Domain/Events`. Testes da spec em `tests/Feature/Foundation/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: esqueleto dos dois apps, sem tocar nos diretórios de documentação
(`base/`, `template/`, `specs/`, `.specify/`, `CLAUDE.md`, `ROADMAP.md`).

- [X] T001 Scaffold Laravel 12 na raiz do repositório (composer create-project em
      diretório temporário e mover conteúdo, preservando os diretórios de
      documentação e o `.git/`); ajustar `.gitignore` (vendor, node_modules, .env,
      storage, frontend/dist)
- [X] T002 [P] Scaffold `frontend/` com Vite + React 18: `frontend/package.json`,
      `frontend/vite.config.js`, `frontend/index.html`, `frontend/src/main.jsx`,
      `frontend/src/App.jsx` (placeholder), `frontend/src/lib/api.js` (axios com
      baseURL `/api` e unwrap do envelope `{ data }`), QueryClient do
      @tanstack/react-query montado em `main.jsx`
- [X] T003 Instalar laravel/sanctum via composer e publicar config
      `config/sanctum.php` (somente instalação — fluxo de auth é spec 002)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: ambiente, convenções de API e exceção de domínio — bloqueia todas as
user stories.

**⚠️ CRITICAL**: nenhuma US começa antes desta fase terminar.

- [X] T004 Criar `docker-compose.yml` na raiz: MySQL 8 (com script de init
      `docker/mysql/init.sql` criando bancos `app` e `app_test`), Redis 7, Mailpit;
      volumes nomeados e healthchecks
- [X] T005 [P] Criar `Makefile` com targets `up`, `down`, `install`, `migrate`
      (`migrate --seed`), `fresh` (`migrate:fresh --seed`), `test` (usa `app_test`),
      `dev` (serve API + Vite) — conforme research Decisão 3
- [X] T006 [P] Criar `.env.example` (placeholders apenas: DB app, DB test, Redis,
      Mailpit, SANCTUM_STATEFUL_DOMAINS) e ajustar `config/database.php`
      (conexão `mysql_testing`) — nenhum segredo real (FR-015)
- [X] T007 Configurar `phpunit.xml` para banco `app_test` (conexão dedicada) e
      `tests/TestCase.php` base; garantir `RefreshDatabase` utilizável
- [X] T008 [P] Criar exceção `app/Domain/Events/Exceptions/DomainRuleViolation.php`
      (mensagem pt-BR, `type` de domínio, status 409)
- [X] T009 Padronizar respostas em `bootstrap/app.php` (withExceptions): render de
      401/403/404/409/422 na shape `{ message, type, status, errors? }` de
      `contracts/api-conventions.md`; helper `app/Support/ApiResponse.php` para
      sucesso `{ data }` camelCase

**Checkpoint**: `make up && make install` funcionais; user stories podem começar.

---

## Phase 3: User Story 1 - Ambiente de desenvolvimento reproduzível (Priority: P1) 🎯 MVP

**Goal**: máquina limpa → ambiente no ar, estrutura aplicada e testes verdes com
comandos padronizados (≤ 3 comandos, < 15 min).

**Independent Test**: quickstart.md §US1 — `make up/install/fresh/test` sem passo
manual; `GET /api/health` responde no envelope; sem segredos versionados.

### Tests for User Story 1

- [X] T010 [P] [US1] Feature test do ambiente em
      `tests/Feature/Foundation/EnvironmentTest.php`: `GET /api/health` → 200
      `{ "data": { "status": "ok" } }`; rota inexistente → 404 na shape de erro
      padrão (escrever antes de T012 e ver falhar)

### Implementation for User Story 1

- [X] T011 [US1] Criar `app/Http/Controllers/Api/HealthController.php` e rota
      `GET /api/health` em `routes/api.php` usando `ApiResponse`
- [X] T012 [US1] Validar idempotência de instalação: `php artisan migrate:fresh --seed`
      2× seguidas sem erro (registrar no alvo `make fresh`); corrigir qualquer
      migration não re-executável encontrada
- [X] T013 [US1] Escrever `README.md` (raiz): pré-requisitos, quickstart
      (`make up/install/fresh/test/dev`), layout do repositório, link para
      `specs/001-fundacao/quickstart.md`

**Checkpoint**: US1 completa — ambiente demonstrável de ponta a ponta.

---

## Phase 4: User Story 2 - Domínio independente e com histórico (Priority: P2)

**Goal**: 23 tabelas em 6 grupos + models com relacionamentos, soft delete,
auditoria e snapshot — zero conceito do sistema de origem.

**Independent Test**: quickstart.md §US2 — grafo completo de entidades criado em
teste; soft delete reversível com trilha; varredura de acoplamento = 0.

### Tests for User Story 2

- [X] T014 [P] [US2] Feature test do grafo de domínio em
      `tests/Feature/Foundation/DomainGraphTest.php`: cria evento completo (tipos,
      lotes, camisas, blocos, pedido com tickets) e verifica todos os
      relacionamentos do diagrama de data-model.md
- [X] T015 [P] [US2] Feature test de histórico em
      `tests/Feature/Foundation/SoftDeleteAuditTest.php`: delete → `deleted_at`
      preenchido + restauração; `created_by`/`updated_by` preenchidos
      automaticamente quando há usuário autenticado
- [X] T016 [P] [US2] Teste de desacoplamento em
      `tests/Feature/Foundation/NoLegacyCouplingTest.php`: varre `app/`,
      `database/`, `routes/`, `frontend/src/` por
      `owner_type|owner_lodge|EventAccessGuard|require\.module|\bMember\b|\bLodge\b|seat_limit_per_lodge`
      → 0 ocorrências (SC-004 como regressão permanente)
- [X] T017 [P] [US2] Feature test de snapshot em
      `tests/Feature/Foundation/TicketSnapshotTest.php`: alterar preço do
      tipo/lote após criar ticket não altera `unit_price`/dados do ticket

### Implementation for User Story 2

- [X] T018 [US2] Migration grupo auth em `database/migrations/`: alterar `users`
      (document, phone, google_id unique nullable, avatar_url, password nullable) +
      criar `roles` + `role_user` (unique composto) — data-model.md Grupo 1
- [X] T019 [P] [US2] Migration grupo lookups: `event_statuses`, `order_statuses`,
      `ticket_statuses`, `payment_statuses`, `event_types` — Grupo 2
- [X] T020 [P] [US2] Migration grupo evento: `events`, `landing_blocks`,
      `ticket_types`, `ticket_lots`, `event_shirt_models`, `event_shirt_sizes` —
      Grupo 3 (índices e defaults do data-model)
- [X] T021 [US2] Migration grupo pedidos: `orders`, `tickets` — Grupo 4 (depende de
      T020; FKs, uniques de `code`, cascade em tickets.order_id)
- [X] T022 [P] [US2] Migration grupo pagamento: `payments` (unique composto
      `provider+provider_charge_id`, sem soft delete), `webhook_events` (unique
      `provider+external_id`) — Grupo 5
- [X] T023 [P] [US2] Migration grupo apoio: `courtesy_vouchers`, `sponsorships`,
      `sponsorship_installments` (unique `sponsorship_id+number`), `support_cases`,
      `support_case_notes` — Grupo 6
- [X] T024 [US2] Criar bases do domínio em `app/Domain/Events/Models/`:
      `BaseModel.php` (SoftDeletes + auditoria automática created_by/updated_by via
      eventos booted + casts UTC), `BaseLookupModel.php` (is_active, sort, sem
      soft/audit) e trait `app/Domain/Events/Support/HasPublicCode.php` (código
      único não sequencial com prefixo ORD-/TCK-/CTY-)
- [X] T025 [P] [US2] Models lookup em `app/Domain/Events/Models/`: `Role.php`
      (constantes de slug), `EventStatus.php`, `OrderStatus.php`,
      `TicketStatus.php` (constantes de status vivos/terminais),
      `PaymentStatus.php`, `EventType.php`
- [X] T026 [P] [US2] Models de configuração: `Event.php`, `LandingBlock.php`,
      `TicketType.php`, `TicketLot.php`, `EventShirtModel.php`,
      `EventShirtSize.php` com todos os relacionamentos (derivações ficam na US3)
- [X] T027 [P] [US2] Models de venda: `Order.php` e `Ticket.php` (HasPublicCode,
      campos de snapshot, relacionamentos incl. transferred_from/to)
- [X] T028 [P] [US2] Models restantes: `Payment.php`, `WebhookEvent.php`,
      `CourtesyVoucher.php`, `Sponsorship.php`, `SponsorshipInstallment.php`,
      `SupportCase.php`, `SupportCaseNote.php`
- [X] T029 [US2] Ajustar `app/Models/User.php`: fillable/casts novos campos,
      password nullable/hashed, relação `roles()` belongsToMany
- [X] T030 [US2] Factories em `database/factories/`: UserFactory (ajuste),
      EventFactory, TicketTypeFactory, TicketLotFactory, EventShirtModelFactory,
      EventShirtSizeFactory, OrderFactory, TicketFactory, LandingBlockFactory
- [X] T031 [US2] Seeders de lookup em `database/seeders/`: `EventStatusSeeder`,
      `OrderStatusSeeder`, `TicketStatusSeeder`, `PaymentStatusSeeder`,
      `EventTypeSeeder` (valores exatos do data-model; necessários aos testes)

**Checkpoint**: US2 completa — domínio inteiro persiste e se relaciona; T014–T017
verdes.

---

## Phase 5: User Story 3 - Estado sempre derivado (Priority: P3)

**Goal**: derivações calculadas conforme `contracts/domain-derivations.md`; guarda
de transição terminal com 409.

**Independent Test**: quickstart.md §US3 — os 7 cenários do contrato passam sem
editar campo de status; nenhuma coluna de estado derivado existe no schema.

### Tests for User Story 3

- [X] T032 [P] [US3] Feature test de derivações em
      `tests/Feature/Foundation/DerivationsTest.php`: cenários 1–6 do contrato
      (salesOpen aberto/fechado, virada de lote por quantidade e por data,
      desempate por sort, preço efetivo, camisa esgotada/ilimitada)
- [X] T033 [P] [US3] Feature test de transições em
      `tests/Feature/Foundation/StatusTransitionTest.php`: cenário 7 —
      `transitionTo` sobre status terminal lança `DomainRuleViolation`; render HTTP
      da exceção → 409 na shape padrão
- [X] T034 [P] [US3] Teste estrutural em
      `tests/Feature/Foundation/NoDerivedColumnsTest.php`: schema não contém
      colunas `sales_open`, `available`, `is_sold_out`/`sold_out` (booleano) nas
      tabelas de negócio (`sold_count` cache permitido)

### Implementation for User Story 3

- [X] T035 [US3] Derivações do `Event` em `app/Domain/Events/Models/Event.php`:
      `salesOpen`, `currentLot(?TicketType $type = null)`, `ticketsSold`,
      `available`, `soldOut` — contagem de tickets vivos conforme contrato
- [X] T036 [P] [US3] Derivações de lote/camisa/tipo: `TicketLot::isCurrent()`,
      `soldOut()`, `effectivePrice()`, `recountSold()`;
      `EventShirtSize::soldOut()`, `recountSold()`; `TicketType::available()` —
      em `app/Domain/Events/Models/`
- [X] T037 [P] [US3] Derivações de venda: `Order::amountPaid()`, `isExpired()`;
      `Ticket::isActive()` — em `app/Domain/Events/Models/`
- [X] T038 [US3] Guarda de transição: `transitionTo(string $statusSlug)` em
      `Order` e `Ticket` (mapa de terminais por entidade; lança
      `DomainRuleViolation`); avanço-somente em `CourtesyVoucher::transitionTo`

**Checkpoint**: US3 completa — contrato de derivações inteiramente verde.

---

## Phase 6: User Story 4 - RBAC + dados de demonstração (Priority: P4)

**Goal**: 4 papéis com middleware/policy e banco de demonstração completo.

**Independent Test**: quickstart.md §US4 — cenários do `contracts/rbac.md` passam;
seed produz roles, lookups, admin e evento de exemplo.

### Tests for User Story 4

- [X] T039 [P] [US4] Feature test RBAC em
      `tests/Feature/Foundation/RbacTest.php`: rota fake protegida com
      `require.role:admin` → anônimo 401, attendee 403 (envelope padrão, sem vazar
      papéis exigidos), admin 200, attendee+admin 200; `require.role:admin,treasury`
      aceita qualquer um dos dois
- [X] T040 [P] [US4] Feature test de seeds em
      `tests/Feature/Foundation/SeedersTest.php`: após `db:seed` — exatamente 4
      roles com slugs estáveis; lookups completos (valores do data-model); admin de
      dev com papel admin; evento de exemplo publicado com 3 tipos, 2 lotes,
      camisas com e sem estoque e blocos de todos os tipos

### Implementation for User Story 4

- [X] T041 [US4] Criar `app/Http/Middleware/RoleMiddleware.php` (semântica "ao menos
      um papel"; 401/403 na shape padrão) e registrar alias `require.role` em
      `bootstrap/app.php`
- [X] T042 [P] [US4] Adicionar `User::hasRole(string $slug)` e
      `hasAnyRole(array $slugs)` em `app/Models/User.php` (eager load do pivot)
- [X] T043 [P] [US4] Criar `app/Policies/EventPolicy.php` (update/publish/cancel/
      view não-publicado → só admin) e registrar em `bootstrap/app.php` ou
      AppServiceProvider
- [X] T044 [US4] Criar `database/seeders/RoleSeeder.php` (4 papéis, slugs de
      `contracts/rbac.md`)
- [X] T045 [P] [US4] Criar `database/seeders/AdminUserSeeder.php` (dev only: 1
      admin, 1 treasury, 1 gate — senhas dummy claramente de dev)
- [X] T046 [US4] Criar `database/seeders/SampleEventSeeder.php`: evento publicado
      (slug fixo), 3 tipos (individual, casal `is_couple`, cortesia), 2 lotes com
      janela/quantidade/price_override, 2 modelos de camisa (um tamanho com estoque
      finito, um ilimitado), blocos de landing dos 7 tipos
- [X] T047 [US4] Orquestrar `database/seeders/DatabaseSeeder.php`: estruturais
      sempre (lookups + roles); demonstração (`AdminUserSeeder`,
      `SampleEventSeeder`) apenas fora de produção

**Checkpoint**: US4 completa — todas as user stories independentes e verdes.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T048 Executar validação completa do `specs/001-fundacao/quickstart.md` em
      sequência limpa (`make down && make up && make install && make fresh && make
      test`, `make fresh` 2×) e corrigir o que falhar
- [X] T049 [P] Revisar conformidade constitucional final: grep de segredos em
      arquivos versionados; UTC/DECIMAL nas migrations; mensagens pt-BR nas
      exceções; atualizar `specs/001-fundacao/checklists/requirements.md` se algo
      mudou
- [X] T050 Marcar status da spec: atualizar `ROADMAP.md` (001 ✅) e status em
      `specs/001-fundacao/spec.md` (Draft → Implemented)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: sem dependências
- **Phase 2 (Foundational)**: depende da 1 — **bloqueia todas as US**
- **US1 (Phase 3)**: depende da 2
- **US2 (Phase 4)**: depende da 2 (não depende da US1, mas T012/US1 valida seeds
  criados na US2/US4 — re-rodar `make fresh` ao final)
- **US3 (Phase 5)**: depende da US2 (models/migrations/factories)
- **US4 (Phase 6)**: depende da US2 (roles/migrations); independente da US3
- **Phase 7 (Polish)**: depende de todas

### Key task-level dependencies

- T021 (orders/tickets) depende de T018–T020; T022/T023 dependem de T018/T019
- T024 (bases) antes de T025–T028; T030 (factories) depende dos models
- T035–T038 dependem de T026–T031; T046 depende de T030/T031/T044
- Testes de cada US escritos antes da implementação correspondente (devem falhar
  primeiro)

### Parallel Opportunities

- Fase 2: T005, T006, T008 em paralelo (T004→T007 sequencial por dependerem do DB)
- US2: T019/T020/T022/T023 (migrations de grupos distintos) em paralelo após T018;
  T025–T028 (models, arquivos distintos) em paralelo após T024; T014–T017 (testes)
  em paralelo
- US3: T032–T034 em paralelo; T036/T037 em paralelo após T035
- US4: T039/T040 em paralelo; T042/T043/T045 em paralelo
- US3 e US4 podem correr em paralelo após a US2

## Parallel Example: User Story 2

```bash
# Após T018 (auth), migrations de grupos independentes em paralelo:
Task: "T019 Migration lookups em database/migrations/"
Task: "T020 Migration evento+config em database/migrations/"
Task: "T022 Migration pagamento em database/migrations/"
Task: "T023 Migration apoio em database/migrations/"

# Após T024 (bases), models em paralelo:
Task: "T025 Models lookup" / "T026 Models config" / "T027 Order+Ticket" / "T028 restantes"
```

## Implementation Strategy

**MVP first**: Fases 1–3 (US1) entregam o marco demonstrável — ambiente
reproduzível com health check. Depois US2 (o grosso do trabalho), US3 e US4 em
sequência (ou 3∥4 após a 2). Parar em cada checkpoint e validar a US isoladamente.
Commits por tarefa ou grupo lógico; merge na `main` só com `make test` verde e
checklist completo (constituição).
