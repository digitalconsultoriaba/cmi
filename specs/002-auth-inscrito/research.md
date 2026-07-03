# Research — 002-auth-inscrito

Decisões técnicas da autenticação. Base: constituição (Sanctum SPA por cookie,
Socialite Google) e fundação da spec 001 (envelope, papéis, Mailpit).

---

## Decisão 1: Controllers próprios, sem Fortify/Breeze

**Decisão**: implementar `AuthController` (+ controllers finos por fluxo) à mão
sobre os blocos nativos do Laravel: `Auth::attempt` (sessão), `MustVerifyEmail`
(verificação), `Password` broker (reset), middleware `throttle`. Sem Fortify,
Breeze ou Jetstream.

**Rationale**: os starter kits impõem estrutura própria de rotas/respostas que
conflita com o envelope `{ data }`/shape de erros da fundação; o volume de código
manual é pequeno e fica 100% sob as convenções da casa (constituição: complexidade
além do necessário deve ser justificada).

**Alternativas consideradas**: Fortify headless — rejeitado: actions opinativas,
respostas fora do envelope, e metade dos recursos (2FA, etc.) fora do escopo.

---

## Decisão 2: Sessão SPA por cookie (Sanctum stateful), sem tokens

**Decisão**: fluxo SPA canônico do Sanctum: front obtém `GET /sanctum/csrf-cookie`,
depois `POST /api/auth/login`; sessão via cookie (driver `database`, já configurado
na 001); axios com `withCredentials` (já no `frontend/src/lib/api.js`) envia o
header XSRF automaticamente. Nenhum token pessoal é emitido nesta spec.

**Rationale**: constituição fixa "Sanctum SPA (cookie)"; cookies httpOnly evitam
exposição de token no JS. `SANCTUM_STATEFUL_DOMAINS` já cobre `localhost:5173`.

**Alternativas consideradas**: tokens Bearer (personal access tokens) — rejeitado:
armazenamento no front é superfície de XSS e contraria a constituição.

---

## Decisão 3: Verificação de e-mail — link assinado da API que redireciona ao front

**Decisão**: e-mail de verificação usa URL **assinada** apontando para
`GET /api/auth/verify-email/{id}/{hash}` (middleware `signed` + throttle). Ao
verificar (ou se já verificada), redireciona para o front
(`/entrar?verified=1`); assinatura inválida → 403 na shape padrão. Reenvio via
`POST /api/auth/email/resend` (autenticado, throttle 1/min — FR-004). Notificação
customizada em pt-BR.

**Rationale**: `MustVerifyEmail` + `signed` dá o mecanismo pronto e seguro (FR-003);
o redirect entrega UX de SPA sem expor token ao JS.

**Alternativas consideradas**: link apontando ao front que chama a API via fetch —
rejeitado: duplica validação de assinatura e complica o fluxo sem ganho.

---

## Decisão 4: Reset de senha via Password broker; e-mail leva ao front

**Decisão**: `POST /api/auth/forgot-password` usa o broker padrão
(`password_reset_tokens`, já migrada na 001 pelo skeleton); a notificação (pt-BR)
aponta para o front `/redefinir-senha?token=…&email=…`, que submete
`POST /api/auth/reset-password`. Resposta do forgot é **sempre a mesma** (FR-011),
com throttle. Tokens: uso único, expiração padrão (60 min), só o mais recente vale
— comportamento nativo do broker. Funciona para conta só-Google (define senha).

**Rationale**: broker nativo cumpre todos os requisitos (uso único, expiração,
invalidação de anteriores) sem código novo de segurança.

---

## Decisão 5: Google via Socialite — vínculo por `google_id`, merge por e-mail normalizado

**Decisão**: `GET /api/auth/google/redirect` responde `{ data: { url } }` (o front
navega); callback `GET /api/auth/google/callback`:
1. busca por `google_id` → login;
2. senão, busca por e-mail normalizado → **vincula** `google_id` (+ avatar) à conta
   existente e loga;
3. senão, cria conta: sem senha, `email_verified_at = now()` (e-mail atestado pelo
   Google), papel `attendee`, avatar/nome do perfil.
Callback redireciona ao front (`/entrar?google=ok|erro`). Erro/cancelamento no
provedor → redirect com flag de erro, sem sessão. Config em `config/services.php`;
`GOOGLE_CLIENT_ID/SECRET/REDIRECT_URI` como placeholders no `.env.example`.

**Rationale**: vínculo pelo id estável do Google resolve o edge case de e-mail
alterado no provedor; merge por e-mail evita conta duplicada (FR-009).

**Alternativas consideradas**: Socialite stateless + endpoint que recebe o code via
front — rejeitado: mais partes móveis; o redirect server-side é o fluxo documentado
e testável com mock.

**Testes**: `Socialite::shouldReceive('driver->user')` mockado — cobre criação,
vínculo e erro sem credenciais reais (SC-006). Teste manual E2E fica pendente do
bloqueador externo (credenciais OAuth).

---

## Decisão 6: Conta só-Google tentando senha → orientação explícita

**Decisão**: no login, se o usuário existe mas `password IS NULL`, responder 422
com mensagem própria ("Esta conta usa o Google para entrar. Use 'Entrar com
Google' ou defina uma senha em 'Esqueci minha senha'.") — tipo `validation`,
campo `email`. Qualquer outra falha → mensagem genérica de credenciais (FR-005).

**Rationale**: FR-010 pede orientação, não erro opaco; revelar que a conta é
Google-only é aceitável (o próprio Google já confirmaria na tentativa) e reduz
chamados de suporte.

---

## Decisão 7: Rate limiting nomeado

**Decisão**: `RateLimiter` nomeados em `AppServiceProvider`:
- `auth-login`: 5/min por e-mail normalizado + IP (FR-006); estourou → 429 na shape
  padrão (`type: throttled`).
- `auth-email`: 1/min por usuário (reenvio de verificação — FR-004).
- `auth-forgot`: 3/min por IP.
Login zera o limitador em sucesso.

**Rationale**: valores alinhados às Assumptions da spec; nomeados ficam testáveis e
ajustáveis num único lugar. 429 entra no handler de exceções da fundação
(`ThrottleRequestsException`).

---

## Decisão 8: Normalização de e-mail na borda

**Decisão**: e-mail sempre `trim` + `mb_strtolower` antes de validar/gravar/buscar
(FormRequests de register/login/forgot/reset e no merge do Google).

**Rationale**: edge case da spec (maiúsculas/espaços não podem duplicar conta);
fazer na borda (FormRequest `prepareForValidation`) mantém o domínio limpo.

---

## Decisão 9: Frontend — React Router + contexto de auth sobre React Query

**Decisão**: adicionar `react-router-dom`; estado de sessão via React Query
(`useQuery(['auth','me'])` chamando `/api/auth/me`) exposto por `AuthProvider`/
`useAuth()`; `<ProtectedRoute>` redireciona para `/entrar` guardando o destino
(`state.from`) e volta após login (FR-012). Páginas: `/entrar`, `/cadastro`,
`/esqueci-senha`, `/redefinir-senha`, `/minha-conta` (placeholder da área do
inscrito). Interceptor 401 no axios invalida a query de sessão. UI mínima sem
framework visual (o tema Tabler entra nas specs de painel, 003+).

**Rationale**: React Query já é o padrão do projeto para estado de servidor; a
sessão É estado de servidor — evita duplicar em Context/Redux.

---

## Riscos / notas

- **Credenciais Google** (bloqueador externo): fluxo real só testável manualmente
  quando existirem; CI cobre com mock. Redirect URI de dev:
  `http://localhost:8000/api/auth/google/callback`.
- **CORS/cookies**: dev usa proxy do Vite (`/api` → `:8000`), mesmo site — sem CORS.
  Produção (domínio único) idem; se front e API separarem domínios na Fase 2,
  revisar `SANCTUM_STATEFUL_DOMAINS`/`SESSION_DOMAIN`.
- **Verificação não bloqueia login** (Assumption da spec): middleware `verified`
  NÃO é aplicado aqui; specs 004+ decidem onde exigir.
- E-mails (verificação/reset) são enfileiráveis; nesta spec seguem síncronos no dev
  (queue `database` já existe; ativação de worker é operação, não código).
