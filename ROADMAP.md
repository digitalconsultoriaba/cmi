# Roadmap de Specs — Plataforma de Eventos (cmi)

O projeto é dividido em **specs funcionais** (Spec Kit): cada uma passa pelo ciclo
`/speckit-specify` → `/speckit-clarify` → `/speckit-plan` → `/speckit-tasks` →
`/speckit-implement`, em branch próprio, e entrega **backend + frontend + testes** da
sua área. O pacote em `base/` é material de referência; a constituição está em
`.specify/memory/constitution.md`.

| # | Spec | Escopo | Origem (tasks.md da base) | Status |
|---|---|---|---|---|
| 001 | **fundacao** | Scaffold Laravel 11 + React 18 (Vite) + Sanctum; extração do domínio do 061 (sem acoplamentos); migrations, models, lookups, seeders; RBAC (roles, middleware, policies); Docker/make | Fases 0 + 1 | ⏳ |
| 002 | **auth-inscrito** | Cadastro e-mail+senha, verificação, reset; login Google (Socialite); `/auth/me`; contexto de auth + rotas protegidas no front | Fase 2 | ⏳ |
| 003 | **config-evento** | Admin: evento (CRUD/publish/cancel/banner), tipos de ingresso, lotes, camisas com estoque, regra de cortesia + vouchers, patrocínio, editor da landing (blocos); telas admin correspondentes | Fase 3 + T084/T087 | ⏳ |
| 004 | **catalogo-compra** | Landing pública renderizada (blocos), catálogo do lote vigente, carrinho + participantes (grupo/casal), reserva com TTL + expiração, área do inscrito (meus pedidos/ingressos), comprovante PDF+QR | Fase 4 + T074 + T081/T083 (parcial) | ⏳ |
| 005 | **pagamento** | `PaymentGatewayContract`, `SicoobClient` (OAuth2+mTLS), Pix, boleto híbrido, cartão tokenizado, webhooks (dedupe + reconsulta), `RegisterPayment`, `ReconcilePayments`, baixa manual de contingência; checkout front (Pix/boleto/cartão + polling); tesouraria: recebimentos + conciliação | Fase 5 + T073/T082/T085 (parcial) | ⏳ |
| 006 | **ciclo-vida-suporte** | Cancelamento (pedido/ingresso/evento), transferência por e-mail, estorno (`RefundPayment`), suporte (cases + notas); telas do inscrito e tesouraria correspondentes | Fase 6 | ⏳ |
| 007 | **checkin-portaria** | Check-in por QR → `used` (recusa inválido/usado/cancelado; casal conta 2), lista presentes/ausentes; `CheckinPanel` (leitor QR) | Fase 7 (T070) + T086 | ⏳ |
| 008 | **painel-relatorios** | Dashboard do evento (contagens, previsto×confirmado, camisas, por lote/forma), relatórios + export .xlsx (filtros mês/ano/período), trilha de auditoria, financeiro da tesouraria | Fase 7 (T071–T073) | ⏳ |

## Regras

- **Ordem**: 001 → 002 → 003 → 004 → 005 → 006 → 007 → 008 (dependência natural;
  007 e 008 podem correr em paralelo após 005).
- **Testes e frontend não são specs separadas** — cada spec inclui os seus
  (Fases 8 e 9 da base foram diluídas).
- Seeders de demonstração (`SampleEventSeeder`, `SampleCheckinSeeder`) evoluem
  dentro das specs que criam as respectivas entidades.
- Fase 2 do produto (multi-evento, split, app offline, cupons, NF, afiliados —
  FR-30…35) fica fora deste roadmap; quando chegar a hora, vira spec nova.

## Bloqueadores externos (fechar antes da spec 005)

1. Gateway de cartão (Cielo/Rede) — define SDK de tokenização e webhook.
2. Certificado A1 Sicoob + aplicação no Portal Developers + escopos `cob`/`cobv`/`pix`.
3. Credenciais Google OAuth (necessárias já na spec 002).
4. Domínio + HTTPS (webhooks exigem URL pública).
5. Política de reembolso (prazo/percentual) — necessária na spec 006.
