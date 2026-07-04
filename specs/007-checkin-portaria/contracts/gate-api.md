# Contrato — API da portaria (007)

Envelope/erros da 001. Rotas sob `require.role:gate,admin` (portaria opera;
admin supervisiona).

## Validação

### `POST /api/gate/checkin`

```json
{ "code": "tck-abc123..." }   // normalizado: trim + maiúsculas
```

- **200** (entrada registrada):

```json
{
  "data": {
    "code": "TCK-ABC123...",
    "participantName": "Ana Silva",
    "companionName": "Beto Silva",
    "ticketTypeName": "Casal",
    "seats": 2,
    "usedAt": "2026-07-04T19:02:11Z"
  }
}
```

- **Recusas** (shape padrão + contexto): 409 `already_used`
  (`errors: { usedAt, validatedBy }`) · 409 `ticket_cancelled` · 409
  `ticket_transferred` (`errors: { transferredToCode }`) · 409 `not_paid` ·
  409 `event_cancelled` · 404 `not_found` · 422 código vazio.
- Recusa NUNCA altera estado; sucesso é atômico (lock na linha do ticket).

## Presenças

### `GET /api/gate/attendance?search=`

```json
{
  "data": {
    "expectedPeople": 62,
    "presentPeople": 38,
    "absentPeople": 24,
    "tickets": [
      {
        "code": "TCK-…", "participantName": "…", "companionName": null,
        "ticketTypeName": "Individual", "seats": 1,
        "status": "used", "usedAt": "…", "validatedBy": "Portaria Dev"
      }
    ]
  }
}
```

- Apenas elegíveis (paid/confirmed/courtesy/used); contadores em pessoas;
  `search` filtra por participante, acompanhante ou código.

## Contrato de frontend (painel `/painel/checkin`)

- Acesso: papéis `gate` e `admin`; gate-only cai direto nesta tela ao abrir o
  painel.
- Aba **Leitor**: câmera (html5-qrcode) + campo manual; resultado em tela cheia
  (verde: nomes + tipo + "2 pessoas" no casal / vermelho: motivo + contexto)
  por ~2,5s; debounce de 5s por código repetido; mobile-first.
- Aba **Presenças**: contadores grandes + lista com busca; refresh manual.
- Falha de rede: aviso claro, nada marcado.

## Seed de demonstração

`SampleCheckinSeeder` (dev): ~30 ingressos confirmados no evento demo
(individuais + 2 casais + 2 cortesias; ~5 já utilizados) via pedidos pagos.
