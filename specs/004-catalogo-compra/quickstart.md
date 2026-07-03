# Quickstart — 004-catalogo-compra (guia de validação)

Referências: [spec.md](spec.md), [contrato](contracts/public-api.md),
[data-model](data-model.md), [research](research.md).

## Pré-requisitos

- Specs 001–003 na `main`; `make up && make fresh` (evento demo publicado).
- Contas do seed: `admin@dev.local` (painel) e cadastro novo para comprar.

## Rodar

```bash
make test   # suíte completa (inclui Purchase/Expire/Public)
make dev    # http://localhost:5173/evento/seminario-internacional-2026
```

## Validações por user story

### US1 — Landing + catálogo público
1. Sem login, abrir `/evento/seminario-internacional-2026`: blocos na ordem do
   painel, tipos com preço do 1º lote (R$ 200,00 no seed).
2. No painel, desativar um bloco → some da página pública.
3. Cancelar o evento (painel) → página informa cancelamento sem catálogo;
   `migrate:fresh` para restaurar.

### US2 — Compra em grupo
1. Montar carrinho (1 individual + 1 casal), deslogado → finalizar pede login e
   volta com o carrinho.
2. Preencher participantes (camisas; acompanhante do casal) → pedido criado
   "aguardando pagamento" com prazo visível; vagas/estoque decrementados no
   painel.
3. Testes: última vaga (2º pedido → 409), casal com 1 vaga → 409, lote/estoque
   esgotando, snapshot de preço, `requires_shirt`.
4. **Smoke de concorrência real** (SC-002): com capacidade restante = 1,
   disparar 5 compras paralelas e conferir exatamente 1 sucesso:
   `seq 5 | xargs -P5 -I{} curl -s -o /tmp/r{}.json -w "%{http_code}\n" …` →
   um 201, quatro 409, contagem final = capacidade.

### US3 — Cortesias
1. Regra 10→1 ativa (seed): pedido com 10 pagáveis → 11º ingresso cortesia no
   pedido.
2. Distribuir voucher no painel → resgatar no checkout → ingresso confirmado,
   voucher "resgatado"; segundo resgate → recusa.

### US4 — Minha área + comprovante
1. `/minha-conta/pedidos`: pedido com situação/prazo; `/minha-conta/ingressos`
   lista os meus (inclusive cortesia emitida por outra conta para meu e-mail).
2. Baixar comprovante da cortesia (confirmada): PDF com QR contendo `TCK-…`.
3. Comprovante de ingresso aguardando pagamento → orientação (409); pedido de
   outra conta pela URL → 403.

### US5 — Expiração
1. Testes: relógio avançado além do TTL → `orders:expire` marca pedido expirado,
   tickets cancelados ("Reserva expirada"), vagas liberadas; dentro do prazo →
   intacto.
2. Manual (opcional): TTL de 1 min no painel, comprar, aguardar e rodar
   `docker compose run --rm php php artisan orders:expire`.

## Encerramento da spec

- [ ] `make test` verde (todas as suítes)
- [ ] Smoke de concorrência com zero overselling
- [ ] Fluxos manuais validados (`make dev`)
- [ ] Merge de `004-catalogo-compra` na `main`
