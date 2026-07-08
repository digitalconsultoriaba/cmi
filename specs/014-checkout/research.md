# Research — 014 Checkout do Seminário (multi-inscrição, guest, voucher por participante)

Decisões técnicas. Formato: Decisão / Justificativa / Alternativas rejeitadas.

## R1. Pedido misto com voucher por participante

**Decisão**: Estender `TicketPurchaseService::purchase()` para aceitar **voucher por item** (`items[].voucher_code`). Ao criar as inscrições num **único** `Order`: itens sem voucher viram tickets normais (`unit_price` do tipo/lote, status RESERVED→PAID após pagamento); itens com voucher válido viram tickets **cortesia** (`unit_price = 0`, `is_courtesy = true`, status `COURTESY`), com o voucher resgatado e ligado via `redeemed_ticket_id`. O `total_amount` do pedido = soma dos itens **não** isentos. Status do pedido derivado: `PAID` (todos pagos), `PARTIALLY_PAID`/"pago parcialmente por voucher" (mistos), `PENDING` enquanto há saldo a pagar, `GRATUITO` quando total = 0 e há isenções.

**Justificativa**: O requisito central (voucher isenta **a inscrição**, não o carrinho) exige um pedido misto. Hoje o `CourtesyResolver::redeemVoucher()` cria um **pedido gratuito separado** — inadequado para o carrinho misto. Reaproveita a validação/resgate do voucher, mas passa a resgatar **dentro do pedido em montagem**, mantendo tudo numa transação (constituição II). Não quebra o resgate avulso existente (permanece disponível).

**Alternativas rejeitadas**: (a) manter dois pedidos (um pago, um gratuito) — contraria "um pedido misto" e complica o resumo/entrega; (b) desconto no total do pedido em vez de zerar a inscrição — perde a semântica "aquela inscrição é gratuita" e o vínculo voucher↔ticket.

## R2. Criação de pedido por guest (sem login)

**Decisão**: Novo endpoint **público** `POST /public/orders` (sem `auth:sanctum`) recebendo os **dados do comprador** (nome + e-mail obrigatórios) + `items[]`. Um `GuestBuyerService` **cria ou vincula** um `User` ao e-mail do comprador (conta sem senha, papel `attendee`, `email_verified_at` nulo até o magic link), e o pedido é criado com esse buyer via o `TicketPurchaseService` existente. As rotas autenticadas atuais (`POST /orders`) permanecem para usuários logados.

**Justificativa**: O `TicketPurchaseService::purchase(Event, User $buyer, …)` já exige um `User`; resolvendo/criando o buyer a partir do e-mail, reutilizamos todo o serviço sem reescrever. Contas sem senha já existem (fluxo Google). O e-mail é normalizado (lowercase) no `User`, evitando duplicatas.

**Alternativas rejeitadas**: (a) pedido sem `User` (buyer só em colunas snapshot) — quebraria relações e a área do inscrito; (b) exigir cadastro/login antes do checkout — contraria a decisão de guest checkout e aumenta o atrito.

## R3. Acesso passwordless (magic link) — comprador e participantes

**Decisão**: `MagicLinkService` gera uma **URL assinada com expiração** (`URL::temporarySignedRoute`) para uma rota `GET /auth/magic/{user}` (middleware `signed`); ao abrir, o `MagicLinkController` autentica a sessão Sanctum daquele `User` e redireciona ao SPA (área do inscrito). Cada **participante** com e-mail recebe seu próprio magic link (conta criada/vinculada por `GuestBuyerService`), acessando **apenas os seus** ingressos; o **comprador** recebe um magic link que dá acesso a **todos** os ingressos do pedido. Reenvio disponível.

**Justificativa**: Não há magic link hoje, mas o padrão de **URL assinada** já é usado no `verify-email` (`signed` middleware) — reaproveita a mesma mecânica, sem lib nova, respeitando a constituição IV (sem senha trafegando; expiração; integridade por assinatura). O escopo de acesso reusa as policies/`buyer_user_id`/`participant_user_id` já existentes.

**Alternativas rejeitadas**: (a) senha temporária por e-mail — mais atrito e superfície de risco; (b) token opaco em tabela própria — reinventa o que a URL assinada resolve; (c) acesso só por código do ingresso sem conta — o usuário pediu conta por participante (clarificação Q2).

## R4. Elegibilidade do voucher (available ou distributed)

**Decisão**: A validação de resgate aceita voucher em estado **`available`** ou **`distributed`** (além de: pertence ao evento, dentro da validade se houver, elegível ao tipo/categoria, não `redeemed`). Uso único por padrão; múltiplos usos só se o voucher estiver configurado para tal.

**Justificativa**: Clarificação Q4 — o comprador pode aplicar qualquer código válido do evento no checkout, sem depender de o admin ter "distribuído" antes. Amplia levemente a guarda atual do `CourtesyResolver` (que exige `distributed`), sem afetar a distribuição administrativa.

**Alternativas rejeitadas**: exigir `distributed` (comportamento atual) — rejeitado pela decisão do usuário; travaria o resgate direto no checkout.

## R5. Campos por participante (categorias/campos/afiliações) — standalone

**Decisão**: Config em três tabelas novas: `participant_categories` (por evento: `key`, `label`, `sort`, `is_active`), `participant_fields` (por categoria: `key`, `label`, `type` ∈ `text|affiliation|country|city|conditional`, `required`, `sort`, `config` JSON — ex.: campo condicional "possui cargo?" revela `cargo`), e `affiliations` (lista por evento: `name`, `sort`, `is_active`) como fonte do campo `affiliation`. Os **valores** preenchidos são **snapshotados** no ticket: coluna nova `participant_category_key` + `participant_fields` (JSON) em `tickets`. Código 100% genérico; os rótulos maçônicos (GLMEES/Loja/Potência/Cargo) são apenas `label`/dados. Uma **seed** cria a config padrão do seminário (2 categorias).

**Justificativa**: Honra a constituição I (nenhum conceito maçônico no código). `tickets` não tem campo JSON hoje — a coluna aditiva `participant_fields` guarda o snapshot sem novas tabelas por-ticket. Categorias/campos em tabelas leves dão UI de admin limpa e evitam hardcode. A lista de "lojas" é uma **nova** lista gerenciável (não havia tabela de lojas; os campos maçônicos existentes no `User` são do comprador e ficam intactos).

**Alternativas rejeitadas**: (a) hardcode dos campos maçônicos no código — viola o Princípio I; (b) reutilizar os campos maçônicos do `User` para o participante — participante não é necessariamente um `User` completo e os campos são do comprador; (c) tudo em JSON de config no evento — dificulta a lista de afiliações e a UI.

## R6. Momento de criação do pedido (Q5 deferida da clarificação)

**Decisão**: O **carrinho é client-side** (estado no navegador) enquanto o usuário monta/edita/aplica voucher; o **pedido só é criado no backend ao clicar "Pagar agora"/"Confirmar inscrição gratuita"** (na tela de revisão), como `POST /public/orders`. A partir daí: total > 0 → pedido `PENDING` com `reserved_until` (TTL do evento) e segue ao pagamento; total = 0 → pedido finalizado gratuito. Abandono **antes** de finalizar não persiste nada; abandono **após** criar o pedido e antes de pagar deixa uma **pré-inscrição PENDING** que expira pelo TTL.

**Justificativa**: Alinha-se ao fluxo atual (`POST /orders` cria o pedido na finalização) e ao modelo de reserva com `reserved_until`/`ReconcilePayments`. Evita pedidos órfãos a cada "adicionar participante" e o custo de sincronização incremental. Validação de voucher/afiliação pode ser consultada em tempo real (endpoints de leitura) sem criar pedido.

**Alternativas rejeitadas**: pré-inscrição server-side a cada adição — gera muitos pedidos PENDING/vagas reservadas indevidamente e complica a limpeza; não traz benefício ao usuário.

## R7. Seleção de tipo de ingresso

**Decisão**: Cada item do carrinho referencia um `ticket_type_id` escolhido; o preço é `TicketLot::effectivePrice($type)` (lote vigente) snapshotado em `unit_price` (comportamento atual). O checkout lista os tipos ativos/compráveis do evento (catálogo público já existente). Com um único tipo, ele é pré-selecionado.

**Justificativa**: Clarificação Q1. O serviço/‌esquema já suportam `ticket_type_id` por item e derivação de preço por lote — reuso direto.

**Alternativas rejeitadas**: valor único fixo por evento — rejeitado pela decisão do usuário; perderia casal/tipos distintos.

## R8. Entrega de ingresso e status consolidado

**Decisão**: Após confirmação (pagamento aprovado via `RegisterPayment`, ou finalização gratuita), disparar por participante a notification `TicketIssuedPtBr` (ingresso com QR/`code` + magic link do participante) e ao comprador a `OrderAccessPtBr` (magic link + resumo). Reusa `TicketReceiptPdf`/rota `/tickets/{code}/receipt`. Status por ticket: `AWAITING_PAYMENT`→`PAID` | `COURTESY`(gratuito por voucher) | `CANCELLED`; status consolidado do pedido derivado: aguardando / pago parcialmente por voucher / pago integralmente / gratuito / cancelado.

**Justificativa**: Reaproveita o gerador de comprovante e o padrão de notifications pt-BR (`PaymentConfirmedPtBr`). A baixa continua idempotente pelo ponto único (constituição III), então o envio dispara no evento de confirmação sem duplicar.

**Alternativas rejeitadas**: anexar PDF pesado a cada e-mail — preferir link ao comprovante/área; reduz custo e vazamento.

## R9. Convenções (dinheiro/datas/idioma/LGPD)

**Decisão**: DECIMAL(10,2) para valores; datas em UTC (TTL/validade no fuso do evento na exibição); identificadores/código em inglês; UI/mensagens pt-BR; coletar o mínimo (e-mail para entrega; documento opcional); não logar dado sensível; PAN/CVV nunca no backend.

**Justificativa**: Constituição (stack/convenções, IV, LGPD).
