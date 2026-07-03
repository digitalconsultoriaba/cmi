# Contrato — API de administração do evento (003)

Todas as rotas sob `/api/admin/*`, com `auth:sanctum` + `require.role:admin` +
`EventPolicy` — 401 sem sessão, 403 sem papel (shapes da 001). Envelope `{ data }`
camelCase; 422 validação; 409 regra de negócio.

## Evento

| Método/rota | Regras | Respostas |
|---|---|---|
| `GET /admin/events` | lista (1 item no MVP); inclui status e derivações (salesOpen, available) | 200 |
| `GET /admin/events/{event}` | detalhe completo de configuração | 200 |
| `PUT /admin/events/{event}` | todos os campos de config (FR-002); rejeita se status terminal | 200 · 409 · 422 |
| `POST /admin/events/{event}/publish` | exige dados mínimos (FR-004); resposta 409 lista `missing[]` | 200 · 409 |
| `POST /admin/events/{event}/cancel` | `{ reason }` obrigatório; registra autor/momento | 200 · 409 · 422 |
| `POST /admin/events/{event}/banner` | multipart `banner` (jpeg/png/webp ≤ 5 MB); retorna `bannerUrl` | 200 · 422 |

## Tipos de evento (lookup)

| Método/rota | Regras |
|---|---|
| `GET /admin/event-types` | lista com uso (contagem de eventos) |
| `POST /admin/event-types` | `{ name }` único |
| `PUT /admin/event-types/{type}` | renomear / `isActive` |
| `DELETE /admin/event-types/{type}` | 409 se vinculado a evento; soft delete |

## Catálogo (aninhado no evento)

| Método/rota | Regras |
|---|---|
| `GET /…/ticket-types` | inclui `available`, `soldOut`, contagem vendida |
| `POST /…/ticket-types` · `PUT /…/{id}` | campos FR-007; capacidade ≥ vendido (409) |
| `DELETE /…/ticket-types/{id}` | 409 com vendas; soft delete sem |
| `PATCH /…/ticket-types/reorder` | `{ ids: [...] }` na nova ordem |
| `GET /…/lots` | inclui `isCurrent`, `soldOut`, `effectivePrice` por tipo |
| `POST /…/lots` · `PUT /…/{id}` · `DELETE` · `PATCH /…/lots/reorder` | mesmas guardas (409 com vendas) |

## Camisas

| Método/rota | Regras |
|---|---|
| `GET /…/shirt-models` | modelos com tamanhos aninhados (vendido/esgotado por tamanho) |
| `POST/PUT/DELETE /…/shirt-models[/{id}]` | delete 409 se algum tamanho tem vendas |
| `POST/PUT/DELETE /…/shirt-models/{model}/sizes[/{id}]` | estoque ≥ vendido (409); delete 409 com vendas |

## Landing (editor de blocos)

| Método/rota | Regras |
|---|---|
| `GET /…/landing-blocks` | ordenados por `sort` |
| `POST /…/landing-blocks` | `{ type, payload, isActive }` — payload validado por tipo |
| `PUT /…/landing-blocks/{id}` · `DELETE` | validação idem; delete livre (soft) |
| `PATCH /…/landing-blocks/reorder` | `{ ids: [...] }` transacional |

## Cortesias

| Método/rota | Regras |
|---|---|
| `PUT /admin/events/{event}` | a regra X→Y/limite viaja nos campos do evento (FR-013) |
| `GET /…/courtesy-vouchers?status=` | filtro por situação |
| `POST /…/courtesy-vouchers` | `{ quantity: 1..500, ticketTypeId? }` → N códigos `CTY-…` |
| `PATCH /…/courtesy-vouchers/{id}/distribute` | `{ note? }`; só de `available` (409); registra autor/momento |

## Patrocínios

| Método/rota | Regras |
|---|---|
| `GET /…/sponsorships` | com parcelas aninhadas e status geral |
| `POST /…/sponsorships` | `{ companyName, contact?, totalAmount, paymentMethod?, installmentsCount }` → parcelas geradas (soma = total) |
| `PUT /…/sponsorships/{id}` | dados cadastrais; cancelamento via `{ status: "cancelled" }` preserva tudo |
| `POST /…/sponsorships/{id}/installments/{n}/pay` | `{ paidAmount, method?, paidAt? }`; parcela paga → 409; recalcula status geral |

## Contrato de frontend (painel `/painel`)

- Guarda: `RoleRoute role="admin"` (sobre o ProtectedRoute da 002); sem papel →
  página 403.
- Layout Tabler (`@tabler/core`): sidebar Evento · Tipos & Lotes · Camisas ·
  Landing · Cortesias · Patrocínios.
- Telas exibem estados derivados vindos da API (vigente/esgotado/preço efetivo) —
  nunca recalculam no cliente.
- Inputs monetários aceitam vírgula; normalização em `lib/money.js` antes do envio.
