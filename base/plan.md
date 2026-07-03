# Plan — Plataforma de Eventos (standalone, single-event)

Projeto novo, **fork/extração** do módulo 061. Estratégia: copiar o domínio
`app/Domain/Events` do 061, **arrancar** o acoplamento de loja/GL/member, e **plugar**
autenticação própria + pagamento real (Sicoob/gateway) + landing page.

Stack: **Laravel 11 + React 18 (Vite)**, MySQL, Sanctum (SPA cookie), Socialite
(Google), openspout (.xlsx), barryvdh/laravel-dompdf (comprovante), simple-qrcode
(QR no PDF). Filas via `queue` (webhook/e-mail/reconciliação).

---

## 0. Extração do 061 (o passo de desmembramento)

1. **Novo repositório** `eventos-plataforma` (Laravel skeleton limpo).
2. Copiar de 061 para o novo domínio, **removendo**:
   - `owner_type`/`owner_lodge_id` de `events` e todo `resolveOwner`.
   - `EventAccessGuard` → reescrever como `EventPolicy`/`RolePolicy` (RBAC 4 papéis).
   - `require.module:events` → `require.role:*`.
   - Referências a `Member`, `Lodge`, `searchBrothers`, `seat_limit_per_lodge`,
     grants `Baseline/ModuleSeeder`, integração 054/055.
   - Os três chromes → substituir por site público + admin + área do inscrito.
3. **Renomear** tabelas `event_*` → nomes limpos (`orders`, `tickets`, `payments`…)
   conforme data-model, mantendo o padrão order→tickets e o snapshot.
4. Manter intactos (só limpando imports): `TicketPurchaseService`, `CourtesyResolver`,
   `TicketCodeGenerator`, `RegisterPayment` (ex-RegisterEventPayment), `SponsorshipService`,
   `TicketLifecycleService`, `EventExporter`, o check-in.

---

## 1. Backend

### 1.1 Migrations
Um conjunto por área (auth, lookups, event+config, orders+tickets, payments, courtesy,
sponsorship, support, webhook) — ver `data-model.md`. Seeders de lookup + `RoleSeeder`
(admin/treasury/gate/attendee) + `AdminUserSeeder` (dev) + `SampleEventSeeder`.

### 1.2 Auth (novo)
- **Sanctum** SPA (cookie) para o painel e a área do inscrito.
- **Socialite Google**: `AuthController@redirectGoogle` / `@callbackGoogle` → cria/liga
  `users.google_id`, atribui papel `attendee`.
- E-mail/senha com `email_verified_at` + reset de senha padrão Laravel.
- `RoleMiddleware` (`require.role:admin`, `:treasury`, `:gate`).

### 1.3 Models (BaseModel + BaseLookupModel)
`Role`, `EventStatus`, `OrderStatus`, `TicketStatus`, `PaymentStatus`, `EventType`,
`Event` (hasMany landingBlocks/ticketTypes/lots/shirtModels/shirtSizes/orders/
sponsorships; derived `salesOpen`, `currentLot`, `ticketsSold`, `available`),
`LandingBlock`, `TicketType`, `TicketLot` (derived `isCurrent`, `soldOut`),
`EventShirtModel`, `EventShirtSize` (derived `soldOut`), `Order` (belongsTo buyerUser;
hasMany tickets/payments; derived `amountPaid`, `isExpired`), `Ticket` (belongsTo
order/type/lot/shirt*/status; derived `isActive`), `Payment`, `WebhookEvent`,
`CourtesyVoucher`, `Sponsorship`, `SponsorshipInstallment`, `SupportCase`,
`SupportCaseNote`.

### 1.4 Policies (RBAC — substitui o EventAccessGuard)
- `admin` → tudo do evento.
- `treasury` → pagamentos/conciliação/estorno/relatório financeiro.
- `gate` → check-in.
- `attendee` → só os próprios pedidos/ingressos; comprar; 2ª via; transferir/cancelar
  (quando a flag permite). **Quem compra não dá a própria baixa** (regra mantida).

### 1.5 Services (DB::transaction em toda escrita multi-passo)
- `EventConfigService` — cria/edita evento + tipos + lotes + camisas; publica; cancela.
- `LandingPageService` — CRUD dos `landing_blocks`.
- `TicketPurchaseService` — `purchase(event, buyer, items[])`: valida flags/lote
  vigente/capacidade/janela/estoque de camisa → cria `order` (status `pending`,
  `reserved_until`) + N `tickets` (snapshot, `code`), aplica `CourtesyResolver`,
  calcula total.
- `CourtesyResolver` — regra X→Y + limite por conta + resgate de voucher (dentro da
  transação, recontando pagos — corrige race).
- **Pagamento** (novo, o núcleo desmembrado):
  - `PaymentGatewayContract` (interface) → `SicoobPixGateway`, `SicoobBoletoGateway`,
    `CardGateway` (implementações).
  - `SicoobClient` — OAuth2 + **mTLS com certificado A1 (.pem)**; métodos
    `createPixCharge`, `createHybridBoleto`, `getCharge`, `cancelCharge`.
  - `CreateCharge` — dado um `order` + método, cria a cobrança no provedor, grava
    `payments` com QR/linha digitável/`due_date`.
  - `RegisterPayment` — **ponto único de baixa** (mantido do 061, agora ligado):
    marca `payment`/`order`/`tickets` `paid`/`confirmed`. **Idempotente**.
  - `HandleWebhook` — valida/deduplica (`webhook_events`), consulta o provedor,
    chama `RegisterPayment`.
  - `ReconcilePayments` (Job agendado diário) — varre pedidos `pending` com cobrança e
    concilia com o Sicoob (fallback do webhook).
  - `RefundPayment` — estorno (cartão via gateway; Pix/boleto → operacional +
    `support_case`).
  - `ExpireReservations` (Job a cada 5 min) — pedidos `pending` vencidos → `expired`,
    libera vaga.
- `TicketLifecycleService` — cancel/refund/transfer/checkIn (mantido; transferência
  por e-mail/dados, não por member).
- `SponsorshipService` — cria + parcelas + `payInstallment`.
- `TicketCodeGenerator`, `OrderRecalculator`.
- `TicketReceiptPdf` — dompdf + QR (agora MVP, não deferido).
- `EventExporter` — .xlsx por relatório (openspout).
- **Notificações** — Mailables: `OrderPlaced`, `PaymentConfirmed`, `BoletoIssued`,
  `EventReminder` (fila).

### 1.6 Controllers (`app/Http/Controllers/Api`)
Público (sem auth): `PublicEventController` (landing + detalhe + lote vigente),
`AuthController` (register/login/google/verify/reset).
Inscrito: `OrderController` (store/mine/show), `CheckoutController` (createCharge por
método), `TicketController` (show/receipt/transfer/cancel), `SupportController`.
Admin: `EventController`, `EventTypeController`, `TicketTypeController`,
`TicketLotController`, `ShirtOptionController`, `LandingBlockController`,
`CourtesyController`, `SponsorshipController`, `DashboardController`, `ReportController`,
`AuditController`.
Tesouraria: `TreasuryController` (conciliação/painel), `PaymentController` (baixa
manual de contingência), `RefundController`.
Portaria: `CheckinController` (validate + list).
Webhook (sem auth, assinado): `WebhookController@sicoob`, `@card`.

### 1.7 Rotas — `routes/api.php` (+ `routes/domains/*.php`)
Público em `/api/public/*` e `/api/auth/*`. Área autenticada sob `auth:sanctum`.
Gestão sob `require.role:*`. Webhooks em `/api/webhooks/{provider}` (sem sessão, com
verificação de assinatura/allowlist de IP + mTLS quando aplicável).

---

## 2. Frontend (`frontend/src`)

Três "áreas" (não três chromes de perfil como no 061, e sim por função):

- **Site público** (`/`, `/evento/:slug`, `/checkout`) — landing renderizada a partir
  dos `landing_blocks`, seleção de ingresso, carrinho, checkout. Layout público próprio.
- **Área do inscrito** (`/minha-conta`) — meus pedidos/ingressos, 2ª via, comprovante,
  transferência, suporte. Layout logado leve.
- **Painel admin/tesouraria/portaria** (`/painel`) — sidebar por papel:
  - Admin: Evento · Landing · Tipos & Lotes · Camisas · Cortesias · Patrocínio ·
    Inscritos · Painel · Relatórios · Trilha.
  - Tesouraria: Recebimentos · Conciliação · Baixa manual · Estornos · Financeiro.
  - Portaria: Check-in.

Componentes-chave: `LandingRenderer`/`LandingEditor`, `TicketPicker`, `Cart`,
`ParticipantForm`, `CheckoutPix` (QR + copia-e-cola + polling de status),
`CheckoutBoleto` (linha digitável + PDF), `CheckoutCard` (SDK de tokenização do
gateway — **o PAN nunca vai ao nosso backend**), `MyOrders`/`MyTickets`,
`TicketReceipt` (PDF/print + QR), `RegistrationsPanel`, `PaymentReconcilePanel`,
`RefundPanel`, `SponsorshipPanel`, `CourtesyPanel`, `CheckinPanel` (leitor QR),
`DashboardPanel`, `ReportsPanel` (mês/ano/período + .xlsx), `AuditPanel`.

Estado do servidor: React Query. QR no browser via canvas (padrão do 061).

---

## 3. Segurança / pagamento (regras duras)

- **Cartão**: tokenização client-side pelo SDK do gateway. O backend recebe só o
  **token**; **nunca** PAN/CVV/validade. Sem exceção.
- **Sicoob**: certificado A1 e Client ID em secret manager / `.env` fora do VCS; mTLS
  na chamada. Nada de credencial no front.
- **Webhook**: verificação de assinatura/allowlist + idempotência por
  `webhook_events`. Nunca confiar só no corpo do webhook — sempre reconsultar a
  cobrança no provedor antes de baixar.
- **Ponto único de baixa** (`RegisterPayment`): toda confirmação passa por ele;
  idempotente (não confirma duas vezes).
- **URLs/QR públicos** usam `code`/`uuid`, nunca id sequencial.

---

## 4. Testes — `tests/Feature` (MySQL de teste)
Cobrir: landing pública; cadastro/login (senha + Google mock); compra (carrinho/
grupo/casal); lote vigente + virada + esgotamento; reserva expira; **pagamento**
(Pix cria cobrança / webhook confirma / idempotência / reconciliação; boleto híbrido;
cartão tokenizado auto-confirma; baixa manual de contingência; quem compra não baixa a
própria); cortesia (regra + voucher); cancelamento (pedido/ingresso/evento) preserva
histórico; transferência (novo titular por e-mail); estorno; check-in QR; escopo do
inscrito (só vê o seu); RBAC (portaria não acessa financeiro etc.); relatórios com
filtro mês/ano/período; comprovante PDF.

---

## 5. Riscos / notas

- **Sicoob**: documentação do webhook é reconhecidamente fraca/incompleta → o job de
  **reconciliação diária** não é opcional, é a garantia de baixa. Certificado A1 tem
  validade — monitorar expiração.
- **Cartão**: escolher o gateway (Cielo/Rede) fecha o SDK de tokenização e o formato
  de webhook; manter atrás do `PaymentGatewayContract` para trocar sem reescrever.
- **Reserva/estoque**: capacidade, lote e estoque de camisa têm **race** em compras
  concorrentes → resolver dentro de `DB::transaction` com recontagem no momento
  (mesmo princípio da cortesia no 061).
- **Snapshot** de preço/nome/camisa/lote no ticket protege relatórios se o tipo/lote
  mudar depois.
- **LGPD**: dados pessoais dos inscritos (CPF/e-mail) — minimizar, criptografar em
  repouso quando aplicável, e ter rota de exclusão. (Detalhar na Fase 2.)
- **Single-event**: modelar `events` como tabela desde já evita reescrita quando
  virar multi-evento (Fase 2).
