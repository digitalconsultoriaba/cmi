# Research — 004-catalogo-compra

Decisões técnicas do catálogo público e da compra. Base: specs 001–003.

---

## Decisão 1: Compra serializada por evento — lock pessimista + recontagem

**Decisão**: `TicketPurchaseService::purchase()` roda em `DB::transaction` e abre
com `Event::lockForUpdate()` na linha do evento — mutex natural por evento. Dentro
do lock: revalida publicado/janela/`salesOpen`, reconta capacidade (evento e tipo),
resolve o lote vigente e seu saldo, reconta estoque por tamanho de camisa
(titular + acompanhante), cria order + tickets com snapshot, incrementa
`sold_count` via `recountSold()` (lote e tamanhos) — tudo ou nada.

**Rationale**: princípio II da constituição ("dentro de DB::transaction com
recontagem"); o lock na linha do evento serializa compras concorrentes do mesmo
evento no InnoDB, eliminando overselling por corrida (SC-002). Single-event no
MVP torna o custo de serialização irrelevante.

**Alternativas consideradas**: locks granulares por lote/tamanho — rejeitado:
múltiplas linhas → risco de deadlock e complexidade sem ganho no volume esperado.
Otimista (retry por versão) — rejeitado: mais código e UX pior sob disputa.

**Teste de concorrência (SC-002)**: automatizado como *contenção determinística*
(última vaga: 1º pedido ok, 2º → 409; casal com 1 vaga → 409; lote/estoque idem) +
smoke manual de concorrência real no quickstart (N curls paralelos via `xargs -P`
disputando as últimas vagas, conferindo zero excedente). PHPUnit é single-thread —
paralelismo real fica no smoke, a corretude do guard fica no teste.

---

## Decisão 2: Pedido de total zero nasce pago; voucher gera pedido próprio

**Decisão**: duas regras que simplificam o ciclo de vida:
1. **Total 0 → pago na hora**: pedido cujo total é zero (ex.: só cortesias em
   evento gratuito) nasce `paid` com tickets confirmados — não há o que aguardar.
2. **Voucher gera pedido próprio**: o resgate de voucher cria **um pedido
   separado** (total 0 → regra 1), mesmo quando submetido junto de um carrinho
   pago. Assim a expiração do pedido pago **nunca** desfaz um resgate de voucher
   (o ciclo do voucher só avança — guarda da 001 — e não precisaria de rollback).

**Rationale**: sem isso, expirar um pedido misto exigiria "des-resgatar" voucher
(violando o ciclo só-avança) ou perder o voucher injustamente. Pedido separado
elimina a classe inteira de problema.

**Alternativas consideradas**: voucher no mesmo pedido com retorno a
`distributed` na expiração — rejeitado: viola a guarda da fundação e abre brecha
de dupla utilização.

---

## Decisão 3: Cortesia automática via `CourtesyResolver` dentro da transação

**Decisão**: `CourtesyResolver::grantsFor(Event, User, int $paidCount)` calcula:
`floor(paidCount / threshold) * grantPer`, limitado por
`courtesy_limit_per_account − cortesias vivas já obtidas pelo comprador` (contagem
recontada dentro da transação do purchase, sob o mesmo lock do evento). Cortesias
geradas ocupam vaga (COUNTS_CAPACITY) — se não couberem na capacidade, o pedido
inteiro é recusado (409 `sold_out`), nunca meio-pedido. Participantes das
cortesias vêm do payload (ou ficam com o nome do comprador por padrão).

**Rationale**: FR-009 e o edge case "cortesia também ocupa vaga"; o mesmo lock da
Decisão 1 protege o limite por conta contra envios simultâneos.

---

## Decisão 4: Comprovante PDF — dompdf + QR SVG inline

**Decisão**: novos pacotes `barryvdh/laravel-dompdf` e
`simplesoftwareio/simple-qrcode` (fixados pela constituição). `TicketReceiptPdf`
renderiza Blade → PDF com QR **SVG inline** (backend Bacon SVG — não requer
imagick/GD) contendo o `code` público do ticket. Download em
`GET /api/tickets/{code}/receipt` (auth + policy de dono), apenas para status
`confirmed`/`courtesy`/`paid`/`used`.

**Rationale**: T074 da base ("PDF nativo agora é MVP"); SVG de QR são retângulos
simples — dompdf renderiza bem; sem dependência de extensão nova na imagem PHP.

---

## Decisão 5: Rotas públicas por slug; privadas por código público

**Decisão**: catálogo público em `GET /api/public/events/{slug}` (sem auth; 404
para rascunho; payload especial para cancelado). Pedidos e ingressos do inscrito
usam o **código público** na URL (`/api/orders/{code}`, `/api/tickets/{code}/…`)
com binding por `code` — nunca id sequencial (FR-006 da 001). Policies:
`OrderPolicy::view` (só o comprador), `TicketPolicy::view/receipt` (participante
com conta **ou** comprador).

**Rationale**: constituição (código público em URL/QR); policies dão o 403 de
escopo exigido pelo FR-011/FR-012.

---

## Decisão 6: "Meus ingressos" inclui e-mail — vínculo tardio (claim)

**Decisão**: a listagem de "meus ingressos" busca `participant_user_id = eu` OU
`participant_email = meu e-mail (normalizado)`; ao listar/visualizar, tickets
casados por e-mail ganham `participant_user_id` preenchido (claim preguiçoso, em
update pontual).

**Rationale**: edge case da spec (participante cria conta depois); claim
preguiçoso evita job de reconciliação e mantém a consulta simples depois do
primeiro acesso.

---

## Decisão 7: Expiração — comando agendado, tickets → cancelled

**Decisão**: comando `orders:expire` (agendado a cada 5 min em
`routes/console.php`) varre pedidos `pending` com `reserved_until` vencido; por
pedido, em transação com o mesmo lock de evento: order `transitionTo('expired')`,
tickets vivos → `cancelled` (com `cancel_reason = 'Reserva expirada'`), e
`recountSold()` de lotes/tamanhos afetados. Idempotente e seguro contra corrida
com pagamento (quem pegar o lock primeiro vence; a 005 revalida status).

**Rationale**: FR-014/FR-015; `ticket_statuses` não tem `expired` — `cancelled`
com motivo preserva a semântica e o histórico (o pedido é que fica `expired`).
Testes chamam o comando direto com relógio congelado (`Carbon::setTestNow`).

---

## Decisão 8: Carrinho no navegador; validação total no servidor

**Decisão**: carrinho em `localStorage` (contexto React `CartProvider`), montável
sem login; a finalização exige sessão (ProtectedRoute) e o carrinho sobrevive ao
redirect (fica no storage). O servidor não tem endpoint de carrinho — só o
`POST /api/orders` com os itens completos; toda validação/preço acontece lá
(preço do momento da confirmação; se divergir do exibido, o front avisa
comparando a resposta).

**Rationale**: Assumption da spec; evita estado servidor pré-pedido (e TTL de
carrinho) — a reserva só existe quando o pedido nasce.

---

## Decisão 9: Front público — LandingRenderer + Checkout em etapas

**Decisão**: páginas novas: `/evento/:slug` (LandingRenderer dos 7 tipos de bloco
+ `TicketPicker` com preços/esgotados), `/checkout` (etapas: participantes →
revisão → confirmar; formulário por participante com camisa quando aplicável;
campo de voucher), `/minha-conta/pedidos` e `/minha-conta/ingressos` (listas +
botão de comprovante). Layout público leve (sem o chrome do painel); Tabler CSS já
global serve de base visual.

**Rationale**: T081/T083 da base; reuso do AuthProvider/ProtectedRoute (002) e do
padrão de páginas existente.

---

## Riscos / notas

- **Lock por evento serializa** as compras do evento: aceitável no MVP
  (single-event, venda de seminário); se virar gargalo na Fase 2, migrar para
  locks por recurso com ordenação estável.
- **dompdf + SVG**: QR é SVG simples (rects) — suportado; se algum leitor de PDF
  reclamar, fallback para PNG base64 (exigiria imagick) fica anotado.
- **Limite de 20 ingressos/pedido** (FR-017): constante de config
  (`config/events.php`) — sem tela de admin no MVP.
- `SampleEventSeeder` ganha um pedido de exemplo? Não — os testes criam os seus;
  o seed continua só catálogo (evita poluir demos de compra).
- E-mails de confirmação ficam para a 005 (Assumption da spec).
