# Implementation Plan: Site do evento — CMS completo + landing pública 1:1 (multi-idioma)

**Branch**: `013-site-cms` | **Date**: 2026-07-06 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/013-site-cms/spec.md`

## Summary

Cada evento ganha um **Site** gerido por um CMS (uma aba "Site" no painel do evento, com um painel por seção) e uma **landing pública** que recria o design de alta fidelidade de `cms/Landing.dc.html` em React (stack do projeto), consumindo o CMS. Estrutura de dados **nova**: `event_sites` (1:1 com evento — slug, publicação, tema/cores, data do countdown, SEO, identidade, idiomas ativos), `event_site_sections` (uma linha por seção, payload JSON) e `event_site_items` (itens ordenáveis das seções dinâmicas, com auto-relacionamento para um nível de aninhamento: dia→itens, categoria→contatos, grupo→logos). Multi-idioma **PT base + EN/ES** por campos localizados `{pt,en,es}` embutidos no payload — quais campos são localizados é declarado pelo **schema da seção** (nomes próprios/marcas ficam escalares = não traduzíveis); um `TranslationService` atrás de `TranslationProviderContract` preenche os idiomas-alvo a partir do PT ao salvar (provedor nulo por padrão → preenchimento manual). Publicação derivada (`published_at` + visibilidade do evento); nada é coluna de estado editável duplicada. Substitui a landing mínima da spec 003 de forma **aditiva** (mantém `landing_blocks`, rotas e testes 003 intactos).

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 12) no backend; JavaScript/React 18 (Vite) no frontend.

**Primary Dependencies**: Laravel 12, Sanctum SPA (cookie), Eloquent, `Storage` disco `public` (uploads existentes); React 18 + React Query; nenhuma lib nova obrigatória. Fontes Oswald/Archivo (Google Fonts) na landing. i18n sem pacote novo — solução própria por campos localizados.

**Storage**: MySQL 8 (novas tabelas `event_sites`, `event_site_sections`, `event_site_items`, todas com soft delete + `created_by`/`updated_by`). Uploads no disco `public` (`storage/app/public`), pasta `site/`, guardando path relativo; URL via `Storage::disk('public')->url()`.

**Testing**: PHPUnit Feature em MySQL dedicado `app_test` (nunca SQLite), via `make test` / `docker compose run --rm php`. Cobrir: publicação/visibilidade pública, CRUD e reordenação de seções/itens, preenchimento i18n, upload (validação de tipo/tamanho), RBAC (403).

**Target Platform**: SPA React servida por Vite (dev :5173) + API Laravel (dev :8000). Landing pública é rota React aberta (`/site/:slug`) consumindo `GET /public/sites/{slug}`.

**Project Type**: Web application (backend Laravel + frontend React), monorepo já existente.

**Performance Goals**: landing pública responde em tempo interativo (payload único do site + seções + itens numa chamada); countdown/contadores/carrosséis no cliente. Sem metas de throughput especiais (conteúdo institucional, baixa concorrência de escrita).

**Constraints**: datas UTC no banco (countdown convertido para o fuso do evento na exibição); código/identificadores em inglês, UI/mensagens pt-BR; landing pública multilíngue; segredos fora do VCS (provedor de tradução por `.env`). Estado público **derivado** (publicado + evento visível), nunca coluna duplicada.

**Scale/Scope**: 16 seções (8 dinâmicas), 3 idiomas, 1 site por evento. ~4 controllers admin + 1 público, ~3 models, ~2 services, ~15 componentes React de CMS + ~16 de landing.

## Constitution Check

*GATE: revisado após o design (Phase 1). Sem violações.*

- **I. Standalone — zero acoplamento**: a temática maçônica (triquetra, selos, textos) é **conteúdo/asset** no CMS, não conceito de domínio; nenhuma entidade de Grande Loja/loja/irmão. Nenhum papel novo — gestão do Site é `admin`/`treasury` (middleware `require.role:admin,treasury`), landing aberta. ✓
- **II. Estado derivado, nunca armazenado**: `isPublished` deriva de `published_at`; a **visibilidade pública** deriva de `published_at` + `event.visible_on_site` + status do evento — não há coluna "público" duplicada. `sort` é dado de ordenação (permitido), não estado derivado. Reordenar/recontar itens em `DB::transaction`. ✓
- **III/IV. Pagamento**: N/A — a feature não toca pagamento/cartão. ✓
- **V. Histórico — nada some**: as 3 tabelas novas têm soft delete + `created_by`/`updated_by` (via `BaseModel`/`TracksAuditors`); publicar/despublicar registra quem/quando; remover item/seção é soft delete (preserva histórico). ✓
- **VI. Specs por área**: spec própria `013-site-cms`, entrega backend+frontend+testes da área. **Não redefine** a spec 003: a landing mínima (`landing_blocks`, `PublicEventController`, `/evento/:slug`, `Landing.jsx`, `EventoPublico.jsx`) permanece; esta feature adiciona uma estrutura nova em paralelo (aba "Site", rota `/site/:slug`). ✓

**Convenções**: API `{ data }` camelCase; erros `{ message, type, status, errors }` (422 validação, 409 regra, 403 papel); DECIMAL não se aplica; datas UTC. ✓

**Sem entradas em Complexity Tracking** — nenhuma violação a justificar.

## Project Structure

### Documentation (this feature)

```text
specs/013-site-cms/
├── plan.md              # Este arquivo
├── research.md          # Phase 0 — decisões (i18n, estrutura, publicação, uploads, fidelidade)
├── data-model.md        # Phase 1 — entidades, schema por seção, campos localizados
├── quickstart.md        # Phase 1 — roteiro de validação ponta a ponta
├── contracts/           # Phase 1 — endpoints admin + público
│   ├── admin-site.md
│   ├── admin-sections-items.md
│   ├── admin-media.md
│   └── public-site.md
└── checklists/
    └── requirements.md  # criado no /speckit-specify
```

### Source Code (repository root)

```text
app/
├── Domain/Events/
│   ├── Models/
│   │   ├── EventSite.php              # 1:1 com Event; slug, theme, countdownAt, seo, activeLanguages, publishedAt
│   │   ├── EventSiteSection.php       # type, sort, is_active, payload; belongsTo EventSite
│   │   ├── EventSiteItem.php          # payload, sort, parent_item_id (auto-rel 1 nível); belongsTo section
│   │   ├── SiteSectionType.php        # constantes: config,navbar,hero,stats,about,pillars,speakers,program,local,info,sponsors,testimonials,faq,cta,footer,legal
│   │   └── Event.php                  # + eventSite(): HasOne, + ensureSite()/derivação visibilidade pública
│   ├── Services/
│   │   ├── EventSiteService.php       # ensureSite, updateConfig/theme/seo, publish/unpublish, upsertSection, reorderSections
│   │   ├── SiteItemService.php        # CRUD + reorder de itens (e filhos), tudo em DB::transaction
│   │   └── Translation/
│   │       ├── TranslationProviderContract.php   # translate(text, from, to): string
│   │       ├── NullTranslationProvider.php       # padrão: devolve vazio (preenchimento manual)
│   │       └── TranslationService.php            # varre payload, preenche {pt,en,es} dos idiomas ativos a partir do PT
│   └── Observers/EventObserver.php    # (sem mudança obrigatória; site é criado sob demanda)
├── Http/
│   ├── Controllers/Api/
│   │   ├── Admin/EventSiteController.php     # show, updateConfig(theme/seo/idiomas), publish, unpublish
│   │   ├── Admin/SiteSectionController.php   # show/update seção, toggle ativo, reorder seções
│   │   ├── Admin/SiteItemController.php      # index/store/update/destroy/reorder itens (e filhos)
│   │   ├── Admin/SiteMediaController.php     # upload de imagem → { url, path }
│   │   └── Public/PublicSiteController.php   # show(slug) — respeita publicação + visibilidade do evento
│   ├── Requests/Admin/
│   │   ├── UpdateEventSiteRequest.php
│   │   ├── UpdateSiteSectionRequest.php
│   │   ├── StoreSiteItemRequest.php / UpdateSiteItemRequest.php
│   │   └── UploadSiteMediaRequest.php
│   └── Resources/
│       ├── Admin/EventSiteResource.php / SiteSectionResource.php / SiteItemResource.php
│       └── Public/PublicSiteResource.php     # já resolve o idioma pedido (ou base PT)
├── Providers/AppServiceProvider.php   # bind TranslationProviderContract → provider por config
config/
├── site.php                           # idioma base (pt), idiomas suportados (pt,en,es), provedor de tradução
└── filesystems.php                    # disco public existente (sem mudança)
database/migrations/
├── 2026_07_07_100000_create_event_sites_table.php
├── 2026_07_07_100010_create_event_site_sections_table.php
└── 2026_07_07_100020_create_event_site_items_table.php

frontend/src/
├── admin/eventos/
│   ├── EventoLayout.jsx               # + aba { to:'site', label:'Site' }
│   └── abas/
│       ├── SiteLayout.jsx             # sub-navegação (um painel por seção) + estado do site
│       └── site/
│           ├── ConfigSite.jsx        # identidade, data (countdown), tema/cores, slug, SEO, idiomas, publicar
│           ├── Hero.jsx, Navbar.jsx, Sobre.jsx, Local.jsx, CtaFinal.jsx, Rodape.jsx, Legal.jsx   # seções simples
│           ├── Estatisticas.jsx, Pilares.jsx, Palestrantes.jsx, Programacao.jsx,
│           │   Informacoes.jsx, Patrocinadores.jsx, Depoimentos.jsx, Faq.jsx                     # seções dinâmicas
│           ├── ListaOrdenavel.jsx     # editor genérico de lista (add/editar/remover/reordenar)
│           ├── LocalizedInput.jsx     # campo com abas PT/EN/ES ({pt,en,es})
│           └── MediaUpload.jsx        # upload → guarda path/url
├── pages/
│   ├── SitePublico.jsx               # landing 1:1 (rota /site/:slug)
│   └── site/                          # componentes da landing por seção + hooks (countdown, contadores, carrossel)
│       ├── theme.js                  # tokens → CSS variables
│       ├── NavbarPub.jsx, HeroPub.jsx, StatsPub.jsx, SobrePub.jsx, PilaresPub.jsx,
│       │   PalestrantesPub.jsx, ProgramacaoPub.jsx, LocalPub.jsx, InfoPub.jsx,
│       │   PatrocinadoresPub.jsx, DepoimentosPub.jsx, FaqPub.jsx, CtaPub.jsx, RodapePub.jsx
│       └── ui/ (Countdown, Counter, Carousel, Accordion, Modal, LanguageSwitcher)
└── App.jsx                            # + <Route path="/site/:slug" element={<SitePublico/>} />

tests/Feature/Site/
├── SiteConfigTest.php                # criar/config/publicar/despublicar; slug único
├── PublicSiteVisibilityTest.php      # rascunho/oculto → 404; publicado+visível → 200
├── SiteSectionsTest.php              # update seção, toggle ativo, reorder
├── SiteItemsTest.php                 # CRUD + reorder + filhos (dia→itens, grupo→logos)
├── SiteI18nTest.php                  # preenchimento {pt,en,es} ao salvar; campo não-traduzível intacto; fallback PT
├── SiteMediaTest.php                 # upload válido/ inválido
└── SiteAccessTest.php                # RBAC 403 (gate/attendee), landing pública aberta
```

**Structure Decision**: Web application no monorepo existente. Backend segue o domínio em `app/Domain/Events` (models/services/observers) e os padrões de controller/resource/request já usados na spec 003; frontend adiciona uma aba "Site" no `EventoLayout` (padrão de abas com `when`/sub-rotas) e uma rota pública nova `/site/:slug`. A estrutura de dados é nova e paralela à `landing_blocks` (spec 003 permanece intacta).

## Complexity Tracking

> Sem violações constitucionais — seção não aplicável.
