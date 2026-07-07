---
description: "Task list — 013 Site do evento (CMS + landing 1:1, multi-idioma)"
---

# Tasks: Site do evento — CMS completo + landing pública 1:1 (multi-idioma)

**Input**: Design documents from `/specs/013-site-cms/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: incluídos — a constituição exige Feature tests (MySQL `app_test`) cobrindo caminho feliz + regras (409/403/escopo) antes do merge.

**Organization**: por user story (P1–P5), cada uma entregável e testável de forma independente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos distintos, sem dependência pendente)
- Caminhos são absolutos ao repo (raiz `glmees-cmi/`). PHP/Composer/artisan rodam via Docker (`docker compose run --rm php ...`).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: config e scaffolding compartilhados por todas as histórias.

- [X] T001 Criar `config/site.php` com `base_locale=pt`, `locales=['pt','en','es']`, `translation.provider=null` (lido de `env('SITE_TRANSLATION_PROVIDER')`) e adicionar placeholders correspondentes em `.env.example` (nenhum segredo real).
- [X] T002 [P] Criar enum de constantes `app/Domain/Events/Models/SiteSectionType.php` (config, navbar, hero, stats, about, pillars, speakers, program, local, info, sponsors, testimonials, faq, cta, footer, legal) + helpers `dynamic()` (lista das seções com itens) e `all()`.
- [X] T003 [P] Criar helper de schema de campos localizáveis `app/Domain/Events/Services/Translation/SectionSchema.php` (mapa tipo → lista de caminhos de campos `L` por seção/item/filho, conforme data-model.md).
- [X] T004 [P] Adicionar diretório de testes `tests/Feature/Site/` e um `SiteTestCase.php` com helpers (`admin()`, `gate()`, `attendee()`, `makeEventWithSite()`, `publishSite()`, `paidAdminHeaders()`), seguindo o padrão de `tests/Feature/Multiday/MultidayTestCase.php`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: migrations, models, service de tradução e wiring — bloqueiam todas as histórias.

- [X] T005 [P] Migration `database/migrations/2026_07_07_100000_create_event_sites_table.php` (event_id unique FK, slug unique, theme json, identity json, countdown_at datetime nullable, seo json, active_languages json, published_at datetime nullable, published_by FK nullable, soft delete + audit).
- [X] T006 [P] Migration `database/migrations/2026_07_07_100010_create_event_site_sections_table.php` (event_site_id FK, type string(20), sort int, is_active bool, payload json, index (event_site_id, sort), soft delete + audit).
- [X] T007 [P] Migration `database/migrations/2026_07_07_100020_create_event_site_items_table.php` (event_site_section_id FK, parent_item_id FK nullable self, sort int, payload json, index (event_site_section_id, parent_item_id, sort), soft delete + audit).
- [X] T008 [P] Model `app/Domain/Events/Models/EventSite.php` extends BaseModel: casts (theme/identity/seo/active_languages array, countdown_at datetime, published_at datetime), relações `event()`, `sections()`, `publishedBy()`, derivados `isPublished()`, `isPubliclyVisible()`, `publish()/unpublish()`.
- [X] T009 [P] Model `app/Domain/Events/Models/EventSiteSection.php` extends BaseModel: casts (payload array, is_active bool), relações `site()`, `items()` (topo, ordenado), `isDynamic()`.
- [X] T010 [P] Model `app/Domain/Events/Models/EventSiteItem.php` extends BaseModel: cast payload array, relações `section()`, `parent()`, `children()` (por parent_item_id, ordenado).
- [X] T011 Estender `app/Domain/Events/Models/Event.php`: `eventSite(): HasOne` e `ensureSite()` (cria site com slug sugerido do evento + seções default de todos os tipos, idempotente).
- [X] T012 [P] Contrato `app/Domain/Events/Services/Translation/TranslationProviderContract.php` (`translate(string $text, string $from, string $to): string`) + `NullTranslationProvider.php` (retorna `''`).
- [X] T013 `app/Domain/Events/Services/Translation/TranslationService.php`: dado um payload e o tipo (seção/item), percorre os campos `L` do `SectionSchema`, preenche idiomas ativos ≠ pt vazios a partir do pt via provider; nunca falha se provider indisponível.
- [X] T014 Bind de `TranslationProviderContract` em `app/Providers/AppServiceProvider.php` conforme `config('site.translation.provider')` (default Null).
- [X] T015 `app/Domain/Events/Services/EventSiteService.php`: `ensureSite(Event)`, `updateConfig(EventSite, data)` (slug/theme/identity/countdownAt/seo/activeLanguages + tradução do SEO), `publish/unpublish`, `upsertSection`, `reorderSections` — escritas em `DB::transaction`.
- [X] T016 [P] `app/Domain/Events/Services/SiteItemService.php`: `create/update/delete/reorder` de itens e filhos (valida parent no mesmo site), tradução do payload, `DB::transaction` com recálculo de `sort`.

**Checkpoint**: banco migrado (aditivo, **sem** migrate:fresh), models e services prontos; nenhuma história ainda exposta por API.

---

## Phase 3: User Story 1 — Criar o Site e publicá-lo (Priority: P1) 🎯 MVP

**Goal**: admin cria/config o Site (slug, data, tema, SEO, Hero) e publica; landing mínima no ar pela URL; rascunho/oculto não aparece.

**Independent Test**: criar Site, definir slug/data/tema/Hero, publicar, abrir `/site/{slug}` e ver Hero + countdown + tema; rascunho/oculto → 404; slug duplicado → 422.

### Tests (US1)

- [X] T017 [P] [US1] `tests/Feature/Site/SiteConfigTest.php`: GET cria site sob demanda; PUT config (slug/tema/data/SEO/idiomas); slug único → 422; publish/unpublish idempotentes.
- [X] T018 [P] [US1] `tests/Feature/Site/PublicSiteVisibilityTest.php`: publicado+visível → 200; rascunho → 404; evento oculto → 404; `?lang` default pt.
- [X] T019 [P] [US1] `tests/Feature/Site/SiteAccessTest.php`: `gate`/`attendee` em rotas admin do Site → 403; landing pública aberta (sem auth) → 200.

### Implementation (US1)

- [X] T020 [P] [US1] `app/Http/Requests/Admin/UpdateEventSiteRequest.php` (slug regex+unique ignorando próprio, theme array, countdownAt date nullable, activeLanguages ⊆ suportados e contém pt, seo array; messages pt-BR).
- [X] T021 [P] [US1] `app/Http/Resources/Admin/EventSiteResource.php` (camelCase: id, eventId, slug, theme, identity com URLs, countdownAt, seo, activeLanguages, isPublished, publishedAt, sections via SiteSectionResource).
- [X] T022 [P] [US1] `app/Http/Resources/Admin/SiteSectionResource.php` (id, type, sort, isActive, payload, items quando dinâmica).
- [X] T023 [P] [US1] `app/Http/Resources/Public/PublicSiteResource.php` (achata campos `L` para `?lang` com fallback pt; paths → URLs absolutas; omite seções inativas/vazias; ordenado por sort).
- [X] T024 [US1] `app/Http/Controllers/Api/Admin/EventSiteController.php`: `show` (ensureSite), `update` (updateConfig), `publish`, `unpublish`.
- [X] T025 [US1] `app/Http/Controllers/Api/Public/PublicSiteController.php`: `show(string $slug, Request)` — 404 se não `isPubliclyVisible`; resolve `lang`.
- [X] T026 [US1] Registrar rotas em `routes/api.php`: dentro do grupo admin `events/{event}` → `GET/PUT /site`, `POST /site/publish`, `POST /site/unpublish`; e pública `GET /public/sites/{slug}` (fora do grupo admin).
- [X] T027 [P] [US1] Frontend: adicionar aba `{ to:'site', label:'Site' }` em `frontend/src/admin/eventos/EventoLayout.jsx` e rota React `/site/:slug` → `SitePublico` em `frontend/src/App.jsx`.
- [X] T028 [US1] Frontend CMS: `frontend/src/admin/eventos/abas/SiteLayout.jsx` (carrega `GET /admin/events/{id}/site`, sub-navegação por seção, botão Publicar/Despublicar, badge de estado) + `frontend/src/admin/eventos/abas/site/ConfigSite.jsx` (slug, data/countdown, tema/cores, SEO PT, idiomas ativos).
- [X] T029 [P] [US1] Frontend CMS: `frontend/src/admin/eventos/abas/site/Hero.jsx` (campos do hero) usando `LocalizedInput` (stub inicial só PT nesta fase).
- [X] T030 [US1] Frontend landing: `frontend/src/pages/SitePublico.jsx` + `frontend/src/pages/site/theme.js` (tokens→CSS vars) + `frontend/src/pages/site/ui/Countdown.jsx` + `HeroPub.jsx` + `NavbarPub.jsx` (mínimo para provar Hero+countdown+tema); importar fontes Oswald/Archivo.

**Checkpoint**: US1 verde e demonstrável isoladamente (MVP).

---

## Phase 4: User Story 2 — Seções de conteúdo simples (Priority: P2)

**Goal**: editar Navbar, Sobre (+galeria), Local (+foto/mapa), CTA final, Rodapé, Legal com uploads.

**Independent Test**: preencher Sobre/Local/Rodapé/Legal + uploads e ver cada bloco na landing; upload inválido → 422.

### Tests (US2)

- [X] T031 [P] [US2] `tests/Feature/Site/SiteSectionsTest.php`: PUT seção simples atualiza payload; toggle `isActive` remove da landing; reorder de seções.
- [X] T032 [P] [US2] `tests/Feature/Site/SiteMediaTest.php`: upload válido → { path, url }; tipo/tamanho inválido → 422; papel não autorizado → 403.

### Implementation (US2)

- [X] T033 [P] [US2] `app/Http/Requests/Admin/UpdateSiteSectionRequest.php` (payload array + isActive bool, validação condicional por type) e `app/Http/Requests/Admin/UploadSiteMediaRequest.php` (image, mimes jpeg/png/webp/svg, máx ~4MB).
- [X] T034 [US2] `app/Http/Controllers/Api/Admin/SiteSectionController.php`: `update` (payload+isActive via TranslationService) e `reorder`.
- [X] T035 [US2] `app/Http/Controllers/Api/Admin/SiteMediaController.php`: `store` (`->store('site','public')`, retorna path+url; cleanup de arquivo trocado).
- [X] T036 [US2] Rotas em `routes/api.php` (grupo admin `events/{event}/site`): `PUT /sections/{section}`, `PATCH /sections/reorder`, `POST /media`.
- [X] T037 [P] [US2] Frontend CMS componente reutilizável `frontend/src/admin/eventos/abas/site/MediaUpload.jsx` (usa `apiUpload` → guarda path, preview por url).
- [X] T038 [P] [US2] Frontend CMS seções simples: `Navbar.jsx`, `Sobre.jsx` (galeria via MediaUpload), `Local.jsx` (foto+mapHref), `CtaFinal.jsx` (retratos), `Rodape.jsx`, `Legal.jsx` em `frontend/src/admin/eventos/abas/site/`.
- [X] T039 [P] [US2] Frontend landing seções: `SobrePub.jsx` (mosaico), `LocalPub.jsx` (card+botão mapa), `CtaPub.jsx`, `RodapePub.jsx`, e modais legais em `frontend/src/pages/site/` + `NavbarPub.jsx` completa.

**Checkpoint**: US1+US2 verdes; landing renderiza blocos institucionais + navbar completa.

---

## Phase 5: User Story 3 — Seções em lista dinâmica (Priority: P3)

**Goal**: adicionar/editar/remover/reordenar N itens (Estatísticas, Pilares, Palestrantes, Programação, Informações, Patrocinadores, Depoimentos, FAQ), com aninhamento.

**Independent Test**: 3 palestrantes → reordenar → remover 1 → landing com 2 na ordem + modal; Programação com dias/itens tipados; Estatísticas anima; Patrocinadores por grupo.

### Tests (US3)

- [X] T040 [P] [US3] `tests/Feature/Site/SiteItemsTest.php`: CRUD de itens; reorder recalcula sort; filhos (dia→entradas, grupo→logos) com `parentItemId`; parent de outro site → 422; delete é soft delete (com filhos).

### Implementation (US3)

- [X] T041 [P] [US3] `app/Http/Requests/Admin/StoreSiteItemRequest.php` e `UpdateSiteItemRequest.php` (payload array, parentItemId nullable+pertence ao site, sort int; validação condicional por type de seção: program.type enum, icon enums).
- [X] T042 [P] [US3] `app/Http/Resources/Admin/SiteItemResource.php` (id, parentItemId, sort, payload, children).
- [X] T043 [US3] `app/Http/Controllers/Api/Admin/SiteItemController.php`: `index`, `store`, `update`, `destroy`, `reorder` (usa SiteItemService).
- [X] T044 [US3] Rotas em `routes/api.php` (grupo admin `events/{event}/site/sections/{section}`): `GET/POST /items`, `PUT/DELETE /items/{item}`, `PATCH /items/reorder`.
- [X] T045 [P] [US3] Frontend CMS editor genérico `frontend/src/admin/eventos/abas/site/ListaOrdenavel.jsx` (add/editar/remover/reordenar via setas ↑↓ chamando reorder).
- [X] T046 [P] [US3] Frontend CMS seções dinâmicas simples: `Estatisticas.jsx`, `Pilares.jsx`, `Depoimentos.jsx`, `Faq.jsx` em `frontend/src/admin/eventos/abas/site/` (usam ListaOrdenavel).
- [X] T047 [US3] Frontend CMS seções dinâmicas aninhadas: `Palestrantes.jsx`, `Programacao.jsx` (dias→entradas, workshop activities), `Informacoes.jsx` (categorias→contatos), `Patrocinadores.jsx` (grupos→logos) em `frontend/src/admin/eventos/abas/site/`.
- [X] T048 [P] [US3] Frontend landing dinâmicas + UI: `StatsPub.jsx` (`ui/Counter.jsx` IntersectionObserver), `PilaresPub.jsx`, `PalestrantesPub.jsx` (`ui/Carousel.jsx` + modal), `ProgramacaoPub.jsx` (stagger), `InfoPub.jsx` (modais), `PatrocinadoresPub.jsx`, `DepoimentosPub.jsx` (carrossel), `FaqPub.jsx` (`ui/Accordion.jsx`) em `frontend/src/pages/site/`.

**Checkpoint**: landing pública essencialmente completa em PT com todas as seções.

---

## Phase 6: User Story 4 — Multi-idioma PT/EN/ES (Priority: P4)

**Goal**: textos traduzíveis; PT base; EN/ES automáticos ao salvar (ou manuais); campos não traduzíveis intactos; seletor de idioma na landing; SEO por idioma.

**Independent Test**: com EN ativo, salvar PT → EN disponível (auto/manual); campo não traduzível igual nos 3; landing troca idioma; falta de tradução → PT.

### Tests (US4)

- [X] T049 [P] [US4] `tests/Feature/Site/SiteI18nTest.php`: salvar seção preenche `{pt,en,es}` dos idiomas ativos via provider fake; campo escalar (não traduzível) inalterado; provider indisponível não falha o save; landing `?lang=en` resolve EN e cai no PT quando ausente; SEO por idioma.

### Implementation (US4)

- [X] T050 [P] [US4] Adicionar `TranslationProviderFake` em `tests/` (implementa o contrato, prefixa `[en]`/`[es]`) e cobrir `TranslationService` no teste T049.
- [X] T051 [US4] Frontend CMS `frontend/src/admin/eventos/abas/site/LocalizedInput.jsx` (abas PT/EN/ES editando `{pt,en,es}`) e integrá-lo em ConfigSite/Hero e demais seções que usam campos `L` (substituir stubs PT).
- [X] T052 [US4] Frontend landing `frontend/src/pages/site/ui/LanguageSwitcher.jsx` (PT/EN/ES) + `SitePublico.jsx` refaz a query com `?lang=` (ou cacheia os 3) e aplica `<title>`/meta por idioma.

**Checkpoint**: conteúdo multilíngue ponta a ponta.

---

## Phase 7: User Story 5 — Landing pública fiel ao design (Priority: P5)

**Goal**: fidelidade 1:1 (ordem, tema, tipografia, comportamentos) e responsividade (hambúrguer <1040px; reflow 760/640).

**Independent Test**: comparar `/site/{slug}` com `cms/Landing.dc.html` no desktop e mobile; countdown/contadores/carrosséis/FAQ funcionando; navbar vira hambúrguer.

### Implementation (US5)

- [X] T053 [US5] Refinar `frontend/src/pages/site/theme.js` e CSS (raio 4–14px, sombras, gradiente de fundo, dourado/hover) para bater com os tokens do handoff; garantir Oswald/Archivo (pesos e caixa alta).
- [X] T054 [US5] Layout responsivo em `frontend/src/pages/site/` (navbar fixa→hambúrguer <1040px em `NavbarPub.jsx`; reflow de grades 760/640; rolagem suave por âncora com `scroll-margin-top`).
- [X] T055 [P] [US5] Polir interações finais: hovers dourados (botões/links), transições dos modais e do accordion (chevron 180°), setas/dots dos carrosséis (4/2/1 por breakpoint), entrada em stagger da programação.
- [X] T056 [P] [US5] Estado vazio/robustez: countdown com data ausente/passada (zera sem quebrar), seção/lista vazia omitida, fallback de foto (monograma do palestrante) em `frontend/src/pages/site/`.

**Checkpoint**: landing pública 1:1 e responsiva.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: refinos que cruzam histórias.

- [X] T057 [P] Revisar mensagens de validação pt-BR em todos os FormRequests do Site e shape de erro `{ message, type, status, errors }`.
- [ ] T058 [P] `SampleSiteSeeder` (opcional, `database/seeders/`) populando um site de demonstração a partir do conteúdo de exemplo do handoff (`cms/`), registrado no DatabaseSeeder de demo — sem migrate:fresh.
- [X] T059 Rodar `make test` completo (incl. specs 003/012) e garantir tudo verde; ajustar quaisquer regressões introduzidas.
- [X] T060 [P] Validar `quickstart.md` manualmente (5 fluxos) com API :8000 + Vite :5173 no ar ao final.

---

## Dependencies & Execution Order

- **Setup (P1–T004)** → **Foundational (T005–T016)** bloqueiam tudo.
- Ordem das histórias: **US1 (P1)** → US2 (P2) → US3 (P3) → US4 (P4) → US5 (P5).
- US1 é o MVP e independente após a fundação. US2 e US3 dependem só da fundação + rotas base de US1 (controller/rotas do Site). US4 depende de US1–US3 existirem (aplica i18n aos campos já criados). US5 refina a landing produzida em US1–US3.
- Dentro de cada fase: Requests/Resources `[P]` → Controller → Rotas → Frontend. Tests `[P]` podem preceder a implementação (TDD) da história.

## Parallel Opportunities

- Setup: T002/T003/T004 em paralelo após T001.
- Foundational: migrations T005–T007 `[P]`; models T008–T010 `[P]`; contrato/provider T012 `[P]`.
- US1: T017–T019 (tests) `[P]`; T020–T023 (requests/resources) `[P]`; frontend T027/T029 `[P]`.
- US3: T045/T046/T048 `[P]` (arquivos distintos).

## Implementation Strategy

- **MVP = US1** (Fases 1–3): Site criável, configurável e publicável, com landing mínima (Hero+countdown+tema) e gating de visibilidade. Entregável e testável sozinho.
- Incrementos: US2 (conteúdo institucional + uploads) → US3 (listas dinâmicas = grosso da landing) → US4 (i18n) → US5 (fidelidade/responsivo).
- Tudo aditivo à spec 003; migrations aditivas; **nunca** `migrate:fresh` sem autorização. Testes verdes antes de commit/merge.
