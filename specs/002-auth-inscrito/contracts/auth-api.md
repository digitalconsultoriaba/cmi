# Contrato — API de autenticação (002)

Todas as rotas sob `/api/auth/*`, seguindo o envelope da fundação
(`specs/001-fundacao/contracts/api-conventions.md`). Sessão = cookie Sanctum SPA
(front chama `GET /sanctum/csrf-cookie` antes de mutações).

## Shape do usuário autenticado (`me`)

```json
{
  "data": {
    "id": 1,
    "name": "Ana Silva",
    "email": "ana@exemplo.com",
    "emailVerified": true,
    "document": null,
    "phone": null,
    "avatarUrl": null,
    "hasPassword": true,
    "hasGoogle": false,
    "roles": ["attendee"]
  }
}
```

## Endpoints

| Método/rota | Auth | Regras | Respostas |
|---|---|---|---|
| `POST /auth/register` | pública | name, email (único, normalizado), password ≥ 8 + confirmação; atribui `attendee`; envia verificação; **inicia sessão** | 201 `{data: me}` · 422 validação |
| `POST /auth/login` | pública | rate limit `auth-login` (5/min e-mail+IP); credencial errada → mensagem genérica; conta só-Google → mensagem de orientação | 200 `{data: me}` · 422 (genérica ou orientação Google) · 429 `throttled` |
| `POST /auth/logout` | sessão | invalida sessão + regenera token CSRF | 200 `{data: null}` · 401 |
| `GET /auth/me` | sessão | dados da própria conta (shape acima) | 200 · 401 |
| `POST /auth/email/resend` | sessão | throttle `auth-email` (1/min); inócuo se já verificada | 200 `{data: null}` · 429 |
| `GET /auth/verify-email/{id}/{hash}` | link assinado | middleware `signed`; verifica e **redireciona** ao front `/entrar?verified=1`; revisita inócua | 302 · 403 assinatura inválida |
| `POST /auth/forgot-password` | pública | throttle `auth-forgot` (3/min IP); **resposta idêntica** exista ou não a conta | 200 `{data: null}` · 429 |
| `POST /auth/reset-password` | pública | token do broker (uso único, 60 min, só o mais recente); define senha (inclusive conta só-Google) | 200 `{data: null}` · 422 token inválido/expirado |
| `GET /auth/google/redirect` | pública | devolve URL de autorização | 200 `{data: {url}}` |
| `GET /auth/google/callback` | pública | login por `google_id` → merge por e-mail → cria conta (verificada, sem senha, `attendee`); **redireciona** ao front `/entrar?google=ok` (erro: `?google=erro`) | 302 |

## Regras transversais

- Erros seguem `{ message, type, status, errors? }`; mensagens em pt-BR.
- 429 (`ThrottleRequestsException`) entra no handler global com `type: "throttled"`.
- Nenhuma resposta expõe `password`, `google_id` bruto ou tokens.
- `roles` no `me` alimenta o roteamento por papel no front (painéis nas specs 003+).

## Contrato de frontend

- `<ProtectedRoute>`: sem sessão → redireciona `/entrar` com destino guardado;
  após login, volta ao destino (FR-012).
- Interceptor 401: invalida o cache da sessão e trata como deslogado.
- Páginas: `/entrar` (com botão Google), `/cadastro`, `/esqueci-senha`,
  `/redefinir-senha` (lê token/e-mail da URL), `/minha-conta` (protegida).
