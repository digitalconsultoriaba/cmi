# Tasks: Pagamento

**Input**: Design documents from `/specs/005-pagamento/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/
(payment-api.md, gateway-contract.md), quickstart.md — e as specs 001–004
mergeadas.

**Tests**: INCLUÍDOS — exigência da constituição; drivers fake + `Http::fake()`
para o Sicoob; varredura anti-PAN automatizada (SC-005).

**Organization**: agrupado por user story; **nenhuma migration nova**. A
fundação da baixa (RegisterPayment + fakes) fica na Fase 2 porque TODAS as
stories a consomem; a US2 endurece a confiabilidade sobre ela.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizável (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1–US5 (mapeia para spec.md)

## Path Conventions

Gateways em `app/Domain/Events/Payments/`; services em
`app/Domain/Events/Services/`; testes em `tests/Feature/Payment/`.

---

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Criar `config/payments.php` (pix_driver/card_driver com default
      `fake`, bloco `sicoob` — base_url sandbox/prod, client_id, cert paths,
      webhook_secret —, `webhook_secret` do cartão) e adicionar placeholders ao
      `.env.example` (`PAYMENTS_PIX_DRIVER`, `PAYMENTS_CARD_DRIVER`,
      `SICOOB_CLIENT_ID`, `SICOOB_CERT_PATH`, `SICOOB_CERT_KEY_PATH`,
      `SICOOB_SANDBOX`, `SICOOB_WEBHOOK_SECRET`, `CARD_WEBHOOK_SECRET`) e
      valores de dev ao `.env`
- [X] T002 [P] Criar `app/Domain/Events/Payments/PaymentGatewayContract.php`
      (5 métodos do gateway-contract.md) e os DTOs readonly `ChargeData.php`,
      `CardResult.php`, `ChargeStatus.php` e
      `app/Domain/Events/Services/PaymentEvidence.php` (source, raw, actor?)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: fakes, ponto único de baixa e casca de rotas — TODAS as stories
dependem disto.

**⚠️ CRITICAL**: nenhuma US começa antes desta fase terminar.

- [X] T003 Criar `app/Domain/Events/Payments/FakePixGateway.php`: banco simulado
      em tabela de cache/estático por teste com `settle(externalId, ?amount)`;
      createPixCharge (externalId `fakepix-…`, copia-e-cola determinístico,
      expiresAt ≤ reserved_until), createBoletoCharge (linha/barcode/pix
      híbrido), getChargeStatus (lê o simulado), cancelCharge
- [X] T004 [P] Criar `app/Domain/Events/Payments/FakeCardGateway.php`:
      chargeCard — `tok_ok_*` aprova (brand/last4 fake), `tok_declined_*`
      recusa com motivo; **guarda anti-PAN** (token contendo 13+ dígitos
      seguidos → exceção); getChargeStatus/cancelCharge coerentes
- [X] T005 Registrar bind dos drivers por config em
      `app/Providers/AppServiceProvider.php` (contract → Sicoob|FakePix para
      pix/boleto; card driver separado — container resolve por
      `config('payments.*')`)
- [X] T006 Criar `app/Domain/Events/Services/RegisterPayment.php` — PONTO ÚNICO
      (data-model, fluxo da baixa): transação + lockForUpdate no payment; já
      paid → no-op; evidência bruta (raw_response/paid_at/registered_by);
      valor divergente → order partially_paid sem confirmar tickets; order
      terminal → registra sem reativar; caminho feliz → order paid + tickets
      reserved→confirmed + `PaymentConfirmedPtBr` após commit
- [X] T007 [P] Criar notificações queued
      `app/Notifications/PaymentConfirmedPtBr.php` (ingressos confirmados) e
      `app/Notifications/BoletoIssuedPtBr.php` (linha digitável + instruções) —
      pt-BR; falha de e-mail nunca propaga
- [X] T008 Criar `app/Domain/Events/Services/CreateCharge.php`: valida dono/
      pending/dentro do TTL/meio habilitado no evento (403/409 com types do
      data-model); expira payments pending anteriores (+ cancelCharge melhor
      esforço); cria payment do meio com campos próprios (QR SVG do pix via
      simple-qrcode)
- [X] T009 Adicionar rotas em `routes/api.php`: grupo auth — POST
      `/orders/{order:code}/checkout/{pix|boleto|card}`, GET
      `/orders/{order:code}/payment-status`; sem sessão — POST
      `/webhooks/sicoob`, `/webhooks/card`; grupo `require.role:treasury` — GET
      `/treasury/receivables`, POST `/treasury/reconcile`, POST
      `/treasury/orders/{order:code}/pay-manual` (controllers das fases
      seguintes)

**Checkpoint**: fundação da baixa pronta e testável.

---

## Phase 3: User Story 1 - Pagar com Pix e confirmar na hora (Priority: P1) 🎯 MVP

**Goal**: cobrança Pix + webhook confirmando + tela atualizando + e-mail.

**Independent Test**: quickstart.md §US1.

### Tests for User Story 1

- [X] T010 [P] [US1] Feature test em
      `tests/Feature/Payment/CheckoutPixTest.php`: cria cobrança (campos pix,
      QR SVG, expiração ≤ reserved_until, payment pending vinculado); segunda
      cobrança expira a primeira (uma ativa); dono de outro pedido → 403;
      pedido expirado → 409; `allow_pix=false` → 409 method_disabled;
      payment-status retorna pending
- [X] T011 [P] [US1] Feature test em
      `tests/Feature/Payment/WebhookHappyPathTest.php`: settle no fake + POST
      /webhooks/sicoob assinado → order paid, tickets confirmed, payment com
      paid_at/raw_response, payment-status `paid`,
      `PaymentConfirmedPtBr` enviada ao comprador

### Implementation for User Story 1

- [X] T012 [US1] Criar `app/Http/Controllers/Api/CheckoutController.php`
      (pix + paymentStatus usando CreateCharge/policies) e
      `app/Http/Resources/PaymentResource.php` (shape do contrato)
- [X] T013 [US1] Criar `app/Http/Controllers/Api/WebhookController.php` método
      `sicoob`: grava webhook_events (dupe → 200 ignored), verifica
      `X-Webhook-Secret` (falha → 401 + registro `error`), **reconsulta**
      getChargeStatus, paga → RegisterPayment (fonte webhook), marca
      processed_at/result
- [X] T014 [US1] Criar `frontend/src/pages/PagarPedido.jsx` (aba Pix: QR SVG +
      copia-e-cola com copiar + polling 3s do payment-status + celebração ao
      pagar), botão "Pagar" nos pedidos pending em
      `frontend/src/pages/MeusPedidos.jsx` e rota `/pedido/:code/pagar` em
      `frontend/src/App.jsx`

**Checkpoint**: Pix ponta a ponta com fake — MVP do pagamento.

---

## Phase 4: User Story 2 - Baixa única e confiável (Priority: P2)

**Goal**: idempotência total, verificação de origem, reconsulta obrigatória e
reconciliação diária.

**Independent Test**: quickstart.md §US2.

### Tests for User Story 2

- [X] T015 [P] [US2] Feature test em
      `tests/Feature/Payment/WebhookReliabilityTest.php`: mesmo webhook 2× →
      1 baixa (2º = 200 ignored, valor nunca dobra); assinatura inválida → 401
      registrado result=error sem efeito; corpo dizendo "pago" mas provedor
      pending → NÃO baixa (reconsulta manda); payload bruto persistido
- [X] T016 [P] [US2] Feature test em
      `tests/Feature/Payment/RegisterPaymentTest.php`: register 2× → uma
      transição só; valor divergente → payment paid + order partially_paid +
      tickets NÃO confirmados; order expirada → payment paid + order intocada
      (pendência derivada aparece na query da tesouraria); baixa manual grava
      registered_by
- [X] T017 [P] [US2] Feature test em
      `tests/Feature/Payment/ReconcileTest.php`: settle sem webhook →
      `payments:reconcile` baixa (fonte reconciliation); cobrança expirada no
      provedor → payment expired; resumo {checked, settled, expired}; comando
      idempotente

### Implementation for User Story 2

- [X] T018 [US2] Criar `app/Domain/Events/Services/ReconcilePayments.php`
      (varre payments pending de orders não terminais → getChargeStatus →
      RegisterPayment/expira; retorna resumo) +
      `app/Console/Commands/ReconcilePaymentsCommand.php` (`payments:reconcile`)
      + agendar diário 04:00 em `routes/console.php`

**Checkpoint**: princípio III blindado por testes.

---

## Phase 5: User Story 3 - Boleto híbrido (Priority: P3)

**Goal**: linha digitável + QR Pix na mesma cobrança, e-mail, compensação.

**Independent Test**: quickstart.md §US3.

### Tests for User Story 3

- [X] T019 [P] [US3] Feature test em
      `tests/Feature/Payment/CheckoutBoletoTest.php`: cobrança híbrida
      (boleto_line + barcode + pix da mesma cobrança), vencimento ≤
      reserved_until, `BoletoIssuedPtBr` enviada; liquidação via reconcile
      confirma pedido; `allow_boleto=false` → 409

### Implementation for User Story 3

- [X] T020 [US3] Adicionar `boleto` ao `CheckoutController` (CreateCharge já
      genérico) e a aba Boleto em `frontend/src/pages/PagarPedido.jsx` (linha
      digitável com copiar + QR pix híbrido + aviso de compensação)

**Checkpoint**: três meios bancários cobertos (pix imediato + boleto híbrido).

---

## Phase 6: User Story 4 - Cartão sem expor dados (Priority: P4)

**Goal**: tokenização local, aprovação síncrona, recusa clara, anti-PAN.

**Independent Test**: quickstart.md §US4.

### Tests for User Story 4

- [X] T021 [P] [US4] Feature test em `tests/Feature/Payment/CardTest.php`:
      `tok_ok_*` → pedido pago na mesma resposta (tickets confirmados, e-mail);
      `tok_declined_*` → 409 card_declined, pedido pending, retry possível;
      installments 1..12 gravado (0/13 → 422); token parecendo PAN → exceção
      do fake (nunca processa)
- [X] T022 [P] [US4] Feature test em `tests/Feature/Payment/AntiPanTest.php`:
      executa fluxos pix/boleto/cartão e varre `payments`+`webhook_events`
      (todas as colunas), resposta das APIs e `storage/logs` por padrões de
      PAN (`\d{13,19}` com Luhn) → zero ocorrências (SC-005)

### Implementation for User Story 4

- [X] T023 [US4] Adicionar `card` ao CheckoutController
      (`CardCheckoutRequest` — token obrigatório, installments 1..12; aprovado
      → RegisterPayment fonte gateway na mesma request), método `card` no
      WebhookController (dedupe+secret, eventos assíncronos registrados) e a
      aba Cartão em `frontend/src/pages/PagarPedido.jsx` (formulário local que
      NUNCA envia o número — monta `tok_ok_…`/`tok_declined_…` pelos cartões de
      teste, seletor de parcelas)

**Checkpoint**: tripé de meios completo.

---

## Phase 7: User Story 5 - Tesouraria (Priority: P5)

**Goal**: recebimentos com pendências derivadas, conciliação sob demanda e
baixa manual auditada.

**Independent Test**: quickstart.md §US5.

### Tests for User Story 5

- [X] T024 [P] [US5] Feature test em `tests/Feature/Payment/TreasuryTest.php`:
      receivables lista com filtros status/method e pendências derivadas
      destacadas (paid × order não-pago); attendee → 403; reconcile endpoint
      retorna resumo; pay-manual: sem justificativa → 422, confirma com
      registered_by e fonte manual, **comprador-operador → 403**, pedido pago
      → 409

### Implementation for User Story 5

- [X] T025 [US5] Criar
      `app/Http/Controllers/Api/Treasury/TreasuryController.php`
      (receivables com joins/filtros + pendência derivada; reconcile chamando
      o service; payManual com `PayManualRequest` — justificativa obrigatória —
      e guarda comprador≠operador antes do RegisterPayment)
- [X] T026 [US5] Front: `frontend/src/auth/RoleRoute.jsx` aceita
      `roles={[...]}` (qualquer um), menu de `frontend/src/admin/AdminLayout.jsx`
      filtrado por papel (+ item Tesouraria p/ treasury/admin, rotas /painel
      liberadas a ambos) e tela `frontend/src/admin/pages/Tesouraria.jsx`
      (tabela de recebimentos + filtros + badge pendência, botão "Conciliar
      agora" com resumo, modal de baixa manual com justificativa e erros
      403/409 tratados)

**Checkpoint**: operação financeira completa.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T027 Criar `app/Domain/Events/Payments/SicoobClient.php` (OAuth2
      client_credentials com cache de token + mTLS via cert paths; métodos
      createPixCharge/createHybridBoleto/getCharge/cancelCharge) e
      `SicoobGateway.php` (mapeia para o contrato) + teste
      `tests/Feature/Payment/SicoobClientTest.php` com `Http::fake()`
      (token, criação de cobrança, consulta, erro de certificado registrado)
- [X] T028 Integrar cancelamento de cobrança à expiração: em
      `app/Console/Commands/ExpireReservations.php`, expirar payments pending
      do pedido + `cancelCharge` melhor esforço (teste em
      `tests/Feature/Purchase/ExpireTest.php` — cobrança cancelada junto)
- [X] T029 Executar `specs/005-pagamento/quickstart.md` completo: fluxos
      manuais pix/boleto/cartão no navegador (fake), Mailpit com os 2 e-mails,
      tesouraria; varredura de segredos (placeholders) e build do frontend
- [X] T030 Atualizar `ROADMAP.md` (005 ✅; 007/008 desbloqueadas em paralelo) e
      `specs/005-pagamento/spec.md` (Status: Draft → Implemented)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** → **Phase 2 (Foundational)** → US1…US5
- **US1 (Pix)**: primeira entrega ponta a ponta (usa T006/T008 da fundação)
- **US2**: endurece o que a US1 usa — logo após a US1 (T015 reusa o webhook
  T013)
- **US3/US4**: independentes entre si após US1 (reusam CheckoutController/
  PagarPedido — sequenciar edições no mesmo arquivo)
- **US5**: após US2 (reusa ReconcilePayments) — pode correr em paralelo com
  US3/US4
- **Phase 8**: por último (Sicoob real não bloqueia nada)

### Key task-level dependencies

- T006 (RegisterPayment) antes de T011/T013/T016/T018/T023/T025
- T008 (CreateCharge) antes de T012/T020/T023
- T013 (webhook) antes de T015; T018 (reconcile) antes de T017/T024/T025
- T012→T020→T023 e T014→T020→T023 tocam os mesmos arquivos
  (CheckoutController/PagarPedido) — sequenciais
- Testes de cada US antes da implementação correspondente

### Parallel Opportunities

- Setup: T002 ∥ T001
- Foundational: T003 ∥ T004 ∥ T007 (T005 após T003/T004; T006 após T002)
- US1: T010 ∥ T011; US2: T015 ∥ T016 ∥ T017
- Após US2: US5 ∥ (US3 → US4)
- T027 (Sicoob real) paralelizável com qualquer story (arquivos próprios)

## Parallel Example: Foundational

```bash
Task: "T003 FakePixGateway"
Task: "T004 FakeCardGateway"
Task: "T007 Notificações pt-BR"
```

## Implementation Strategy

**MVP first**: Fases 1–3 entregam Pix ponta a ponta com fake (demonstrável no
navegador com webhook simulado). US2 blinda o princípio III antes de ampliar a
superfície (boleto/cartão). Tesouraria fecha a operação. O driver Sicoob real
(T027) é aditivo — entra por env quando as credenciais existirem, sem tocar no
fluxo. Merge na `main` só com a suíte inteira verde e anti-PAN limpo.
