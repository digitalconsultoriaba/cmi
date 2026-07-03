# Constituição — Plataforma de Eventos (cmi)

Produto standalone de venda e gestão de ingressos para um seminário internacional,
desmembrado do módulo 061 (Gestão de Eventos da Grande Loja). Estas regras valem para
**todas** as specs; nenhuma spec pode contrariá-las sem emenda registrada aqui.

## Princípios fundamentais

### I. Standalone — zero acoplamento com a maçonaria
Nenhum conceito de Grande Loja, loja, irmão, `owner_type` polimórfico, matriz de
módulos (047) ou integração 054/055 entra neste código. O dono é o organizador único,
implícito. Autorização é RBAC simples com 4 papéis: `admin`, `treasury`, `gate`,
`attendee`. Um usuário pode acumular papéis. Escopo inicial single-event, mas `events`
é tabela (não constante) para permitir multi-evento na Fase 2 sem reescrita.

### II. order → tickets com snapshot; estado derivado, nunca armazenado
Um pedido agrega N ingressos (um por participante), cada ticket com **snapshot** de
preço/nome/camisa/lote no momento da compra. Disponibilidade, "inscrições abertas",
lote vigente, "esgotado" e previsto×confirmado são **sempre derivados** — nunca
persistidos como estado editável. Contagens de vaga/lote/estoque/cortesia acontecem
**dentro de `DB::transaction` com recontagem** (proteção contra race em compras
concorrentes).

### III. Ponto único de baixa, idempotente (NÃO NEGOCIÁVEL)
Toda confirmação de pagamento — webhook Sicoob, retorno do gateway de cartão,
reconciliação diária ou baixa manual da tesouraria — passa por `RegisterPayment`.
É idempotente: o mesmo evento externo nunca baixa duas vezes (unique
`provider + provider_charge_id`; dedupe em `webhook_events`). Webhook **nunca** é
fonte única de verdade: sempre reconsultar a cobrança no provedor antes de baixar, e o
job diário `ReconcilePayments` é obrigatório (a doc do webhook Sicoob é incompleta).
**Quem compra não dá a própria baixa** (403).

### IV. Segurança de pagamento (NÃO NEGOCIÁVEL)
O PAN/CVV/validade do cartão **nunca** trafega pelo nosso backend — tokenização
client-side pelo SDK do gateway; o backend recebe só o token. Certificado A1 Sicoob,
Client ID e chaves ficam em `.env`/secret manager, fora do VCS e do front. Webhooks
verificam assinatura/allowlist. URLs e QR públicos usam `code`/`uuid`, nunca id
sequencial. Provedores ficam atrás de `PaymentGatewayContract` (trocar sem reescrever).

### V. Histórico — nada some
Soft delete em todas as tabelas de negócio + activity log + colunas de estado
(`cancelled_at`, `cancelled_by`, `cancel_reason`, …) + `created_by`/`updated_by`.
Situações terminais (cancelled/refunded/used/expired) rejeitam transição com 409.
Cancelar evento/pedido/ingresso preserva o registro. Toda ação administrativa registra
quem/quando.

### VI. Specs por área funcional
Todo trabalho nasce de uma spec própria em `specs/NNN-nome/` (specify → clarify →
plan → tasks → implement). Cada spec entrega backend + frontend + testes da sua área
(corte por domínio, não por camada). O pacote em `base/` é **material de referência**,
nunca fonte direta de implementação. Uma spec não pode redefinir o que outra já
entregou — mudanças geram nova spec ou emenda.

## Stack e convenções

- **Backend**: Laravel 12 (PHP 8.3+), MySQL 8, Redis, Sanctum SPA (cookie),
  Socialite (Google), openspout (.xlsx), barryvdh/laravel-dompdf + simple-qrcode
  (comprovante), filas via `queue`.
- **Frontend**: React 18 + Vite, React Query para estado do servidor.
- **API**: respostas `{ data: ... }` com chaves **camelCase**; erros na shape de
  domínio (`message`/`type`/`errors`/`status`); FormRequest → 422; regra de
  negócio violada → 409; fora de papel/escopo → 403.
- **Dinheiro**: DECIMAL(10,2). **Datas**: UTC no banco.
- **Domínio** em `app/Domain/Events`; services com escrita multi-passo sempre em
  `DB::transaction`.
- **Idioma**: código/identificadores em inglês; UI, mensagens de erro e documentação
  em pt-BR.
- **LGPD**: minimizar dados pessoais (CPF/e-mail); nunca logar dado sensível.

## Fluxo de trabalho e qualidade

- Uma spec por vez, em branch próprio (`NNN-nome`), mergeado na `main` ao concluir.
- Feature tests (MySQL de teste) cobrem o caminho feliz + regras de negócio da spec
  (409/403/escopo) antes do merge; testes falhando bloqueiam merge.
- Segredos jamais commitados — `.env.example` só com placeholders.
- Migrations aditivas; renomeações destrutivas exigem justificativa na spec.

## Governança

Esta constituição prevalece sobre qualquer spec, plan ou task. Emendas exigem:
registro da mudança neste arquivo (com versão e data), justificativa, e verificação
de impacto nas specs já entregues. Toda revisão de PR verifica conformidade com os
princípios I–VI; complexidade além do necessário deve ser justificada na spec.

## Emendas

- **1.1.0 (2026-07-03)** — Laravel 11 → **Laravel 12**. Justificativa: o Laravel 11
  saiu do suporte de segurança em mar/2026 e o Composer bloqueia sua instalação por
  advisories ativos (PKSA-m5cs-t1y6-qpcs e outros); plataforma de pagamento não pode
  nascer em framework EOL. Impacto nas specs entregues: nenhum (descoberto durante a
  implementação da 001; API do framework compatível). Specs 002+ herdam o Laravel 12.

**Version**: 1.1.0 | **Ratified**: 2026-07-03 | **Last Amended**: 2026-07-03
