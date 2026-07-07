# Quickstart — Validação 014 Checkout do Seminário

Backend/PHP via Docker; frontend via Vite. Migrations **aditivas** (nunca `migrate:fresh` sem autorização).

## Pré-requisitos

```bash
make up                                     # MySQL/Redis/Mailpit
docker compose --profile dev up -d api      # API :8000
cd frontend && npm run dev                  # Vite :5173
docker compose run --rm php php artisan migrate
docker compose run --rm php php artisan db:seed --class=SeminarioCheckoutSeeder   # config padrão (categorias/campos/afiliações) + evento demo
```

E-mails de teste caem no **Mailpit** (verificar ingressos/magic links enviados).

## Fluxo 1 — Inscrição paga multi-participante (guest) (US1)

1. No site do evento, clicar **Inscreva-se** → abre `/checkout/{slug}` (sem login).
2. Escolher **categoria** (ex.: "Irmão da GLMEES"), o **tipo de ingresso**, preencher os campos (Loja via autocomplete; "Possui cargo?" → Cargo) e informar e-mail do participante.
3. Clicar **Adicionar outro irmão**, cadastrar um 2º (categoria "outra potência": Potência/País/Cidade). Conferir o **resumo** recalcular (2 × valor).
4. Informar o **e-mail do comprador**. Ir à **tela de revisão** (cards por participante; testar **remover** → total recalcula).
5. Clicar **Pagar agora** → pagar com o gateway de teste (cartão `tok_ok_4242`).
6. **Esperado**: pedido **pago integralmente**, 2 tickets `PAID`; no Mailpit, e-mail de ingresso para cada participante (com QR/`code` + magic link) e e-mail de acesso ao comprador.
7. Abrir o **magic link do comprador** → back-office com **os 2 ingressos**. Abrir o **magic link de um participante** → vê **apenas o seu**.

## Fluxo 2 — Voucher por participante (pedido misto) (US2)

1. Carrinho com 3 participantes. No 2º, **aplicar voucher** válido do evento (código `available` ou `distributed`).
2. **Esperado**: 2º vira **R$ 0,00** ("Voucher aplicado com sucesso…"); total = 2 × valor.
3. Aplicar voucher **inválido/expirado/usado** noutro → mensagem de erro; valor **permanece**.
4. Tentar aplicar o **mesmo** voucher (uso único) em outra inscrição → recusado.
5. **Remover** o participante com voucher → total recalcula e o voucher é **liberado**.
6. Finalizar e pagar o saldo → 2 tickets `PAID` + 1 `COURTESY` no **mesmo pedido**; pedido "pago parcialmente por voucher" → "pago integralmente".

## Fluxo 3 — Checkout 100% gratuito (US3)

1. Carrinho com 2 participantes, **ambos com voucher** válido → total = **R$ 0,00**.
2. O botão vira **"Confirmar inscrição gratuita"**.
3. Confirmar → **sem** etapa de pagamento; 2 tickets `COURTESY`; pedido **gratuito**; ingressos + magic links enviados.

## Fluxo 4 — Configuração (admin) (US4)

1. Painel → evento → aba de **Inscrições/Categorias**: criar/editar categorias, campos (incl. condicional) e a **lista de afiliações** ("lojas").
2. No checkout, confirmar que o formulário muda por categoria e o autocomplete carrega a lista.
3. Após uma inscrição, confirmar que os valores dos campos ficam **snapshotados** no ingresso (mudar a config depois não altera inscrições antigas).

## Fluxo 5 — Acesso pós-compra (US5)

1. Reenviar acesso: `POST /public/orders/{code}/resend-access` e `POST /auth/magic/request` (por e-mail) → novo link no Mailpit.
2. Link **expirado** → página de reenvio (403 tratado).
3. Baixar o comprovante `GET /tickets/{code}/receipt` (PDF/QR) como comprador e como participante do ticket.

## Testes automatizados

```bash
make test
docker compose run --rm php php artisan test --testsuite=Feature --filter=Checkout
```

Cobertura esperada (tests/Feature/Checkout): guest order + tipos + snapshot de campos; pedido misto com voucher por item (total/status); validação de voucher (available/distributed, inválido/expirado/usado/não elegível); total-zero gratuito; magic link + escopo de acesso (comprador todos / participante o seu, link assinado/expirado); entrega de ingresso (`Notification::fake`); abandono → pré-inscrição PENDING/TTL; concorrência (dois pedidos não consomem a mesma vaga/voucher). **Verde** antes do merge; specs 002/004/006/011 permanecem verdes (aditivo).
