# Tasks: Autenticação do Inscrito (login)

**Input**: Design documents from `/specs/002-auth-inscrito/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md,
contracts/auth-api.md, quickstart.md — e a fundação (spec 001) mergeada.

**Tests**: INCLUÍDOS — exigência da constituição (feature tests bloqueiam merge);
Google coberto com Socialite mockado, e-mails com `Notification::fake()`.

**Organization**: agrupado por user story; nenhuma migration nova (data-model).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizável (arquivos diferentes, sem dependência pendente)
- **[Story]**: US1–US4 (mapeia para spec.md)

## Path Conventions

Laravel na raiz + SPA em `frontend/` (padrão da 001). Controllers de auth em
`app/Http/Controllers/Api/Auth/`; testes em `tests/Feature/Auth/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: dependências e configuração que todos os fluxos usam.

- [X] T001 Instalar laravel/socialite via composer (`docker compose run --rm php
      composer require laravel/socialite`) e adicionar bloco `google`
      (client_id/client_secret/redirect) em `config/services.php`
- [X] T002 [P] Adicionar placeholders `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`,
      `GOOGLE_REDIRECT_URI` em `.env.example` e valores de dev em `.env` (redirect
      `http://localhost:8000/api/auth/google/callback`)
- [X] T003 [P] Instalar react-router-dom no frontend (`npm install
      react-router-dom --prefix frontend`)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: rate limiting, 429 no envelope e casca de rotas — bloqueiam todas as US.

- [X] T004 Definir RateLimiters nomeados em
      `app/Providers/AppServiceProvider.php` (research, Decisão 7): `auth-login`
      (5/min por e-mail normalizado+IP), `auth-email` (1/min por usuário),
      `auth-forgot` (3/min por IP)
- [X] T005 [P] Adicionar render de `ThrottleRequestsException` → 429
      `{ message, type: "throttled", status }` em `bootstrap/app.php`
- [X] T006 [P] Criar `app/Http/Resources/UserResource.php` com o shape `me` do
      contrato (camelCase: emailVerified, hasPassword, hasGoogle, roles[])
- [X] T007 Criar grupo de rotas `/auth` em `routes/api.php` (register, login,
      logout, me, email/resend, verify-email/{id}/{hash}, forgot-password,
      reset-password, google/redirect, google/callback) apontando para os
      controllers das fases seguintes

**Checkpoint**: casca pronta — user stories podem começar.

---

## Phase 3: User Story 1 - Cadastro com e-mail e senha (Priority: P1) 🎯 MVP

**Goal**: cadastro universal com verificação de e-mail; papel `attendee`
automático; sessão iniciada no registro.

**Independent Test**: quickstart.md §US1 — cadastrar, receber e-mail no Mailpit,
verificar, `me` mostra `emailVerified: true`.

### Tests for User Story 1

- [X] T008 [P] [US1] Feature test de cadastro em
      `tests/Feature/Auth/RegisterTest.php`: sucesso (201, envelope `me`, papel
      attendee, sessão ativa, notificação de verificação enviada); e-mail
      duplicado com caixa diferente → 422; senha < 8 → 422; e-mail malformado →
      422; e-mail gravado normalizado
- [X] T009 [P] [US1] Feature test de verificação em
      `tests/Feature/Auth/EmailVerificationTest.php`: link assinado válido
      verifica e redireciona ao front; assinatura adulterada → 403; revisita
      inócua; reenvio autenticado envia notificação; reenvio repetido em <60s →
      429; reenvio com conta já verificada é inócuo

### Implementation for User Story 1

- [X] T010 [P] [US1] Criar `app/Http/Requests/Auth/RegisterRequest.php`: name,
      email (único, normalizado em `prepareForValidation` — trim+minúsculas),
      password ≥ 8 confirmado; mensagens pt-BR
- [X] T011 [US1] Implementar `MustVerifyEmail` em `app/Models/User.php` +
      accessor de normalização de e-mail; criar notificações pt-BR
      `app/Notifications/VerifyEmailPtBr.php` (URL assinada para
      `/api/auth/verify-email/{id}/{hash}`) e registrar
- [X] T012 [US1] Criar `app/Http/Controllers/Api/Auth/RegisterController.php`:
      `DB::transaction` (cria user + papel attendee), dispara verificação, inicia
      sessão, retorna 201 `{data: me}` via UserResource
- [X] T013 [US1] Criar
      `app/Http/Controllers/Api/Auth/EmailVerificationController.php`: `verify`
      (middleware `signed` + throttle, marca verificado, redireciona
      `FRONTEND_URL/entrar?verified=1`, revisita inócua) e `resend` (auth +
      throttle `auth-email`)

**Checkpoint**: cadastro funcional de ponta a ponta (API) — T008/T009 verdes.

---

## Phase 4: User Story 2 - Entrar, sair e manter a sessão (Priority: P2)

**Goal**: login/logout por sessão, `me`, proteção contra força bruta, orientação
para conta só-Google.

**Independent Test**: quickstart.md §US2 — login com usuário do seed, `me`,
logout; 6 erros seguidos → 429.

### Tests for User Story 2

- [X] T014 [P] [US2] Feature test de login em
      `tests/Feature/Auth/LoginTest.php`: sucesso (200 `{data: me}`, sessão);
      senha errada → 422 mensagem genérica idêntica para e-mail inexistente;
      conta só-Google → 422 com orientação; 6ª tentativa → 429; sucesso zera o
      limitador; e-mail com maiúsculas loga normalmente
- [X] T015 [P] [US2] Feature test de sessão em `tests/Feature/Auth/MeTest.php`:
      `me` autenticado → shape completo do contrato (roles, hasPassword,
      hasGoogle); sem sessão → 401 na shape padrão; logout → 200 e `me`
      subsequente → 401

### Implementation for User Story 2

- [X] T016 [P] [US2] Criar `app/Http/Requests/Auth/LoginRequest.php` (e-mail
      normalizado, mensagens pt-BR)
- [X] T017 [US2] Criar `app/Http/Controllers/Api/Auth/LoginController.php`:
      `login` (RateLimiter `auth-login` manual: hit/clear, `Auth::attempt`,
      regenera sessão, orientação só-Google — research Decisão 6) e `logout`
      (invalida sessão + regenera CSRF)
- [X] T018 [US2] Criar `app/Http/Controllers/Api/Auth/MeController.php`:
      retorna UserResource do usuário autenticado (rota com `auth:sanctum`)

**Checkpoint**: sessão completa — T014/T015 verdes.

---

## Phase 5: User Story 3 - Entrar com Google (Priority: P3)

**Goal**: as 3 vias do callback (login por google_id, merge por e-mail, criação
verificada sem senha) + erro amigável.

**Independent Test**: quickstart.md §US3 — testes mockados cobrem as 3 vias e o
erro; manual só com credenciais reais (bloqueador externo).

### Tests for User Story 3

- [X] T019 [P] [US3] Feature test em `tests/Feature/Auth/GoogleTest.php`
      (Socialite mockado): redirect retorna `{data:{url}}`; callback com e-mail
      novo cria conta (sem senha, verificada, papel attendee, avatar) e loga;
      callback com e-mail já cadastrado vincula google_id sem duplicar e sem
      tocar senha/papéis; callback com google_id já vinculado loga direto
      (mesmo com e-mail mudado no Google); exceção do provedor → redirect
      `?google=erro` sem sessão

### Implementation for User Story 3

- [X] T020 [US3] Criar `app/Http/Controllers/Api/Auth/GoogleController.php`:
      `redirect` (Socialite driver google, retorna URL no envelope) e `callback`
      (3 vias em `DB::transaction`, e-mail normalizado no merge, redireciona
      `FRONTEND_URL/entrar?google=ok|erro`)

**Checkpoint**: Google funcional com mock — T019 verde.

---

## Phase 6: User Story 4 - Redefinir a senha (Priority: P4)

**Goal**: forgot/reset via broker nativo; resposta idêntica exista ou não a
conta; conta só-Google ganha senha.

**Independent Test**: quickstart.md §US4 — fluxo completo pelo Mailpit; link
reusado recusado.

### Tests for User Story 4

- [X] T021 [P] [US4] Feature test em
      `tests/Feature/Auth/PasswordResetTest.php`: forgot com e-mail existente e
      inexistente → mesma resposta 200 (notificação só no existente); reset com
      token válido troca a senha e permite login; token reusado/expirado → 422;
      emissão nova invalida a anterior; conta só-Google completa reset e passa a
      logar com senha (hasPassword true); throttle `auth-forgot` → 429

### Implementation for User Story 4

- [X] T022 [P] [US4] Criar `app/Http/Requests/Auth/ForgotPasswordRequest.php` e
      `ResetPasswordRequest.php` (e-mail normalizado, password ≥ 8 confirmado)
- [X] T023 [US4] Criar notificação pt-BR
      `app/Notifications/ResetPasswordPtBr.php` (link
      `FRONTEND_URL/redefinir-senha?token=…&email=…`) e registrar no User
- [X] T024 [US4] Criar
      `app/Http/Controllers/Api/Auth/PasswordResetController.php`: `forgot`
      (Password broker, resposta única, throttle) e `reset` (broker, evento
      PasswordReset, 200)

**Checkpoint**: todos os fluxos de API completos — suíte Auth verde.

---

## Phase 7: Frontend (todas as US)

**Purpose**: telas e proteção de rotas — consome os endpoints acima (FR-012,
FR-015).

- [X] T025 Atualizar `frontend/src/lib/api.js`: helper `csrf()`
      (`GET /sanctum/csrf-cookie` antes de mutações), `apiPut/apiDelete` se
      necessário e interceptor de resposta 401 que invalida a query `['auth','me']`
- [X] T026 Criar `frontend/src/auth/AuthProvider.jsx` (useQuery `['auth','me']` →
      user/isLoading), hook `useAuth()` com `login/logout/register` (mutations que
      invalidam a query) e `frontend/src/auth/ProtectedRoute.jsx` (sem sessão →
      `/entrar` guardando destino em `state.from`; volta após login)
- [X] T027 Configurar rotas em `frontend/src/App.jsx` com react-router-dom:
      públicas (`/`, `/entrar`, `/cadastro`, `/esqueci-senha`,
      `/redefinir-senha`) e protegida (`/minha-conta`); `RouterProvider` em
      `frontend/src/main.jsx` sob o AuthProvider
- [X] T028 [P] Criar `frontend/src/pages/Entrar.jsx`: formulário e-mail/senha com
      erros 422 campo a campo, aviso de bloqueio 429, botão "Entrar com Google"
      (usa `/api/auth/google/redirect`), banners `?verified=1` / `?google=ok|erro`,
      link para cadastro e esqueci-senha
- [X] T029 [P] Criar `frontend/src/pages/Cadastro.jsx`: nome/e-mail/senha/
      confirmação, validação 422, pós-registro → `/minha-conta` com aviso
      "verifique seu e-mail" + botão de reenvio (throttle-aware)
- [X] T030 [P] Criar `frontend/src/pages/EsqueciSenha.jsx` (resposta única) e
      `frontend/src/pages/RedefinirSenha.jsx` (lê token/e-mail da URL, submete
      reset, sucesso → `/entrar`)
- [X] T031 [P] Criar `frontend/src/pages/MinhaConta.jsx` (protegida): dados do
      `me` (nome, e-mail, verificação, papéis, métodos de entrada) + botão sair
- [X] T032 Validar build (`npm run build --prefix frontend`) e fluxos manuais do
      quickstart §US1–US4 com `make dev`

**Checkpoint**: front completo — login/cadastro/reset navegáveis.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T033 Executar `specs/002-auth-inscrito/quickstart.md` de ponta a ponta
      (make fresh + suíte + fluxos manuais) e corrigir o que falhar
- [X] T034 [P] Varredura de conformidade: nenhum segredo real versionado
      (`GOOGLE_*` placeholder), mensagens pt-BR, suíte Foundation continua verde
- [X] T035 Atualizar `ROADMAP.md` (002 ✅) e `specs/002-auth-inscrito/spec.md`
      (Status: Draft → Implemented)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** → **Phase 2 (Foundational)** → US1…US4
- **US1 (Phase 3)**: primeira — cria notificação de verificação e padrão de
  FormRequest que as demais seguem
- **US2 (Phase 4)**: independente da US1 em runtime, mas usa UserResource (T006)
- **US3 (Phase 5)** e **US4 (Phase 6)**: podem correr em paralelo após a US2
- **Phase 7 (Frontend)**: depende das US1–US4 (consome os endpoints)
- **Phase 8 (Polish)**: por último

### Key task-level dependencies

- T007 (rotas) referencia controllers de T012/T013/T017/T018/T020/T024 — criar a
  casca primeiro e preencher por fase
- T011 (MustVerifyEmail + notificação) antes de T012/T013
- T026 (AuthProvider) antes de T027–T031
- Testes de cada US antes da implementação correspondente (devem falhar primeiro)

### Parallel Opportunities

- Setup: T002 ∥ T003 (após T001)
- Foundational: T005 ∥ T006 (T004 primeiro — T005 depende do handler)
- US1: T008 ∥ T009; T010 em paralelo com testes
- US2: T014 ∥ T015; T016 ∥ testes
- US3 ∥ US4 inteiras (após US2)
- Frontend: T028–T031 em paralelo (após T026/T027)

## Parallel Example: User Story 1

```bash
# Testes primeiro, em paralelo:
Task: "T008 RegisterTest em tests/Feature/Auth/RegisterTest.php"
Task: "T009 EmailVerificationTest em tests/Feature/Auth/EmailVerificationTest.php"

# Depois implementação (T010 ∥, T011 → T012 → T013)
```

## Implementation Strategy

**MVP first**: Fases 1–3 (US1) entregam cadastro verificável — já demonstrável via
API + Mailpit. US2 destrava a navegação logada; US3/US4 em paralelo; front por
último consumindo tudo. Parar em cada checkpoint; merge na `main` só com a suíte
inteira verde (Foundation + Auth) e checklist completo.
