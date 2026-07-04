# Implementation Plan: Pagamento

**Branch**: `005-pagamento` | **Date**: 2026-07-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/005-pagamento/spec.md`

## Summary

O desmembramento que dá nome ao projeto: `PaymentGatewayContract` com drivers
configuráveis (Sicoob real via `SicoobClient` OAuth2+mTLS; fakes como default de
dev/teste — bloqueadores externos não travam a entrega); checkout Pix (QR +
copia-e-cola + polling), boleto híbrido e cartão tokenizado (PAN nunca no
backend, nem no fake); `RegisterPayment` como ponto único de baixa idempotente
(lock + guarda "já pago" + evidência bruta) consumido por webhooks (dedupe +
verificação de origem + **reconsulta obrigatória**), reconciliação diária
(`payments:reconcile` + disparo da tesouraria) e baixa manual (quem compra não
baixa — 403); políticas explícitas para valor divergente (parcial) e pagamento
de pedido expirado (registra sem reativar); painel da tesouraria no layout
existente com pendências derivadas. **Nenhuma tabela nova.**

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12); JavaScript ES2022 (React 18)

**Primary Dependencies**: nenhuma nova — HTTP client nativo (Sicoob),
simple-qrcode (QR do Pix, já presente), filas database (e-mails)

**Storage**: MySQL 8 — `payments`/`webhook_events` da 001; config nova
`config/payments.php`; **zero migrations**

**Testing**: PHPUnit Feature em `app_test`; `Http::fake()` para SicoobClient;
FakePixGateway com "banco simulado" controlável (`settle()`);
`Notification::fake()`; varredura anti-PAN automatizada

**Target Platform**: idem specs anteriores; produção exigirá HTTPS público
(webhooks) + certificado A1 — pendências externas registradas

**Project Type**: web application — API + SPA existentes

**Performance Goals**: criar cobrança < 2s; webhook processado < 1s; polling
3s; confirmação ponta a ponta < 30s (SC-001)

**Constraints**: princípio III (ponto único idempotente; reconsulta; quem compra
não baixa) e IV (PAN nunca; segredos fora do VCS); envelope/erros da 001;
DECIMAL em centavos exatos; trilha completa

**Scale/Scope**: 8 endpoints + 2 webhooks + 1 comando agendado, 2 áreas de
front (pagar + tesouraria), 0 migrations, 5 user stories

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação |
|---|---|
| I. Standalone, RBAC | ✅ Tesouraria sob `require.role:treasury`; comprador só no próprio pedido (policy da 004). |
| II. Estado derivado | ✅ Pendências da tesouraria são consulta derivada (sem flag); confirmação de tickets só via baixa; nenhuma coluna nova. |
| III. Ponto único de baixa (NÃO NEGOCIÁVEL) | ✅ Núcleo da spec: `RegisterPayment` único caminho; idempotência estrutural (uniques + lock + guarda); webhook nunca é fonte única (reconsulta obrigatória); `payments:reconcile` diário; **comprador nunca baixa o próprio pedido — 403 mesmo com papel** (FR-013). |
| IV. Segurança de pagamento (NÃO NEGOCIÁVEL) | ✅ Token opaco com guarda anti-PAN até no fake; certificado/segredos só por env (placeholders no exemplo); webhooks verificados por segredo; QR usa código público; teste automatizado varre PAN em banco/log/payload (SC-005). |
| V. Histórico | ✅ Evidência bruta em toda baixa (webhook_events + raw_response); origem/autor registrados; payment sem soft delete (correção = novo registro, decisão da 001). |
| VI. Specs por área | ✅ Consome 001–004 sem redefinir; estorno explicitamente delegado à 006; relatórios financeiros à 008. |
| Stack e convenções | ✅ Sem dependência nova; filas via queue (constituição). |

**Resultado**: PASS (pré-Phase 0 e pós-Phase 1). Sem entradas em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/005-pagamento/
├── plan.md              # Este arquivo
├── research.md          # 10 decisões
├── data-model.md        # 0 tabelas — máquina de estados da baixa + invariantes
├── quickstart.md        # validação por user story + sandbox Sicoob opcional
├── contracts/
│   ├── payment-api.md   # checkout, webhooks, tesouraria, comandos
│   └── gateway-contract.md # PaymentGatewayContract + drivers + fakes
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks (próximo passo)
```

### Source Code (repository root)

```text
app/
├── Domain/Events/Payments/
│   ├── PaymentGatewayContract.php      # interface (5 métodos)
│   ├── ChargeData.php / CardResult.php / ChargeStatus.php / PaymentEvidence.php
│   ├── SicoobClient.php                # OAuth2 client_credentials + mTLS
│   ├── SicoobGateway.php               # pix/boleto híbrido via client
│   ├── FakePixGateway.php              # banco simulado (settle controlável)
│   └── FakeCardGateway.php             # tok_ok/tok_declined + guarda anti-PAN
├── Domain/Events/Services/
│   ├── RegisterPayment.php             # PONTO ÚNICO de baixa (idempotente)
│   ├── CreateCharge.php                # cria cobrança (expira anteriores)
│   └── ReconcilePayments.php           # varredura (comando + tesouraria)
├── Console/Commands/ReconcilePaymentsCommand.php  # payments:reconcile (diário)
├── Http/Controllers/Api/
│   ├── CheckoutController.php          # pix/boleto/card + payment-status
│   ├── WebhookController.php           # sicoob/card (dedupe + reconsulta)
│   └── Treasury/TreasuryController.php # receivables/reconcile/pay-manual
├── Http/Requests/{CardCheckoutRequest,PayManualRequest}.php
├── Http/Resources/PaymentResource.php
├── Notifications/{BoletoIssuedPtBr,PaymentConfirmedPtBr}.php  # queued
├── Providers/AppServiceProvider.php    # bind dos drivers por config
config/payments.php                     # drivers, sicoob.*, webhook secrets
routes/api.php                          # checkout + webhooks + treasury
routes/console.php                      # schedule payments:reconcile daily
tests/Feature/Payment/                  # Checkout, Webhook, RegisterPayment,
                                        # Reconcile, Card, Treasury, AntiPan
frontend/src/
├── pages/PagarPedido.jsx               # abas pix/boleto/cartão + polling
├── admin/pages/Tesouraria.jsx          # recebimentos + conciliar + baixa manual
├── auth/RoleRoute.jsx                  # aceita roles=[...] (qualquer um)
└── admin/AdminLayout.jsx               # menu filtrado por papel + item Tesouraria
```

**Structure Decision**: gateways isolados em `Domain/Events/Payments` (o
contrato é a fronteira); services de baixa no domínio; tesouraria como
sub-namespace de controllers.

## Fases de implementação (visão para /speckit-tasks)

1. **Setup**: `config/payments.php` + env placeholders; DTOs + contrato; bind
   por config no provider.
2. **US2 primeiro (fundação da baixa)**: RegisterPayment + fakes + testes de
   idempotência/valor divergente/pedido expirado — o resto consome isso.
3. **US1 — Pix**: CreateCharge + FakePix/SicoobGateway(+Client) +
   CheckoutController pix/status + WebhookController sicoob + notificação +
   página Pagar (aba Pix + polling); testes.
4. **US3 — boleto**: createBoletoCharge (híbrido) + aba boleto + e-mail; testes.
5. **US4 — cartão**: FakeCardGateway + checkout card + webhook card + aba
   cartão (tokenização local) + anti-PAN; testes.
6. **US5 — tesouraria**: ReconcilePayments (comando+endpoint) + receivables +
   pay-manual (403 do próprio) + tela; testes.
7. **Polish**: expiração cancela cobrança (hook no orders:expire), quickstart
   completo, ROADMAP/status.

## Complexity Tracking

Sem violações constitucionais a justificar.
