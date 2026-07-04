# Quickstart — 009-refatoracao-telas (guia de validação)

Referências visuais: [`referencias/INDEX.md`](referencias/INDEX.md) (14 imagens).
Demais: [spec.md](spec.md), [contrato](contracts/panel-api.md),
[data-model](data-model.md), [research](research.md).

## Pré-requisitos

- Specs 001–008 na `main`; `make up && make fresh` (seeds de demo com vários
  eventos, vendas, cortesias, patrocínios e check-ins).
- Logins: `admin@dev.local`, `tesouraria@dev.local`, `portaria@dev.local` /
  `password`.
- Logo em `frontend/public/logo.png` (copiada de `public/logo.png`).

## Rodar

```bash
make test   # suíte completa (inclui Panel/* e mantém 007/008 verdes)
make dev    # http://localhost:5173/painel
```

## Validações por user story

### US1 — Casca + identidade
1. Manual: entrar como `admin` → **sidebar azul com a logo CMI/GLMEES**, tema
   claro; módulo "Eventos e Ingressos" com abas Painel/Eventos/Atendimentos/
   Tipos; abrir um evento pela lista → cabeçalho fixo (Voltar/nome/badge/
   Editar/Banner/Cancelar) + abas do evento; trocar de aba mantém o cabeçalho.
2. Editar evento em **modal** (todos os campos + toggles + gratuidade X→Y);
   criar novo evento em modal.
3. Papéis: `tesouraria` e `portaria` veem só as abas do seu escopo; nenhum
   item do menu anfitrião navega para tela vazia.

### US2 — Painéis com gráficos
1. Testes: `overview` e `dashboard(Event)` batem com contagens; filtro de
   período recalcula; série mensal fecha com o card de inscritos;
   `byTicketType` no lugar de "por loja"; evento sem vendas → gráficos vazios
   coerentes; papéis (treasury/gate → 403).
2. Manual: Painel do módulo (rosca de eventos + curva de inscrições), abrir um
   evento e ver o Painel do evento (contadores + rosca de situação).

### US3 — Check-in + presença manual
1. Testes: `attendance(Event)` escopado; presença manual pela lista =
   `POST /gate/checkin` (1 entrada, `ticket.checked_in`, casal = 2); não
   elegível recusa com o mesmo motivo; % presença e contadores atualizam.
2. Manual: validar um código (vira presente + donut sobe); "Registrar
   presença" num ausente da lista; buscar por nome.

### US4 — Camisas com estoque
1. Testes: disponível = estoque − vendidas; estoque nulo = ilimitado (sem
   negativo); somatório do modelo fecha.
2. Manual: abrir Camisas, conferir total/vendidas/disponível por tamanho,
   adicionar tamanho novo, baixar relatório por modelo e geral.

### US5 — Relatórios com preview
1. Testes: prévia e export .xlsx do mesmo recorte trazem as mesmas linhas;
   filtro vazio → "0 linhas" sem erro; `type` desconhecido → 422.
2. Manual: escolher relatório, aplicar filtro, conferir prévia + total,
   exportar e abrir a planilha.

## Sem regressão

- [ ] `make test` verde — inclusive as suítes 007/008 (endpoints antigos
      intactos).
- [ ] Build do frontend ok.
- [ ] Nenhuma tabela/coluna nova.

## Encerramento da spec

- [ ] Fluxos manuais das 14 referências conferidos no navegador.
- [ ] Merge de `009-refatoracao-telas` na `main`.
