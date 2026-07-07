# Contract — Admin: Site (config, tema, SEO, publicação)

Grupo `admin` (`auth:sanctum` + `require.role:admin,treasury` + `scopeBindings`), prefixo `events/{event}`. Sucesso `{ data }` camelCase; erros `{ message, type, status, errors? }` (422 validação, 409 regra, 403 papel).

## `GET /admin/events/{event}/site`

Retorna o Site do evento; **cria sob demanda** (`ensureSite`) se ainda não existe (com seções default por tipo e slug sugerido a partir do evento).

**200** →
```json
{ "data": {
  "id": 1, "eventId": 1, "slug": "congresso-2026",
  "theme": { "bg": "#071E2E", "surface": "#191376", "accent": "#CCA54D", "accentHover": "#E3C173", "textLight": "#EAECF0", "textMuted": "#B9C6D4", "blue": "#7FA0C8" },
  "identity": { "eventName": "Congresso...", "logoPath": "site/logo.png", "sealPaths": [], "watermarkPath": null },
  "countdownAt": "2026-09-18T17:00:00Z",
  "seo": { "title": { "pt": "...", "en": "", "es": "" }, "description": { "pt": "...", "en": "", "es": "" }, "ogImagePath": null },
  "activeLanguages": ["pt"],
  "isPublished": false, "publishedAt": null,
  "sections": [ { "id": 10, "type": "hero", "sort": 2, "isActive": true, "payload": { } } ]
} }
```
`sections` inclui todas as seções (com itens embutidos quando dinâmicas, via `SiteSectionResource`).

## `PUT /admin/events/{event}/site`

Atualiza config/tema/SEO/idiomas/slug/data. Dispara `TranslationService` nos campos localizados do SEO.

**Body** (parcial aceito): `slug`, `theme`, `identity`, `countdownAt`, `seo`, `activeLanguages`.

- **200** → Site atualizado (mesmo shape do GET).
- **422** → `slug` inválido/duplicado, `activeLanguages` sem `pt` ou fora dos suportados.

## `POST /admin/events/{event}/site/publish`

Seta `published_at`/`published_by` (idempotente).

- **200** → `{ data: { isPublished: true, publishedAt } }`.

## `POST /admin/events/{event}/site/unpublish`

Zera `published_at` (idempotente).

- **200** → `{ data: { isPublished: false, publishedAt: null } }`.

## Erros comuns

- **403** → papel `gate`/`attendee` em qualquer rota admin do Site.
- **404** → evento inexistente/soft-deleted.
