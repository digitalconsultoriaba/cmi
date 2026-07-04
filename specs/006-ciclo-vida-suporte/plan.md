# Implementation Plan: Ciclo de Vida e Suporte

**Branch**: `006-ciclo-vida-suporte` | **Date**: 2026-07-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/006-ciclo-vida-suporte/spec.md`

## Summary

O pós-venda completo: `TicketLifecycleService` (cancelar/transferir sob o mesmo
lock da compra, com recount imediato); `RefundPolicy` pura com a política
definida pelo organizador (100% até 7 dias antes; pisos CDC e evento
cancelado); caso de reembolso = `support_case type refund` (tabela da 001);
`RefundPayment` da tesouraria (cartão via emenda `refundCharge` no contrato da
005; Pix/boleto operacional; auto-estorno 403; parcial mantém payment paid com
registro no ticket); `CancelEventCascade` resiliente (fila de devoluções 100%);
suporte com notas públicas/internas; 4 e-mails do ciclo; ações no front do
inscrito + filas no painel. **Nenhuma tabela nova.**

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12); JavaScript ES2022 (React 18)

**Primary Dependencies**: nenhuma nova

**Storage**: MySQL 8 — campos/tabelas da 001; configs novas em
`config/events.php` (refund_full_until_days, refund_purchase_grace_days)

**Testing**: PHPUnit Feature em `app_test`; relógio com `Carbon::setTestNow`
para as janelas da política; drivers fake da 005

**Target Platform**: idem specs anteriores

**Project Type**: web application — API + SPA existentes

**Performance Goals**: cascata de evento com centenas de pedidos < 30s
(síncrona, volume MVP); demais operações < 2s

**Constraints**: guardas terminais/escopo/papéis existentes; recount
transacional; trilha completa; pt-BR; elegibilidade calculada só no servidor

**Scale/Scope**: ~12 endpoints novos, 1 emenda de contrato (gateway), 4
notificações, 3 áreas de front, 0 migrations, 5 user stories

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação |
|---|---|
| I. Standalone, RBAC | ✅ Inscrito nas próprias coisas (policies); filas sob `require.role`; suporte com escopo de dono. |
| II. Estado derivado + recontagem | ✅ Cancelamento/transferência recontam sob o lock do evento; elegibilidade (`cancellable`/`transferable`/`refundPreview`) derivada no servidor. |
| III. Ponto único de baixa | ✅ Estorno segue o espelho da regra: fluxo único da tesouraria, **operador nunca no próprio pedido (403)**, evidência bruta; nada baixa/estorna por fora. |
| IV. Segurança | ✅ Estorno de cartão via conector (emenda ao contrato — sem tocar PAN); operacional exige justificativa; sem credencial nova. |
| V. Histórico — nada some | ✅ Núcleo da spec: cancelado/transferido/estornado preservam tudo (quem/quando/motivo/vínculos from-to); terminais recusam transição; cascata não apaga nada. |
| VI. Specs por área | ✅ Consome 003 (cancel do evento), 004 (compra/claim), 005 (payments/conector — emenda registrada); QR recusado na portaria é guarda que a 007 consumirá. |
| Stack e convenções | ✅ Sem dependência nova. |

**Resultado**: PASS (pré-Phase 0 e pós-Phase 1). Sem entradas em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/006-ciclo-vida-suporte/
├── plan.md              # Este arquivo
├── research.md          # 8 decisões
├── data-model.md        # 0 tabelas — transições, política, efeitos, invariantes
├── quickstart.md        # validação por user story
├── contracts/lifecycle-api.md
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks (próximo passo)
```

### Source Code (repository root)

```text
app/
├── Domain/Events/
│   ├── Services/
│   │   ├── RefundPolicy.php            # política pura (100% até D-7; pisos)
│   │   ├── TicketLifecycleService.php  # cancelTicket/cancelOrder/transferTicket
│   │   ├── RefundPayment.php           # estorno (conector ou operacional)
│   │   └── CancelEventCascade.php      # cascata resiliente do evento
│   └── Payments/                       # + refundCharge no contrato/drivers
│       └── RefundResult.php / RefundNotSupported.php
├── Http/
│   ├── Controllers/Api/
│   │   ├── TicketLifecycleController.php   # cancel/transfer (inscrito)
│   │   ├── SupportCaseController.php       # casos do inscrito
│   │   ├── Admin/SupportQueueController.php # fila admin+treasury
│   │   └── Treasury/RefundController.php   # fila + execução de estornos
│   ├── Requests/{TransferTicketRequest,SupportCaseRequest,ExecuteRefundRequest}.php
│   └── Resources/SupportCaseResource.php   # + emenda no TicketResource (elegibilidade)
├── Notifications/{TicketCancelledPtBr,TicketTransferredPtBr,
│                  RefundCompletedPtBr,EventCancelledPtBr}.php
├── Policies/SupportCasePolicy.php
config/events.php                           # + janelas da política de reembolso
routes/api.php                              # lifecycle + support + treasury/refunds
tests/Feature/Lifecycle/                    # Cancel, Transfer, Refund, Support,
                                            # EventCascade
frontend/src/
├── pages/MeusIngressos.jsx                 # + modais cancelar/transferir
├── pages/MeusPedidos.jsx                   # + cancelar pedido
├── pages/Suporte.jsx                       # /minha-conta/suporte (lista+conversa)
└── admin/pages/{Suporte.jsx, Tesouraria.jsx} # fila de suporte + seção Estornos
```

**Structure Decision**: services do ciclo no domínio (mesma casa do purchase);
suporte separado em controllers por audiência (inscrito × organização).

## Fases de implementação (visão para /speckit-tasks)

1. **Setup**: configs da política; emenda do contrato
   (`refundCharge`/`RefundResult`/`RefundNotSupported` + fakes); rotas.
2. **US1 — cancelamento**: RefundPolicy + cancelTicket/cancelOrder + casos
   automáticos + TicketResource com elegibilidade + modais no front; testes
   (janelas da política com relógio).
3. **US2 — transferência**: transferTicket + e-mail + modal; testes (vínculos,
   guardas, claim do destinatário).
4. **US3 — estorno**: RefundPayment + fila/execução na tesouraria + e-mail;
   testes (conector, operacional, parcial, auto-estorno 403).
5. **US4 — suporte**: controllers/policy/resource + telas (inscrito e fila);
   testes (escopo, visibilidade, transições).
6. **US5 — cascata**: CancelEventCascade + gancho no EventConfigService +
   e-mail; testes (misto, resiliência).
7. **Polish**: quickstart manual, suítes anteriores, ROADMAP/status.

## Complexity Tracking

Sem violações constitucionais a justificar.
