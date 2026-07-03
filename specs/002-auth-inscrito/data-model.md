# Data Model — 002-auth-inscrito

**Nenhuma tabela nova.** A fundação (spec 001) já criou tudo que esta spec
movimenta; aqui ficam as regras de uso e transições de estado.

## Tabelas reutilizadas

| Tabela | Uso nesta spec |
|---|---|
| `users` | cadastro/login; `password` nullable (conta só-Google), `google_id` unique nullable (vínculo Socialite), `email_verified_at`, `avatar_url` |
| `roles` / `role_user` | atribuição automática de `attendee` no cadastro e na criação via Google |
| `sessions` | sessão SPA por cookie (driver database, skeleton da 001) |
| `password_reset_tokens` | broker de redefinição (skeleton da 001) |
| `cache` | contadores de rate limiting |

## Regras de escrita

- **E-mail normalizado** (trim + minúsculas) em toda gravação e busca — cadastro,
  login, forgot/reset e merge do Google (research, Decisão 8).
- **Cadastro (e-mail/senha)**: `password` obrigatório (hash), `email_verified_at`
  null até verificar; papel `attendee` via `role_user` na mesma transação.
- **Criação via Google**: `password` null, `google_id` preenchido,
  `email_verified_at = now()`, `name`/`avatar_url` do perfil Google, papel
  `attendee`.
- **Vínculo via Google** (e-mail já cadastrado): preenche apenas `google_id` (+
  `avatar_url` se vazio); não altera senha nem papéis existentes.
- **Reset de senha**: grava hash novo; para conta só-Google, passa a coexistir com
  o vínculo (os dois métodos de entrada valem).

## Estados e transições

```
Verificação:  não verificada ──(link assinado válido)──► verificada
              (revisita do link: inócua; assinatura inválida: recusada 403)

Método de entrada:
  só senha ──(callback Google, mesmo e-mail)──► senha + Google
  só Google ──(reset de senha concluído)──────► Google + senha
  (nenhuma transição remove um método nesta spec)

Token de reset: emitido ─► usado (única vez) | expirado (60 min) |
                invalidado (por emissão mais recente)
```

## Invariantes (verificáveis em teste)

1. Nunca existem duas contas com o mesmo e-mail normalizado (unique + normalização).
2. `google_id` é único; o vínculo nunca migra entre contas.
3. Toda conta criada por esta spec tem ao menos o papel `attendee`.
4. Conta criada via Google nasce verificada e sem senha.
5. Auditoria da fundação intocada: nada desta spec apaga usuários (sem delete).
