# Quickstart — 007-checkin-portaria (guia de validação)

Referências: [spec.md](spec.md), [contrato](contracts/gate-api.md),
[data-model](data-model.md), [research](research.md).

## Pré-requisitos

- Specs 001–006 na `main`; `make up && make fresh` (agora inclui o
  `SampleCheckinSeeder` — ingressos prontos para escanear).
- Login: `portaria@dev.local` / `password`.
- Câmera no navegador exige `localhost` (dev ok) ou HTTPS — a digitação manual
  é o caminho de validação garantido.

## Rodar

```bash
make test   # suíte completa (inclui Gate/*)
make dev    # http://localhost:5173/painel → Check-in
```

## Validações por user story

### US1 — Validação
1. Testes: cada linha da matriz do data-model (elegível marca used com trilha;
   used/cancelled/transferred/not_paid/inexistente/evento cancelado recusam com
   o type e contexto certos); casal seats=2; dupla validação → 1 entrada;
   recusa não altera nada.
2. Manual: pegar um código do seed (aba Presenças), digitar no leitor → verde
   com nome; repetir → vermelho "já utilizado" com horário.

### US2 — Painel
1. Logar como `portaria@dev.local` → painel abre direto no Check-in; menu só
   com Check-in; attendee → 403 (RoleRoute).
2. Ativar a câmera (localhost) e escanear o QR de um comprovante PDF baixado
   (spec 004) → verde; QR enquadrado continuamente não dispara rajada.
3. Campo manual funciona identicamente; testar no celular (viewport pequeno).

### US3 — Presenças
1. Aba Presenças: contadores (esperados/presentes/ausentes em pessoas — casal
   conta 2), lista com horário/operador dos presentes.
2. Buscar por nome do acompanhante e por código → filtra.
3. Testes: contadores em pessoas, cancelado/transferido fora da lista, busca.

## Encerramento da spec

- [ ] `make test` verde (todas as suítes)
- [ ] Fluxo manual do leitor validado (digitado no mínimo; câmera se disponível)
- [ ] Merge de `007-checkin-portaria` na `main`
