# Quickstart — 003-config-evento (guia de validação)

Referências: [spec.md](spec.md), [contrato](contracts/admin-api.md),
[data-model](data-model.md), [research](research.md).

## Pré-requisitos

- Specs 001+002 na `main`; `make up && make fresh` executados.
- Login admin de dev: `admin@dev.local` / `password` (seed da 001).
- Nenhuma credencial externa.

## Rodar

```bash
make test   # suíte completa (Foundation + Auth + Admin)
make dev    # painel em http://localhost:5173/painel
```

## Validações por user story

### US1 — Evento (configurar/publicar/cancelar/banner)
1. Logar como `admin@dev.local` → `/painel` abre; logar como attendee → página 403.
2. Editar dados do evento e salvar; enviar banner (jpeg < 5 MB) → aparece; enviar
   PDF → erro de validação.
3. Com evento em rascunho sem tipos ativos → publicar recusado listando o que
   falta; após criar tipo → publica.
4. Cancelar com motivo → status cancelado, autor/momento registrados; tentar
   editar/republicar → conflito (409).

### US2 — Tipos & lotes
1. Criar tipos (individual/casal/cortesia), reordenar, desativar.
2. Criar 2 lotes com janelas e preço promocional → tela mostra o vigente e o
   preço efetivo por tipo (igual à regra da fundação).
3. Excluir tipo/lote com vendas (usar dados do seed) → 409; desativar → ok.
4. Reduzir capacidade abaixo do vendido → 409.

### US3 — Camisas
1. Criar modelo + tamanhos (um com estoque, um ilimitado) → vendido/esgotado
   visíveis.
2. Definir estoque < vendido → 409; excluir tamanho com vendas → 409.

### US4 — Landing
1. Criar blocos dos 7 tipos; capa sem título → 422 no campo.
2. Reordenar e desativar blocos → persistido (recarregar a tela confirma).

### US5 — Cortesias
1. Definir regra X→Y e limite por conta no evento.
2. Gerar 10 vouchers → 10 códigos `CTY-…` únicos em "disponível".
3. Distribuir um (com nota) → situação avança com autor/momento; tentar voltar →
   409.

### US6 — Patrocínios
1. Criar patrocínio de R$ 1.000,00 em 3 parcelas → parcelas 1–3 somando o total.
2. Pagar a 1ª → status geral "parcial"; pagar todas → "pago"; repagar → 409.

## Encerramento da spec

- [ ] `make test` verde (todas as suítes)
- [ ] Fluxos manuais acima validados no painel (`make dev`)
- [ ] Nenhum segredo novo; auditoria em amostra de ações verificada
- [ ] Merge de `003-config-evento` na `main`
