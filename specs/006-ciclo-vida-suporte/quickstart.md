# Quickstart — 006-ciclo-vida-suporte (guia de validação)

Referências: [spec.md](spec.md), [contrato](contracts/lifecycle-api.md),
[data-model](data-model.md), [research](research.md).

## Pré-requisitos

- Specs 001–005 na `main`; `make up && make fresh`; drivers fake (padrão).
- Nenhuma credencial externa.

## Rodar

```bash
make test   # suíte completa (inclui Lifecycle/Support/Refund)
make dev    # fluxos no navegador
```

## Validações por user story

### US1 — Cancelamento pelo inscrito
1. Comprar e pagar (cartão `4242…` confirma na hora); em Meus Ingressos,
   Cancelar → modal mostra reembolso de 100% (evento do seed está a >7 dias) →
   confirmar → ingresso cancelado, vaga liberada (conferir no painel).
2. Testes: pendente cancela sem caso; pago gera caso com valor da política;
   evento a <7 dias → exige `confirm_no_refund`; compra <7 dias → 100% mesmo
   perto do evento (CDC); flag desabilitada → 409; terminal → 409; trilha.

### US2 — Transferência
1. Em um ingresso confirmado, Transferir → nome+e-mail novos → original some
   das ações, novo aparece; logar com conta do e-mail destino → ingresso lá
   (claim), comprovante ok.
2. Testes: original transferred + vínculos bidirecionais; QR antigo recusável
   (guarda de status); reservado/voucher/usado/re-transferência → 409; evento
   iniciado → 409; vagas neutras.

### US3 — Estorno
1. Tesouraria → Estornos: caso do US1 na fila com valor; executar (cartão →
   provedor fake; pix → justificativa) → caso finalizado, e-mail no Mailpit.
2. Testes: cartão via refundCharge; operacional exige justificativa; total →
   payment refunded / parcial → paid + registro no ticket; repetido → 409;
   **auto-estorno → 403**.

### US4 — Suporte
1. Inscrito abre caso (`/minha-conta/suporte`); admin responde no painel com
   nota pública e outra interna; inscrito vê SÓ a pública; finalizar; inscrito
   reabre com nova mensagem.
2. Testes: escopo (403 caso alheio), visibilidade de notas, transições
   open⇄finished/reopened.

### US5 — Cancelamento do evento
1. Testes: evento com pedidos pago/pendente/cancelado → cancelar evento →
   vivos cancelados com motivo, casos 100% só para pagos, e-mails enviados,
   histórico intacto; falha em um pedido não interrompe os demais.
2. Manual (opcional): cancelar o evento do seed no painel e conferir cascata;
   `make fresh` restaura.

## Encerramento da spec

- [ ] `make test` verde (todas as suítes)
- [ ] Fluxos manuais US1–US4 no navegador
- [ ] Merge de `006-ciclo-vida-suporte` na `main`
