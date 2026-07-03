# Quickstart — 001-fundacao (guia de validação)

Prova que a fundação funciona de ponta a ponta. Referências: [spec.md](spec.md),
[data-model.md](data-model.md), [contratos](contracts/).

## Pré-requisitos

- Docker (Compose v2) e Node 20+. **PHP/Composer não são necessários no host** —
  rodam via Docker (research, Decisão 3 emendada).
- Nenhuma credencial externa é necessária nesta spec (Google/Sicoob/gateway só nas
  specs 002/005).

## Subir do zero (US1)

```bash
make up        # MySQL 8 (app + app_test), Redis, Mailpit via Docker
make install   # composer install, npm --prefix frontend, .env, key:generate
make fresh     # migrate:fresh --seed (estrutura + lookups + roles + dados demo)
make test      # suíte Feature no banco app_test
make dev       # API :8000 + Vite :5173 (opcional)
```

**Esperado**: os 4 comandos iniciais concluem sem erro nem passo manual (SC-001/002);
`make test` termina verde (SC-003).

## Validações por user story

### US1 — Ambiente reproduzível
- `make fresh` executado 2× seguidas: sem erro nem duplicação.
- `git grep` por credenciais reais: nada; `.env.example` só placeholders (SC-006).
- `curl http://localhost:8000/api/health` → `{ "data": { "status": "ok" } }`
  (contrato de envelope).

### US2 — Domínio sem acoplamentos, com histórico
- Varredura (SC-004):
  `git grep -iE "owner_type|owner_lodge|EventAccessGuard|require\.module|Member|Lodge|seat_limit_per_lodge" -- app/ database/ routes/ frontend/src` → 0 ocorrências.
- Teste Feature: cria evento completo (tipos, lotes, camisas, blocos) e verifica
  relacionamentos; deleta registro → `deleted_at` preenchido, registro recuperável,
  `created_by`/`updated_by` corretos.

### US3 — Estado derivado
- Testes dos 7 cenários de `contracts/domain-derivations.md` (janela, lote vigente,
  virada por quantidade/data, preço efetivo, camisa esgotada, transição terminal
  → `DomainRuleViolation`/409).
- Verificação estrutural: nenhuma coluna `sales_open`/`sold_out`/`available` nas
  migrations (grep nas migrations).

### US4 — RBAC + dados de demonstração
- Testes do contrato `contracts/rbac.md`: 401 anônimo, 403 sem papel, 200 com papel,
  acúmulo de papéis.
- Após seed: 4 roles; lookups completos (4 tabelas de status + event_types);
  admin de dev; evento de exemplo publicado com 3 tipos, 2 lotes, camisas com e sem
  estoque e blocos de landing de todos os tipos.

## Encerramento da spec

- [ ] `make test` verde no CI/local
- [ ] Checklist `checklists/requirements.md` completo
- [ ] Varredura SC-004 = 0 ocorrências
- [ ] Merge de `001-fundacao` na `main`
