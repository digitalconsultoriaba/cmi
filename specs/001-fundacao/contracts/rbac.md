# Contrato — RBAC (fundação)

## Papéis (seeded, imutáveis em runtime)

| slug | Acesso (visão geral; policies detalhadas nas specs de cada área) |
|---|---|
| `admin` | tudo do evento: configuração, cortesias, patrocínio, relatórios |
| `treasury` | pagamentos, conciliação, baixa manual, estorno, financeiro |
| `gate` | check-in |
| `attendee` | os próprios pedidos/ingressos; comprar; suporte |

- Relação usuário↔papel é N:N (`role_user`); acumular papéis é permitido.
- `attendee` é atribuído por default no cadastro (fluxo na spec 002).
- **Quem compra não dá a própria baixa** (403) — regra aplicada na spec 005, mas o
  papel `treasury` já nasce separado por isso.

## Middleware `require.role`

- Registro: alias `require.role`, uso `->middleware('require.role:admin,treasury')`.
- Semântica: usuário autenticado precisa de **ao menos um** dos papéis listados.
- Sem sessão → 401 `unauthenticated`. Autenticado sem papel → 403 `forbidden`
  (envelope de `api-conventions.md`), sem revelar quais papéis a rota exige.

## Policies nesta spec

- `EventPolicy`: `update`/`publish`/`cancel` → só `admin`; `view` de evento
  não publicado → só `admin`. Policies de pagamento (treasury), check-in (gate) e
  escopo do inscrito chegam nas specs 005/007/004 respectivamente — este contrato
  reserva a matriz acima como referência.

## Verificação (testes desta spec)

1. Rota protegida com `require.role:admin`: anônimo → 401; `attendee` → 403;
   `admin` → 200; usuário com papéis `attendee`+`admin` → 200.
2. Resposta 403 segue o envelope de erro padrão.
3. `RoleSeeder` cria exatamente os 4 papéis com slugs estáveis.
