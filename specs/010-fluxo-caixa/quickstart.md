# Quickstart — 010-fluxo-caixa (guia de validação)

Referências: [spec.md](spec.md), [contrato](contracts/finance-api.md),
[data-model](data-model.md), [research](research.md).

## Pré-requisitos

- Specs 001–009 na `main`; `make up && make fresh` (agora com seeds financeiros:
  categorias, formas de pagamento, alguns lançamentos demo).
- Logins: `admin@dev.local`, `tesouraria@dev.local` / `password`.

## Rodar

```bash
make test   # suíte completa (inclui Finance/* e mantém 001-009 verdes)
make dev    # http://localhost:5173 → menu Financeiro
```

## Validações por user story

### US1 — Lançar e situar
1. Testes: criar a pagar/receber (com e sem evento); valor ≤ 0 → 422; situação
   inicial "em aberto"; vencimento passado → "vencido" derivado; RBAC (só
   admin/financeiro; demais 403).
2. Manual: Financeiro → Contas a Pagar → Nova → despesa com evento; outra sem
   evento (administrativa).

### US2 — Baixa total/parcial
1. Testes: baixa parcial → "recebido/pago parcialmente" com saldo correto;
   quitar → "recebido/pago"; baixa > saldo → 422; editar baixada sem
   justificativa → 422; histórico registra cada baixa.
2. Manual: dar baixa parcial e depois total; conferir saldo e histórico.

### US3 — Evento como centro de resultado
1. Testes: filtrar por evento traz só os dele; saldo previsto/realizado e
   resultado batendo; cancelados fora; ingressos/patrocínios espelhados
   aparecem como receita (sem duplicar).
2. Manual: filtrar o módulo por um evento e conferir o resultado; telas 008/009
   do evento seguem funcionando (não mudaram).

### US4 — Dashboard geral
1. Testes: cards do mês (a receber/recebido/a pagar/pago), vencidos, saldos,
   resultado, melhores/piores eventos; filtros recalculam.
2. Manual: Financeiro → Dashboard; aplicar filtro de período.

### US5 — Cadastros
1. Testes: categoria em uso não exclui (409, inativa); pessoa/forma vinculadas
   aparecem em filtro/relatório.

### US6 — Parcelamento e recorrência
1. Testes: 12.000 em 3 parcelas = 3× 4.000 com vencimentos; pagar uma não mexe
   nas outras; recorrência mensal gera lançamentos pelo comando.

### US7 — Anexos, cancelamento, estorno, histórico
1. Testes: anexar/baixar/remover; cancelar com motivo (fora dos saldos, no
   histórico); estornar recebimento (saldo atualiza).

### US8 — Relatórios
1. Testes: prévia e export (.xlsx/PDF) do mesmo recorte batem; filtro por
   período.

### Integração automática (FR-020) — sem duplicidade
1. Testes: comprar ingresso (pendente) → conta a receber "em aberto" espelhada;
   pagar → "recebido"; cancelar → cancelada; reembolsar → estorno. Cortesia
   NÃO gera receita. Reprocessar não cria segundo lançamento (UNIQUE source).

## Sem regressão

- [ ] `make test` verde — inclusive 001–009 (nada quebrado).
- [ ] Build do frontend ok.

## Encerramento da spec

- [ ] Fluxos manuais conferidos no navegador.
- [ ] Merge de `010-fluxo-caixa` na `main`.
