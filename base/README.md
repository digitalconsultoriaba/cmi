# Plataforma de Eventos — Seminário Internacional (projeto novo)

Pacote de especificação completo para começar o projeto **do zero**, desmembrando o
módulo de eventos (061) do sistema da maçonaria e transformando-o num **produto
autônomo** de venda e gestão de ingressos.

## Decisões da fundação (definidas com o PO)

- **Stack**: Laravel + React (reaproveita o núcleo do 061).
- **Conta do inscrito**: cadastro próprio + login Google.
- **Escopo**: single-event (um seminário internacional por instância).
- **Pagamento**: Sicoob (Pix + boleto híbrido) + gateway de cartão (Cielo/Rede).
- **Papéis**: Admin · Tesouraria · Portaria · Inscrito.
- **Abordagem**: fork/extração do código 061 existente.

## Documentos (ordem de leitura)

1. **`spec.md`** — o quê e por quê. Inclui a seção "O que muda ao desmembrar do 061"
   (removido / mantido / novo) e as regras funcionais (FR-01…FR-29).
2. **`research.md`** — as 12 decisões de arquitetura, com destaque para a integração
   Sicoob (webhook fraco → reconciliação diária) e a segurança de cartão.
3. **`data-model.md`** — esquema de tabelas do produto standalone.
4. **`api-spec.md`** — todos os endpoints (público, auth, inscrito, admin, tesouraria,
   portaria, webhooks).
5. **`plan.md`** — arquitetura backend + frontend + regras duras de segurança de pagamento.
6. **`tasks.md`** — roteiro por fases (Fase 0 extração → Fase 9 testes) + decisões a
   fechar antes de codar.
7. **`quickstart.md`** — instalar, migrar, rodar, `.env`, fluxos de validação.

## O caminho crítico

O coração do projeto é: **landing → cadastro → carrinho → checkout → pagamento real
(Sicoob/cartão) → confirmação automática (webhook + reconciliação) → ingresso com QR →
check-in**. Tudo o que era acoplado à loja/irmão sai; o núcleo order→tickets, cortesia,
patrocínio, check-in e relatórios do 061 é reaproveitado.

## Antes de começar a codar (bloqueadores)

1. Escolher o **gateway de cartão** (Cielo/Rede) → fecha SDK e webhook.
2. Obter **certificado A1 Sicoob** + cadastrar aplicação + liberar escopos.
3. **Google OAuth** (client id/secret).
4. **Domínio + HTTPS** (webhooks exigem URL pública).
5. Definir a **política de reembolso** (prazo/percentual).
