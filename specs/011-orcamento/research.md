# Research — Aba Orçamento (spec 011)

Consolidação das decisões técnicas. A spec já resolveu as ambiguidades de produto (ver Assumptions); aqui ficam as escolhas de implementação e a forma de integrar com o que já existe. Nenhum `NEEDS CLARIFICATION` pendente.

## D1 — Onde vivem os dados do orçamento

- **Decisão**: novas tabelas dedicadas em `app/Domain/Events` — `budget_plans` (1:1 com `events`), `budget_cost_items`, `budget_ticket_lots`, `budget_sponsorships`, `budget_scenarios`. Todas estendem `BaseModel` (soft delete + `TracksAuditors`).
- **Rationale**: orçamento é planejamento independente dos dados reais (specs 004/patrocínios) e do Financeiro (010). Misturar nas tabelas reais violaria o princípio de separação previsão×realidade da própria spec e poluiria consultas reais.
- **Alternativas rejeitadas**: (a) reaproveitar `ticket_types`/`ticket_lots` reais como previsão — rejeitado: os lotes previstos têm semântica e campos diferentes (pagantes estimados) e não devem afetar vendas; (b) guardar orçamento como JSON no evento — rejeitado: perde histórico por linha, soft delete e consultas por categoria.

## D2 — Totais e indicadores: derivados, nunca colunas

- **Decisão**: um serviço `BudgetCalculator` recebe o `BudgetPlan` (com filhos carregados) e devolve o resumo (custo total, receitas previstas, resultado, investimento próprio, ticket médio, custo por participante/pagante, ponto de equilíbrio) calculado na leitura. `BudgetSummaryResource` serializa esse array.
- **Rationale**: constituição II — estado derivado nunca é persistido. Facilita testar as fórmulas isoladamente.
- **Alternativas rejeitadas**: colunas de total no `budget_plans` recalculadas por observer — rejeitado por violar II e criar risco de divergência.
- **Regras de borda**: divisores zero (pagantes/participantes/ticket médio) → indicador retorna `null` (UI mostra "—"). Itens `cancelled` fora do custo. Cortesias nunca viram receita.

## D3 — Conversão em financeiro (item→pagar; patrocínio→receber)

- **Decisão**: `BudgetConversionService` chama `FinancialEntryService::create($data, $actor)` dentro de `DB::transaction`, com `direction` = `payable`/`receivable`, `origin` = `event_expense`/`sponsorship`, `event_id` do evento, `description`/`amount` da linha e `due_date` (default: data do evento ou hoje quando ausente). Guarda o `financial_entry_id` na linha do orçamento para bloquear duplicidade.
- **Rationale**: reutiliza o ponto único de criação de lançamentos do módulo 010 (sem reimplementar regras). O vínculo por FK garante idempotência simples e o comparativo consegue rastrear origem.
- **Duplicidade**: se a linha já tem `financial_entry_id` não-nulo (e o lançamento não está soft-deleted), a ação retorna **409** (`DomainRuleViolation`, `already_converted`).
- **Categoria**: `category_id` resolvido por *match* de nome com `financial_categories` quando existir; senão `null` (o Financeiro aceita categoria nula). Sem criar categorias novas silenciosamente.
- **Preservação**: excluir/cancelar a linha do orçamento **não** remove o `FinancialEntry` (só o soft delete da linha); o lançamento segue a política do Financeiro.

## D4 — Comparativo orçado × realizado

- **Decisão**: o lado "realizado" reutiliza `FinancialReportService::eventResult($event)` (despesa paga, receita recebida, patrocínio recebido derivados dos settlements do evento) e as **vendas reais** de ingressos do evento (contagem/receita já disponíveis no painel do evento — tickets confirmados/pagos). O lado "orçado" vem do `BudgetCalculator`. Um `BudgetComparisonResource` monta os pares e o % de atingimento da meta de ingressos.
- **Rationale**: não duplica lógica financeira; usa a fonte de verdade do módulo 010 e das vendas.
- **Alternativas rejeitadas**: recalcular settlements no serviço de orçamento — rejeitado (duplicaria regra do 010).

## D5 — Simuladores e cenários

- **Decisão**: cálculos de simulador de **preço mínimo** e **margem de segurança** são *stateless* (calculados no cliente e/ou num endpoint puro que não persiste). Os **3 cenários** (Conservador/Realista/Otimista) são persistidos em `budget_scenarios` (um registro por cenário por plano) para sobreviver ao reload; o "qual fecha o orçamento" é derivado.
- **Rationale**: cenários precisam persistir (o usuário compara ao longo do tempo); preço/margem são what-if efêmeros e não sujam o banco.
- **Alternativas rejeitadas**: persistir cada simulação de preço — rejeitado (ruído sem valor).

## D6 — Frontend: aba, componentes e gráficos

- **Decisão**: nova rota-filha `orcamento` no `EventoLayout` (aba entre "Financeiro" e "Relatórios" conforme a ordem pedida). Componente `Orcamento.jsx` com subcomponentes por seção; cadastros em **modais** (`Modal` reutilizável da spec 009); gráficos com **ApexCharts** (já usado no painel/financeiro); valores com `formatMoney/parseMoney`.
- **Rationale**: consistência com o padrão já entregue; respeita a preferência do usuário por modais e header/abas fixas.
- **Alternativas rejeitadas**: formulários inline (contrariam a preferência registrada) e nova lib de gráficos (ApexCharts já está no bundle).

## D7 — Exportação

- **Decisão**: Excel via **openspout** (streaming, padrão da plataforma) e PDF via **dompdf** (padrão dos comprovantes/relatórios). Endpoints `GET …/budget/export.xlsx` e `…/budget/export.pdf` respeitando o estado atual do orçamento. CSV não é adicionado (sem padrão prévio nesta tela).
- **Rationale**: reaproveita libs e convenções já existentes (specs 008/009/010).

## D8 — Permissões e escopo

- **Decisão**: todos os endpoints sob o grupo do evento com `require.role:admin,treasury`. `admin` = total; `treasury` = ver/editar orçamento e converter em contas. `gate`/`attendee` → 403. A aba só aparece no menu para papéis autorizados.
- **Rationale**: constituição I (4 papéis). Mapeia "Organizador/Consulta" da descrição aos papéis existentes.

## D9 — Validação e integridade

- **Decisão**: FormRequests para todas as escritas (→ 422); valores monetários `> 0` com 2 casas (regra reaproveitada do padrão `assertPositive`); status por enum de constantes; `budget_plans.event_id` único (1 por evento, criado sob demanda no primeiro acesso via `firstOrCreate`).
- **Rationale**: alinha com o padrão de erros de domínio (422 validação, 409 regra, 403 escopo).
