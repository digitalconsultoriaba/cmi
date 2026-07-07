---
description: "Task list — 014 Checkout do Seminário (multi-inscrição, guest, voucher por participante)"
---

# Tasks: Checkout do Seminário Internacional

**Input**: Design documents from `/specs/014-checkout/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: incluídos — a constituição exige Feature tests (MySQL `app_test`) cobrindo caminho feliz + regras (409/403/escopo) antes do merge.

**Organization**: por user story (P1–P5). Feature **aditiva** às specs 002/004/006/011; migrations aditivas; **nunca `migrate:fresh`** sem autorização.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos distintos, sem dependência pendente). PHP/artisan via Docker (`docker compose run --rm php ...`).

---

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 [P] Migration `database/migrations/2026_07_08_100000_create_participant_categories_table.php` (event_id FK, key string(40), label, sort, is_active, unique(event_id,key), soft delete + audit).
- [X] T002 [P] Migration `database/migrations/2026_07_08_100010_create_participant_fields_table.php` (participant_category_id FK, key, label, type string(20), required bool, sort, config json nullable, unique(participant_category_id,key), soft delete + audit).
- [X] T003 [P] Migration `database/migrations/2026_07_08_100020_create_affiliations_table.php` (event_id FK, name string(160), sort, is_active, index(event_id,name), soft delete + audit).
- [X] T004 [P] Migration `database/migrations/2026_07_08_100030_add_participant_snapshot_to_tickets.php` (adiciona `participant_category_key` string(40) nullable e `participant_fields` json nullable a `tickets`).
- [X] T005 [P] Diretório de testes `tests/Feature/Checkout/` + `CheckoutTestCase.php` com helpers (evento com tipos/lotes vigentes, categorias/campos/afiliações seed, voucher `available`/`distributed`, `guestOrderPayload()`), no padrão dos TestCases existentes.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: models, config de participante e as 3 extensões de peso — bloqueiam as histórias.

- [X] T006 [P] Model `app/Domain/Events/Models/ParticipantCategory.php` (BaseModel; casts is_active bool; relações `event()`, `fields()` ordenado).
- [X] T007 [P] Model `app/Domain/Events/Models/ParticipantField.php` (BaseModel; casts required bool, config array; relação `category()`).
- [X] T008 [P] Model `app/Domain/Events/Models/Affiliation.php` (BaseModel; cast is_active bool; relação `event()`).
- [X] T009 Estender `app/Domain/Events/Models/Event.php`: relações `participantCategories()`, `affiliations()`.
- [X] T010 Estender `app/Domain/Events/Models/Ticket.php`: casts `participant_fields` array; expor `participant_category_key`; sem quebrar snapshot existente.
- [X] T011 Estender `app/Domain/Events/Models/CourtesyVoucher.php` + `CourtesyResolver.php`: elegibilidade de resgate aceita `available` OU `distributed` (research R4); método para **resgatar dentro de um pedido existente** (não criar pedido separado), ligando `redeemed_ticket_id`.
- [X] T012 Estender `app/Domain/Events/Services/TicketPurchaseService.php`: aceitar `voucherCode`/categoria/campos **por item**; montar **pedido misto** (tickets pagos + cortesia no mesmo Order); snapshot de `participant_category_key`/`participant_fields`; total derivado; recontagem em `DB::transaction` (research R1).
- [X] T013 [P] `app/Domain/Events/Services/GuestBuyerService.php`: cria/vincula `User` (comprador e participantes) por e-mail normalizado, sem senha, papel `attendee` (research R2).
- [X] T014 [P] `app/Domain/Events/Services/MagicLinkService.php`: gera `URL::temporarySignedRoute('auth.magic', …)` por usuário e dispara a notification correspondente (research R3).
- [X] T015 `database/seeders/SeminarioCheckoutSeeder.php`: cria a config padrão do seminário (categorias "Irmão da GLMEES" e "Irmão de outra potência" + campos + afiliações exemplo) — idempotente, sem `migrate:fresh`.

**Checkpoint**: banco migrado (aditivo), models/serviços prontos; nenhuma rota nova ainda.

---

## Phase 3: User Story 1 — Inscrever participantes e pagar (guest) (Priority: P1) 🎯 MVP

**Goal**: guest adiciona N participantes (categoria/tipo/campos), revisa, paga e recebe ingressos; comprador acessa o back-office.

**Independent Test**: sem login, 2 participantes de categorias diferentes → revisar → pagar (gateway teste) → 2 tickets `PAID`, e-mails enviados, acesso do comprador.

### Tests (US1)

- [X] T016 [P] [US1] `tests/Feature/Checkout/GuestCheckoutTest.php`: `POST /public/orders` sem auth cria pedido; tipos/preço snapshot; campos por categoria snapshotados; e-mail de participante obrigatório com >1; esgotado/fora de janela → 409.
- [X] T017 [P] [US1] `tests/Feature/Checkout/ParticipantConfigTest.php`: `GET /public/events/{slug}/checkout-config` devolve tipos + categorias/campos + afiliações; render condicional (config).

### Implementation (US1)

- [X] T018 [P] [US1] `app/Http/Requests/Public/GuestOrderRequest.php` (buyer.name/email required; items[]: ticketTypeId/participantName required, participantEmail required se >1, categoryKey existe/ativa, fields obrigatórios da categoria + affiliation na lista, voucherCode nullable; messages pt-BR).
- [X] T019 [P] [US1] Resources: estender `OrderResource`/`TicketResource` (ou novos) para expor status consolidado, `participantCategoryKey`, `participantFields`, `isCourtesy`, `unitPrice` em camelCase.
- [X] T020 [US1] `app/Http/Controllers/Api/Public/GuestCheckoutController.php`: `checkoutConfig(slug)`, `store` (GuestOrderRequest → GuestBuyerService + TicketPurchaseService; 201 com order+payment.required), e proxies `pix`/`card`/`paymentStatus` por `{order:code}`.
- [X] T021 [US1] Registrar rotas públicas em `routes/api.php` (sem auth): `GET /public/events/{event:slug}/checkout-config`, `POST /public/orders`, `POST /public/orders/{order:code}/checkout/{pix,card}`, `GET /public/orders/{order:code}/payment-status`, `POST /public/orders/{order:code}/resend-access`.
- [X] T022 [US1] Frontend: página `frontend/src/pages/CheckoutSeminario.jsx` + rota `/checkout/:slug` em `App.jsx`; ligar o botão "Inscreva-se" da landing (SitePublico/EventoPublico) a essa rota.
- [X] T023 [P] [US1] Frontend componentes em `frontend/src/pages/checkout/`: `CategoriaForm.jsx` (escolhe categoria + tipo), `CampoDinamico.jsx` (text/affiliation/country/city/conditional), `CardParticipante.jsx`, `ResumoCarrinho.jsx`.
- [X] T024 [US1] Frontend tela de **revisão** `frontend/src/pages/checkout/Revisao.jsx` (cards + remover + total) e submit → `POST /public/orders`; encaminhar ao pagamento quando `payment.required`.

**Checkpoint**: US1 verde e demonstrável (MVP — inscrição paga guest).

---

## Phase 4: User Story 2 — Voucher de gratuidade por participante (Priority: P2)

**Goal**: aplicar/remover voucher por inscrição num pedido misto; total recalcula; status corretos.

**Independent Test**: 3 participantes, voucher no 2º → 2º R$0, total 2×; pagar → 2 `PAID` + 1 `COURTESY` no mesmo pedido.

### Tests (US2)

- [X] T025 [P] [US2] `tests/Feature/Checkout/MixedVoucherOrderTest.php`: voucher por item cria cortesia no mesmo Order; total = soma das não isentas; status consolidado (parcial→integral); remover item libera voucher.
- [X] T026 [P] [US2] `tests/Feature/Checkout/VoucherValidationTest.php`: `available` e `distributed` válidos; inválido/expirado/já usado/de outro evento/não elegível → recusa; mesmo voucher em 2 inscrições (uso único) → recusa.

### Implementation (US2)

- [X] T027 [P] [US2] `app/Http/Controllers/Api/Public/GuestCheckoutController.php@validateVoucher` + Request `ValidateVoucherRequest`; rota `POST /public/vouchers/validate` (não resgata; mensagens padrão de sucesso/erro).
- [X] T028 [US2] Integrar no `store`/`TicketPurchaseService`: item com `voucherCode` → resgate dentro do pedido (via CourtesyResolver estendido), garantindo uso único e recontagem transacional.
- [X] T029 [P] [US2] Frontend: em `CardParticipante.jsx`/`ResumoCarrinho.jsx`, aplicar/remover voucher por inscrição chamando `POST /public/vouchers/validate`; refletir R$0, descontos e total em tempo real; mensagens de sucesso/erro.

**Checkpoint**: pedido misto pago+gratuito funcionando ponta a ponta.

---

## Phase 5: User Story 3 — Checkout 100% gratuito (Priority: P3)

**Goal**: total = 0 finaliza sem pagamento e confirma inscrições gratuitas.

**Independent Test**: 2 participantes ambos com voucher → total 0 → "Confirmar inscrição gratuita" → 2 `COURTESY`, pedido gratuito, sem pagamento.

### Tests (US3)

- [X] T030 [P] [US3] `tests/Feature/Checkout/FreeCheckoutTest.php`: total 0 → `payment.required=false`, pedido gratuito, tickets `COURTESY`, sem cobrança; voucher inválido no submit não confirma indevidamente (409).

### Implementation (US3)

- [X] T031 [US3] Backend: no `store`/serviço, quando total = 0 finalizar direto (sem gateway) marcando tickets `COURTESY`/pedido gratuito (research R1/R8) — reusar a lógica de total-zero existente.
- [X] T032 [P] [US3] Frontend: em `Revisao.jsx`, alternar o botão para **"Confirmar inscrição gratuita"** quando total = 0 e pular a etapa de pagamento.

**Checkpoint**: caminho gratuito fechado.

---

## Phase 6: User Story 4 — Categorias/campos configuráveis + afiliações (admin) (Priority: P4)

**Goal**: admin configura categorias, campos (incl. condicional) e a lista de afiliações; snapshot por inscrição.

**Independent Test**: configurar 2 categorias e afiliações; checkout muda por categoria e autocomplete carrega a lista; inscrição antiga não muda ao editar config.

### Tests (US4)

- [X] T033 [P] [US4] `tests/Feature/Checkout/ParticipantAdminConfigTest.php`: CRUD de categorias/campos/afiliações (admin/treasury) + reorder + import; 403 para gate/attendee; snapshot imutável após edição da config.

### Implementation (US4)

- [X] T034 [P] [US4] Requests `app/Http/Requests/Admin/ParticipantCategoryRequest.php`, `ParticipantFieldRequest.php`, `AffiliationRequest.php` (validação de `type` enum, `config` do condicional, etc.).
- [X] T035 [P] [US4] Resources `ParticipantCategoryResource`/`ParticipantFieldResource`/`AffiliationResource` (camelCase).
- [X] T036 [US4] Controllers `app/Http/Controllers/Api/Admin/ParticipantCategoryController.php` (index/store/update/destroy + fields store/update/destroy/reorder) e `AffiliationController.php` (index/store/update/destroy/import).
- [X] T037 [US4] Rotas admin em `routes/api.php` (grupo `events/{event}`): `/participant-categories[...]`, `/participant-categories/{category}/fields[...]`, `/affiliations[...]` (withoutScopedBindings onde o filho não é relação direta do evento).
- [X] T038 [P] [US4] Frontend admin: aba do evento `frontend/src/admin/eventos/abas/inscricoes/` com `CategoriasConfig.jsx` (categorias + campos + condicional) e `AfiliacoesConfig.jsx` (lista/import); registrar a aba em `EventoLayout.jsx`.

**Checkpoint**: configuração alimentando o checkout.

---

## Phase 7: User Story 5 — Acesso pós-compra (magic link) (Priority: P5)

**Goal**: comprador vê todos os ingressos; cada participante acessa o seu; ingressos por e-mail; reenvio.

**Independent Test**: pedido pago com 3 participantes (e-mails) → 3 e-mails de ingresso + magic links; comprador vê 3, participante vê 1; reenvio funciona.

### Tests (US5)

- [X] T039 [P] [US5] `tests/Feature/Checkout/MagicLinkAccessTest.php`: link assinado autentica sessão; comprador vê todos os tickets do pedido, participante vê só o seu (policies); link expirado → 403; `POST /auth/magic/request` neutro + throttle.
- [X] T040 [P] [US5] `tests/Feature/Checkout/TicketDeliveryTest.php` (`Notification::fake`): ao confirmar (pago e gratuito), cada participante recebe `TicketIssuedPtBr` e o comprador `OrderAccessPtBr`; reenvio dispara de novo.

### Implementation (US5)

- [X] T041 [P] [US5] Notifications `app/Notifications/TicketIssuedPtBr.php` (ingresso com `code`/QR + magic link do participante) e `OrderAccessPtBr.php` (magic link do comprador + resumo), pt-BR.
- [X] T042 [US5] `app/Http/Controllers/Api/Auth/MagicLinkController.php`: `consume({user})` (middleware `signed` → login sessão + redirect SPA) e `request` (por e-mail, neutro, throttle); rotas `GET /auth/magic/{user}` (name `auth.magic`) e `POST /auth/magic/request`.
- [X] T043 [US5] Disparo dos e-mails no ponto de confirmação: no `RegisterPayment`/observer de pagamento aprovado e no caminho gratuito, enviar `TicketIssuedPtBr` por participante e `OrderAccessPtBr` ao comprador (idempotente — não duplicar em rebaixa).
- [X] T044 [US5] Estender policies de `Order`/`Ticket` para permitir o **participante** (`participant_user_id`) ver/baixar o **seu** ticket; back-office reusa `MeusPedidos.jsx`/`MeusIngressos.jsx`.
- [X] T045 [P] [US5] Frontend: `frontend/src/pages/MagicLink.jsx` (landing do link — consome e entra; trata expirado com reenvio) + rota `/acesso` em `App.jsx`.

**Checkpoint**: pós-compra completo (entrega + acesso).

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T046 [P] Revisar shape de erro `{ message, type, status, errors }` e mensagens pt-BR de todos os Requests novos; garantir 422/409/403 corretos.
- [X] T047 [P] LGPD/segurança: conferir que documento é opcional, e-mails minimizados, magic link expira, nada sensível logado; PAN/CVV fora do backend (reuso).
- [X] T048 Rodar `make test` completo (incl. specs 002/004/006/011/012/013) e garantir tudo verde; ajustar regressões.
- [X] T049 [P] Validar `quickstart.md` manualmente (5 fluxos) com API :8000 + Vite :5173 no ar (e-mails no Mailpit) ao final.

---

## Dependencies & Execution Order

- **Setup (T001–T005)** → **Foundational (T006–T015)** bloqueiam tudo.
- Histórias: **US1 (P1)** → US2 (P2) → US3 (P3) → US4 (P4) → US5 (P5).
- US2/US3 dependem do pedido misto (T011/T012) e das rotas de US1. US4 é config (independente após a fundação, mas alimenta o checkout de US1). US5 depende de pedidos confirmáveis (US1).
- Dentro de cada fase: Requests/Resources `[P]` → Controller → Rotas → Frontend; testes `[P]` podem preceder (TDD).

## Parallel Opportunities

- Setup: T001–T005 quase todos `[P]`.
- Foundational: models T006–T008 `[P]`; serviços T013/T014 `[P]`.
- US1: T016/T017 (tests) `[P]`; T018/T019 `[P]`; T023 `[P]`.
- US4: T034/T035/T038 `[P]`. US5: T039/T040/T041/T045 `[P]`.

## Implementation Strategy

- **MVP = US1** (Fases 1–3): checkout guest multi-participante com tipos/campos, revisão e pagamento; entrega e acesso mínimos. Testável sozinho.
- Incrementos: US2 (voucher por item / pedido misto) → US3 (total zero) → US4 (config admin) → US5 (magic link + entrega completa).
- Tudo aditivo (specs 002/004/006/011); migrations aditivas; **nunca `migrate:fresh`** sem autorização; verde antes de commit/merge.
