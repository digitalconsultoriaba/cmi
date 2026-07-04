# Tasks: Ciclo de Vida e Suporte

**Input**: Design documents from `/specs/006-ciclo-vida-suporte/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md,
contracts/lifecycle-api.md, quickstart.md — e as specs 001–005 mergeadas.

**Tests**: INCLUÍDOS — exigência da constituição; janelas da política com
`Carbon::setTestNow`; estorno de cartão via fake da 005.

**Organization**: agrupado por user story; **nenhuma migration nova**.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizável (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1–US5 (mapeia para spec.md)

## Path Conventions

Services em `app/Domain/Events/Services/`; testes em
`tests/Feature/Lifecycle/`.

---

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Adicionar janelas da política a `config/events.php`
      (`refund_full_until_days` = 7, `refund_purchase_grace_days` = 7, env
      opcionais) — política registrada na spec (100% até D-7 + pisos)
- [X] T002 [P] Emenda ao contrato do gateway: criar
      `app/Domain/Events/Payments/RefundResult.php` (`refunded, externalId?,
      raw`) e `RefundNotSupported.php` (exceção); adicionar
      `refundCharge(Payment, string $amount): RefundResult` à interface
      `PaymentGatewayContract.php`; implementar no `FakeCardGateway` (estorna
      no banco simulado) e lançar `RefundNotSupported` em
      `FakePixGateway`/`SicoobGateway`; registrar a emenda em
      `specs/005-pagamento/contracts/gateway-contract.md`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: política de reembolso e rotas — bloqueiam todas as US.

- [X] T003 Criar `app/Domain/Events/Services/RefundPolicy.php`:
      `refundableAmount(Ticket): string` (100% se ≤ 7 dias da compra [CDC] OU
      ≥ 7 dias antes do evento; senão 0.00) e
      `refundableForEventCancellation(Order): string` (amountPaid — 100%
      sempre); janelas lidas da config
- [X] T004 Adicionar rotas em `routes/api.php`: inscrito —
      `POST /tickets/{ticket:code}/cancel`, `POST /orders/{order:code}/cancel`,
      `POST /tickets/{ticket:code}/transfer`, `GET|POST /support-cases`,
      `GET /support-cases/{supportCase}`,
      `POST /support-cases/{supportCase}/notes`; organização
      (`require.role:admin,treasury`) — `GET /admin/support-cases`,
      `GET /admin/support-cases/{supportCase}`, `POST .../notes`,
      `POST .../finish`, `POST .../reopen`; tesouraria — `GET /treasury/refunds`,
      `POST /treasury/refunds/{supportCase}/execute`

**Checkpoint**: fundação pronta — user stories podem começar.

---

## Phase 3: User Story 1 - Cancelar meu pedido ou ingresso (Priority: P1) 🎯 MVP

**Goal**: cancelamento pelo inscrito com vaga liberada e caso de reembolso pela
política.

**Independent Test**: quickstart.md §US1.

### Tests for User Story 1

- [X] T005 [P] [US1] Feature test em
      `tests/Feature/Lifecycle/CancelTest.php`: pendente cancela (vaga
      liberada via recount, cobrança expirada, sem caso); pago a >7 dias do
      evento → caso refund open com refund_amount = unit_price; compra há <7
      dias com evento a <7 dias → 100% (piso CDC via setTestNow); evento a <7
      dias e compra antiga → 409 `refund_confirmation_required` sem
      confirm_no_refund e cancela com 0,00 quando confirmado;
      `allow_user_cancel=false` → 409; ticket usado/transferido → 409; dono de
      outro → 403; trilha (requested_by/cancelled_by/motivo); cancelar pedido
      pago inteiro → 1 caso com amountPaid

### Implementation for User Story 1

- [X] T006 [US1] Criar `app/Domain/Events/Services/TicketLifecycleService.php`
      com `cancelTicket()` e `cancelOrder()` (transação + lock do evento,
      guardas, recountSold, expirePendingPayments no pedido, caso refund
      automático com nota de origem, e-mail `TicketCancelledPtBr`)
- [X] T007 [P] [US1] Criar notificação
      `app/Notifications/TicketCancelledPtBr.php` (confirmação + situação do
      reembolso conforme política)
- [X] T008 [US1] Criar `app/Http/Controllers/Api/TicketLifecycleController.php`
      (métodos cancelTicket/cancelOrder — policies de dono; payload
      `{reason?, confirm_no_refund?}`) e estender
      `app/Http/Resources/TicketResource.php` com `cancellable`,
      `refundPreview` (RefundPolicy) e códigos de transferência
- [X] T009 [US1] Front: modal de cancelamento em
      `frontend/src/pages/MeusIngressos.jsx` (mostra refundPreview ou
      confirmação "sem devolução") e cancelar pedido em
      `frontend/src/pages/MeusPedidos.jsx`

**Checkpoint**: cancelamento completo com política aplicada.

---

## Phase 4: User Story 2 - Transferir meu ingresso (Priority: P2)

**Goal**: original terminal + novo ingresso confirmado vinculado, vagas neutras.

**Independent Test**: quickstart.md §US2.

### Tests for User Story 2

- [X] T010 [P] [US2] Feature test em
      `tests/Feature/Lifecycle/TransferTest.php`: transfere confirmado →
      original `transferred` com transferred_to, novo `confirmed` com
      transferred_from/snapshot herdado (unit_price/camisa), vagas líquidas
      inalteradas, e-mail ao destinatário; destinatário com conta vê em "meus
      ingressos" (claim); reservado → 409; voucher resgatado → 409; usado →
      409; re-transferir o antigo → 409; evento iniciado → 409;
      `allow_transfer=false` → 409; não-dono → 403

### Implementation for User Story 2

- [X] T011 [US2] Adicionar `transferTicket()` ao
      `app/Domain/Events/Services/TicketLifecycleService.php` (guardas incl.
      detecção de voucher via redeemed_ticket_id; clonagem com snapshot;
      vínculos bidirecionais) + notificação
      `app/Notifications/TicketTransferredPtBr.php`
- [X] T012 [US2] Adicionar `transfer` ao `TicketLifecycleController` com
      `app/Http/Requests/TransferTicketRequest.php` (nome/e-mail obrigatórios,
      e-mail normalizado) e `transferable` no TicketResource; modal de
      transferência em `frontend/src/pages/MeusIngressos.jsx`

**Checkpoint**: transferência fechada (guarda que a 007 consumirá).

---

## Phase 5: User Story 3 - Estorno pela tesouraria (Priority: P3)

**Goal**: fluxo único de devolução com conector/operacional, trilha e e-mail.

**Independent Test**: quickstart.md §US3.

### Tests for User Story 3

- [X] T013 [P] [US3] Feature test em
      `tests/Feature/Lifecycle/RefundTest.php`: fila `/treasury/refunds` lista
      casos refund abertos (attendee → 403); executar estorno de cartão → 
      refundCharge no fake, tickets com refunded_at/refund_amount, payment
      `refunded` (total), caso finished com nota, e-mail
      `RefundCompletedPtBr`; estorno de pix → exige justificativa (422 sem),
      origem operacional na trilha; parcial (amount menor) → payment continua
      paid + registro; repetir sobre caso fechado → 409; **operador comprador →
      403**

### Implementation for User Story 3

- [X] T014 [US3] Criar `app/Domain/Events/Services/RefundPayment.php`
      (guardas; cartão → refundCharge / demais → operacional com justificativa;
      efeitos em tickets/payment/caso; e-mail) + notificação
      `app/Notifications/RefundCompletedPtBr.php`
- [X] T015 [US3] Criar
      `app/Http/Controllers/Api/Treasury/RefundController.php` (fila +
      execute com `app/Http/Requests/ExecuteRefundRequest.php`) e a seção
      **Estornos** em `frontend/src/admin/pages/Tesouraria.jsx` (fila de casos
      + modal executar com justificativa)

**Checkpoint**: ciclo financeiro de devolução fechado.

---

## Phase 6: User Story 4 - Suporte (Priority: P4)

**Goal**: casos com conversas, visibilidade por nota e filas por papel.

**Independent Test**: quickstart.md §US4.

### Tests for User Story 4

- [X] T016 [P] [US4] Feature test em
      `tests/Feature/Lifecycle/SupportTest.php`: inscrito abre caso (com e sem
      vínculo a pedido/ingresso) → open na fila; responde e vê SÓ notas
      públicas; caso alheio → 403; organização vê tudo, nota interna invisível
      ao inscrito; finish → finished; inscrito adiciona nota em finished →
      reopened; admin E treasury acessam a fila (attendee → 403)

### Implementation for User Story 4

- [X] T017 [US4] Criar `app/Policies/SupportCasePolicy.php` (view: dono),
      `app/Http/Resources/SupportCaseResource.php` (notas filtradas por
      audiência), `app/Http/Requests/SupportCaseRequest.php` e os controllers
      `app/Http/Controllers/Api/SupportCaseController.php` (inscrito:
      index/store/show/addNote com reopen automático) e
      `app/Http/Controllers/Api/Admin/SupportQueueController.php` (fila/show/
      addNote com visible_to_attendee/finish/reopen); registrar policy no
      AppServiceProvider
- [X] T018 [US4] Front: `frontend/src/pages/Suporte.jsx` (lista + conversa +
      novo caso) com rota `/minha-conta/suporte` em `frontend/src/App.jsx` e
      link em `MinhaConta.jsx`; `frontend/src/admin/pages/Suporte.jsx` (fila
      com filtros, conversa com toggle público/interno, finalizar/reabrir) no
      menu do painel (roles admin+treasury)

**Checkpoint**: canal de suporte completo.

---

## Phase 7: User Story 5 - Cancelar o evento inteiro (Priority: P5)

**Goal**: cascata resiliente com fila de devoluções 100% e avisos.

**Independent Test**: quickstart.md §US5.

### Tests for User Story 5

- [X] T019 [P] [US5] Feature test em
      `tests/Feature/Lifecycle/EventCascadeTest.php`: evento com pedidos pago/
      pendente/já-cancelado → cancelar evento → vivos cancelados (motivo
      "Evento cancelado"), cobranças pendentes expiradas, caso refund 100%
      APENAS por pedido pago (amountPaid), `EventCancelledPtBr` por comprador
      afetado, histórico intacto; simular falha em um pedido (mock/estado
      inválido) → demais processam (resiliência); compra/pagamento/transferência
      após cancelamento → 409 (guardas existentes)

### Implementation for User Story 5

- [X] T020 [US5] Criar `app/Domain/Events/Services/CancelEventCascade.php`
      (iteração resiliente try/catch por pedido, log de falhas) + notificação
      `app/Notifications/EventCancelledPtBr.php`; integrar ao
      `app/Domain/Events/Services/EventConfigService.php@cancel` (003)

**Checkpoint**: todas as US completas.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T021 Executar `specs/006-ciclo-vida-suporte/quickstart.md` de ponta a
      ponta (fluxos manuais US1–US4 no navegador; US5 opcional com make fresh)
      e corrigir o que falhar
- [X] T022 [P] Varredura: suítes 001–005 verdes; nenhuma coluna nova;
      elegibilidade só no servidor; build do frontend ok
- [X] T023 Atualizar `ROADMAP.md` (006 ✅) e
      `specs/006-ciclo-vida-suporte/spec.md` (Status: Draft → Implemented)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** → **Phase 2 (Foundational)** → US1…US5
- **US1**: primeira — cria o TicketLifecycleService e os casos de reembolso
- **US2**: depende do service da US1 (mesmo arquivo — sequencial a ela)
- **US3**: depende da US1 (consome os casos criados)
- **US4**: independente após a Fase 2 (∥ US2/US3)
- **US5**: depende de US1 (cancelamento) e US3 (casos de reembolso)
- **Phase 8**: por último

### Key task-level dependencies

- T003 (RefundPolicy) antes de T005/T006/T008
- T006 (service) antes de T011 (mesmo arquivo) e de T014 (casos)
- T008 e T012 tocam TicketLifecycleController/TicketResource — sequenciais
- T009/T012 tocam MeusIngressos.jsx — sequenciais
- Testes de cada US antes da implementação correspondente

### Parallel Opportunities

- Setup: T001 ∥ T002
- US1: T005 ∥ T007 (T006 depois de T003)
- US4 inteira ∥ US2/US3 (arquivos distintos)
- Todos os testes de US (T005/T010/T013/T016/T019) são [P] entre si

## Parallel Example: pós-US1

```bash
Task: "US3 estorno (T013–T015)"
Task: "US4 suporte (T016–T018)"
# US2 aguarda o service (mesmo arquivo), depois corre em paralelo com o resto
```

## Implementation Strategy

**MVP first**: Fases 1–3 (US1) entregam o cancelamento com política — o fluxo
de pós-venda mais usado. US2/US3 fecham transferência e devolução; US4 o canal;
US5 reusa tudo na cascata. Merge na `main` só com a suíte inteira verde e
quickstart validado.
