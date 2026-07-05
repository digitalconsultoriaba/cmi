# Quickstart — Aba Orçamento (spec 011)

Guia de validação end-to-end. Referências de detalhe: [data-model.md](./data-model.md) e [contracts/budget-api.md](./contracts/budget-api.md).

## Pré-requisitos

- Ambiente dev no ar: `make up` (MySQL/Redis/Mailpit), API em `docker compose --profile dev up -d api` (:8000) e Vite em `npm run dev` (:5173).
- Migrations aplicadas: `docker compose run --rm php php artisan migrate`.
- Logado como usuário `admin` (ex.: `admin@dev.local`). Evento de exemplo `seminario-internacional-2026` disponível.

## Fluxo manual (UI)

1. Abrir `http://localhost:5173/painel/eventos/1/orcamento`.
2. **Resumo** aparece zerado; cadastrar em **Itens de custo** (modal) "Sonorização", categoria "Som e iluminação", quantidade 1, valor R$ 26.000,00 → o card **Custo total previsto** passa a R$ 26.000,00.
3. Adicionar um segundo item com status **Cancelado** → confirmar que ele **não** entra no custo total.
4. Em **Ingressos previstos**, cadastrar 3 lotes (200×250, 200×300, 200×350) → **Receita prevista com ingressos** = R$ 180.000,00.
5. Em **Patrocínios**, cadastrar "Master" R$ 100.000,00 status Confirmado → **Patrocínio confirmado** = R$ 100.000,00; um "Bronze" status Perdido → não soma no confirmado.
6. Em **Participantes**, informar 500 pagantes / 50 cortesias / 20 equipe / 5 palestrantes → conferir **custo por participante**, **ticket médio** e **ponto de equilíbrio** (frase "vender ~N ingressos com ticket médio de R$ X").
7. No item "Sonorização", clicar **Gerar conta a pagar** → verificar na aba **Financeiro** do evento que surgiu uma conta a pagar de R$ 26.000,00 vinculada; a linha do orçamento fica marcada "conta a pagar gerada". Clicar de novo → aviso de duplicidade (409), nenhuma conta nova.
8. No patrocínio "Master", clicar **Gerar conta a receber** → conta a receber criada no Financeiro; duplicidade bloqueada.
9. Abrir **Comparativo** → conferir orçado × realizado e o % de atingimento da meta de ingressos (usa vendas reais).
10. **Simuladores**: preencher preço mínimo (custo 250k, patrocínio 100k, 500 pagantes → R$ 300,00); aplicar margem 10% (custo com margem R$ 275.000,00 sem alterar o custo base); comparar os 3 cenários.
11. **Exportar** em Excel e PDF e conferir as seções.

## Verificações automatizadas (Feature tests, MySQL `app_test`)

Rodar: `docker compose run --rm php php artisan test --filter=Budget`

- **BudgetSummaryTest**
  - item com quantidade×unitário deriva `totalAmount`; item só com total é aceito.
  - item `cancelled` fora do custo total; cortesia nunca vira receita.
  - resultado = receita total − custo; investimento próprio = max(0, custo − receita).
  - ponto de equilíbrio dos exemplos = ~500 pagantes; ticket médio/custo por participante corretos.
  - divisores zero → indicadores `null` (sem erro).
- **BudgetConversionTest**
  - `generate-payable` cria exatamente 1 `FinancialEntry` payable vinculado ao evento; 2ª tentativa → 409 `already_converted` (0 lançamentos novos).
  - `generate-receivable` idem para receivable; patrocínio `lost`/`cancelled` → 409 `invalid_sponsorship_status`.
  - excluir a linha do orçamento após converter **não** remove o `FinancialEntry`.
- **BudgetAccessTest**
  - `attendee`/`gate` → 403 em todos os endpoints; `admin`/`treasury` → 200/201.
  - valor ≤ 0, status inválido, categoria fora da lista → 422.

## Critérios de aceite (mapeados à spec)

- SC-002/SC-006: fórmulas de resultado/investimento e exclusão de cancelados/cortesias validadas nos testes de resumo.
- SC-003: ponto de equilíbrio e preço mínimo dentro de 1 unidade do cálculo manual.
- SC-004: 1 lançamento na conversão, 0 na duplicidade.
- SC-005: comparativo reflete vendas/lançamentos reais e % de atingimento.
- SC-007: nenhum erro com divisores zero.
