# API Contracts — Plataforma de Eventos (standalone)

**Auth**: Sanctum (SPA cookie). Rotas públicas sem auth; gestão sob `require.role:*`.
Resposta `{ data: ... }`, chaves **camelCase**. Erros na shape de domínio (ver §final).

Prefixos:
- `/api/public/*` — sem auth (landing, catálogo, detalhe).
- `/api/auth/*` — cadastro/login/Google/verificação.
- `/api/*` — autenticado (inscrito); gestão adiciona `require.role`.
- `/api/webhooks/*` — sem sessão, verificação de assinatura + idempotência.

---

## Público (sem auth)

| Método | Rota | Ação |
|---|---|---|
| GET | `/public/event` | evento vigente + landing blocks + lote/preço atual (single-event) |
| GET | `/public/event/{slug}` | idem por slug (prep multi-evento) |
| GET | `/public/event/ticket-types` | tipos de ingresso do lote vigente + disponibilidade (derivada) |
| GET | `/public/event/shirt-options` | modelos + tamanhos + estoque (quando exige camisa) |

---

## Auth

| Método | Rota | Ação |
|---|---|---|
| POST | `/auth/register` | cadastro e-mail+senha → envia verificação |
| POST | `/auth/login` | login e-mail+senha |
| POST | `/auth/logout` | logout |
| GET | `/auth/google/redirect` | inicia OAuth Google (Socialite) |
| GET | `/auth/google/callback` | callback → cria/liga conta, papel `attendee` |
| POST | `/auth/email/verify` | confirma e-mail |
| POST | `/auth/password/forgot` | envia reset |
| POST | `/auth/password/reset` | redefine |
| GET | `/auth/me` | usuário atual + papéis |

---

## Inscrito (auth:sanctum)

### Compra / checkout
| Método | Rota | Ação |
|---|---|---|
| POST | `/orders` | **compra/carrinho**: cria `order` `pending` + N `tickets`; reserva com TTL |
| GET | `/orders/mine` | "Meus pedidos/ingressos" (escopo do próprio usuário) |
| GET | `/orders/{code}` | detalhe do pedido (só dono) |
| GET | `/orders/{code}/history` | trilha do pedido |
| POST | `/orders/{code}/checkout/pix` | gera cobrança **Pix Sicoob** (QR + copia-e-cola) |
| POST | `/orders/{code}/checkout/boleto` | gera **boleto híbrido Sicoob** (linha + PDF + QR Pix) |
| POST | `/orders/{code}/checkout/card` | paga com **cartão** (recebe **token** do gateway, nunca PAN) |
| GET | `/orders/{code}/payment-status` | polling do status (pending/paid) enquanto aguarda |

**`POST /orders`** — corpo: `items[]`, cada item = participante (nome/e-mail/documento)
+ `ticketTypeId` + camisa (`shirtSizeId`/`shirtModelId`) + acompanhante do casal
(`companionName` + camisas) + `courtesyCode?`. Aplica lote vigente, cortesia
(`CourtesyResolver`), valida capacidade/janela/estoque, gera `code`, snapshot de preço.
Estoura capacidade/lote/estoque → 409.

**`POST /orders/{code}/checkout/card`** — corpo: `{ token, installments }`. O `token`
vem do SDK de tokenização do gateway no navegador. O backend **nunca** recebe número
de cartão, CVV ou validade.

### Ingressos
| Método | Rota | Ação |
|---|---|---|
| GET | `/tickets/{code}` | ingresso (comprovante/QR) — só dono |
| GET | `/tickets/{code}/receipt` | **comprovante PDF** (dompdf + QR) |
| POST | `/tickets/{code}/transfer` | transfere (gated `allow_transfer`; só pago; novo titular por e-mail/dados) |
| POST | `/tickets/{code}/cancel` | cancela (só se `allow_user_cancel`; pago → abre `support_case` de reembolso) |

### Suporte
| Método | Rota | Ação |
|---|---|---|
| GET | `/support-cases/mine` | meus atendimentos |
| POST | `/support-cases` | abre (dúvida/reembolso/troca de camisa) |
| POST | `/support-cases/{id}/note` | responde no thread |

---

## Admin (require.role:admin)

### Evento + configuração
| Método | Rota | Ação |
|---|---|---|
| GET | `/admin/event` | evento (todos os campos) |
| PUT | `/admin/event` | edita evento + flags + cortesia |
| POST | `/admin/event/publish` | `draft` → `published` |
| POST | `/admin/event/cancel` | `cancelled` (quem/quando/motivo); bloqueia compras; preserva histórico |
| POST | `/admin/event/banner` / DELETE | upload/remove banner |

### Landing page (FR-17)
| Método | Rota | Ação |
|---|---|---|
| GET | `/admin/landing-blocks` | lista blocos |
| POST | `/admin/landing-blocks` | cria bloco (hero/text/schedule/speakers/faq/location/cta) |
| PUT | `/admin/landing-blocks/{id}` | edita conteúdo/ordem |
| DELETE | `/admin/landing-blocks/{id}` | remove |

### Tipos de evento / ingresso / lotes
| Método | Rota | Gate | Ação |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/admin/event-types[/{id}]` | admin | CRUD lookup |
| GET/POST | `/admin/ticket-types` | admin | lista/cria |
| PUT/DELETE | `/admin/ticket-types/{id}` | admin | edita/remove |
| GET/POST | `/admin/ticket-lots` | admin | lotes (janela/quantidade/preço) |
| PUT/DELETE | `/admin/ticket-lots/{id}` | admin | edita/remove |

### Camisas (com estoque)
| Método | Rota | Ação |
|---|---|---|
| GET | `/admin/shirt-options` | modelos + tamanhos + estoque |
| POST/PUT/DELETE | `/admin/shirt-models[/{id}]` | CRUD modelo |
| POST/PUT/DELETE | `/admin/shirt-sizes[/{id}]` | CRUD tamanho (`stockQuantity?`) |

### Cortesia (vouchers)
| Método | Rota | Ação |
|---|---|---|
| GET | `/admin/vouchers` | lista |
| POST | `/admin/vouchers` | gera códigos |
| POST | `/admin/vouchers/{id}/distribute` | marca distribuído (quem/quando) |
| POST | `/admin/tickets/{code}/courtesy` | cortesia manual (converte ingresso) |

### Patrocínio
| Método | Rota | Ação |
|---|---|---|
| GET/POST | `/admin/sponsorships` | lista/cria (+ gera parcelas) |
| PUT/DELETE | `/admin/sponsorships/{id}` | edita/exclui |
| POST | `/admin/sponsorship-installments/{id}/pay` | baixa de parcela → status agregado |

### Inscritos / painel / relatórios / trilha
| Método | Rota | Ação |
|---|---|---|
| GET | `/admin/orders` | inscritos do evento + filtros |
| GET | `/admin/dashboard` | painel: contagens, previsto×confirmado, camisas, por lote, por forma |
| GET | `/admin/reports` | preview; `type` ∈ registrations\|by_ticket_type\|by_lot\|by_status\|checkin\|shirts\|financial\|sponsorships + filtros mês/ano/período |
| GET | `/admin/reports/export` | **.xlsx** (openspout) do mesmo `type` |
| GET | `/admin/audit` | trilha (activity log) do evento + filhos |

---

## Tesouraria (require.role:treasury)

| Método | Rota | Ação |
|---|---|---|
| GET | `/treasury/receivables` | recebimentos: previsto×recebido, por forma, por dia, pendências |
| GET | `/treasury/reconciliation` | conciliação Sicoob (cobrança ↔ liquidação); dispara `ReconcilePayments` |
| POST | `/treasury/orders/{code}/pay-manual` | **baixa manual de contingência** (data/forma/quem/comprovante) → confirma |
| POST | `/treasury/tickets/{code}/refund` | devolução/estorno (cartão via gateway; Pix/boleto operacional) → `refunded` |
| GET | `/treasury/financial-report` | financeiro do evento |

Regra mantida: **quem compra não dá a própria baixa** → 403. Baixa manual exige papel
`treasury`/`admin`, nunca o próprio comprador.

---

## Portaria (require.role:gate)

| Método | Rota | Ação |
|---|---|---|
| POST | `/gate/checkin` | lê `code` (QR) → `used` (quem/quando); recusa inválido/já usado/cancelado |
| GET | `/gate/checkin/list` | inscritos aptos: presentes/ausentes + busca + resumo (casal conta 2) |

---

## Webhooks (sem sessão — assinados + idempotentes)

| Método | Rota | Ação |
|---|---|---|
| POST | `/webhooks/sicoob` | liquidação Pix/boleto → grava `webhook_events`, reconsulta a cobrança, `RegisterPayment` |
| POST | `/webhooks/card` | status do gateway de cartão (autorizado/capturado/estornado/chargeback) |

Processamento: dedupe por `webhook_events`; **nunca** confia só no corpo — reconsulta a
cobrança no provedor antes de baixar; baixa via ponto único idempotente.

---

## Error Shape

```json
{
  "message": "Você só pode acessar os seus próprios pedidos.",
  "type": "forbidden",
  "errors": null,
  "status": 403
}
```

| Status | Quando |
|---|---|
| 401 | não autenticado |
| 403 | fora do papel/escopo; baixa da própria compra; compra bloqueada por flag |
| 404 | recurso inexistente |
| 409 | situação terminal; capacidade/lote/estoque estourado; reserva expirada; transição inválida |
| 422 | corpo inválido (FormRequest) |
| 402 / 502 | falha do provedor de pagamento (gateway/Sicoob indisponível) |

---

## Fora de escopo (MVP)

Multi-evento/tenant, split de pagamento, mais gateways, app de portaria offline,
cupons de desconto, nota fiscal, notificações push/sino (só e-mail transacional).
