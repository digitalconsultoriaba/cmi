# Contract — Público: Landing do Site

Rota **aberta** (sem auth), fora do grupo admin. Binding por slug do Site.

## `GET /public/sites/{slug}`

Retorna o Site publicado e visível, resolvido no idioma pedido. Consumido pela landing React em `/site/:slug`.

**Query**: `lang` (opcional; `pt|en|es`; default `pt`).

**Visibilidade (derivada)**: retorna **200** apenas se `isPublished && event.visible_on_site && evento em status publicado`. Caso contrário **404** (não distingue rascunho/oculto de inexistente — não vaza).

- **200** →
```json
{ "data": {
  "slug": "congresso-2026",
  "lang": "pt",
  "availableLanguages": ["pt", "en", "es"],
  "theme": { "bg": "#071E2E", "accent": "#CCA54D", "...": "..." },
  "identity": { "eventName": "...", "logoUrl": "http://.../storage/site/logo.png", "sealUrls": [], "watermarkUrl": null },
  "countdownAt": "2026-09-18T17:00:00Z",
  "seo": { "title": "...", "description": "...", "ogImageUrl": null },
  "sections": [
    { "type": "hero", "sort": 2, "payload": { "titleLine1": "...", "primaryLabel": "...", "primaryHref": "..." } },
    { "type": "speakers", "sort": 6, "payload": { "speakersLabel": "..." },
      "items": [ { "payload": { "name": "...", "role": "...", "talk": "...", "photoUrl": "http://.../storage/site/s1.jpg" }, "children": [] } ] }
  ]
} }
```

**Resolução de idioma**: cada campo localizado `{pt,en,es}` é achatado para o valor do `lang` pedido; ausente → cai no `pt`. Campos de path viram `...Url` absolutos (via `Storage::disk('public')->url`). Seções `isActive=false` e listas vazias **são omitidas**. Seções vêm ordenadas por `sort`.

- **404** → slug inexistente, site em rascunho, ou evento oculto/não publicado.

## Notas de renderização (cliente)

- O `lang` troca via seletor na navbar → nova chamada `?lang=` (ou cache local dos 3 idiomas).
- `theme` aplicado como CSS variables; `countdownAt` alimenta o countdown; `seo` alimenta `<title>`/meta por idioma.
- Nenhum dado sensível; somente conteúdo público do site.
