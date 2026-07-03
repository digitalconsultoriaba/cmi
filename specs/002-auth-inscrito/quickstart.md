# Quickstart — 002-auth-inscrito (guia de validação)

Referências: [spec.md](spec.md), [contrato da API](contracts/auth-api.md),
[research](research.md).

## Pré-requisitos

- Fundação no ar: `make up && make fresh` (spec 001).
- Mailpit em http://localhost:8025 (e-mails de verificação/reset no dev).
- **Google real é opcional**: testes usam mock; para E2E manual, preencher
  `GOOGLE_CLIENT_ID/SECRET` no `.env` (redirect
  `http://localhost:8000/api/auth/google/callback`).

## Rodar

```bash
make test                 # suíte completa (Foundation + Auth)
make dev                  # API :8000 + front :5173
```

## Validações por user story

### US1 — Cadastro + verificação
1. Em `/cadastro`, criar conta nova → volta logado (`/minha-conta`).
2. No Mailpit, abrir o e-mail de verificação → clicar → redireciona com
   confirmação; `GET /api/auth/me` mostra `emailVerified: true`.
3. Repetir cadastro com o mesmo e-mail (variando maiúsculas) → 422.
4. Testes: registro, papel `attendee`, link assinado válido/adulterado, reenvio
   com throttle.

### US2 — Sessão + rotas protegidas
1. Deslogado, acessar `/minha-conta` → redireciona a `/entrar`; após login, volta
   a `/minha-conta`.
2. Login com senha errada 6× → 429 (bloqueio temporário).
3. Logout → `/minha-conta` volta a exigir login.
4. Testes: login ok/erro genérico/throttle, `me` (shape do contrato), logout.

### US3 — Google (mockado)
1. Testes cobrem: conta nova (verificada, sem senha, `attendee`), merge por
   e-mail existente (não duplica), erro no provedor (sem sessão).
2. Manual (com credenciais reais): botão "Entrar com Google" em `/entrar` →
   consentimento → volta logado.

### US4 — Reset de senha
1. `/esqueci-senha` com e-mail cadastrado E não cadastrado → mesma resposta.
2. Link do Mailpit → `/redefinir-senha` → nova senha → login com ela.
3. Reusar o link → recusado.
4. Testes: fluxo completo, token expirado/reusado, conta só-Google ganhando senha.

## Encerramento da spec

- [ ] `make test` verde (Foundation continua verde + Auth novos)
- [ ] Varredura: nenhum segredo real versionado (`GOOGLE_*` só placeholder)
- [ ] Fluxos manuais do front validados (`make dev`)
- [ ] Merge de `002-auth-inscrito` na `main`
