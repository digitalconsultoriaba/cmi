# Research — 003-config-evento

Decisões técnicas do painel administrativo. Base: fundação (001) e auth (002).

---

## Decisão 1: Rotas aninhadas por evento (`/api/admin/events/{event}/…`)

**Decisão**: todas as rotas de gestão são aninhadas no id do evento
(`/api/admin/events/{event}/ticket-types`, `…/lots`, `…/shirt-models`, etc.),
protegidas por `auth:sanctum` + `require.role:admin` + `EventPolicy`. O front
resolve o evento via `GET /api/admin/events` (lista com 1 item no MVP) e navega
com o id.

**Rationale**: single-event é regra de negócio do MVP, não do modelo (constituição,
princípio I: `events` é tabela para a Fase 2 não reescrever nada). Rotas por id
evitam retrabalho de API/front quando vier multi-evento; o custo agora é zero.

**Alternativas consideradas**: rota singleton `/api/admin/event` — rejeitada:
economiza um clique hoje e cobra uma migração de API inteira depois.

---

## Decisão 2: Regras de negócio no domínio; controllers finos

**Decisão**: escrita multi-passo e regras ficam em métodos de domínio/service:
- `EventConfigService::publish(Event)` — valida requisitos mínimos (nome, data,
  tipo, ≥ 1 tipo de ingresso ativo) e transiciona via `transitionTo` (guarda
  terminal da 001); `cancel(Event, reason)` idem, registrando autor/motivo.
- Guardas de exclusão como métodos de modelo: `TicketType::hasSales()`,
  `TicketLot::hasSales()`, `EventShirtSize::hasSales()` — exclusão com vendas
  lança `DomainRuleViolation` (409); capacidade/estoque abaixo do vendido idem.
- `SponsorshipService::createWithInstallments()` e `payInstallment()` — geração de
  parcelas e baixa com recálculo do status em `DB::transaction`.
- CRUDs simples (event types, blocos, camisas) são controller + FormRequest.

**Rationale**: constituição (escrita multi-passo em transação; 409 para regra
violada); os guards precisam valer para qualquer chamador futuro, não só para o
controller de hoje.

---

## Decisão 3: Status do patrocínio como cache recalculado (mesmo padrão de sold_count)

**Decisão**: `sponsorships.status` (pending|partial|paid|cancelled) é recalculado
por `Sponsorship::recalculateStatus()` dentro da mesma transação de qualquer baixa
de parcela — nunca editado diretamente pela tela.

**Rationale**: coerência com o princípio II (fonte de verdade = parcelas); o
padrão já existe na fundação (`sold_count` + `recount()`), documentado no contrato
de derivações.

---

## Decisão 4: Banner via upload multipart + disco `public`

**Decisão**: `POST /api/admin/events/{event}/banner` (multipart) valida
`image` (jpeg/png/webp) até 5 MB, grava no disco `public`
(`storage/app/public/banners/…`), atualiza `banner_path` e retorna a URL pública.
`php artisan storage:link` entra no `make install`. Banner anterior é removido do
disco (o path fica na trilha de auditoria do update).

**Rationale**: padrão Laravel para upload local no MVP; trocar para S3 na Fase 2 é
troca de driver, sem mudança de API.

---

## Decisão 5: Validação de payload de bloco por tipo

**Decisão**: `LandingBlockRequest` valida o `payload` conforme o `type`
(regras por tipo num mapa: hero exige `title`; schedule/speakers/faq exigem
`items` array; location exige `address`; cta exige `label`). Reordenação em massa
via `PATCH /…/landing-blocks/reorder` com a lista ordenada de ids (transacional).

**Rationale**: FR-012 pede conteúdo validado por tipo; endpoint de reorder único
evita N updates e condições de corrida de ordenação.

---

## Decisão 6: Vouchers — geração em lote reusando HasPublicCode

**Decisão**: `POST /…/courtesy-vouchers` recebe `{ quantity, ticketTypeId? }` (até
500 por chamada) e cria N vouchers via model (códigos `CTY-…` únicos do trait da
001). `PATCH /…/courtesy-vouchers/{voucher}/distribute` usa
`CourtesyVoucher::transitionTo('distributed')` (ciclo só avança — guarda da 001) e
grava `distributed_by/at` + nota. Listagem com filtro por status.

**Rationale**: tudo já existe na fundação; a spec só expõe os fluxos.

---

## Decisão 7: Painel com Tabler CSS (`@tabler/core` via npm)

**Decisão**: instalar `@tabler/core` no frontend e construir o layout do painel
(`/painel`) com as classes do Tabler: sidebar por papel, cards, tabelas e forms —
componentes React próprios, sem dependência do JS do Tabler. `template/` fica como
referência visual de páginas.

**Rationale**: o tema já é a referência do projeto (`template/tabler-admin`);
o pacote npm dá o mesmo visual sem copiar 155 MB de template. Fidelidade
pixel-perfect não é critério (Assumption da spec).

**Alternativas consideradas**: CSS próprio — rejeitado: reinventar tabela/form/
sidebar consumiria a spec inteira; copiar HTML do template — rejeitado: estático,
não-React, difícil de manter.

---

## Decisão 8: Guarda de rota por papel no front (`RoleRoute`)

**Decisão**: componente `RoleRoute` (sobre o `ProtectedRoute` da 002) exige
`user.roles` conter o papel; sem papel → página 403 amigável (sem redirect ao
login, a pessoa está logada). O `me` da 002 já entrega `roles`.

**Rationale**: FR-001; separa "não logado" (login) de "sem permissão" (403),
mesma semântica da API.

---

## Decisão 9: Dinheiro nas telas — máscara com vírgula, API com ponto

**Decisão**: inputs monetários aceitam vírgula e normalizam para string decimal
com ponto e 2 casas antes do POST (`"1.234,56" → "1234.56"`); a API segue o
contrato da 001 (DECIMAL string). Helper único `parseMoney/formatMoney` em
`frontend/src/lib/money.js`.

**Rationale**: edge case da spec (entradas com vírgula); pt-BR na UI, contrato
estável na API.

---

## Riscos / notas

- **Sem trava otimista** (Assumption da spec): última gravação vale; se virar dor,
  `updated_at` como token de versão em spec futura.
- **Exclusão de bloco de landing**: soft delete normal (sem guarda de vendas — 
  blocos não têm dependentes).
- **EventType em uso**: desativar sempre possível; excluir só sem eventos
  vinculados (409 caso contrário).
- **Upload em dev via Docker**: o serviço `api` serve `/storage` após
  `storage:link`; conferir volume no compose (mesmo bind da raiz — ok).
- A tela de tipos & lotes mostra vigência/preço efetivo chamando as derivações da
  fundação — nenhuma lógica de vigência duplicada no front.
