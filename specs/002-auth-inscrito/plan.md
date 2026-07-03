# Implementation Plan: Autenticação do Inscrito (login)

**Branch**: `002-auth-inscrito` | **Date**: 2026-07-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/002-auth-inscrito/spec.md`

## Summary

Autenticação completa do inscrito sobre a fundação da 001: cadastro e-mail/senha
com verificação (link assinado → redirect ao front), login/logout por sessão
Sanctum SPA com rate limiting nomeado, `GET /auth/me` no envelope padrão, login
Google via Socialite (login por `google_id` → merge por e-mail normalizado → criação
verificada sem senha), reset de senha via broker nativo (inclusive para contas
só-Google), e frontend com React Router + `AuthProvider` sobre React Query + rotas
protegidas com retorno ao destino. **Nenhuma tabela nova** — tudo usa o schema da
fundação. Controllers próprios, sem Fortify (research, Decisão 1).

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12); JavaScript ES2022 (React 18, Node 20)

**Primary Dependencies**: laravel/sanctum (já instalado), **laravel/socialite**
(novo), **react-router-dom** (novo); demais já presentes (React Query, axios)

**Storage**: MySQL 8 — tabelas existentes (`users`, `role_user`, `sessions`,
`password_reset_tokens`, `cache`); nenhuma migration nova

**Testing**: PHPUnit Feature em `app_test` (padrão da 001); Socialite mockado;
`Notification::fake()` para e-mails

**Target Platform**: idem 001 (Docker dev; PHP via container)

**Project Type**: web application — API + SPA existentes

**Performance Goals**: login < 5s ponta a ponta (SC-002); suíte da spec < 1 min

**Constraints**: envelope `{ data }`/shape de erros da fundação; sessão por cookie
(nunca token no JS); mensagens pt-BR; credenciais Google só em `.env` (placeholders
no exemplo); e-mail normalizado em toda borda

**Scale/Scope**: 10 endpoints, ~5 páginas de front, 0 migrations, 4 user stories

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação |
|---|---|
| I. Standalone, RBAC 4 papéis | ✅ `attendee` automático no cadastro/Google (contrato da 001); nenhum conceito externo. |
| II. Estado derivado | ✅ N/A a esta spec (sem catálogo); nenhum estado editável novo — verificação é timestamp de evento real. |
| III. Ponto único de baixa | ✅ N/A — sem pagamento. |
| IV. Segurança | ✅ Senha só com hash; segredos Google em `.env` (placeholder no exemplo); nenhum token exposto ao JS; links assinados; rate limiting (FR-006/FR-014). |
| V. Histórico | ✅ Nenhum delete; vínculo Google só adiciona; auditoria da fundação intacta. |
| VI. Specs por área | ✅ Consome contratos da 001 sem redefini-los; políticas de verificação obrigatória delegadas às specs 004+ (registrado). |
| Stack e convenções | ✅ Sanctum SPA + Socialite Google = exatamente o que a constituição fixa. |

**Resultado**: PASS (pré-Phase 0 e pós-Phase 1). Sem entradas em Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/002-auth-inscrito/
├── plan.md              # Este arquivo
├── research.md          # 9 decisões
├── data-model.md        # 0 tabelas novas — regras de uso + transições
├── quickstart.md        # validação por user story
├── contracts/auth-api.md# 10 endpoints + contrato de frontend
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks (próximo passo)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/Api/Auth/
│   │   ├── RegisterController.php        # POST /auth/register
│   │   ├── LoginController.php           # POST /auth/login + logout
│   │   ├── MeController.php              # GET /auth/me
│   │   ├── EmailVerificationController.php # verify (signed) + resend
│   │   ├── PasswordResetController.php   # forgot + reset
│   │   └── GoogleController.php          # redirect + callback
│   ├── Requests/Auth/                    # Register/Login/Forgot/Reset (normalização de e-mail)
│   └── Resources/UserResource.php        # shape `me` (camelCase)
├── Notifications/                        # VerifyEmailPtBr, ResetPasswordPtBr
├── Providers/AppServiceProvider.php      # RateLimiters nomeados (auth-login/email/forgot)
config/services.php                       # bloco google (Socialite)
routes/api.php                            # grupo /auth
bootstrap/app.php                         # render de ThrottleRequestsException (429)
.env.example                              # GOOGLE_CLIENT_ID/SECRET/REDIRECT_URI (placeholders)
tests/Feature/Auth/                       # Register/Verification/Login/Me/Google/PasswordReset
frontend/src/
├── auth/                                 # AuthProvider, useAuth, ProtectedRoute
├── pages/                                # Entrar, Cadastro, EsqueciSenha, RedefinirSenha, MinhaConta
├── lib/api.js                            # + csrf() e interceptor 401
└── main.jsx / App.jsx                    # RouterProvider + rotas
```

**Structure Decision**: controllers de auth agrupados em `Api/Auth` (um por fluxo,
finos); front ganha `auth/` e `pages/` — primeiro uso real do scaffold da 001.

## Fases de implementação (visão para /speckit-tasks)

1. **Setup**: composer require socialite; npm react-router-dom; config services +
   env placeholders; rate limiters nomeados + render 429; rota de grupo `/auth`.
2. **US1 — cadastro/verificação**: FormRequests (normalização), RegisterController
   (transação: user + papel + sessão + notificação), notificações pt-BR,
   EmailVerificationController (signed + resend), UserResource; testes.
3. **US2 — sessão**: LoginController (attempt + throttle + orientação só-Google),
   logout, MeController; testes (genérico/429/me/logout).
4. **US3 — Google**: GoogleController (redirect/callback com as 3 vias), testes
   mockados (cria/vincula/erro).
5. **US4 — reset**: PasswordResetController (forgot/reset via broker), notificação
   pt-BR, testes (fluxo, expirado, só-Google ganha senha, resposta idêntica).
6. **Frontend**: router + AuthProvider/useAuth/ProtectedRoute + 5 páginas +
   interceptor 401 + csrf-cookie; build verde.
7. **Polish**: quickstart completo, varredura de segredos, ROADMAP/status.

## Complexity Tracking

Sem violações constitucionais a justificar.
