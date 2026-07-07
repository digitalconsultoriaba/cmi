# Contract — Admin: Seções e Itens do Site

Mesmo grupo admin/prefixo `events/{event}` do arquivo `admin-site.md`. Ao salvar seção/item, o `TranslationService` preenche os idiomas ativos (≠ pt) dos campos localizados a partir do PT (provedor nulo → alvos vazios; salvar nunca falha).

## Seções

### `PUT /admin/events/{event}/site/sections/{section}`
Atualiza o `payload` e/ou `isActive` de uma seção (schema validado por `type`).

**Body**: `{ "payload": { ... }, "isActive": true }`
- **200** → `SiteSectionResource` (payload já com mapas `{pt,en,es}` preenchidos).
- **422** → payload fora do schema do tipo (ex.: `program.type` inválido, `stats.value` não numérico).

### `PATCH /admin/events/{event}/site/sections/reorder`
Reordena as seções.

**Body**: `{ "order": [10, 12, 11, ...] }` (ids de seção na nova ordem)
- **200** → `{ data: [ { id, type, sort } ] }`.

## Itens (seções dinâmicas)

Escopo por seção. Suporta aninhamento de **um** nível via `parentItemId` (dia→entradas, categoria→contatos, grupo→logos).

### `GET /admin/events/{event}/site/sections/{section}/items`
- **200** → `{ data: [ { id, parentItemId, sort, payload, children: [...] } ] }` (topo + filhos aninhados).

### `POST /admin/events/{event}/site/sections/{section}/items`
**Body**: `{ "payload": { ... }, "parentItemId": null }`
- **201** → item criado.
- **422** → payload inválido para o tipo da seção; `parentItemId` de outra seção/site → 422.

### `PUT /admin/events/{event}/site/sections/{section}/items/{item}`
- **200** → item atualizado (campos localizados preenchidos).

### `DELETE /admin/events/{event}/site/sections/{section}/items/{item}`
Soft delete (e dos filhos). Preserva histórico.
- **200** → `{ data: { deleted: true } }`.

### `PATCH /admin/events/{event}/site/sections/{section}/items/reorder`
Reordena itens dentro de um escopo (topo ou de um pai).

**Body**: `{ "parentItemId": null, "order": [ ...ids ] }`
- **200** → `{ data: [ { id, sort } ] }`.

## Regras

- Escritas multi-passo (reorder, remoção com filhos) em `DB::transaction` com recálculo de `sort`.
- Situações terminais N/A; remoção é sempre soft delete.
- **403** para papéis fora de admin/treasury; **404** para seção/item de outro site (scopeBindings).
