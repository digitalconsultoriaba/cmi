# Quickstart — Plataforma de Eventos (standalone)

**Projeto**: `eventos-plataforma` | Single-event | Laravel 11 + React 18

Produto autônomo de venda e gestão de ingressos, **desmembrado** do módulo 061 da
Grande Loja. Sem dependência de loja/irmão/matriz de permissões.

---

## Prerequisites

- PHP 8.3+, Composer, Node 20+, MySQL 8.
- **Google OAuth** (client id/secret) para login social.
- **Sicoob**: aplicação no Portal Developers + certificado A1 (`.pfx`→`.pem`) + Client ID
  + escopos `cob`/`cobv`/`pix`.
- **Gateway de cartão** (Cielo/Rede): credenciais + SDK de tokenização.
- Domínio com **HTTPS** (webhooks do Sicoob e do gateway exigem URL pública).

---

## 1. Instalar + migrar + semear

```bash
composer install
npm install --prefix frontend
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed
```

Seeders:
- **lookups** — event/order/ticket/payment statuses + event_types.
- **`RoleSeeder`** — `admin`/`treasury`/`gate`/`attendee`.
- **`AdminUserSeeder`** (dev) — um admin, um tesoureiro, um porteiro.
- **`SampleEventSeeder`** (dev) — evento publicado com lotes, tipos, camisas (com
  estoque), cortesia, patrocínio e inscritos em vários status.
- **`SampleCheckinSeeder`** (dev) — ~30 inscritos p/ testar o QR na portaria.

---

## 2. Rodar

```bash
php artisan serve            # API :8000
npm run dev --prefix frontend # Vite :5173
php artisan queue:work        # filas (webhook, e-mail, reconciliação)
php artisan schedule:work     # jobs: ExpireReservations (5min), ReconcilePayments (diário)
```

---

## 3. `.env` — variáveis-chave (placeholders — nunca commitar valores reais)

```
APP_URL=https://seu-dominio
SANCTUM_STATEFUL_DOMAINS=seu-dominio,localhost:5173

GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=${APP_URL}/api/auth/google/callback

SICOOB_CLIENT_ID=...
SICOOB_CERT_PATH=/secrets/sicoob-a1.pem   # fora do repositório
SICOOB_CERT_KEY_PATH=/secrets/sicoob-a1.key
SICOOB_SANDBOX=true
SICOOB_WEBHOOK_SECRET=...

CARD_GATEWAY=cielo            # ou rede
CARD_GATEWAY_MERCHANT_ID=...
CARD_GATEWAY_KEY=...
CARD_GATEWAY_WEBHOOK_SECRET=...
```

> **Segurança**: nenhuma dessas variáveis vai ao front nem ao VCS. O SDK de cartão
> tokeniza no navegador do inscrito — o backend recebe **só o token**, nunca o número
> do cartão.

---

## 4. Testar a API (curl)

```bash
# Landing pública (sem auth)
curl http://localhost:8000/api/public/event

# Compra (inscrito autenticado) — cria pedido + reserva
curl -b cookies.txt -X POST http://localhost:8000/api/orders \
  -d '{"items":[{"ticketTypeId":1,"participantName":"Ana","participantEmail":"ana@x.com","shirtSizeId":3}]}'

# Checkout Pix → devolve QR + copia-e-cola
curl -b cookies.txt -X POST http://localhost:8000/api/orders/ABC123/checkout/pix

# Webhook Sicoob confirma (simulado no sandbox) → pedido paid, ingressos confirmed

# Check-in por código (portaria) → used
curl -b cookies.txt -X POST http://localhost:8000/api/gate/checkin -d '{"code":"TCK-XYZ"}'
```

---

## 5. Fluxos-chave para validar

1. Visitante vê a landing e os ingressos do lote vigente **sem login**.
2. Cadastro/login (e-mail + **Google**); verificação de e-mail.
3. Compra em grupo (N participantes, casal, camisa por pessoa); reserva expira se não pagar.
4. Lote vira por data/quantidade; esgota → bloqueia (409).
5. **Pix** (QR) confirma por webhook; **boleto híbrido** (linha + QR); **cartão**
   tokenizado auto-confirma; idempotência (webhook não baixa 2×); reconciliação diária.
6. Cortesia automática (X→Y) + voucher resgatado no checkout.
7. Tesouraria: conciliação Sicoob + baixa manual de contingência (quem compra não baixa → 403).
8. Cancelar pedido/ingresso/evento preserva histórico; estorno abre atendimento.
9. Transferência (novo titular por e-mail) muda o dono de verdade.
10. Check-in valida QR → `used`; recusa já usado/cancelado; casal conta 2.
11. Relatórios .xlsx com filtro mês/ano/período; comprovante PDF com QR.
12. RBAC: portaria não acessa financeiro; inscrito só vê o seu.

---

## 6. Testes

```bash
php artisan test --testsuite=Feature
```

Cobrem compra, pagamento (Pix/webhook/idempotência/reconciliação/boleto/cartão/manual),
lote/reserva, cortesia, ciclo de vida, transferência, estorno, check-in, escopo/RBAC,
relatórios e comprovante.
