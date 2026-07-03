# Implementation Plan: Catálogo Público e Compra

**Branch**: `004-catalogo-compra` | **Date**: 2026-07-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/004-catalogo-compra/spec.md`

## Summary

A experiência pública: `GET /public/events/{slug}` renderiza landing (blocos da
003) + catálogo com derivações da 001; `TicketPurchaseService` cria pedidos em
transação única com `lockForUpdate` no evento e recontagem total (capacidade,
lote, estoque de camisa, casal ×2) — zero overselling; `CourtesyResolver` gera
cortesias automáticas (regra X→Y, limite por conta) e o resgate de voucher vira
**pedido próprio de total zero** (nasce pago — expiração nunca desfaz voucher);
comando `orders:expire` agendado libera vagas de reservas vencidas; área do
inscrito (pedidos/ingressos com claim por e-mail) e comprovante PDF (dompdf) com
QR SVG do código público. **Nenhuma tabela nova.**

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12); JavaScript ES2022 (React 18)

**Primary Dependencies**: novos — `barryvdh/laravel-dompdf`,
`simplesoftwareio/simple-qrcode` (constituição); demais já presentes

**Storage**: MySQL 8 — tabelas da fundação; nenhuma migration nova; config nova
`config/events.php` (max_tickets_per_order = 20)

**Testing**: PHPUnit Feature em `app_test`; `Carbon::setTestNow` para TTL;
contenção determinística + smoke manual de concorrência (research, Decisão 1)

**Target Platform**: idem specs anteriores (Docker dev)

**Project Type**: web application — API + SPA existentes

**Performance Goals**: página pública < 1s; compra (transação) < 2s; suíte da
spec < 2 min

**Constraints**: transação única com lock por evento + recontagem (princípio II);
snapshot de preço no ticket; códigos públicos em URL/QR; 409 na shape padrão com
`type` específico (`sales_closed`/`sold_out`/`invalid_voucher`); pt-BR

**Scale/Scope**: 6 endpoints novos + 1 comando agendado, 4 páginas/áreas de
front, 0 migrations, 5 user stories

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação |
|---|---|
| I. Standalone, RBAC | ✅ Público sem auth; compra/área do inscrito sob sessão + policies de dono (attendee); nada de conceito externo. |
| II. Snapshot + derivado + recontagem transacional | ✅ Núcleo da spec: lock no evento + recontagem de tudo dentro da transação; `unit_price` congelado; catálogo público só derivações; `sold_count` recontado na mesma transação. |
| III. Ponto único de baixa | ✅ Nenhuma baixa aqui — pedido para em `pending` (005). Exceção deliberada: total 0 nasce `paid` (não há pagamento a registrar; documentado em research Decisão 2). |
| IV. Segurança | ✅ URLs/QR só com `code` público; policies de dono (403); sem credenciais novas; PDF só para o dono. |
| V. Histórico | ✅ Expiração preserva tudo (order expired + tickets cancelled com motivo); voucher só avança; terminais rejeitam transição. |
| VI. Specs por área | ✅ Consome 001–003 sem redefinir; pagamento/e-mails explicitamente delegados à 005; QR lido na 007. |
| Stack e convenções | ✅ dompdf + simple-qrcode são exatamente os fixados pela constituição. |

**Resultado**: PASS (pré-Phase 0 e pós-Phase 1). Sem entradas em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/004-catalogo-compra/
├── plan.md              # Este arquivo
├── research.md          # 9 decisões
├── data-model.md        # 0 tabelas — fluxo da compra, transições, invariantes
├── quickstart.md        # validação por user story + smoke de concorrência
├── contracts/public-api.md
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks (próximo passo)
```

### Source Code (repository root)

```text
app/
├── Domain/Events/Services/
│   ├── TicketPurchaseService.php   # purchase() — lock + recontagem + snapshot
│   ├── CourtesyResolver.php        # regra X→Y + limite/conta + voucher
│   └── TicketReceiptPdf.php        # dompdf + QR SVG
├── Console/Commands/ExpireReservations.php  # orders:expire (agendado 5min)
├── Http/
│   ├── Controllers/Api/
│   │   ├── PublicEventController.php       # GET /public/events/{slug}
│   │   ├── OrderController.php             # store/index/show (por code)
│   │   └── TicketController.php            # index/show/receipt (por code)
│   ├── Requests/StoreOrderRequest.php      # itens 1..20, casal, camisa, voucher
│   └── Resources/{OrderResource,TicketResource,PublicEventResource}.php
├── Policies/{OrderPolicy,TicketPolicy}.php # dono (participante/comprador)
config/events.php                           # max_tickets_per_order
resources/views/pdf/ticket-receipt.blade.php
routes/api.php                              # /public + /orders + /tickets
routes/console.php                          # schedule orders:expire
tests/Feature/{Public,Purchase}/            # PublicEvent, Purchase, Courtesy,
                                            # MyArea, Receipt, Expire
frontend/src/
├── cart/CartProvider.jsx                   # localStorage por evento
├── pages/
│   ├── EventoPublico.jsx                   # LandingRenderer + TicketPicker
│   ├── Checkout.jsx                        # participantes → revisão → confirmar
│   ├── MeusPedidos.jsx / MeusIngressos.jsx # área do inscrito
└── App.jsx                                 # rotas /evento/:slug, /checkout, /minha-conta/*
```

**Structure Decision**: services de compra no domínio (o coração); controllers
públicos fora de Admin/Auth; testes divididos em `Public/` (leitura) e
`Purchase/` (escrita).

## Fases de implementação (visão para /speckit-tasks)

1. **Setup**: composer dompdf + simple-qrcode; `config/events.php`; policies
   registradas; rotas.
2. **US1 — catálogo público**: PublicEventResource/Controller (404/cancelado/
   salesState); LandingRenderer + TicketPicker; testes.
3. **US2 — compra**: TicketPurchaseService (lock/recontagem/snapshot/TTL/casal),
   StoreOrderRequest, OrderController@store; CartProvider + Checkout; testes de
   contenção determinística.
4. **US3 — cortesias**: CourtesyResolver (automática + limite) + voucher como
   pedido próprio; campo no checkout; testes.
5. **US4 — minha área**: OrderController index/show, TicketController (claim por
   e-mail) + TicketReceiptPdf + policies; páginas; testes (403/PDF).
6. **US5 — expiração**: ExpireReservations + schedule; testes com relógio.
7. **Polish**: quickstart completo (incl. smoke de concorrência), ROADMAP/status.

## Complexity Tracking

Sem violações constitucionais a justificar.
