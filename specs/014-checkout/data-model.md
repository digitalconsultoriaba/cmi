# Data Model — 014 Checkout do Seminário

Convenções: colunas `snake_case` (inglês); tabelas de negócio herdam soft delete + `created_by`/`updated_by` + `timestamps`. Dinheiro DECIMAL(10,2); datas UTC. Reutiliza `orders`, `tickets`, `payments`, `courtesy_vouchers`, `ticket_types`, `ticket_lots`, `users` (não recria).

## Tabelas novas (config — standalone, rótulos maçônicos como dado)

### `participant_categories` (por evento)

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `event_id` | FK → events | |
| `key` | string(40) | identificador estável (ex.: `glmees`, `outra_potencia`) |
| `label` | string(120) | rótulo pt-BR exibido (ex.: "Irmão da GLMEES") |
| `sort` | int | ordem no seletor |
| `is_active` | boolean | |
| auditoria | | soft delete + audit |

**Unique**: `(event_id, key)`.

### `participant_fields` (campos de uma categoria)

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `participant_category_id` | FK → participant_categories | |
| `key` | string(40) | ex.: `loja`, `potencia`, `pais`, `cidade`, `cargo` |
| `label` | string(120) | rótulo pt-BR |
| `type` | string(20) | `text` \| `affiliation` \| `country` \| `city` \| `conditional` |
| `required` | boolean | obrigatório |
| `sort` | int | ordem no formulário |
| `config` | json nullable | ex.: condicional `{ question, reveals: 'cargo' }` |
| auditoria | | soft delete + audit |

**Unique**: `(participant_category_id, key)`.

### `affiliations` (lista gerenciável — "lojas")

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `event_id` | FK → events | |
| `name` | string(160) | item da lista (ex.: nome da loja) |
| `sort` | int | |
| `is_active` | boolean | |
| auditoria | | soft delete + audit |

**Índice**: `(event_id, name)`.

## Extensões aditivas em `tickets`

| Coluna nova | Tipo | Notas |
|---|---|---|
| `participant_category_key` | string(40) nullable | categoria escolhida (snapshot) |
| `participant_fields` | json nullable | valores dos campos da categoria (snapshot imutável) |

Campos já existentes reutilizados: `participant_name`, `participant_email`, `participant_document`, `participant_user_id`, `ticket_type_id`, `ticket_lot_id`, `unit_price`, `is_courtesy`, `is_guest`, `status_id`, `code`.

## Entidades reutilizadas (sem recriar)

- **Order**: `code`, `event_id`, `buyer_user_id`, `buyer_name`, `buyer_email`, `total_amount`, `status_id`, `reserved_until`. Passa a ser criado por **guest** (buyer resolvido/criado do e-mail) e a suportar **pedido misto** (tickets pagos + cortesia no mesmo pedido). Status consolidado **derivado** (ver máquina de estados).
- **Ticket**: uma inscrição; snapshot de valor/tipo/categoria/campos; `is_courtesy`, status próprio; `code`/QR público; `participant_user_id` = conta do participante.
- **CourtesyVoucher**: `code`, `event_id`, `ticket_type_id?`, `status` (`available`→`distributed`→`redeemed`), `redeemed_ticket_id`, validade (se houver). Elegibilidade de resgate ampliada para `available|distributed`.
- **Payment**: cobrança do total > 0 via gateway; baixa idempotente (`provider+provider_charge_id`).
- **User**: comprador e participantes; conta sem senha (magic link); e-mail normalizado; papel `attendee`.

## Máquina de estados

### Ticket (por inscrição)

- `RESERVED` → (pagamento aprovado) → `PAID`
- item com voucher válido → criado direto como `COURTESY` (gratuito por voucher)
- gratuidade automática (allow_courtesy) → `CONFIRMED`/`COURTESY` (fluxo existente)
- `RESERVED`/`AWAITING_PAYMENT` → (cancelamento/expiração) → `CANCELLED`
- terminais (`USED`, `CANCELLED`, `REFUNDED`, `TRANSFERRED`) rejeitam transição (409)

### Order (consolidado — derivado dos tickets)

- **aguardando pagamento** (`PENDING`): há saldo a pagar; `reserved_until` futuro
- **pago parcialmente por voucher**: parte cortesia, parte paga — ao quitar o saldo → **pago integralmente**
- **pago integralmente** (`PAID`): sem saldo pendente e ≥1 ticket pago
- **gratuito**: total = 0 (todas as inscrições cortesia/gratuidade)
- **cancelado** (`CANCELLED`)
- expiração do `reserved_until` sem pagamento → pré-inscrição expira (fluxo `ReconcilePayments`)

## Regras de negócio (derivadas dos FRs)

- **Total** = soma de `unit_price` dos tickets **não** isentos (voucher zera o item). Recalculado no cliente (tempo real) e **conferido/recalculado no servidor** na finalização (transação com recontagem).
- **Voucher por item** (FR-008/009/010): validado (existe, `available|distributed`, do evento, não `redeemed`, validade, elegível ao tipo/categoria); resgatado dentro do pedido, `redeemed_ticket_id` aponta o ticket; uso único salvo múltiplos usos. Remover o participante/voucher **libera** o voucher (não fica `redeemed` antes de finalizar — validação é no submit).
- **Finalização** (FR-012/013): total > 0 → cria pedido `PENDING` + segue ao pagamento existente; total = 0 → finaliza gratuito sem pagamento.
- **Guest → conta** (FR-020/021): `GuestBuyerService` cria/vincula `User` do comprador e de cada participante com e-mail; magic link (URL assinada) por e-mail; comprador vê todos, participante vê o seu.
- **Snapshot** (FR-024): valores de categoria/campos gravados no ticket na criação; imutáveis a mudanças de config/lista.
- **Histórico** (FR-027): soft delete; nada físico; ações logadas; abandono vira pré-inscrição pendente/expirada.

## Validações-chave (FormRequests)

- `GuestOrderRequest`: `event_slug` (exists); `buyer.name`/`buyer.email` (required, email); `items` (array, `max:max_tickets_per_order`); por item: `ticket_type_id` (required, do evento), `participant_name` (required), `participant_email` (required se >1 participante), `participant_category_key` (existe e ativa no evento), `fields` (array; obrigatórios da categoria presentes; `affiliation` ∈ lista), `voucher_code` (nullable).
- `ParticipantCategoryRequest`/`ParticipantFieldRequest`: `key`/`label`/`type` (enum)/`required`/`sort`/`config`.
- `AffiliationRequest`: `name` (required), `sort`, `is_active`.
- Magic link: rota `signed` (integridade + expiração); sem corpo.
