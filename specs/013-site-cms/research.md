# Research — 013 Site do evento (CMS + landing 1:1, multi-idioma)

Decisões técnicas que resolvem as incógnitas do plano. Formato: Decisão / Justificativa / Alternativas rejeitadas.

## R1. Estrutura de dados do CMS

**Decisão**: Três tabelas novas — `event_sites` (1:1 com `events`), `event_site_sections` (uma linha por seção, `payload` JSON) e `event_site_items` (itens ordenáveis das seções dinâmicas, com `parent_item_id` auto-relacional para **um** nível de aninhamento). Todas com soft delete + `created_by`/`updated_by` via `BaseModel`.

**Justificativa**: espelha o padrão da spec 003 (`landing_blocks` com `payload` JSON) mas separando os **itens** em linhas próprias — necessário para adicionar/editar/remover/**reordenar** N itens (FR-008) e para o aninhamento real do handoff (dia→itens de programação, categoria→contatos, grupo→logos). Um nível de `parent_item_id` cobre todos os casos do `cms-gerenciador.md`; `activities` do workshop e `items:string[]` dos pilares ficam como array dentro do payload do item (não precisam de linha). JSON no payload mantém o schema flexível por seção sem dezenas de colunas.

**Alternativas rejeitadas**: (a) reutilizar `landing_blocks` com tudo no payload — não dá reordenação/aninhamento por item de forma limpa e a spec pede estrutura nova; (b) uma tabela por seção — explosão de tabelas/migrations para 16 seções; (c) EAV puro — complexidade desnecessária.

## R2. Internacionalização (PT base + EN/ES)

**Decisão**: Campos de texto traduzíveis são **mapas `{ pt, en, es }`** embutidos no `payload` JSON. **Quais** campos são localizados é declarado pelo **schema da seção/item** (constantes no código) — campos escalares (hrefs, ícones, números, nomes próprios/marcas) **não** são traduzíveis. Um `TranslationService` percorre o payload ao salvar e, para cada campo localizado, preenche os idiomas-alvo ativos a partir do PT via `TranslationProviderContract` (implementação `NullTranslationProvider` por padrão → alvos ficam vazios para preenchimento manual). Idiomas ativos ficam em `event_sites.active_languages`. SEO é localizado da mesma forma.

**Justificativa**: sem pacote novo (não há `spatie/laravel-translatable` no projeto e i18n inexistente hoje); mantém uma linha por seção/item (sem join explosivo); "não traduzível" vira decisão de schema (testável, previsível) em vez de flag por campo; provedor atrás de contrato respeita o padrão constitucional "trocar sem reescrever" e permite dev sem chave (salvar nunca falha por indisponibilidade do provedor — FR-013). A landing resolve o idioma escolhido e cai no PT quando falta tradução (FR-014).

**Alternativas rejeitadas**: (a) tabela `translations` polimórfica (owner_type/owner_id/field/locale) — mais joins e código para ganho nulo aqui, já que o payload já é JSON; (b) flag `translatable` por campo editável pelo usuário — UX confusa e difícil de testar; (c) traduzir no cliente — quebraria SEO por idioma.

## R3. Publicação e visibilidade pública (estado derivado)

**Decisão**: `event_sites.published_at` (+ `published_by`) nullable. `isPublished` deriva de `published_at !== null`. A **visibilidade pública** é derivada em runtime: `isPublished && event.visible_on_site && event.status == published`. O endpoint público `GET /public/sites/{slug}` retorna 404 quando não visível (não distingue rascunho de inexistente — não vaza).

**Justificativa**: Constituição II proíbe estado derivado como coluna. Reaproveita `events.visible_on_site` (spec 003) em vez de duplicar. Publicar/despublicar é ação explícita registrada (Constituição V).

**Alternativas rejeitadas**: coluna booleana `is_public` no site — duplicaria a visibilidade do evento e violaria II.

## R4. Slug e URL pública

**Decisão**: `event_sites.slug` **único** (índice unique), URL-safe. Landing pública na rota React nova `/site/:slug` consumindo `GET /public/sites/{slug}` (binding por slug). Slug default sugerido a partir do slug do evento na criação do site, editável.

**Justificativa**: a spec pede "URL pública por slug do Site", distinta do evento. Mantém `/evento/:slug` (spec 003) intacto. Binding por slug segue o padrão `{event:slug}` já usado.

**Alternativas rejeitadas**: reusar o slug do evento — acopla site à URL do evento e conflita com a landing 003.

## R5. Uploads de mídia

**Decisão**: Endpoint `POST /admin/events/{event}/site/media` (multipart), valida imagem (mime `jpeg,png,webp,svg`; tamanho máx. ~4 MB), `->store('site', 'public')`, retorna `{ url, path }`. O front guarda o `path` no campo do payload e usa `url` para preview. Remoção de mídia órfã segue o padrão de cleanup do `EventController::banner()` quando um campo é substituído.

**Justificativa**: reaproveita disco `public` e o padrão de upload existente (`apiUpload`, `Storage::disk('public')->url()`); path relativo no banco → portável entre dev/prod via `APP_URL`.

**Alternativas rejeitadas**: base64 no payload JSON — incha o banco e o payload público; disco privado — landing é pública, precisa de URL acessível.

## R6. Fidelidade "1:1" da landing

**Decisão**: Recriar `cms/Landing.dc.html` em **componentes React**, extraindo os **design tokens** (cores/fontes/espaçamentos/breakpoints do `README.md`/`cms-gerenciador.md`) para `theme.js` → aplicados como **CSS variables** a partir do tema salvo no CMS. Comportamentos nativos em hooks: countdown (setInterval 1s a partir de `countdownAt`), contadores (IntersectionObserver 0→valor), carrosséis (estado + setas/dots, 4/2/1 por breakpoint), programação (stagger via IntersectionObserver), FAQ accordion (estado + rotação do chevron), navbar fixa→hambúrguer < 1040px, rolagem suave por âncora. Fontes Oswald/Archivo via Google Fonts. **Não** portar `support.js`/`image-slot.js` (runtime do protótipo).

**Justificativa**: o handoff é explícito — recriar na stack, não copiar o HTML/runtime (README "não é código de produção"). Tema por CSS variables permite trocar a paleta no CMS (FR-002/FR-015) sem recompilar. Sem dependências novas (carrossel/accordion/countdown são triviais em React).

**Alternativas rejeitadas**: (a) embutir o HTML do protótipo num iframe/`dangerouslySetInnerHTML` — não conecta ao CMS nem ao i18n e traz o runtime; (b) libs de carrossel/animação de terceiros — desnecessário para o escopo e aumenta o bundle.

## R7. Compatibilidade com a spec 003

**Decisão**: Feature **aditiva**. Mantém `landing_blocks`, `LandingBlockController`, `PublicEventController`, rotas `/admin/events/{event}/landing-blocks*` e `/public/events/{event:slug}`, e os componentes `Landing.jsx`/`EventoPublico.jsx` + rota `/evento/:slug`. A nova aba "Site" e a rota `/site/:slug` convivem. Nenhuma migration destrutiva.

**Justificativa**: Constituição VI ("uma spec não pode redefinir o que outra entregou") e "migrations aditivas". Evita quebrar testes 003. A migração do conteúdo antigo é opcional e fora de escopo (Assumptions da spec).

**Alternativas rejeitadas**: dropar `landing_blocks`/rotas 003 — destrutivo e quebraria testes; exige emenda constitucional.

## R8. Provedor de tradução (config)

**Decisão**: `config/site.php` define `base_locale` (`pt`), `locales` (`pt,en,es`) e `translation.provider` (`null` por padrão). `AppServiceProvider` faz bind de `TranslationProviderContract` para a implementação configurada; chaves/credenciais de qualquer provedor real ficam em `.env` (fora do VCS). Sem provedor → `NullTranslationProvider` (alvos vazios, preenchimento manual), sem falha ao salvar.

**Justificativa**: Constituição IV (segredos em `.env`, provedores atrás de contrato) aplicada por analogia ao `PaymentGatewayContract`. Permite plugar um tradutor real numa spec futura sem mexer no CMS.

**Alternativas rejeitadas**: hardcode de um SDK de tradução agora — traz dependência/segredo sem necessidade imediata e acopla o CMS ao provedor.

## R9. Idioma no CMS × datas × dinheiro

**Decisão**: UI do CMS em pt-BR; identificadores/código em inglês. `countdownAt` armazenado em UTC (cast datetime), exibido/contado no fuso do evento (`config('events.timezone')`). Sem valores monetários nesta feature.

**Justificativa**: convenções da constituição (datas UTC, código inglês, UI pt-BR).
