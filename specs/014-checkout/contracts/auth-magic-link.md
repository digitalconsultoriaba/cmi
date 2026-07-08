# Contract — Auth: Magic link (acesso passwordless)

Padrão de **URL assinada** do Laravel (mesma mecânica do `verify-email`): integridade por assinatura + expiração. Sem senha trafegando (constituição IV). Emitido a comprador e a cada participante com e-mail, ao confirmar o pedido.

## `GET /auth/magic/{user}` (middleware `signed`)

Consome o link mágico: valida a assinatura/expiração, autentica a **sessão Sanctum** daquele `User` e redireciona ao SPA (área do inscrito).

- **302** → redireciona ao front (`/minha-conta/ingressos`) com sessão iniciada.
- **403** → link inválido/expirado (assinatura falha) → página de "link expirado" com opção de reenvio.

Notas:
- O link é gerado por `MagicLinkService` (`URL::temporarySignedRoute('auth.magic', now()->addDays(N), ['user' => $id])`).
- Não altera senha; apenas inicia sessão. Marca `email_verified_at` no primeiro uso.

## `POST /public/orders/{order:code}/resend-access`

Reenvia o magic link do **comprador** (e opcionalmente reenvia os ingressos/links dos participantes). Sem auth (identificado pelo `code` do pedido criado na sessão).

- **200** → `{ data: { sent: true } }`.

## `POST /auth/magic/request`

Solicita um novo magic link informando o **e-mail** (comprador ou participante). Resposta neutra (não revela se o e-mail existe).

**Body**: `{ "email": "i1@ex.com" }`
- **200** → `{ data: { sent: true } }` (envia se houver conta; silencioso caso contrário).
- Throttle aplicado (anti-abuso).

## Escopo de acesso pós-login

- **Comprador** (`buyer_user_id`): vê **todos** os pedidos/ingressos onde é comprador (rotas existentes `GET /orders`, `GET /tickets`).
- **Participante** (`participant_user_id`): vê **apenas** os ingressos onde é o participante. As policies existentes de `Order`/`Ticket` são estendidas para permitir o participante ver **o seu** ticket (além do comprador).
- Reenvio/baixa do ingresso: `GET /tickets/{ticket:code}/receipt` (PDF/QR), autorizado a comprador e ao participante do ticket.
