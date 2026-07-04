# Data Model — 007-checkin-portaria

**Nenhuma tabela nova** — `tickets.used_at`/`validated_by` existem desde a 001.

## Transição movimentada

```
Ticket: paid | confirmed | courtesy ──check-in──► used (terminal, sem desfazer)
        (used_at = agora, validated_by = operador — trilha completa)
```

## Matriz de validação (por status do ticket)

| Situação | Resultado | type | Contexto no payload |
|---|---|---|---|
| paid / confirmed / courtesy | ✅ marca `used` | — | participante, acompanhante, tipo, seats (1\|2) |
| used | ❌ 409 | `already_used` | `usedAt`, `validatedBy` |
| cancelled / refunded | ❌ 409 | `ticket_cancelled` | — |
| transferred | ❌ 409 | `ticket_transferred` | `transferredToCode` (o que vale) |
| reserved / awaiting_payment | ❌ 409 | `not_paid` | — |
| código inexistente | ❌ 404 | `not_found` | — |
| evento cancelado (qualquer status) | ❌ 409 | `event_cancelled` | — |

## Concorrência

`lockForUpdate` na linha do ticket: validações simultâneas do mesmo código se
serializam — a segunda encontra `used` e recusa. Tickets diferentes não se
bloqueiam (fila anda).

## Consultas derivadas (presenças — em PESSOAS)

```
elegíveis  = tickets do evento com status ∈ {paid, confirmed, courtesy, used}
esperados  = Σ assentos dos elegíveis        (casal = 2, join no tipo)
presentes  = Σ assentos dos elegíveis `used`
ausentes   = esperados − presentes
lista      = elegíveis com nome/acompanhante/tipo/código/situação/usedAt/validatedBy
busca      = participant_name | companion_name | code (LIKE)
```

Cancelados/transferidos/expirados NUNCA aparecem (não valem entrada).

## Invariantes (verificáveis em teste)

1. Um código só produz UMA entrada, mesmo sob validações simultâneas.
2. Nenhum status fora de {paid, confirmed, courtesy} jamais vira `used` pela
   portaria.
3. `used` carrega sempre momento + operador.
4. Presentes (pessoas) = soma de assentos dos `used`; casal validado soma 2.
5. Recusa nunca altera nada.
