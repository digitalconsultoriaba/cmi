# Tasks: Catálogo Público e Compra

**Input**: Design documents from `/specs/004-catalogo-compra/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md,
contracts/public-api.md, quickstart.md — e as specs 001–003 mergeadas.

**Tests**: INCLUÍDOS — exigência da constituição; contenção determinística no
PHPUnit + smoke manual de concorrência no quickstart (research, Decisão 1).

**Organization**: agrupado por user story; **nenhuma migration nova**.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizável (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1–US5 (mapeia para spec.md)

## Path Conventions

Laravel na raiz + SPA em `frontend/`. Services do domínio em
`app/Domain/Events/Services/`; testes em `tests/Feature/Public/` (leitura) e
`tests/Feature/Purchase/` (escrita).

---

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Instalar `barryvdh/laravel-dompdf` e `simplesoftwareio/simple-qrcode`
      via composer (`docker compose run --rm php composer require
      barryvdh/laravel-dompdf simplesoftwareio/simple-qrcode`)
- [X] T002 [P] Criar `config/events.php` com `max_tickets_per_order` (default 20,
      env `EVENTS_MAX_TICKETS_PER_ORDER`)
- [X] T003 [P] Criar `app/Policies/OrderPolicy.php` (view: só o comprador) e
      `app/Policies/TicketPolicy.php` (view/receipt: participante com conta ou
      comprador) e registrar ambas em `app/Providers/AppServiceProvider.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: rotas e binding por código público — bloqueiam todas as US.

- [X] T004 Adicionar rotas em `routes/api.php`: pública
      `GET /public/events/{event:slug}`; autenticadas `POST /orders`,
      `GET /orders`, `GET /orders/{order:code}`, `GET /tickets`,
      `GET /tickets/{ticket:code}`, `GET /tickets/{ticket:code}/receipt` —
      bindings por slug/code (nunca id), apontando para os controllers das
      fases seguintes

**Checkpoint**: casca pronta — user stories podem começar.

---

## Phase 3: User Story 1 - Ver o evento e o catálogo sem login (Priority: P1) 🎯 MVP

**Goal**: landing pública renderizada + catálogo do lote vigente, sem sessão.

**Independent Test**: quickstart.md §US1.

### Tests for User Story 1

- [X] T005 [P] [US1] Feature test em
      `tests/Feature/Public/PublicEventTest.php`: publicado → 200 com blocos
      ativos ordenados (inativos fora), tipos ativos com effectivePrice do lote
      vigente e esgotamentos, shirtModels com tamanhos; salesState correto nos
      4 cenários (open/soon/closed/soldOut); rascunho → 404; cancelado → 200
      `{cancelled: true}` sem catálogo; slug inexistente → 404

### Implementation for User Story 1

- [X] T006 [US1] Criar `app/Http/Resources/PublicEventResource.php` (shape do
      contrato: blocks, ticketTypes com effectivePrice/currentLotName/soldOut/
      available, shirtModels ativos, salesState derivado) e
      `app/Http/Controllers/Api/PublicEventController.php` (404 rascunho/soft
      deleted; payload mínimo para cancelado)
- [X] T007 [P] [US1] Criar `frontend/src/pages/EventoPublico.jsx` com
      `LandingRenderer` (7 tipos de bloco com layout público leve) e
      `TicketPicker` (quantidades por tipo, respeita salesState/esgotado, botão
      "Continuar" → /checkout); registrar rota `/evento/:slug` em
      `frontend/src/App.jsx`

**Checkpoint**: página pública demonstrável com o evento do seed.

---

## Phase 4: User Story 2 - Comprar ingressos para o grupo (Priority: P2)

**Goal**: pedido em transação única com lock + recontagem; snapshot; TTL;
casal ×2; zero overselling.

**Independent Test**: quickstart.md §US2 (+ smoke de concorrência no Polish).

### Tests for User Story 2

- [X] T008 [P] [US2] Feature test em
      `tests/Feature/Purchase/PurchaseTest.php`: compra válida cria order
      pending com reserved_until = now+TTL, buyer congelado, total correto, 1
      ticket/participante com unit_price do lote vigente e code TCK-;
      catálogo muda depois → pedido intacto (snapshot); casal ocupa 2 vagas e
      grava acompanhante+camisas; guest sem login → 401; carrinho vazio/21
      itens → 422; requires_shirt sem camisa → 422; evento não publicado/janela
      fechada → 409 sales_closed
- [X] T009 [P] [US2] Feature test de contenção em
      `tests/Feature/Purchase/PurchaseContentionTest.php`: última vaga do
      evento (2º pedido → 409 sold_out); casal com 1 vaga → 409; última unidade
      do lote → 409 e lote seguinte assume preço; último estoque do tamanho
      (incl. acompanhante) → 409; contagens finais nunca excedem limites
      (invariante 1 do data-model); sold_count recontado após compra

### Implementation for User Story 2

- [X] T010 [US2] Criar
      `app/Domain/Events/Services/TicketPurchaseService.php`: `purchase(Event,
      User, array $items, array $courtesyParticipants = [], ?string
      $voucherCode = null)` — transação com `lockForUpdate` no evento,
      revalidação completa (publicado/salesOpen/lote por tipo/capacidades/
      estoques com casal ×2), criação de order+tickets com snapshot, regra
      total 0 → paid/confirmed (research Decisão 2), `recountSold()` em lotes e
      tamanhos afetados; erros 409 com types do data-model
- [X] T011 [P] [US2] Criar `app/Http/Requests/StoreOrderRequest.php`: eventSlug,
      items 0..max (com voucher permite 0), participantes (nome obrigatório,
      camisa quando requires_shirt, companion para casal, ids pertencentes ao
      evento), courtesyParticipants, voucherCode — mensagens pt-BR
- [X] T012 [US2] Criar `app/Http/Resources/OrderResource.php` e
      `app/Http/Resources/TicketResource.php` (shapes do contrato, com
      receiptAvailable) e `app/Http/Controllers/Api/OrderController.php@store`
      (201 `{orders: []}`)
- [X] T013 [US2] Criar `frontend/src/cart/CartProvider.jsx` (localStorage por
      slug; add/remove/clear; sobrevive ao login) e
      `frontend/src/pages/Checkout.jsx` (protegida): formulário por
      participante (camisa/acompanhante), revisão com totais, aviso se o preço
      retornado divergir do exibido, submit → sucesso limpa o carrinho e leva a
      /minha-conta/pedidos; registrar rota `/checkout` em App.jsx

**Checkpoint**: compra completa funcionando com todas as guardas.

---

## Phase 5: User Story 3 - Cortesias na compra (Priority: P3)

**Goal**: regra X→Y automática com limite por conta + voucher como pedido
próprio de total zero.

**Independent Test**: quickstart.md §US3.

### Tests for User Story 3

- [X] T014 [P] [US3] Feature test em
      `tests/Feature/Purchase/CourtesyTest.php`: regra 10→1 gera cortesia
      confirmada no pedido (valor 0, participante do payload); 9 pagáveis → 0
      cortesias; limite por conta considera pedidos anteriores; cortesia
      ocupando a última vaga → pedido inteiro 409; voucher distribuído →
      pedido separado total 0 paid com ticket courtesy vinculado
      (redeemed_ticket_id) e voucher redeemed; voucher available/redeemed/de
      outro evento/inexistente → 409 invalid_voucher; resgate puro (sem itens)
      funciona; expiração do pedido pago NÃO desfaz o pedido do voucher

### Implementation for User Story 3

- [X] T015 [US3] Criar `app/Domain/Events/Services/CourtesyResolver.php`:
      `automaticGrants(Event, User, int $paidCount): int` (floor(paid/X)*Y,
      limitado por courtesy_limit_per_account − cortesias vivas do comprador,
      recontagem sob o lock) e `redeemVoucher(Event, User, string $code, array
      $participant): Order` (lockForUpdate no voucher, exige distributed do
      evento, pedido próprio total 0 + ticket courtesy + transitionTo redeemed)
- [X] T016 [US3] Integrar o resolver ao `TicketPurchaseService` (cortesias
      automáticas dentro da mesma transação, ocupando vaga) e expor
      voucherCode/courtesyParticipants no fluxo do `OrderController@store`;
      adicionar campo de voucher e participantes de cortesia em
      `frontend/src/pages/Checkout.jsx`

**Checkpoint**: cortesias e vouchers fechados (painel da 003 mostra resgates).

---

## Phase 6: User Story 4 - Minha área: pedidos, ingressos e comprovante (Priority: P4)

**Goal**: listas do inscrito (com claim por e-mail), detalhe por código público
e PDF com QR.

**Independent Test**: quickstart.md §US4.

### Tests for User Story 4

- [X] T017 [P] [US4] Feature test em `tests/Feature/Purchase/MyAreaTest.php`:
      meus pedidos lista só os meus (com tickets aninhados e reservedUntil);
      pedido de outro por code → 403; meus ingressos inclui ticket emitido para
      meu e-mail por outra conta e preenche participant_user_id (claim);
      detalhe por code; anônimo → 401
- [X] T018 [P] [US4] Feature test em `tests/Feature/Purchase/ReceiptTest.php`:
      cortesia confirmada → 200 PDF (content-type, corpo contém código);
      ticket reserved → 409 com orientação; ticket de outro dono → 403;
      comprador baixa comprovante de participante sem conta → 200

### Implementation for User Story 4

- [X] T019 [US4] Completar `app/Http/Controllers/Api/OrderController.php`
      (index/show com policy) e criar
      `app/Http/Controllers/Api/TicketController.php` (index com claim por
      e-mail normalizado, show, receipt) — bindings por code
- [X] T020 [US4] Criar `app/Domain/Events/Services/TicketReceiptPdf.php`
      (dompdf + QR SVG inline do code) e a view
      `resources/views/pdf/ticket-receipt.blade.php` (evento, participante,
      tipo, código, QR — pt-BR)
- [X] T021 [P] [US4] Criar `frontend/src/pages/MeusPedidos.jsx` (situação,
      contagem regressiva simples do prazo, tickets aninhados) e
      `frontend/src/pages/MeusIngressos.jsx` (lista + botão comprovante quando
      receiptAvailable); linkar em `frontend/src/pages/MinhaConta.jsx` e rotas
      `/minha-conta/pedidos` e `/minha-conta/ingressos` em App.jsx

**Checkpoint**: pós-compra completo.

---

## Phase 7: User Story 5 - Reserva expira e libera as vagas (Priority: P5)

**Goal**: expiração automática idempotente que devolve disponibilidades.

**Independent Test**: quickstart.md §US5.

### Tests for User Story 5

- [X] T022 [P] [US5] Feature test em `tests/Feature/Purchase/ExpireTest.php`:
      pedido vencido (setTestNow além do TTL) → orders:expire marca expired,
      tickets vivos → cancelled "Reserva expirada", vagas/lote/estoque
      liberados (nova compra passa); dentro do prazo → intacto; segunda
      execução → inócua; pedido expirado rejeita transição (terminal);
      pedido de voucher (paid) nunca expira

### Implementation for User Story 5

- [X] T023 [US5] Criar `app/Console/Commands/ExpireReservations.php`
      (`orders:expire` — por pedido: transação com lock do evento, transitionTo
      expired, tickets → cancelled com motivo, recountSold dos afetados) e
      agendar a cada 5 min em `routes/console.php`

**Checkpoint**: ciclo da reserva fechado.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T024 Executar `specs/004-catalogo-compra/quickstart.md` de ponta a ponta,
      incluindo o **smoke de concorrência real** (§US2 item 4: N compras
      paralelas via curl disputando a última vaga → exatamente 1 sucesso) e os
      fluxos manuais no navegador; corrigir o que falhar
- [X] T025 [P] Varredura: suítes 001–003 verdes; nenhuma coluna nova; códigos
      públicos em todas as URLs novas; build do frontend ok
- [X] T026 Atualizar `ROADMAP.md` (004 ✅) e `specs/004-catalogo-compra/spec.md`
      (Status: Draft → Implemented)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** → **Phase 2 (Foundational)** → US1…US5
- **US1**: independente (leitura pública)
- **US2**: depende da Fase 2; não depende da US1 em runtime (mas o front do
  checkout parte do TicketPicker)
- **US3**: depende da US2 (integra o purchase)
- **US4**: depende da US2 (precisa de pedidos criados)
- **US5**: depende da US2; independente de US3/US4
- **Phase 8**: por último

### Key task-level dependencies

- T010 (purchase) antes de T012 (controller) e de T015/T016 (cortesias)
- T012 (resources) antes de T019 (index/show reusam)
- T020 (PDF) antes do botão de comprovante em T021 funcionar de fato
- Testes de cada US antes da implementação correspondente (devem falhar
  primeiro)

### Parallel Opportunities

- Setup: T002 ∥ T003 (após T001)
- US1: T005 ∥ (T006 → T007); T007 em paralelo com T006 após o contrato
- US2: T008 ∥ T009 ∥ T011; T013 (front) em paralelo com T010–T012
- Após US2: US3 ∥ US4 ∥ US5 (tocam arquivos distintos — exceto
  Checkout.jsx entre US2/US3, sequenciar T013 → T016)
- Todos os testes de US (T005/T008/T009/T014/T017/T018/T022) são [P] entre si

## Parallel Example: pós-US2

```bash
# Stories independentes em paralelo:
Task: "US3 cortesias (T014–T016)"
Task: "US4 minha área + PDF (T017–T021)"
Task: "US5 expiração (T022–T023)"
```

## Implementation Strategy

**MVP first**: Fases 1–3 (US1) entregam a página pública demonstrável com o
evento do seed. US2 é o coração (compra com as guardas) — validar o checkpoint
com os testes de contenção antes de seguir. US3/US4/US5 podem correr em
paralelo. O smoke de concorrência real fica no Polish, com a API no ar. Merge na
`main` só com a suíte inteira verde e o smoke com zero overselling.
