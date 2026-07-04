# Quickstart — 005-pagamento (guia de validação)

Referências: [spec.md](spec.md), [contratos](contracts/), [data-model](data-model.md),
[research](research.md).

## Pré-requisitos

- Specs 001–004 na `main`; `make up && make fresh`.
- **Drivers fake por padrão** — nenhuma credencial externa necessária para tudo
  abaixo. (Sandbox Sicoob real: seção final, opcional.)

## Rodar

```bash
make test   # suíte completa (inclui Payment/*)
make dev    # comprar em /evento/... e pagar em /pedido/{code}/pagar
```

## Validações por user story

### US1 — Pix
1. Comprar ingresso → "Meus pedidos" → botão Pagar → aba Pix: QR + copia-e-cola.
2. Simular o pagamento:
   `docker compose run --rm php php artisan tinker --execute="..."` (settle no
   fake) + POST do webhook simulado — a tela atualiza sozinha (polling) e o
   e-mail aparece no Mailpit.
3. Testes: cobrança criada com TTL, webhook confirma, tela/status, e-mail.

### US2 — Baixa única e confiável
1. Testes: webhook duplicado → 1 baixa (`ignored` na 2ª); assinatura errada →
   401 registrado; reconsulta obrigatória (webhook "paid" com provedor
   "pending" → NÃO baixa); webhook perdido → `payments:reconcile` baixa;
   pagamento de pedido expirado → registrado sem reativar; valor divergente →
   `partially_paid`.
2. Conferir trilha: `webhook_events` com payload bruto e resultado;
   `payments.raw_response` preenchido.

### US3 — Boleto híbrido
1. Aba Boleto: linha digitável + QR pix da mesma cobrança; e-mail "boleto
   emitido" no Mailpit.
2. Testes: settle via reconciliação (compensação sem webhook) confirma.

### US4 — Cartão
1. Aba Cartão: `4242 4242 4242 4242` → aprovado na hora (pedido pago na mesma
   tela); `4000 0000 0000 0002` → recusa com mensagem e nova tentativa.
2. Testes: aprovado confirma via RegisterPayment; recusado deixa pedido
   pendente; **varredura anti-PAN** (nenhum payload/log/banco contém número).

### US5 — Tesouraria
1. Logar `tesouraria@dev.local` → painel → Tesouraria: recebimentos com filtros
   e pendências destacadas.
2. "Conciliar agora" → resumo (verificados/baixados).
3. Baixa manual com justificativa → pedido confirma com trilha; tentar baixar
   pedido próprio → 403; já pago → 409.

## Sandbox Sicoob real (opcional — exige credenciais)

1. `.env`: `PAYMENTS_PIX_DRIVER=sicoob`, `SICOOB_SANDBOX=true`,
   `SICOOB_CLIENT_ID`, `SICOOB_CERT_PATH`/`SICOOB_CERT_KEY_PATH` (fora do repo).
2. Criar cobrança Pix real de um pedido e pagar no app do banco (sandbox);
   conferir webhook (exige URL pública HTTPS) OU rodar `payments:reconcile`.
3. Nada disso bloqueia o merge — registro de pendência externa.

## Encerramento da spec

- [ ] `make test` verde (todas as suítes)
- [ ] Fluxos manuais Pix/boleto/cartão validados no navegador (fake)
- [ ] Varredura anti-PAN e de segredos limpa
- [ ] Merge de `005-pagamento` na `main`
