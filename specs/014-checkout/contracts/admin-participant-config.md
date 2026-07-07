# Contract — Admin: Categorias, campos e afiliações

Grupo `admin` (`auth:sanctum` + `require.role:admin,treasury` + `scopeBindings`), prefixo `events/{event}`. Sucesso `{ data }` camelCase; erros na shape padrão.

## Categorias de participante

### `GET /admin/events/{event}/participant-categories`
- **200** → `{ data: [ { id, key, label, sort, isActive, fields: [ { id, key, label, type, required, sort, config } ] } ] }`

### `POST /admin/events/{event}/participant-categories`
**Body**: `{ key, label, sort?, isActive? }` → **201**.

### `PUT /admin/events/{event}/participant-categories/{category}`
Atualiza rótulo/ordem/ativo. **200**.

### `DELETE /admin/events/{event}/participant-categories/{category}`
Soft delete (categoria sem uso). **200**. Categoria já usada por inscrições permanece referenciável pelo snapshot (não apaga histórico).

## Campos da categoria

### `POST /admin/events/{event}/participant-categories/{category}/fields`
**Body**: `{ key, label, type: text|affiliation|country|city|conditional, required?, sort?, config? }` → **201**.
- `config` para `conditional`: `{ "question": "Possui cargo?", "reveals": "cargo" }`.

### `PUT .../fields/{field}` / `DELETE .../fields/{field}`
Atualiza/remove um campo (soft delete). **200**.

### `PATCH .../fields/reorder`
**Body**: `{ order: [ ...ids ] }` → **200**.

## Afiliações ("lojas")

### `GET /admin/events/{event}/affiliations`
- **200** → `{ data: [ { id, name, sort, isActive } ] }`

### `POST /admin/events/{event}/affiliations`
**Body**: `{ name, sort?, isActive? }` → **201**.

### `PUT .../affiliations/{affiliation}` / `DELETE .../affiliations/{affiliation}`
Atualiza/remove (soft delete). **200**.

### `POST /admin/events/{event}/affiliations/import`
Importa uma lista (texto/linhas) para popular a fonte do autocomplete. **200** → `{ data: { imported: N } }`.

## Regras

- Escritas multi-passo (reorder, import) em `DB::transaction`.
- **403** para papéis fora de admin/treasury; **404** para recurso de outro evento.
- Standalone: `key`/`label`/`type` são genéricos; os rótulos maçônicos vivem em `label`/dados.
- Uma **seed** cria a configuração padrão do seminário (categorias "Irmão da GLMEES" e "Irmão de outra potência" + campos), sem `migrate:fresh`.
