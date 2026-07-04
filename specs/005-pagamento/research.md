# Research — 005-pagamento

Decisões técnicas do pagamento. Base: constituição (princípios III e IV),
`base/research.md` Decisão 5 e specs 001–004.

---

## Decisão 1: `PaymentGatewayContract` + drivers configuráveis (fake por padrão no dev)

**Decisão**: interface única em `app/Domain/Events/Payments/PaymentGatewayContract`:

```
createPixCharge(Order): ChargeData          — txid, copia-e-cola, expiração
createBoletoCharge(Order): ChargeData       — nossoNumero, linha, barcode, pdfUrl?, QR pix
chargeCard(Order, string $token, int $installments): CardResult — approved|declined
getChargeStatus(Payment): ChargeStatus      — paid|pending|expired|cancelled (+ valor pago, evidência)
cancelCharge(Payment): void                 — melhor esforço
```

Drivers registrados por config `payments.pix_driver` / `payments.card_driver`:
- `sicoob` → `SicoobGateway` (usa `SicoobClient`)
- `fake` → `FakePixGateway` / `FakeCardGateway` (dev/teste; **default no dev**)

Container faz o bind por config; trocar provedor = trocar env (FR-018).

**Rationale**: constituição IV ("provedores atrás de contract"); os bloqueadores
externos (certificado, escolha Cielo/Rede) não travam a entrega — o fake cobre
dev/teste e o Sicoob real entra por env quando as credenciais existirem.

**Alternativas consideradas**: omnipay/pacotes multi-gateway — rejeitado:
abstração genérica demais para Pix/boleto híbrido Sicoob; nosso contrato é
pequeno e específico.

---

## Decisão 2: `SicoobClient` — OAuth2 client_credentials + mTLS, testado com HTTP fake

**Decisão**: `SicoobClient` encapsula: token OAuth2 (client_credentials, escopos
`cob`/`cobv`/`pix`, cache até expirar) e chamadas mTLS (opções `cert`/`ssl_key`
do HTTP client apontando para paths de env — nunca no repo). Métodos:
`createPixCharge` (PUT `/cob/{txid}`), `createHybridBoleto` (`"hibrido": true` —
linha digitável + QR na mesma cobrança), `getCharge`, `cancelCharge`. Config
`payments.sicoob.*` (base_url sandbox/prod, client_id, cert paths, webhook
secret) — tudo placeholder no `.env.example`.

**Testes**: `Http::fake()` com payloads do formato documentado da API v3; a
validação contra o sandbox real fica como etapa manual no quickstart
(bloqueador externo).

**Rationale**: pesquisa da base (research Decisão 5) já confirmou o desenho da
API v3; implementar agora com fake HTTP deixa só a rotação de credencial para
depois.

---

## Decisão 3: `RegisterPayment` — ponto único, idempotente por construção

**Decisão**: `RegisterPayment::register(Payment $payment, PaymentEvidence $evidence)`
único caminho que confirma dinheiro. Em `DB::transaction`:

1. `lockForUpdate` no payment; **se já `paid` → retorna sem efeito** (idempotente).
2. Valida valor: divergente do `order.total_amount` → payment `paid` com o valor
   recebido + order `partially_paid` (FR-011) — sem confirmar tickets.
3. Order em status terminal (expired/cancelled) → payment `paid` registrado,
   order intocado (FR-012) — a pendência aparece na tesouraria.
4. Caminho feliz: payment `paid` (paid_at, evidência bruta em raw_response,
   registered_by se manual), order `pending → paid` (guarda da 001), tickets
   vivos `reserved → confirmed`, e-mail `PaymentConfirmed` enfileirado (falha de
   e-mail não bloqueia — dispatch após commit).

Idempotência estrutural: unique `(provider, provider_charge_id)` (payments) +
unique `(provider, external_id)` (webhook_events) + guarda "já pago" sob lock.

**Rationale**: princípio III literal; toda origem (webhook/reconciliação/manual)
converge aqui com a mesma evidência estruturada.

---

## Decisão 4: Webhooks — registrar primeiro, confiar nunca

**Decisão**: `POST /api/webhooks/sicoob` e `/api/webhooks/card` (sem sessão,
fora do grupo stateful): (1) grava `webhook_events` com payload bruto (dedupe
pelo unique — duplicata → 200 `ignored`); (2) verifica origem por segredo
compartilhado em header (`payments.webhook_secret`; allowlist de IP é infra de
produção); inválido → 401 registrado como `error`; (3) **reconsulta**
`getChargeStatus` no gateway; (4) só então `RegisterPayment`; (5) marca
`processed_at`/`result`. Resposta sempre 200 rápida para eventos válidos
(provedores reenviam em erro).

**Rationale**: FR-007/FR-008; doc do webhook Sicoob é incompleta — o corpo é só
um gatilho de verificação, nunca a fonte (research da base).

---

## Decisão 5: Reconciliação — comando diário + disparo da tesouraria

**Decisão**: `payments:reconcile` (agendado diário 04:00; tesouraria dispara o
mesmo código via `GET /treasury/reconciliation?run=1`): varre payments `pending`
de orders não terminais, `getChargeStatus` em cada, paga → `RegisterPayment`
(fonte `reconciliation`), expirada no provedor → payment `expired`. Retorna
resumo (verificados/baixados/expirados) para a tela.

**Rationale**: FR-009; é a garantia de baixa (SC-003) — obrigatória pela
constituição, não opcional.

---

## Decisão 6: Uma cobrança ativa por pedido; expiração cancela no provedor

**Decisão**: criar cobrança nova (qualquer meio) primeiro marca payments
`pending` anteriores do pedido como `expired` + `cancelCharge` (melhor esforço).
`ExpireReservations` (004) ganha o mesmo passo ao expirar o pedido. Pix nasce
com expiração = segundos até `reserved_until`; boleto com vencimento no dia do
`reserved_until`.

**Rationale**: FR-005 e edge cases (troca de meio, pagamento duplo); pagamento
tardio de cobrança não cancelada a tempo cai no FR-012 (registra sem reativar).

---

## Decisão 7: Cartão — token opaco, aprovação síncrona, fake com cartões de teste

**Decisão**: `POST /orders/{code}/checkout/card` recebe `{ token, installments }`
(1..12). `FakeCardGateway`: token `tok_ok_*` → aprovado; `tok_declined_*` →
recusado (mensagem clara, 409 `card_declined`); front do MVP monta o token fake
a partir de um formulário local com cartões de teste (`4242…` aprova, `4000…0002`
recusa) **sem nunca enviar o número ao backend** — o mesmo lugar onde o SDK real
do gateway escolhido se encaixará. Aprovado → `RegisterPayment` na hora (fonte
`gateway`). Webhook `/webhooks/card` fica pronto para eventos assíncronos
(captura/chargeback — consumo pleno na 006/Fase 2).

**Rationale**: FR-004; preserva o invariante "PAN nunca no backend" já no fake
(o teste SC-005 varre payloads/logs); a troca para Cielo/Rede é um driver novo.

---

## Decisão 8: Sinalização de pendências da tesouraria por derivação (sem tabela nova)

**Decisão**: "pagamentos com pendência" (pago em pedido expirado; valor
divergente; pagamento duplo) são uma **consulta derivada** na listagem de
recebimentos (`payment paid` cujo `order` não está `paid`), com destaque visual
— nenhuma flag persistida.

**Rationale**: princípio II (derivar, não armazenar); o tratamento (estorno)
é a spec 006 — aqui a tesouraria precisa **ver**.

---

## Decisão 9: Polling do comprador + QR gerado no servidor

**Decisão**: `GET /orders/{code}/payment-status` (dono) retorna
`{ status, paidAt }`; o front consulta a cada 3s enquanto `pending`
(`refetchInterval`) e celebra quando `paid` (FR-017 — polling; push é Fase 2).
O QR do Pix é gerado no servidor (simple-qrcode SVG, já dependência) a partir do
copia-e-cola e devolvido no payload — front não ganha dependência nova.

---

## Decisão 10: Painel — tesouraria entra no layout existente com guarda por papéis

**Decisão**: `RoleRoute` passa a aceitar `roles={[...]}` (qualquer um);
`/painel` fica acessível a `admin` **ou** `treasury`, com o menu filtrado por
papel (itens de config só admin; "Tesouraria" para treasury/admin). Tela única
`Tesouraria.jsx`: recebimentos (filtros situação/meio + badge de pendência),
botão "Conciliar agora" (resumo do resultado) e baixa manual (modal com
justificativa) — recusa 403 do próprio pedido tratada com mensagem.

**Rationale**: evita um segundo chrome; a matriz de papéis do contrato RBAC
(001) já previa tesouraria no painel.

---

## Riscos / notas

- **Certificado A1 expira**: falha de autenticação no Sicoob fica logada e o
  comando de reconciliação reporta erro visível — monitoramento dedicado na 008.
- **Boleto PDF**: o Sicoob retorna dados, não PDF hospedado; no MVP exibimos
  linha digitável + QR (o `boleto_pdf_url` fica nulo no fake) — geração de PDF
  próprio de boleto fica fora (não é requisito).
- **Valores**: comparação de valor com tolerância zero em centavos (string
  DECIMAL); divergência de 1 centavo já é `partially_paid`.
- **Queue no dev**: e-mails enfileirados rodam com `QUEUE_CONNECTION=database`;
  nos testes, `sync`/`Notification::fake()`.
- Zero migrations — payments/webhook_events da 001 já têm todas as colunas.
