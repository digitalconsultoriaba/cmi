# Quickstart — 008-painel-relatorios (guia de validação)

Referências: [spec.md](spec.md), [contrato](contracts/reports-api.md),
[data-model](data-model.md), [research](research.md).

## Pré-requisitos

- Specs 001–007 na `main`; `make up && make fresh` (seeds de demo: vendas,
  cortesias, check-ins do `SampleCheckinSeeder`).
- Logins: `admin@dev.local`, `tesouraria@dev.local` / `password`.

## Rodar

```bash
make test   # suíte completa (inclui Reports/*)
make dev    # http://localhost:5173/painel
```

## Validações por user story

### US1 — Dashboard
1. Testes: pessoas × capacidade (casal = 2); receita confirmada × prevista
   (baixa manual com desconto conta o recebido); grade de camisas fecha com o
   total de pessoas (incl. "não informado"); por lote/forma; estorno reflete
   imediatamente; attendee/gate → 403.
2. Manual: logar como admin → home do painel é o Dashboard; conferir números
   contra a aba Presenças (007) e a Tesouraria (005).

### US2 — Financeiro
1. Testes: soma por forma bate com o total; filtro mês/ano e intervalo (fuso
   do evento — pagamento às 23h BRT do dia 30 entra no mês certo); estorno no
   período aparece em confirmados E estornos; patrocínio em atraso destacado;
   gate → 403, treasury → 200.
2. Manual: Financeiro → filtrar o mês corrente → conferir com pagamentos da
   demo; limpar filtro → totais crescem.

### US3 — Exports .xlsx
1. Testes: resposta 200 com content-type de planilha e attachment; RBAC de
   cada rota.
2. Manual: baixar os 3 relatórios e abrir no LibreOffice/Excel/Numbers —
   inscritos com acompanhante em linha própria (com a camisa dele); financeiro
   filtrado = linhas da tela; acentuação correta.

### US4 — Auditoria
1. Testes: baixa manual, estorno, cancelamento, transferência, cortesia,
   check-in e edição do evento geram exatamente 1 registro cada, com autor;
   expiração automática → causer sistema; filtros action/período; paginação;
   treasury/gate → 403; nenhuma rota de escrita existe.
2. Manual: executar uma baixa manual na Tesouraria e um check-in → abrir
   Auditoria → os dois aparecem no topo com autor e descrição pt-BR.

## Encerramento da spec

- [ ] `make test` verde (todas as suítes)
- [ ] Planilhas abertas e conferidas manualmente
- [ ] Merge de `008-painel-relatorios` na `main`
