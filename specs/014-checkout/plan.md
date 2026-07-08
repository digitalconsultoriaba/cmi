# Implementation Plan: Checkout do Seminário Internacional (multi-inscrição, guest, voucher por participante)

**Branch**: `014-checkout` | **Date**: 2026-07-07 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/014-checkout/spec.md`

## Summary

Checkout guest (sem login) que inscreve N participantes num carrinho, cada um com **categoria** e **campos configuráveis** (rótulos maçônicos como dado, código genérico), **tipo de ingresso** escolhido (preço do tipo/lote), **voucher de gratuidade por inscrição** dentro de um **pedido misto** (pagas + isentas), **tela de revisão**, e finalização com **pagamento** (total > 0) ou **confirmação gratuita** (total = 0). Após finalizar, o comprador e cada participante recebem **acesso passwordless (magic link)** ao back-office e o ingresso por e-mail. A feature **estende** o fluxo existente (`TicketPurchaseService`, `CourtesyResolver`, `CheckoutController`+gateways, `Order`/`Ticket`, área do inscrito) sem redefini-lo: as três mudanças de peso são (1) **pedido misto com voucher por item**, (2) **criação de pedido por guest**, (3) **login passwordless por magic link** para comprador e participantes. Configuração de categorias/campos/afiliações entra como tabelas novas + snapshot por inscrição.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12) no backend; JavaScript/React 18 (Vite) no frontend.

**Primary Dependencies**: Laravel 12, Sanctum SPA (cookie), Eloquent, gateways de pagamento existentes (Sicoob PIX/boleto, gateway de cartão) via `CheckoutController`/`CreateCharge`, `RegisterPayment` (baixa idempotente), `barryvdh/laravel-dompdf` + `simple-qrcode` (ingresso PDF/QR já existentes), Notifications pt-BR. Magic link via **URLs assinadas** do Laravel (mesmo padrão do `verify-email` — sem lib nova). React Query no front.

**Storage**: MySQL 8. Novas tabelas: `participant_categories`, `participant_fields`, `affiliations` (config, soft delete + audit). Colunas novas (aditivas) em `tickets`: `participant_category_key`, `participant_fields` (JSON snapshot). Reutiliza `orders`/`tickets`/`payments`/`courtesy_vouchers`.

**Testing**: PHPUnit Feature em MySQL `app_test` (nunca SQLite), via `make test`. Cobrir: guest order, pedido misto com voucher por item, validação de voucher (available/distributed), total-zero gratuito, seleção de tipo, magic link + escopo de acesso (comprador vê todos / participante só o seu), snapshot de campos por categoria, envio de e-mail (Notification::fake), abandono/TTL, concorrência (recontagem transacional).

**Target Platform**: SPA React (dev :5173) + API Laravel (dev :8000). Checkout público (sem auth) chamando endpoints guest; back-office pós-login (Sanctum cookie) via magic link.

**Performance Goals**: checkout interativo; criação do pedido e cálculo do total num POST único ao finalizar. Sem metas especiais de throughput (volume de seminário).

**Constraints**: dinheiro DECIMAL(10,2); datas UTC; código/identificadores em inglês; UI/mensagens pt-BR; PAN/CVV nunca no backend (tokenização no gateway); códigos públicos (`code`/uuid) em URLs/QR; magic link por URL assinada com expiração; LGPD (coletar mínimo; e-mail para entrega; documento opcional; não logar sensível).

**Project Type**: Web application (backend Laravel + frontend React), monorepo existente.

**Scale/Scope**: 2 categorias padrão (config), N campos por categoria, N participantes por pedido (limite `max_tickets_per_order`), 3 meios de pagamento reutilizados.

## Constitution Check

*GATE: revisado após o design (Phase 1). Sem violações.*

- **I. Standalone — zero acoplamento**: categorias de participante, campos e a lista de **afiliações** ("lojas") são **configuração/dados genéricos**; o código usa "categorias de participante"/"campos de inscrição"/"afiliações", e os rótulos maçônicos (GLMEES/Loja/Potência/Cargo) vivem só como config/rótulo. Nenhuma entidade de Grande Loja/loja/irmão no código. Papéis RBAC inalterados (4). ✓
- **II. order→tickets com snapshot; estado derivado**: cada inscrição é um `Ticket` com snapshot de `unit_price`, tipo, categoria e campos; **total é derivado** (soma das inscrições não isentas); voucher zera o valor **da inscrição**; contagem de vaga/lote/voucher em `DB::transaction` com recontagem. Nenhum estado derivado vira coluna editável. ✓
- **III. Ponto único de baixa, idempotente (NÃO NEGOCIÁVEL)**: pagamento reutiliza `CheckoutController`→`RegisterPayment` (idempotente, `provider+provider_charge_id`); **guest paga pelo gateway**, não dá baixa manual; comprador nunca baixa o próprio pedido. Total-zero gratuito confirma sem passar por baixa financeira. ✓
- **IV. Segurança de pagamento (NÃO NEGOCIÁVEL)**: PAN/CVV nunca no backend (tokenização client-side já existente); URLs/QR usam `code`/uuid; **magic link = URL assinada com expiração** (padrão `signed` já usado no verify-email), sem senha trafegando. Gateways atrás dos contratos existentes. ✓
- **V. Histórico — nada some**: `Order`/`Ticket` com soft delete + status + audit; novas tabelas idem; abandono → pedido **PENDING** com `reserved_until` (expira, não some); voucher resgatado registra `redeemed_ticket_id`; toda ação logada. ✓
- **VI. Specs por área**: spec própria `014-checkout` que **estende** 002/004/006/011 sem redefinir. Migrations **aditivas** (colunas novas em `tickets`, tabelas novas); serviços existentes ganham parâmetros novos, sem quebrar chamadas atuais. ✓

**Convenções**: API `{ data }` camelCase; erros `{ message, type, status, errors }` (422 validação, 409 `DomainRuleViolation`, 403 papel/escopo); DECIMAL/UTC; pt-BR. ✓ **Sem entradas em Complexity Tracking.**

## Project Structure

### Documentation (this feature)

```text
specs/014-checkout/
├── plan.md              # Este arquivo
├── research.md          # Phase 0 — decisões (pedido misto, guest, magic link, campos por participante, timing do pedido)
├── data-model.md        # Phase 1 — entidades novas + extensões de Order/Ticket + máquina de estados
├── quickstart.md        # Phase 1 — roteiro de validação ponta a ponta
├── contracts/           # Phase 1 — endpoints guest/checkout, config admin, magic link
│   ├── public-checkout.md
│   ├── admin-participant-config.md
│   └── auth-magic-link.md
└── checklists/
    └── requirements.md  # criado no /speckit-specify
```

### Source Code (repository root)

```text
app/
├── Domain/Events/
│   ├── Models/
│   │   ├── ParticipantCategory.php     # config por evento (key, label, sort, is_active)
│   │   ├── ParticipantField.php        # campos da categoria (key, label, type, required, config)
│   │   ├── Affiliation.php             # lista gerenciável ("lojas") por evento
│   │   ├── Order.php / Ticket.php      # + snapshot de categoria/campos; helpers de status consolidado
│   │   └── CourtesyVoucher.php         # (sem quebra) elegibilidade available|distributed
│   └── Services/
│       ├── TicketPurchaseService.php   # ESTENDIDO: voucher por item → pedido misto; buyer guest
│       ├── CourtesyResolver.php        # ESTENDIDO: valida available|distributed; resgata em pedido existente
│       ├── GuestBuyerService.php       # cria/vincula conta (comprador + participantes) sem senha
│       └── MagicLinkService.php        # gera URL assinada de login + envia
├── Http/
│   ├── Controllers/Api/
│   │   ├── Public/GuestCheckoutController.php   # POST /public/orders (+ iniciar pagamento) sem auth
│   │   ├── Admin/ParticipantCategoryController.php / AffiliationController.php
│   │   └── Auth/MagicLinkController.php         # consumir URL assinada → sessão Sanctum
│   ├── Requests/
│   │   ├── Public/GuestOrderRequest.php         # buyer + items[](categoria/tipo/campos/voucher/email)
│   │   └── Admin/ParticipantCategoryRequest.php / AffiliationRequest.php
│   └── Resources/                                # OrderResource/TicketResource + categoria/campos
├── Notifications/
│   ├── TicketIssuedPtBr.php            # ingresso por participante + magic link (novo)
│   └── OrderAccessPtBr.php             # magic link do comprador (novo)
config/
└── events.php                          # (reuso: reservation_ttl, max_tickets_per_order)
database/migrations/
├── 2026_07_08_100000_create_participant_categories_table.php
├── 2026_07_08_100010_create_participant_fields_table.php
├── 2026_07_08_100020_create_affiliations_table.php
└── 2026_07_08_100030_add_participant_snapshot_to_tickets.php   # participant_category_key, participant_fields json

frontend/src/
├── pages/
│   ├── CheckoutSeminario.jsx          # fluxo: categoria → tipo → campos → carrinho → revisão → pagar/confirmar
│   └── checkout/                       # CategoriaForm, CampoDinamico, CardParticipante, ResumoCarrinho, Revisao
├── pages/MagicLink.jsx                # landing do link mágico (consome e entra)
├── admin/eventos/abas/                # config de categorias/campos + afiliações (aba do evento)
│   └── inscricoes/ (CategoriasConfig, AfiliacoesConfig)
└── (reuso) MeusPedidos.jsx / MeusIngressos.jsx  # back-office comprador + participante

tests/Feature/Checkout/
├── GuestCheckoutTest.php              # criar pedido guest, tipos, campos snapshot
├── MixedVoucherOrderTest.php         # voucher por item em pedido misto; total; status
├── VoucherValidationTest.php         # available|distributed; inválido/expirado/usado/não elegível
├── FreeCheckoutTest.php              # total 0 → confirma sem pagamento
├── MagicLinkAccessTest.php           # comprador vê todos / participante só o seu; link assinado
├── TicketDeliveryTest.php            # Notification::fake — ingresso por participante + acesso
└── ParticipantConfigTest.php         # categorias/campos/afiliações (admin) + render de campos
```

**Structure Decision**: Web application no monorepo. Backend estende `app/Domain/Events` (serviços/modelos existentes ganham parâmetros; novas tabelas de config) seguindo os padrões de controller/resource/request/rotas já usados. O checkout público é uma página React nova consumindo endpoints **guest** (sem auth); o back-office reutiliza a área do inscrito, acessada por **magic link**. Tudo aditivo às specs 002/004/006/011.

## Complexity Tracking

> Sem violações constitucionais — seção não aplicável.
