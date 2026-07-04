# Research — 007-checkin-portaria

Decisões técnicas do check-in. Base: specs 001–006 (código público no QR,
transições terminais, papéis).

---

## Decisão 1: `CheckinService` — lock na LINHA do ticket (corrida entre dispositivos)

**Decisão**: `CheckinService::checkIn(string $code, User $operator)` em
`DB::transaction` com `lockForUpdate` **na linha do ticket** (não no evento —
check-ins de tickets diferentes não devem se serializar):

1. Normaliza o código (trim + maiúsculas);
2. Busca por `code` com lock → inexistente: 404 padrão;
3. Guardas em ordem: evento cancelado → 409 `event_cancelled`; status `used` →
   409 `already_used` **com `usedAt` e `validatedBy` no payload de erro**;
   `cancelled` → `ticket_cancelled`; `transferred` → `ticket_transferred` (dica
   do ingresso novo); `reserved`/`awaiting_payment` → `not_paid`;
4. Elegível (`paid`/`confirmed`/`courtesy`): `used_at = now()`,
   `validated_by = operador`, `transitionTo(used)`.

Dois dispositivos no mesmo código: o segundo espera o lock e encontra `used` →
recusa correta. Exatamente uma entrada (FR-003/SC-003).

**Rationale**: mesmo padrão de corrida das specs 004/005, com granularidade
mínima (linha) para não criar gargalo na fila.

---

## Decisão 2: Resposta rica para a portaria (sucesso E recusa)

**Decisão**: sucesso → `{ participantName, companionName?, ticketTypeName,
seats (1|2), usedAt }`. Recusa → shape de erro da 001 com `type` específico e
`errors` carregando contexto (`usedAt`/`validatedBy` no já-utilizado;
`transferredToCode` no transferido — a portaria pode conferir o novo na hora).

**Rationale**: FR-002/FR-005; a tela verde/vermelha precisa de conteúdo, não só
de status.

---

## Decisão 3: Leitor de QR — `html5-qrcode` no navegador

**Decisão**: dependência nova de frontend `html5-qrcode` (leitura pela câmera
via getUserMedia, cross-browser incluindo Safari/iOS — o celular da portaria é
imprevisível). Digitação manual como caminho equivalente (FR-007).

**Rationale**: sem aplicativo instalado (Assumption); API simples
(start/stop + callback por leitura).

**Alternativas consideradas**: `BarcodeDetector` nativo — rejeitado: sem
suporte no Safari/iOS; `@zxing/browser` — equivalente, porém mais verboso para
o mesmo resultado.

---

## Decisão 4: Proteção de re-leitura no painel (rajada)

**Decisão**: o painel ignora leituras do MESMO código por 5 segundos (último
código + timestamp em ref) e pausa o leitor enquanto o resultado toma a tela
(~2,5s), retomando em seguida. O backend continua sendo a garantia real
(Decisão 1) — o debounce é UX.

**Rationale**: FR-008; leitores disparam múltiplos callbacks por segundo com o
QR enquadrado.

---

## Decisão 5: Presenças por consulta derivada (pessoas, não tickets)

**Decisão**: `GET /gate/attendance?search=` retorna contadores + lista:
- **Esperados** = tickets em {paid, confirmed, courtesy, used}, em PESSOAS
  (soma de assentos — casal 2, via join no tipo, mesmo cálculo do
  `ticketsSold` da 004);
- **Presentes** = subconjunto `used` (pessoas); **ausentes** = diferença;
- Lista com nome, acompanhante, tipo, código, situação, `usedAt`/`validatedBy`;
  busca por participante/acompanhante/código.

**Rationale**: FR-009/SC-004; derivação pura — nenhuma coluna nova (princípio
II).

---

## Decisão 6: Portaria entra no painel existente

**Decisão**: `/painel` passa a aceitar o papel `gate` (RoleRoute
`['admin','treasury','gate']`); `PainelHome` para gate-only cai direto no
Check-in; item de menu "Check-in" (gate + admin). Página única
`Checkin.jsx` com duas abas: **Leitor** (câmera + campo manual + resultado em
tela cheia verde/vermelho) e **Presenças** (contadores + lista + busca) —
layout mobile-first (a portaria usa celular).

**Rationale**: FR-006/FR-008; terceiro papel no mesmo chrome (padrão da 005
para treasury).

---

## Decisão 7: `SampleCheckinSeeder` (dev) para demo da portaria

**Decisão**: seeder dev-only criando ~30 ingressos confirmados no evento demo
(mistura: individuais, 2 casais, 2 cortesias, ~5 já utilizados) via pedidos
pagos — regra do ROADMAP (seeders evoluem na spec que usa). Entra no
`DatabaseSeeder` fora de produção, depois do `SampleEventSeeder`.

**Rationale**: FR-011; permite abrir o painel e escanear/buscar sem montar
massa manualmente.

---

## Riscos / notas

- **Câmera exige HTTPS ou localhost**: em dev, `localhost:5173` funciona;
  acesso pelo celular na rede local exigirá HTTPS (nota no quickstart — a
  digitação manual cobre a validação manual da entrega).
- **Parcialmente pago**: tickets ficam `reserved` (a 005 não confirma por valor
  errado) → recusa `not_paid` automaticamente ✓.
- Zero migrations; 1 dependência nova de front (`html5-qrcode`).
