# Data Model — 001-fundacao

Fonte: `base/data-model.md` (referência integral). Este documento fixa o que a spec
001 **cria**, com regras de validação e transições. Migrations em
`database/migrations`, models em `app/Domain/Events/Models`.

**Convenções transversais** (aplicam a toda tabela de negócio, salvo lookups e pivots):
`created_by`/`updated_by` (FK users, nullable), `deleted_at` (soft delete),
`created_at`/`updated_at`. Dinheiro DECIMAL(10,2). Datetimes em UTC.
Identificador público = `code` único não sequencial (nunca id em URL/QR).

---

## Grupo 1 — Auth e papéis

### `users`
| Campo | Tipo | Regras |
|---|---|---|
| name | string | required |
| email | string unique | required, e-mail válido |
| email_verified_at | datetime? | |
| password | string? | nullable (conta só-Google) |
| document | string? | CPF/passaporte |
| phone | string? | |
| google_id | string? unique | Socialite (spec 002) |
| avatar_url | string? | |
| remember_token, timestamps | | padrão Laravel |

### `roles` (lookup seeded)
`slug` unique (`admin`|`treasury`|`gate`|`attendee`), `name`, `is_active`.

### `role_user` (pivot puro)
`user_id` + `role_id` (unique composto). Usuário acumula papéis; `attendee` é o
default de cadastro pelo site (spec 002).

---

## Grupo 2 — Lookups de status (seeded, `BaseLookupModel`)

Todas: `slug` unique, `name`, `sort`, `is_active`.

| Tabela | Valores seeded |
|---|---|
| event_statuses | draft, published, cancelled, finished |
| order_statuses | pending, paid, partially_paid, cancelled, expired, refunded |
| ticket_statuses | reserved, awaiting_payment, paid, confirmed, courtesy, cancelled, refunded, transferred, used |
| payment_statuses | pending, paid, failed, expired, refunded, chargeback |

### `event_types` (CRUD admin na spec 003; seeded aqui)
`name`, `is_active`, audit, soft. Seeds: seminário, congresso, palestra, workshop,
social, outro.

**Status terminais** (rejeitam transição → 409): ticket: cancelled, refunded,
transferred, used, (expired implícito via order); order: cancelled, expired, refunded;
payment: refunded, chargeback. Tickets "vivos" (contam vaga): reserved,
awaiting_payment, paid, confirmed, courtesy.

---

## Grupo 3 — Evento e configuração

### `events`
| Campo | Tipo | Regras |
|---|---|---|
| name | string | required |
| slug | string unique | URL pública |
| description | text? | rich |
| event_type_id | FK | |
| starts_at / ends_at? | datetime | ends_at ≥ starts_at |
| location, location_map_url, banner_path | string? | |
| total_capacity | int? | null = ilimitado |
| sales_start_at / sales_end_at | datetime? | janela de vendas |
| reservation_ttl_minutes | int | default 30 |
| participation_rules, internal_notes | text? | |
| pricing_mode | enum | paid\|free\|mixed |
| allow_card, allow_boleto, allow_pix, allow_shirt_choice, requires_shirt, allow_kit, allow_transfer, allow_user_cancel, allow_refund_request, allow_courtesy | bool | defaults sensatos |
| courtesy_paid_threshold | int? | regra X→Y |
| courtesy_grant_per_threshold | int | default 1 |
| courtesy_limit_per_account | int? | |
| cancelled_at, cancelled_by, cancel_reason | ?, FK?, string? | |
| status_id | FK event_statuses | |
| audit + soft | | |

Índices: slug (unique), status_id, event_type_id, starts_at.

### `landing_blocks`
`event_id`, `type` enum (hero|text|schedule|speakers|faq|location|cta), `sort`,
`is_active`, `payload` JSON, audit, soft. Índice (event_id, sort).

### `ticket_types`
`event_id`, `name`, `price` DECIMAL(10,2) ≥ 0, `capacity?`, `seats_per_ticket` int
default 1, `is_couple` bool (força 2 assentos), `includes_shirt`, `includes_kit`,
`is_courtesy`, `audience` enum (any|adult|child|guest), `is_active`, `sort`, audit,
soft. Índice event_id.

### `ticket_lots`
`event_id`, `ticket_type_id?` (null = evento todo), `name`, `price_override?`
DECIMAL(10,2), `starts_at?`, `ends_at?`, `quantity?`, `sold_count` int default 0
(cache recalculável), `sort`, `is_active`, audit, soft. Índice (event_id,
ticket_type_id).

**Regra**: lote vigente = `is_active` ∧ agora ∈ [starts_at, ends_at] (bordas nulas =
abertas) ∧ (`quantity` null ∨ `sold_count < quantity`); desempate determinístico por
`sort` ASC, depois id ASC. Preço efetivo = `price_override ?? ticket_type.price`.

### `event_shirt_models`
`event_id`, `label`, `sort`, `is_active`, audit, soft. Índice event_id.

### `event_shirt_sizes`
`event_id`, `shirt_model_id` FK, `label`, `stock_quantity?` (null = ilimitado),
`sold_count` int default 0, `sort`, `is_active`, audit, soft. Índice (event_id,
shirt_model_id). **Esgotado** = `stock_quantity` não nulo ∧ `sold_count ≥
stock_quantity`.

---

## Grupo 4 — Pedidos e ingressos

### `orders`
`code` unique público (`ORD-…`), `event_id`, `buyer_user_id` FK users,
`buyer_name`/`buyer_email`/`buyer_document?` (**snapshot**), `total_amount`
DECIMAL(10,2), `status_id` FK order_statuses, `reserved_until?` datetime, `notes?`,
audit, soft. Índices: code (unique), event_id, buyer_user_id, status_id.

### `tickets`
`order_id` FK (cascade), `event_id`, `ticket_type_id`, `ticket_lot_id?`,
`participant_name`, `participant_email?`, `participant_document?`,
`participant_user_id?` FK users, `is_guest` bool, `companion_name?`,
`companion_shirt_model_id?`, `companion_shirt_size_id?`, `shirt_model_id?`,
`shirt_size_id?`, `unit_price` DECIMAL(10,2) (**snapshot**), `is_courtesy` bool,
`status_id` FK ticket_statuses, `code` unique (`TCK-…`, base do QR), `used_at?`,
`validated_by?`, `cancel_requested_by?`, `cancelled_at?`, `cancelled_by?`,
`cancel_reason?`, `refunded_at?`, `refund_amount?` DECIMAL(10,2),
`transferred_from_ticket_id?`, `transferred_to_ticket_id?`, `notes?`, audit, soft.
Índices: order_id, event_id, ticket_type_id, status_id, participant_user_id,
code (unique).

---

## Grupo 5 — Pagamento (estrutura; fluxo na spec 005)

### `payments`
`order_id` FK, `amount` DECIMAL(10,2), `method` enum (pix|boleto|card|manual),
`provider` enum (sicoob|card_gateway|manual), `provider_charge_id?`, `status_id` FK
payment_statuses, `pix_qrcode?`, `pix_qrcode_image?`, `boleto_line?`,
`boleto_pdf_url?`, `boleto_barcode?`, `card_brand?`, `card_last4?`, `installments?`,
`due_date?`, `paid_at?`, `registered_by?` FK users, `raw_response?` JSON, `note?`,
audit (sem soft — registro financeiro imutável, correções via novo registro).
Índices: order_id, status_id. **Unique composto `(provider, provider_charge_id)`**
(idempotência — princípio III).

### `webhook_events`
`provider` enum (sicoob|card_gateway), `external_id?`, `event_type?`, `payload` JSON,
`signature?`, `received_at`, `processed_at?`, `result?` enum (ok|ignored|error).
Unique (provider, external_id) quando external_id presente — dedupe.

---

## Grupo 6 — Cortesia, patrocínio, suporte

### `courtesy_vouchers`
`event_id`, `code` unique (`CTY-…`), `ticket_type_id?`, `status` enum
(available|distributed|redeemed), `distributed_at?`, `distributed_by?`,
`redeemed_at?`, `redeemed_ticket_id?`, `note?`, audit, soft. Índices: event_id, code.
Transições válidas: available → distributed → redeemed (só avança).

### `sponsorships`
`event_id`, `company_name`, `contact?`, `total_amount` DECIMAL(10,2),
`payment_method?`, `installments_count` int default 1, `status` enum
(pending|partial|paid|cancelled), `notes?`, audit, soft. Índice event_id.

### `sponsorship_installments`
`sponsorship_id` FK (cascade), `number` int, `amount` DECIMAL(10,2), `due_date?`,
`status` enum (pending|paid), `paid_at?`, `paid_amount?` DECIMAL(10,2), `method?`,
`registered_by?`, `note?`, audit. Índice sponsorship_id. Unique (sponsorship_id,
number).

### `support_cases`
`event_id`, `order_id?`, `ticket_id?`, `user_id` FK (o inscrito), `type` enum
(refund|question|shirt_change|other), `status` enum (open|finished|reopened),
`subject?`, `refund_amount?` DECIMAL(10,2), audit, soft. Índice (event_id, user_id).

### `support_case_notes`
`support_case_id` FK (cascade), `author_user_id` FK, `body` text,
`visible_to_attendee` bool, `from_attendee` bool, audit. Índice support_case_id.

---

## Models e derivações (nunca colunas)

| Model | Derivações / responsabilidades |
|---|---|
| Event | `salesOpen`, `currentLot(?TicketType)`, `ticketsSold`, `available`, `soldOut`; hasMany landingBlocks/ticketTypes/ticketLots/shirtModels/shirtSizes/orders/courtesyVouchers/sponsorships/supportCases |
| TicketLot | `isCurrent`, `soldOut`, `effectivePrice`; recount de `sold_count` |
| EventShirtSize | `soldOut`; recount de `sold_count` |
| TicketType | `available` (capacity − tickets vivos do tipo) |
| Order | `amountPaid`, `isExpired`; `transitionTo()` com guarda terminal |
| Ticket | `isActive`; `transitionTo()` com guarda terminal; hasOne/belongsTo transferências |
| User | `hasRole()`, `hasAnyRole()`; belongsToMany roles |

**Definições** (contrato completo em `contracts/domain-derivations.md`):
- Disponível por tipo = `capacity − COUNT(tickets vivos do tipo)`; do evento =
  `total_capacity − COUNT(tickets vivos)` (null = ilimitado).
- Inscrições abertas = status published ∧ agora ∈ janela de vendas ∧ existe lote
  vigente ∧ disponível > 0.
- Previsto × confirmado = SUM(unit_price) por situação do ticket (consumo na spec 008).

## Diagrama de relacionamentos

```
users ──< role_user >── roles
users ──< orders (buyer_user_id)
events ──< landing_blocks
events ──< ticket_types ──< ticket_lots (ticket_type_id?)
events ──< event_shirt_models ──< event_shirt_sizes
events ──< orders ──< tickets
orders ──< payments
tickets >── ticket_types / ticket_lots / shirt_models / shirt_sizes
tickets ──0..1 transferred_from/to tickets
events ──< courtesy_vouchers ──0..1 tickets (redeemed_ticket_id)
events ──< sponsorships ──< sponsorship_installments
events ──< support_cases ──< support_case_notes
webhook_events (independente — auditoria)
```
