# Tasks — Plataforma de Eventos (standalone, MVP)

Ordem pensada para ter valor demonstrável cedo: primeiro a fundação + extração do 061,
depois o caminho crítico (comprar → pagar → confirmar → check-in), depois configuração/
tesouraria/relatórios, por fim landing e polimento.

## Fase 0 — Extração do 061 + scaffold  ⏳
- [ ] T000 Criar repositório `eventos-plataforma` (Laravel 11 + Vite React 18 + Sanctum)
- [ ] T001 Copiar `app/Domain/Events` do 061; **remover** owner polimórfico, `EventAccessGuard`,
      `require.module`, Member/Lodge, seat_limit_per_lodge, seeders Baseline/Module, 054/055
- [ ] T002 Renomear tabelas `event_*` → `orders`/`tickets`/`payments`… (data-model)
- [ ] T003 Configurar MySQL dev + `.env` (sem segredos no VCS) + `make` targets (up/migrate/test)

## Fase 1 — Fundação (DB + lookups + RBAC)  ⏳
- [ ] T010 Migrations: auth (users/roles/role_user), lookups (event/order/ticket/payment
      statuses, event_types)
- [ ] T011 Migrations: events + landing_blocks + ticket_types + ticket_lots + shirt_models/sizes
- [ ] T012 Migrations: orders + tickets + payments + webhook_events + courtesy_vouchers +
      sponsorships/installments + support_cases/notes
- [ ] T013 Models + relacionamentos + derivações (salesOpen/currentLot/available/soldOut)
- [ ] T014 `RoleSeeder` + `RoleMiddleware` (require.role:admin|treasury|gate) + Policies
- [ ] T015 Seeders lookup + `AdminUserSeeder` (dev) + `SampleEventSeeder`

## Fase 2 — Auth do inscrito (US2)  ⏳
- [ ] T020 Sanctum SPA + register/login/logout + verificação de e-mail + reset de senha
- [ ] T021 Socialite **Google** (redirect/callback → cria/liga conta, papel `attendee`)
- [ ] T022 `/auth/me` + guarda de sessão no front (contexto de auth + rotas protegidas)

## Fase 3 — Configuração do evento (US4, Admin)  ⏳
- [ ] T030 `EventController` (get/update/publish/cancel/banner) + `EventConfigService`
- [ ] T031 `TicketTypeController` + `TicketLotController` (lotes: janela/quantidade/preço)
- [ ] T032 `ShirtOptionController` (modelos + tamanhos + **estoque**)
- [ ] T033 `EventTypeController` (lookup) + `CourtesyController` (vouchers) + regra de cortesia

## Fase 4 — Catálogo público + compra (US1/US3)  ⏳
- [ ] T040 `PublicEventController` (evento + lote vigente + tipos + disponibilidade derivada)
- [ ] T041 `TicketPurchaseService` (order+tickets, snapshot, code) + `CourtesyResolver` +
      `TicketCodeGenerator` + reserva com TTL (`reserved_until`)
- [ ] T042 `OrderController` store/mine/show/history (escopo do inscrito) + Resources
- [ ] T043 `ExpireReservations` (Job a cada 5 min → `expired`, libera vaga)

## Fase 5 — Pagamento real (US3 — o desmembramento)  ⏳
- [ ] T050 `PaymentGatewayContract` + `SicoobClient` (OAuth2 + **mTLS cert A1**)
- [ ] T051 `SicoobPixGateway` (`createPixCharge`) + `CheckoutController@pix` (QR + copia-e-cola)
- [ ] T052 `SicoobBoletoGateway` (boleto **híbrido** `"hibrido":true`) + `@boleto` (linha + PDF)
- [ ] T053 `CardGateway` (tokenização client-side; backend só recebe **token**) + `@card`
- [ ] T054 `RegisterPayment` (**ponto único**, idempotente) confirma order/tickets
- [ ] T055 `WebhookController@sicoob`/`@card` + `HandleWebhook` (dedupe `webhook_events`,
      reconsulta a cobrança antes de baixar)
- [ ] T056 `ReconcilePayments` (Job diário — fallback do webhook) + `payment-status` polling
- [ ] T057 `PaymentController@pay-manual` (baixa de **contingência** tesouraria; 403 da própria compra)

## Fase 6 — Ciclo de vida + suporte (US6)  ⏳
- [ ] T060 `TicketLifecycleService` cancel/refund/transfer (novo titular por e-mail/dados)
- [ ] T061 `RefundPayment` (cartão via gateway; Pix/boleto operacional) + `RefundController`
- [ ] T062 `EventConfigService@cancel` (bloqueia compras, preserva histórico, fila de estornos)
- [ ] T063 `SupportController` + `support_cases`/notes (canal inscrito ↔ admin/tesouraria)

## Fase 7 — Check-in + painel + relatórios (US7)  ⏳
- [ ] T070 `CheckinController` validate/list (QR → `used`; casal conta 2) — papel `gate`
- [ ] T071 `DashboardController` (contagens/previsto×confirmado/camisas/por lote/por forma)
- [ ] T072 `ReportController` + `EventExporter` (.xlsx, filtros mês/ano/período) + `AuditController`
- [ ] T073 `TreasuryController` (recebimentos + conciliação) + painel financeiro
- [ ] T074 `TicketReceiptPdf` (dompdf + QR) + rota de comprovante

## Fase 8 — Frontend  ⏳
- [ ] T080 types/services/hooks (React Query) + contexto de auth
- [ ] T081 **Site público**: `LandingRenderer` + `TicketPicker` + `Cart` + `ParticipantForm`
- [ ] T082 **Checkout**: `CheckoutPix` (QR/polling) + `CheckoutBoleto` + `CheckoutCard` (SDK token)
- [ ] T083 **Área do inscrito**: `MyOrders`/`MyTickets` + `TicketReceipt` (PDF/print) + transfer/cancel + suporte
- [ ] T084 **Admin**: Evento/Landing/Tipos&Lotes/Camisas/Cortesia/Patrocínio/Inscritos/Painel/Relatórios/Trilha
- [ ] T085 **Tesouraria**: Recebimentos/Conciliação/Baixa manual/Estornos/Financeiro
- [ ] T086 **Portaria**: `CheckinPanel` (leitor QR)
- [ ] T087 `LandingEditor` (blocos hero/text/schedule/speakers/faq/location/cta)
- [ ] T088 Rotas (`/`, `/evento/:slug`, `/checkout`, `/minha-conta`, `/painel/*`) + navegação por papel

## Fase 9 — Testes / dev data / docs  ⏳
- [ ] T090 Feature tests (ver `plan.md §4`): compra, pagamento (Pix/webhook/idempotência/
      reconciliação/boleto/cartão/manual), lote/reserva, cortesia, ciclo de vida,
      transferência, estorno, check-in, escopo/RBAC, relatórios, comprovante
- [ ] T091 `SampleEventSeeder` (evento publicado + lotes + tipos + camisas + inscritos em
      vários status + patrocínio) + `SampleCheckinSeeder` (~30 inscritos p/ testar QR)
- [ ] T092 README + `quickstart.md` + variáveis `.env.example` (Sicoob/gateway/Google — placeholders)

## Fase 2 do produto (deferida — não nesta entrega)
- [ ] FR-30 Multi-evento / multi-tenant
- [ ] FR-31 Mais gateways + split de pagamento
- [ ] FR-32 App de portaria offline-first
- [ ] FR-33 Cupons de desconto
- [ ] FR-34 Nota fiscal / integração contábil
- [ ] FR-35 Afiliados/indicação

---

## Dependências / decisões a fechar antes de codar

1. **Gateway de cartão** — Cielo, Rede ou outro? Define o SDK de tokenização e o
   formato do webhook (T053/T055). Fica atrás do `PaymentGatewayContract`.
2. **Certificado A1 Sicoob** — obter o `.pfx`, converter p/ `.pem`, cadastrar aplicação
   no Portal Developers Sicoob, liberar escopos `cob`/`cobv`/`pix` (T050).
3. **Credenciais Google OAuth** — client id/secret no Google Cloud (T021).
4. **Domínio + HTTPS** — webhook do Sicoob e do gateway exigem URL pública com TLS.
5. **Política de reembolso** — regra de negócio (prazo, percentual) que o
   `RefundPayment`/`support_case` vai seguir.
