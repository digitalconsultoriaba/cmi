# Data Model — Plataforma de Eventos (standalone)

Migrations em `database/migrations`. Domínio em `app/Domain/Events`. Sem morph de
dono (não há loja/GL): o "dono" é o organizador único, implícito. Todas as tabelas de
negócio têm `created_by`/`updated_by` (audit) + `deleted_at` (soft delete), salvo
lookups e tabelas de junção puras.

Convenção: DECIMAL(10,2) para dinheiro; datetimes em UTC; `code`/`uuid` para
identificadores públicos (nunca expor id sequencial em QR/URL pública).

---

## Autenticação e papéis

### `users`
Padrão Laravel + campos do inscrito.
- `name`, `email` (unique), `email_verified_at?`, `password?` (nullable p/ contas só-Google)
- `document?` (CPF/passaporte), `phone?`
- `google_id?` (unique, nullable) — Socialite
- `avatar_url?`
- audit padrão (`remember_token`, timestamps)

### `roles` (lookup seeded)
- `slug` (`admin`|`treasury`|`gate`|`attendee`), `name`, `is_active`

### `role_user` (pivot)
- `user_id`, `role_id` — um usuário pode ter vários papéis. `attendee` é o default de
  quem se cadastra pelo site.

> RBAC simples e próprio (não reusa a matriz de módulos do 047). Middleware
> `require.role:admin|treasury|gate` protege as rotas de gestão.

---

## Lookups (seeded)

### `event_statuses`
`draft` / `published` / `cancelled` / `finished` — `name`, `slug`, `sort`, `is_active`.

### `order_statuses`
`pending` / `paid` / `partially_paid` / `cancelled` / `expired` / `refunded`.

### `ticket_statuses`
`reserved` / `awaiting_payment` / `paid` / `confirmed` / `courtesy` / `cancelled` /
`refunded` / `transferred` / `used`.

### `payment_statuses`
`pending` / `paid` / `failed` / `expired` / `refunded` / `chargeback`.

### `event_types`
CRUD admin (seminário/congresso/palestra/workshop/social/outro) — `name`, `is_active`,
audit, soft.

---

## Evento e configuração

### `events`
Single-event no MVP (uma linha "ativa"), mas modelado como tabela para permitir Fase 2.
- `name`, `slug` (unique, p/ URL pública), `description` (rich), `event_type_id`
- `starts_at`, `ends_at?`, `location?`, `location_map_url?`, `banner_path?`
- `total_capacity?`
- `sales_start_at?`, `sales_end_at?`
- `reservation_ttl_minutes` (default 30) — expiração da reserva no checkout
- `participation_rules?`, `internal_notes?`
- `pricing_mode` (paid|free|mixed)
- **flags**: `allow_card`, `allow_boleto`, `allow_pix`, `allow_shirt_choice`,
  `requires_shirt`, `allow_kit`, `allow_transfer`, `allow_user_cancel`,
  `allow_refund_request`, `allow_courtesy` (bool, defaults)
- **cortesia**: `courtesy_paid_threshold?`, `courtesy_grant_per_threshold?` (default 1),
  `courtesy_limit_per_account?`
- **cancelamento**: `cancelled_at?`, `cancelled_by?`, `cancel_reason?`
- `status_id`, audit, soft
- Índices: `slug` (unique), `status_id`, `event_type_id`, `starts_at`

### `landing_blocks` (novo — editor da landing FR-17)
- `event_id`, `type` (hero|text|schedule|speakers|faq|location|cta), `sort`,
  `is_active`, `payload` (JSON com o conteúdo do bloco), audit, soft
- Índice `event_id, sort`

### `ticket_types`
- `event_id`, `name`, `price` DECIMAL(10,2), `capacity?`, `seats_per_ticket` (default 1),
  `is_couple` (force 2), `includes_shirt`, `includes_kit`, `is_courtesy`,
  `audience` (any|adult|child|guest), `is_active`, `sort`, audit, soft
- Índice `event_id`

### `ticket_lots` (novo — lotes FR-06)
- `event_id`, `ticket_type_id?` (null = aplica ao evento todo), `name` (1º lote…),
  `price_override?` DECIMAL(10,2), `starts_at?`, `ends_at?`, `quantity?` (esgota por qtd),
  `sold_count` (derivado/cacheável), `sort`, `is_active`, audit, soft
- Índice `event_id, ticket_type_id`
- Regra: lote vigente = ativo, dentro da janela e não esgotado. Preço efetivo =
  `price_override ?? ticket_type.price`.

### `event_shirt_models`
- `event_id`, `label`, `sort`, `is_active`. Índice `event_id`

### `event_shirt_sizes`
- `event_id`, `shirt_model_id` (hierárquico: tamanho pertence a um modelo),
  `label`, `stock_quantity?` (null = ilimitado), `sold_count`, `sort`, `is_active`
- Índice `event_id, shirt_model_id`
- "Esgotado" quando `stock_quantity` definido e `sold_count >= stock_quantity`.

---

## Compras e ingressos

### `orders` (era event_orders)
- `code` (unique, público), `event_id`, `buyer_user_id` (FK users — não mais member/lodge)
- `buyer_name`, `buyer_email`, `buyer_document?` (snapshot no momento da compra)
- `total_amount` DECIMAL(10,2), `status_id` (order_statuses)
- `reserved_until?` (TTL da reserva)
- `notes?`, audit, soft
- Índices: `code` (unique), `event_id`, `buyer_user_id`, `status_id`

### `tickets` (era event_tickets)
- `order_id` (cascade), `event_id`, `ticket_type_id`, `ticket_lot_id?`
- `participant_name`, `participant_email?`, `participant_document?`
- `participant_user_id?` (se o participante tem conta; usado por "meus ingressos" e transferência)
- `is_guest` bool
- `companion_name?`, `companion_shirt_size_id?`, `companion_shirt_model_id?` (casal)
- `shirt_size_id?`, `shirt_model_id?`
- `unit_price` DECIMAL(10,2) (**snapshot**), `is_courtesy` bool
- `status_id` (ticket_statuses), `code` (unique — base do QR)
- `used_at?`, `validated_by?`
- `cancel_requested_by?`, `cancelled_at?`, `cancelled_by?`, `cancel_reason?`
- `refunded_at?`, `refund_amount?` DECIMAL(10,2)
- `transferred_from_ticket_id?`, `transferred_to_ticket_id?`
- `notes?`, audit, soft
- Índices: `order_id`, `event_id`, `ticket_type_id`, `status_id`,
  `participant_user_id`, `code` (unique)

---

## Pagamento (agora ligado a Sicoob/gateway)

### `payments` (era event_payments — expandido)
- `order_id`, `amount` DECIMAL(10,2)
- `method` (pix|boleto|card|manual)
- `provider` (sicoob|card_gateway|manual)
- `provider_charge_id?` (txid Pix / nossoNumero boleto / paymentId cartão)
- `status_id` (payment_statuses)
- `pix_qrcode?` (copia-e-cola), `pix_qrcode_image?`, `boleto_line?` (linha digitável),
  `boleto_pdf_url?`, `boleto_barcode?`
- `card_brand?`, `card_last4?`, `installments?` (nunca PAN/CVV)
- `due_date?`, `paid_at?`, `registered_by?` (quem deu baixa manual, se manual)
- `raw_response?` (JSON do provedor, p/ auditoria/conciliação)
- `note?`, audit
- Índices: `order_id`, `provider_charge_id`, `status_id`
- **Idempotência**: `provider` + `provider_charge_id` unique (webhook não baixa 2×).

### `webhook_events` (novo — auditoria/idempotência de webhook)
- `provider` (sicoob|card_gateway), `external_id?`, `event_type?`,
  `payload` (JSON bruto), `signature?`, `processed_at?`, `result?` (ok|ignored|error),
  `received_at`
- Índice `provider, external_id` (unique quando external_id presente) — dedupe.

> Fluxo de baixa: webhook chega → grava `webhook_events` → valida assinatura/consulta
> a cobrança no provedor → `RegisterPayment` (ponto único) marca payment `paid` e
> confirma order/tickets. Job diário `ReconcilePayments` varre `pending` e concilia
> com a API do Sicoob (fallback do webhook).

---

## Cortesia (vouchers)

### `courtesy_vouchers` (era event_courtesy_vouchers)
- `event_id`, `code` (unique), `ticket_type_id?`, `status` (available|distributed|redeemed),
  `distributed_at?`, `distributed_by?`, `redeemed_at?`, `redeemed_ticket_id?`,
  `note?`, audit, soft
- Índice `event_id`, `code` (unique)

---

## Patrocínio (mantido do 061)

### `sponsorships`
- `event_id`, `company_name`, `contact?`, `total_amount` DECIMAL(10,2),
  `payment_method?`, `installments_count` (default 1),
  `status` (pending|partial|paid|cancelled), `notes?`, audit, soft. Índice `event_id`

### `sponsorship_installments`
- `sponsorship_id` (cascade), `number`, `amount` DECIMAL(10,2), `due_date?`,
  `status` (pending|paid), `paid_at?`, `paid_amount?` DECIMAL(10,2), `method?`,
  `registered_by?`, `note?`, audit. Índice `sponsorship_id`

---

## Suporte / atendimento (substitui refund_cases do 061)

### `support_cases` (novo — canal inscrito ↔ admin/tesouraria)
- `event_id`, `order_id?`, `ticket_id?`, `user_id` (o inscrito),
  `type` (refund|question|shirt_change|other), `status` (open|finished|reopened),
  `subject?`, `refund_amount?` DECIMAL(10,2), audit, soft. Índice `event_id, user_id`

### `support_case_notes`
- `support_case_id` (cascade), `author_user_id`, `body`,
  `visible_to_attendee` bool, `from_attendee` bool, audit. Índice `support_case_id`

---

## Relacionamentos (resumo)

```
users ──< role_user >── roles
users ──< orders (buyer_user_id)
events ──< landing_blocks
events ──< ticket_types ──< ticket_lots
events ──< event_shirt_models ──< event_shirt_sizes
events ──< orders ──< tickets
orders ──< payments >── payment_statuses
tickets >── ticket_types / ticket_lots / shirt_sizes / shirt_models / ticket_statuses
tickets ──0..1 transferred_from/to tickets
events ──< courtesy_vouchers ──0..1 tickets (redeemed)
events ──< sponsorships ──< sponsorship_installments
events ──< support_cases ──< support_case_notes
* ──< webhook_events (auditoria)
```

## Derivações (nunca armazenadas)

- **Disponível por tipo** = `capacity − COUNT(tickets vivos do tipo)`.
- **Disponível do evento** = `total_capacity − COUNT(tickets vivos)`.
- **Inscrições abertas** = `now ∈ [sales_start_at, sales_end_at]` E existe lote vigente
  E disponível > 0 E status = published.
- **Lote vigente** = lote ativo, dentro da janela, `sold_count < quantity` (quando há).
- **Previsto × confirmado** = soma de `unit_price` por situação do ticket.
- **Camisa esgotada** = `stock_quantity` definido e `sold_count >= stock_quantity`.
