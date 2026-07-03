# Research — Plataforma de Eventos (standalone)

Decisões que sustentam a extração do 061 para um produto autônomo, com pagamento real.

---

## Decisão 1: Standalone, sem dono polimórfico

**Decisão**: eliminar `owner_type`/`owner_lodge_id` e todo o `resolveOwner`. Há um único
organizador implícito. RBAC próprio (admin/tesouraria/portaria/inscrito) substitui o
`EventAccessGuard` escopado por loja.

**Rationale**: o produto não pertence mais à estrutura da maçonaria; manter o morph de
dono só carregaria complexidade morta. Modelamos `events` como tabela (não constante)
para não reescrever quando virar multi-evento na Fase 2.

**Alternativa rejeitada**: manter o guard e "desligar" a loja — deixaria acoplamento
latente e confundiria o modelo de papéis.

---

## Decisão 2: Autenticação própria + Google

**Decisão**: Sanctum SPA + cadastro e-mail/senha (verificação) + Socialite Google.
Papel default `attendee`. Sem `members.user_id` nem vínculo com o sistema antigo.

**Rationale**: o público agora é qualquer pessoa que compra ingresso, não um irmão
cadastrado. Google reduz atrito no checkout. Contas só-Google têm `password` nulo.

---

## Decisão 3: order → tickets + snapshot + derivação (mantido do 061)

**Decisão**: preservar o núcleo que já era bom: um pedido agrega N ingressos (um por
participante), com snapshot de preço/nome/camisa/lote; disponibilidade e "inscrições
abertas" derivadas, nunca armazenadas.

**Rationale**: padrão consolidado de plataformas de ingresso; o snapshot protege
relatórios se o tipo/lote mudar; derivar evita estado inconsistente.

---

## Decisão 4: Lotes (novo)

**Decisão**: `ticket_lots` com preço/janela/quantidade; lote vigente calculado; virada
automática por data ou por esgotamento. Preço efetivo = `price_override ?? type.price`.

**Rationale**: seminário internacional trabalha com 1º/2º/3º lote — recurso central de
venda que o 061 não tinha (lá o preço era fixo por tipo).

---

## Decisão 5: Pagamento real — Sicoob (Pix + boleto híbrido) + gateway de cartão

**Decisão**: ligar de verdade o "ponto único de baixa" que o 061 deixou preparado.
Sicoob API v3 para Pix (cobrança imediata) e **boleto híbrido** (`"hibrido": true` —
boleto + QR Pix na mesma cobrança). Cartão via gateway (Cielo/Rede) com **tokenização
client-side**. Tudo atrás de um `PaymentGatewayContract` para trocar provedor sem
reescrever.

**Rationale**: a pesquisa confirmou que o Sicoob v3 usa OAuth2 + **certificado A1
(ICP-Brasil)**, escopos `cob`/`cobv`/`pix`, e suporta boleto híbrido e webhook de
liquidação. A baixa entra pelo webhook — que encaixa no ponto único idempotente.

**Ponto crítico**: a documentação do **payload do webhook do Sicoob é reconhecidamente
incompleta** (relatos públicos de "trabalhar por tentativa e erro"). Por isso o webhook
**não é a única** fonte de verdade:
- todo webhook é deduplicado (`webhook_events`) e **reconsulta a cobrança** no provedor
  antes de baixar;
- um **job de reconciliação diário** (`ReconcilePayments`) varre pedidos `pending` com
  cobrança e concilia — garantia de baixa mesmo se o webhook falhar.

**Segurança**: certificado + Client ID + chaves do gateway em secret manager, fora do
VCS e do front. O **PAN do cartão nunca** trafega pelo backend — só o token do gateway.

---

## Decisão 6: Reserva com expiração

**Decisão**: pedido nasce `pending` com `reserved_until` (TTL configurável, ex. 30 min).
`ExpireReservations` (job a cada 5 min) libera a vaga se não pagar → `expired`.

**Rationale**: sem reserva temporária, capacidade e lote ficariam presos por carrinhos
abandonados. A recontagem de vaga/estoque acontece dentro de `DB::transaction` (mesmo
tratamento de race da cortesia no 061).

---

## Decisão 7: Cortesia + vouchers (mantido, limite por conta)

**Decisão**: regra X→Y por evento + **vouchers** rastreáveis; limite **por conta/CPF**
(não mais por loja/irmão).

**Rationale**: o conceito de "por loja" saiu com o desmembramento; o limite passa a ser
por conta do inscrito.

---

## Decisão 8: Transferência por e-mail (não por "irmão")

**Decisão**: `allow_transfer`, só ingresso pago; o novo titular é informado por
e-mail/dados (não busca em `members`). Original → `transferred` (histórico); nasce um
novo ingresso ativo com `participant_user_id` do destinatário.

---

## Decisão 9: Comprovante PDF nativo (agora MVP)

**Decisão**: comprovante em **PDF nativo** (dompdf) com QR (simple-qrcode) + página
imprimível. No 061 o PDF nativo foi deferido; aqui entra no MVP porque é produto
voltado ao consumidor final.

---

## Decisão 10: Landing page configurável (novo)

**Decisão**: `landing_blocks` (JSON por bloco) editáveis pelo admin sem deploy — hero,
texto, programação, palestrantes, FAQ, local. Renderizada publicamente por slug.

**Rationale**: é a "vitrine" que o produto standalone precisa e que o módulo interno
não tinha (lá o evento só aparecia num catálogo interno).

---

## Decisão 11: Suporte (substitui refund_cases GL↔irmão)

**Decisão**: `support_cases` + notas viram canal genérico inscrito ↔ admin/tesouraria
(reembolso/dúvida/troca de camisa), escopado por `user_id`.

---

## Decisão 12: Histórico — nada some (mantido)

**Decisão**: soft delete em tudo + activity log + colunas de estado. Situações
terminais (cancelled/refunded/used/expired) → 409. Cancelar evento/ingresso preserva o
registro.

---

## Riscos / notas

- Webhook Sicoob mal documentado → reconciliação diária obrigatória (não opcional).
- Certificado A1 expira → monitorar validade; renovação quebra a integração.
- Escolha do gateway de cartão fecha SDK/token/webhook — manter atrás do contract.
- Race de capacidade/lote/estoque/cortesia → sempre dentro de transação com recontagem.
- LGPD: dados pessoais dos inscritos (CPF/e-mail) — minimizar, proteger, permitir exclusão.
- Single-event hoje, mas `events` já é tabela → multi-evento na Fase 2 sem reescrita.
