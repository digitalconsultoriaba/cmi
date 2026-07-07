# Data Model — 013 Site do evento (CMS + landing)

Convenções: colunas em `snake_case` (inglês); todas as tabelas herdam `BaseModel` → `SoftDeletes` + `created_by`/`updated_by` (`TracksAuditors`) + `timestamps`. Payload JSON usa **chaves camelCase** (mesmas devolvidas pela API). Campos de texto localizados são mapas `{ pt, en, es }`; os demais são escalares (não traduzíveis).

## Tabelas

### `event_sites` (1:1 com `events`)

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `event_id` | FK → events | **unique** (um site por evento) |
| `slug` | string | **unique**, URL-safe (URL pública) |
| `theme` | json | design tokens (cores/gradiente/superfície/dourado/hover/textos/azul) |
| `identity` | json | `{ logoPath, sealPaths[], watermarkPath, eventName }` |
| `countdown_at` | datetime **nullable** | data-alvo do countdown (UTC no banco) |
| `seo` | json | `{ title:{pt,en,es}, description:{pt,en,es}, ogImagePath }` |
| `active_languages` | json | subconjunto de `['pt','en','es']`; `pt` sempre presente |
| `published_at` | datetime **nullable** | publicação; `isPublished = published_at !== null` |
| `published_by` | FK → users nullable | quem publicou |
| auditoria | | `created_by`, `updated_by`, `deleted_at`, timestamps |

**Derivados (nunca coluna)**: `isPublished` (de `published_at`); `isPubliclyVisible = isPublished && event.visible_on_site && event.isPublishedStatus`.

**Validações**: `slug` único e casável em `[a-z0-9-]+`; `active_languages` ⊆ suportados e contém `pt`; `theme` com as chaves de token conhecidas (extras ignorados).

### `event_site_sections` (uma linha por seção)

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `event_site_id` | FK → event_sites | |
| `type` | string(20) | `SiteSectionType` (ver abaixo) |
| `sort` | int | ordem na landing |
| `is_active` | boolean | seção desativada não renderiza |
| `payload` | json | campos da seção (schema por tipo) |
| auditoria | | soft delete + audit |

**Índice**: `(event_site_id, sort)`. **Unique lógico**: um `type` por site (garantido na criação/`ensureSite`).

### `event_site_items` (itens das seções dinâmicas)

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `event_site_section_id` | FK → event_site_sections | |
| `parent_item_id` | FK → event_site_items **nullable** | um nível de aninhamento |
| `sort` | int | ordem dentro do pai/seção |
| `payload` | json | campos do item (schema por seção) |
| auditoria | | soft delete + audit |

**Índice**: `(event_site_section_id, parent_item_id, sort)`.

## Enum `SiteSectionType`

`config` · `navbar` · `hero` · `stats` · `about` · `pillars` · `speakers` · `program` · `local` · `info` · `sponsors` · `testimonials` · `faq` · `cta` · `footer` · `legal`

Dinâmicas (usam `event_site_items`): `stats`, `pillars`, `speakers`, `program`, `info`, `sponsors`, `testimonials`, `faq`. As demais guardam tudo no `payload` da seção.

## Schema por seção (payload) — `L` = localizado `{pt,en,es}`, `P` = escalar

**config** (também espelha campos do site para edição): `eventName(P)`, `slug(P)`, `countdownAt(P)`, `theme(P)`, `identity(P)`, `seo{title(L),description(L)}`, `activeLanguages(P)`.

**navbar**: `ctaLabel(L)`, `ctaHref(P)`, `anchors[]` de `{ label(L), href(P) }`.

**hero**: `titleLine1(L)`, `titleLine2(L)`, `titleLine3(L)`, `subtitle(L)`, `dateText(L)`, `locationText(L)`, `primaryLabel(L)`, `primaryHref(P)`, `secondaryLabel(L)`, `secondaryHref(P)`.

**stats** *(item)*: `icon(P: people|globe|mic|temple)`, `value(P número)`, `title(L)`, `subtitle(L)`.

**about**: `aboutLabel(L)`, `aboutTitle(L)`, `aboutText(L)`, `aboutBtn(L)`, `aboutHref(P)`, `gallery(P: paths[])` (1 grande + N menores).

**pillars** *(item)*: `icon(P: connect|learn|transform)`, `title(L)`, `items(L: string[])`.

**speakers**: seção `speakersLabel(L)`, `speakersBtn(L)`, `speakersHref(P)`. *(item)*: `name(P)`, `role(L)`, `org(P)`, `talk(L)`, `day(P)`, `time(P)`, `photo(P)`.

**program**: seção `progLabel(L)`, `progBtn(L)`, `progHref(P)`. *(item = dia)*: `label(L)`, `date(P)`. *(filho = entrada)*: `type(P: open|talk|debate|coffee|lunch|workshop|results|coquetel)`, `t1(P)`, `t2(P)`, `title(L)`, `speaker(P?)`, `org(P?)`, `subtitle(L?)`, `desc(L?)`, `activities(L?: [{label,text}])`.

**local**: `localLabel(L)`, `placeName(P)`, `localText(L)`, `localBtn(L)`, `localHref(P)`, `venueName(P)`, `venueAddress(L)`, `mapBtn(L)`, `mapHref(P)`, `venuePhoto(P)`.

**info**: seção `infoLabel(L)`. *(item = categoria)*: `icon(P: bed|transport|tourism|food|money)`, `title(L)`, `text(L)`, `linkLabel(L)`, `modalText(L)`. *(filho = contato)*: `name(P)`, `desc(L)`, `info(L)`, `address(L)`, `phone(P)`, `site(P)`.

**sponsors**: seção `sponsorsLabel(L)`. *(item = grupo)*: `title(L)`. *(filho = logo)*: `name(P)`, `href(P)`, `src(P)`.

**testimonials**: seção `testiLabel(L)`. *(item)*: `text(L)`, `name(P)`, `role(L)`, `photo(P?)`.

**faq**: seção `faqLabel(L)`. *(item)*: `q(L)`, `a(L)`.

**cta**: `ctaKicker(L)`, `ctaTitle(L)`, `ctaSubtitle(L)`, `ctaDate(L)`, `ctaLocation(L)`, `ctaBtnLabel(L)`, `ctaBtnHref(P)`, `portraitLeftName(P)`, `portraitLeftRole(L)`, `portraitLeftPhoto(P)`, `portraitRightName(P)`, `portraitRightRole(L)`, `portraitRightPhoto(P)`.

**footer**: `footerTagline(L)`, `contactEmail(P)`, `contactPhone(P)`, `contactWhatsapp(P)`, `whatsappHref(P)`, `instagramHref(P)`, `facebookHref(P)`, `youtubeHref(P)`, `linkedinHref(P)`, `copyright(L)`, `privacyHref(P)`, `termsHref(P)`.

**legal**: `privacy{ title(L), paragraphs(L: string[]) }`, `terms{ title(L), paragraphs(L: string[]) }`.

## Relacionamentos

- `Event` **1—1** `EventSite` (`hasOne`, `event_id` unique). `Event::eventSite()` + `ensureSite()`.
- `EventSite` **1—N** `EventSiteSection` (`hasMany`, ordenado por `sort`).
- `EventSiteSection` **1—N** `EventSiteItem` (`hasMany`, `parent_item_id` null = topo).
- `EventSiteItem` **1—N** `EventSiteItem` (auto-rel, `children()` por `parent_item_id`, ordenado por `sort`).

## Regras de estado e i18n

- **Publicação**: `publish()` seta `published_at`/`published_by`; `unpublish()` zera. Idempotente. Registrado no histórico.
- **i18n**: ao salvar seção/item, `TranslationService` percorre os campos `L` do schema e, para cada idioma ativo ≠ `pt` sem valor, chama o provedor a partir do `pt`; provedor nulo → mantém vazio (manual). Campos `P` nunca são tocados. Leitura pública resolve idioma pedido → fallback `pt`.
- **Reordenação**: `reorder` recalcula `sort` sequencial dentro do escopo (seção ou pai) em `DB::transaction`.
- **Remoção**: soft delete de seção/item preserva histórico; nada físico.
- **Ativação**: `is_active=false` (ou seção/lista vazia) → seção não renderiza (sem espaço vazio).

## Validações-chave (FormRequests)

- `UpdateEventSiteRequest`: `slug` (unique ignorando o próprio, regex), `theme` (array de tokens), `countdownAt` (date nullable), `activeLanguages` (array ⊆ suportados, contém `pt`), `seo` (array).
- `UpdateSiteSectionRequest`: `payload` (array), `isActive` (boolean) — validação condicional por `type` (ex.: `stats.value` numérico, `program.type` em enum, `icon` em enum da seção).
- `StoreSiteItemRequest`/`UpdateSiteItemRequest`: `payload` (array), `parentItemId` (nullable, existe e pertence ao mesmo site), `sort` (int).
- `UploadSiteMediaRequest`: `file` (image, mimes jpeg/png/webp/svg, máx ~4 MB).
