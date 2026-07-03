# Plataforma de Eventos — Seminário Internacional (produto standalone)

> Status: MVP (Fase 1). Projeto **novo e independente**, desmembrado do módulo 061
> (Gestão de Eventos da Grande Loja). Codinome interno: **`eventos-plataforma`**.
> Stack: **Laravel + React** (reaproveita o núcleo do 061). Escopo inicial:
> **single-event** (um seminário internacional por instância).

---

## 1. Resumo

Plataforma pública de **venda e gestão de ingressos** para um seminário internacional.
Diferente do 061 — que era um módulo interno acoplado à Grande Loja, às Lojas e aos
irmãos — este é um **produto autônomo**, sem nenhuma dependência de maçonaria, loja,
membro ou matriz de permissões daquele sistema.

O fluxo público é o de uma loja virtual de ingressos:

```
Landing page pública do evento
        │  visitante vê descrição, programação, lotes, tipos de ingresso
        ▼
Cadastro / login (e-mail+senha ou Google)
        │  monta o carrinho: ingressos + dados de cada participante + camisa/kit
        ▼
Checkout → escolhe forma de pagamento
        │  Pix (Sicoob) · Boleto híbrido (Sicoob) · Cartão de crédito (gateway)
        ▼
Confirmação automática (webhook Sicoob / retorno do gateway)
        │  ingresso emitido com código + QR
        ▼
Área do inscrito (meus ingressos, comprovante, 2ª via de boleto/Pix)
        ▼
Check-in no evento (portaria valida QR → "usado")
```

Três públicos administrativos + o inscrito:

- **Inscrito** — compra, paga, acompanha e usa o ingresso (área restrita própria).
- **Administrador** — configura o evento, tipos de ingresso, lotes, camisas, cortesias,
  patrocínios, relatórios e a landing page.
- **Tesouraria** — dá baixa/concilia pagamentos, acompanha recebimentos, trata
  devoluções/estornos.
- **Portaria** — faz o check-in (leitura de QR) no dia do evento.

---

## 2. O que muda ao desmembrar do 061

Esta seção é o coração do projeto: o que sai, o que fica e o que entra.

### 2.1 Removido (era acoplamento com a maçonaria)

| Removido do 061 | Motivo |
| --- | --- |
| Dono polimórfico `owner_type` (grand_lodge/lodge) + `owner_lodge_id` | Não há mais GL nem lojas; o dono é o organizador único. |
| `EventAccessGuard` com escopo por loja/irmão | Substituído por RBAC simples (admin/tesouraria/portaria/inscrito). |
| Os três "chromes" (MainLayout GL / BrotherLayout loja / irmão) | Vira: site público + painel admin + área do inscrito. |
| `require.module:events` (matriz de permissões 047) | Não há sistema de módulos; papéis diretos. |
| Cortesia/limite **por loja**; `seat_limit_per_lodge` | Sem lojas. Cortesia por regra global + vouchers; limite por conta/CPF. |
| Transferência vinculada a `members`/`searchBrothers` (011) | Transferência passa a ser por e-mail/dados do novo titular. |
| Atendimento de reembolso GL↔irmão escopado por `member_id` | Vira canal de suporte entre inscrito e admin/tesouraria. |
| Compra "pela Loja para seus irmãos" | Substituída por **compra em grupo** (um comprador, N participantes). |
| `members.user_id`, seeders `Baseline/Module`, integração 054/055 | Fora. Autenticação e financeiro são próprios. |

### 2.2 Mantido (núcleo reaproveitado do 061)

- **order → tickets** (um registro por participante), com **snapshot** de preço/nome/
  camisa no ticket.
- **Capacidade e "disponível" derivados** (capacidade − ingressos vivos), nunca
  armazenados.
- **Ponto único de baixa** — agora conectado de verdade ao Sicoob/gateway (no 061 era
  só "preparado").
- **Cortesia** configurável (X pagantes → Y grátis) **+ vouchers** de cortesia.
- **Check-in por QR** → `used`; recusa inválido/já usado/cancelado.
- **Comprovante** com QR.
- **Patrocínio** com parcelas e baixa.
- **Relatórios .xlsx** (openspout) com filtros mês/ano/período.
- **Histórico que nada apaga** (soft delete + activity log + colunas de estado).

### 2.3 Novo (não existia ou era só "preparado" no 061)

- **Landing page pública** configurável (hero, descrição, programação, palestrantes,
  lotes, FAQ, local/mapa) — a "vitrine" que joga o visitante pro gerenciador.
- **Autenticação do inscrito**: e-mail+senha **+ login Google** (Socialite).
- **Integração Sicoob real**: Pix (cobrança imediata) e **boleto híbrido**, com
  **webhook** de confirmação + job de reconciliação diário (fallback).
- **Gateway de cartão de crédito** (ex.: Cielo/Rede) para pagamento à vista/parcelado
  com auto-confirmação.
- **Lotes** (1º lote, 2º lote…) com virada por data ou por quantidade vendida.
- **Painel de Tesouraria** separado do admin.
- **Área do inscrito** estilo loja virtual (pedidos, 2ª via, comprovante, status).

---

## 3. Decisões desta spec

| Tema | Decisão |
| --- | --- |
| Produto | **Standalone**, single-event, sem qualquer dependência do sistema da maçonaria. |
| Stack | **Laravel 11 + React 18** (Vite). Reaproveita services/models do 061 já limpos do acoplamento de loja. |
| Papéis | **RBAC** simples com 4 papéis: `admin`, `treasury`, `gate` (portaria), `attendee` (inscrito). Sem matriz de módulos. |
| Conta do inscrito | **Cadastro próprio** (e-mail+senha) **+ Google** (Socialite). Verificação de e-mail. |
| Modelo de ingresso | **order → tickets** (um por participante); snapshot; disponível derivado. Idêntico ao 061. |
| Tipos de ingresso + lotes | `ticket_types` por evento + **`ticket_lots`** (lote/preço/janela/quantidade). |
| Pagamento | **Real, com gateway.** Pix + Boleto híbrido via **Sicoob API v3**; cartão via gateway. Baixa **automática** (webhook/retorno) + baixa manual de contingência pela tesouraria. |
| Confirmação | **Webhook Sicoob** + **reconciliação diária** (a doc do payload do Sicoob é fraca; o job de polling garante). Cartão: retorno síncrono + webhook do gateway. |
| Cortesia | Regra global por evento (X→Y, limite por conta/CPF) + **vouchers** rastreáveis. |
| Camisa/kit | Tamanhos + modelos por evento, com **estoque** por tamanho; escolha por participante. |
| Patrocínio | `sponsorships` + `sponsorship_installments` com baixa por parcela (mantido do 061). |
| Check-in | `code` + QR; portaria valida → `used`. |
| Comprovante | Página imprimível + **PDF nativo** (dompdf) com QR — agora é MVP (não deferido). |
| Histórico | Nada some. Soft delete + activity log + colunas de estado. |
| Relatórios | Preview + export **.xlsx** (openspout), filtros mês/ano/período. |

---

## 4. Situações (lookups)

**Evento** (`event_statuses`): `draft` · `published` · `cancelled` · `finished`.
"Inscrições abertas/encerradas" é **derivado** de `sales_start_at`/`sales_end_at` +
capacidade + lote vigente.

**Pedido** (`order_statuses`): `pending` (aguardando pagamento) · `paid` ·
`partially_paid` · `cancelled` · `expired` (reserva venceu) · `refunded`.

**Ingresso** (`ticket_statuses`): `reserved` · `awaiting_payment` · `paid` ·
`confirmed` · `courtesy` · `cancelled` · `refunded` · `transferred` · `used`.

**Pagamento** (`payment_statuses`): `pending` · `paid` · `failed` · `expired` ·
`refunded` · `chargeback`.

---

## 5. Regras funcionais (FR)

### MVP (Fase 1)

**Site público / catálogo**
- **FR-01 — Landing page pública.** Página configurável do evento: hero/banner,
  descrição rica, programação, palestrantes, local + mapa, lote/preço vigente, FAQ,
  CTA de inscrição. SEO básico (title/description/OG). Acessível sem login.
- **FR-02 — Página de detalhe + seleção de ingressos.** Lista tipos de ingresso do
  lote vigente com preço, disponibilidade (derivada) e regras; monta carrinho.

**Conta do inscrito**
- **FR-03 — Cadastro/login.** E-mail+senha com verificação + **Google** (Socialite).
  Recuperação de senha. Perfil (nome, CPF, telefone, dados de nota).
- **FR-04 — Área restrita do inscrito.** "Meus pedidos" e "Meus ingressos": situação
  de pagamento, 2ª via de Pix/boleto, comprovante (PDF/QR), dados de cada participante.

**Compra / checkout**
- **FR-05 — Carrinho + participantes.** N ingressos por pedido; para cada participante:
  nome, e-mail, documento, tamanho/modelo de camisa quando exigido, dados extras.
  **Compra em grupo**: um comprador paga por vários participantes.
- **FR-06 — Lotes.** Preço vigente vem do **lote** ativo (por data e/ou por quantidade
  vendida). Virada automática de lote; bloqueio quando o lote esgota.
- **FR-07 — Checkout + reserva.** Cria pedido `pending` com **reserva temporária** de
  vaga (TTL configurável, ex. 30 min); expira → libera a vaga (`expired`).
- **FR-08 — Pagamento Pix (Sicoob).** Gera cobrança Pix imediata (QR + copia-e-cola);
  confirma por **webhook**; 2ª via na área do inscrito.
- **FR-09 — Pagamento Boleto híbrido (Sicoob).** Registra boleto híbrido (boleto + QR
  Pix na mesma cobrança); confirma por webhook/retorno diário; 2ª via.
- **FR-10 — Pagamento Cartão (gateway).** Tokeniza cartão no gateway (cliente nunca
  trafega PAN pelo nosso backend), autoriza/captura, auto-confirma; suporta parcelas.
- **FR-11 — Confirmação automática de pagamento.** Webhook + reconciliação diária →
  marca pedido/ingressos `paid`/`confirmed`; **ponto único de baixa**
  (`RegisterPayment`). Idempotente (mesmo evento não baixa duas vezes).

**Configuração (admin)**
- **FR-12 — Cadastro/configuração do evento.** Nome, descrição, datas, local, banner,
  capacidade total, janela de vendas, regras, flags (permitir cartão/boleto/pix,
  camisa, kit, transferência, cancelamento pelo usuário, cortesia).
- **FR-13 — Tipos de ingresso + lotes.** CRUD de `ticket_types` (nome, preço,
  capacidade, assentos/ingresso, inclui camisa/kit, público, é cortesia) e `ticket_lots`.
- **FR-14 — Camisas.** Tamanhos + modelos por evento, **com estoque**; "Esgotado"
  bloqueia compra.
- **FR-15 — Regra de cortesia + vouchers.** X pagantes → Y grátis (limite por conta);
  geração/distribuição de vouchers rastreáveis; concessão manual pelo admin.
- **FR-16 — Patrocínio.** Empresa, valor, forma, parcelas; baixa por parcela com
  quem/quando; status agregado.
- **FR-17 — Editor da landing page.** Blocos (hero, texto, programação, palestrantes,
  FAQ, local) editáveis pelo admin sem deploy.

**Tesouraria**
- **FR-18 — Painel de recebimentos.** Previsto × recebido, por forma de pagamento, por
  dia; pendências; conciliação Sicoob (bate cobrança ↔ liquidação).
- **FR-19 — Baixa manual de contingência.** Quando o automático falha, a tesouraria
  registra a baixa (data/forma/quem/comprovante). Quem compra não dá a própria baixa.
- **FR-20 — Devolução/estorno.** Marca `refunded` preservando o pedido; registra
  quem/quando/motivo/valor; para cartão, dispara estorno no gateway quando aplicável.

**Portaria / check-in**
- **FR-21 — Check-in por QR.** Lê `code` → `used` (quem validou + quando); recusa
  inválido/já usado/cancelado; casal/mesa conta N pessoas. Lista de presentes/ausentes.

**Ciclo de vida do ingresso**
- **FR-22 — Cancelamento de pedido/ingresso.** Conforme flag; preserva histórico;
  cancelar **pago** aciona fluxo de estorno (tesouraria).
- **FR-23 — Transferência.** Gated por flag; só ingresso pago; novo titular por
  e-mail/dados (sem depender de "irmãos"). Original → `transferred` (histórico).
- **FR-24 — Cancelamento de evento.** `cancelled`, bloqueia novas compras, preserva
  tudo, aciona fila de estornos.

**Transversais**
- **FR-25 — Comprovante (PDF + QR).** PDF baixável por ingresso + página imprimível.
- **FR-26 — Painel do evento (admin).** Vendidos/reservados/cortesias/cancelados;
  previsto × confirmado; camisas por tamanho/modelo; por lote; por forma de pagamento.
- **FR-27 — Relatórios + .xlsx + filtros (mês/ano/período).** Inscritos, por tipo, por
  lote, financeiro, camisas, cortesias, cancelamentos, check-in, patrocínios.
- **FR-28 — Histórico/auditoria.** Data/hora/usuário em cada ação; nada apagado; soft
  delete + activity log.
- **FR-29 — Notificações transacionais.** E-mail de confirmação de compra, pagamento
  confirmado, boleto/Pix gerado, lembrete do evento. (Sino/push fica pra Fase 2.)

### Fase 2 (deferida)

- **FR-30 — Multi-evento / multi-tenant** (vários eventos ou organizadores).
- **FR-31 — Mais gateways** e split de pagamento.
- **FR-32 — App de portaria dedicado** (offline-first).
- **FR-33 — Cupons de desconto** (além de cortesia).
- **FR-34 — Emissão de nota fiscal** / integração contábil.
- **FR-35 — Programa de afiliados/indicação.**

---

## 6. Perfis e escopo

| Perfil | Vê | Faz |
| --- | --- | --- |
| **Inscrito** | Landing page + **seus** pedidos/ingressos | Compra/reserva; paga (Pix/boleto/cartão); baixa 2ª via; comprovante; transfere/cancela (quando permitido) |
| **Admin** | Tudo do evento | Configura evento/ingressos/lotes/camisas/cortesia/patrocínio; edita landing; painel; relatórios; cancela evento/ingresso |
| **Tesouraria** | Financeiro do evento | Concilia recebimentos; baixa manual de contingência; devolução/estorno; relatório financeiro |
| **Portaria** | Lista de check-in | Valida QR → `used`; presentes/ausentes |

Um usuário pode acumular papéis (ex.: admin que também concilia).

---

## 7. Histórias de usuário

- **US1 — Landing + catálogo público (P1).** Visitante vê o evento e os ingressos do
  lote vigente sem login.
- **US2 — Conta do inscrito (P1).** Cadastro/login (e-mail+Google), verificação,
  recuperação de senha, área restrita.
- **US3 — Compra + checkout + pagamento (P1).** Carrinho, participantes, Pix/boleto/
  cartão, reserva com expiração, confirmação automática.
- **US4 — Configuração do evento (P1, Admin).** Evento, tipos, lotes, camisas,
  cortesia, patrocínio, landing.
- **US5 — Tesouraria (P1).** Conciliação Sicoob, baixa de contingência, devolução/
  estorno, painel financeiro.
- **US6 — Ciclo de vida (P1).** Cancelar pedido/ingresso/evento, transferir, com
  histórico preservado.
- **US7 — Portaria + painel + relatórios (P1).** Check-in QR, painel do evento,
  relatórios .xlsx.

---

## 8. Fora de escopo (MVP)

Multi-evento/multi-tenant, split de pagamento, app de portaria offline, cupons de
desconto, nota fiscal, afiliados, notificações push/sino (só e-mail transacional no MVP).

---

## 9. Integração de pagamento (agora ligada de verdade)

O 061 deixou o "ponto único de baixa" preparado e desligado. Aqui ele está **ligado**:

- **Sicoob API v3** (Pix + boleto híbrido): autenticação **OAuth2 + certificado A1
  (ICP-Brasil, .pfx→.pem)**, Client ID, escopos `cob`/`cobv`/`pix`. Boleto híbrido com
  `"hibrido": true`. Confirmação por **webhook**; como a documentação do payload do
  webhook do Sicoob é reconhecidamente incompleta, um **job de reconciliação diário**
  consulta as cobranças e concilia (defesa em profundidade).
- **Gateway de cartão** (Cielo/Rede ou similar): **tokenização no cliente** (o PAN do
  cartão **nunca** passa pelo nosso backend — requisito de PCI e das regras de
  segurança), autorização/captura, webhook de status, estorno via API.
- **Segredos** (Client ID, certificado, chaves do gateway) ficam em `.env`/secret
  manager, **nunca** no repositório nem no front. O backend nunca recebe nem digita
  dados de cartão — apenas o token do gateway.

> Nota de segurança: nenhuma credencial financeira ou dado de cartão é manipulado em
> texto puro pela aplicação; a captura de cartão é sempre delegada ao SDK/checkout do
> gateway no navegador do inscrito.
