# Contrato — Convenções de API (fundação)

Vale para **todas** as rotas de todas as specs. Implementado nesta spec via handler
de exceções + helpers de resposta; verificado por teste de contrato.

## Envelope de sucesso

```json
{ "data": { "id": 1, "participantName": "Ana", "unitPrice": "150.00" } }
```

- Sempre `{ data: ... }` (objeto ou array). Chaves **camelCase**.
- Dinheiro serializado como string decimal com 2 casas. Datas em ISO-8601 UTC.
- Coleções paginadas: `{ data: [...], meta: { currentPage, perPage, total } }`.

## Envelope de erro

```json
{ "message": "Você não tem permissão para esta ação.", "type": "forbidden", "status": 403 }
```

| HTTP | `type` | Quando |
|---|---|---|
| 401 | `unauthenticated` | sem sessão válida |
| 403 | `forbidden` | sem papel exigido / fora de escopo / policy nega |
| 404 | `not_found` | recurso inexistente ou soft-deleted em consulta padrão |
| 409 | `domain_rule` | regra de negócio violada (ex.: transição de status terminal) — lançada como `DomainRuleViolation` |
| 422 | `validation` | FormRequest; inclui `errors: { campo: [msgs] }` |

- `message` sempre em pt-BR, sem vazar detalhes internos.
- Exceções de domínio carregam `type` próprio quando útil (ex.: `sold_out` — specs 004+).

## Rota de verificação (esta spec)

`GET /api/health` (pública) → `200 { "data": { "status": "ok" } }` — prova o
envelope e serve de smoke test do ambiente.
