# Gerenciador de Conteúdo (CMS) — Landing do Congresso

> Mapa **definitivo** do gerenciador, extraído do handoff real do Claude Design
> (`design_handoff_cms/`). Cada seção do site vira um grupo de campos editáveis. Este
> documento é o que o Claude Code usa para construir o dashboard do site (menu **Sites**).
>
> Fonte de verdade do schema: a classe `Component` e o bloco `data-props` do
> `Landing.dc.html`. Os campos abaixo são exatamente os que existem no design.

---

## Como o design foi entregue (contexto p/ o Claude Code)

- `Landing.dc.html` é o **design de referência de alta fidelidade** (não é código de
  produção). Cores, fontes, espaçamentos e interações são finais — recriar pixel-a-pixel
  no codebase de produção (React), ligando cada bloco ao CMS.
- Runtime do protótipo (`support.js`, `image-slot.js`) **não vai** para produção.
- Todo o conteúdo dinâmico já está modelado: props de texto (bloco `data-props`, cada
  campo com sua `section`) e arrays (`_speakers`, `_progDays`, `_info`, `_testimonials`,
  `_faq`, `sponsorGroups`, `_stats`, `pillars`, `_legal`, `_languages`).

### Design tokens (viram a base de "Cores do evento" no CMS)
| Uso | Hex |
|---|---|
| Fundo (gradiente) | `#071E2E` → `#081127` → `#0B1533` |
| Superfície de card | `#191376` |
| Azul ardósia (apoio) | `#466089` |
| **Dourado (acento principal)** | `#CCA54D` (hover `#E3C173`) |
| Texto claro / título / creme | `#EAECF0` / `#F1F3F7` / `#F5F1E6` |
| Texto secundário | `#B9C6D4` / `#8FA2B6` |
| Azul claro (destaques) | `#7FA0C8` |

Fontes: **Oswald** (títulos/números, caixa alta) + **Archivo** (corpo).

---

## Estrutura do CMS (o que você pediro ↔ o que o design tem)

Tudo que você listou está coberto. Mapa direto:

| Você pediu | Seção no CMS | Como aparece no site |
|---|---|---|
| Cadastrar a data / alterar o dia | **Config › Data do evento** (`eventDateISO`) | Countdown recalcula sozinho |
| Botões participantes/potências/palestras | **Estatísticas** (`_stats[]`) | 4 contadores animados |
| Imagens e textos sobre o Congresso | **Sobre** (textos + galeria) | Texto + mosaico de fotos |
| O "textinho de experiência" (modal) | **Pilares / Experiência** (`pillars[]`) | Conectar/Aprender/Transformar |
| Palestrantes | **Palestrantes** (`_speakers[]`) | Carrossel + modal por card |
| Programação | **Programação** (`_progDays[]`) | Timeline de N dias |
| Local do evento | **Local** (textos + foto + mapa) | Card com endereço + botão mapa |
| Informações p/ participante | **Informações** (`_info[]`) | Cards com modais |
| Cadastrar as cores do evento | **Config › Tema/Cores** (design tokens) | Paleta do site inteiro |

Mais o que o design trouxe e também é editável: **Navbar**, **Hero**, **Patrocinadores**,
**Depoimentos**, **FAQ**, **CTA final**, **Rodapé**, **Modais legais** (privacidade/termos)
e o **seletor de idioma** (PT/EN/ES).

---

## 1. Configurações gerais do site (Config)

Editor de campos simples + upload de logos + tema.

- **Identidade**: `eventName`, logo principal (triquetra), selos (CMI, GLMEES),
  marca-d'água do hero.
- **Data do evento**: `eventDateISO` (ISO 8601, ex.: `2026-09-18T14:00:00-03:00`) —
  **alimenta o countdown**. Este é o campo "alterar o dia" que você pediu.
- **Tema / Cores do evento**: os design tokens acima como campos de cor (fundo,
  superfície, **dourado de acento**, textos). Trocar aqui muda a identidade do site
  inteiro — útil quando cada congresso/edição tiver sua paleta.
- **Slug** do site (o caminho da URL) + SEO (title/description/OG) + idiomas ativos.

---

## 2. Navbar
Campos: `eventName`, `ctaLabel`, `ctaHref` (botão Inscreva-se) + itens de âncora
(O Congresso/Programação/Palestrantes/Local) + seletor de idioma (visual).

## 3. Hero
`titleLine1/2/3`, `subtitle`, `dateText`, `locationText`, `primaryLabel/Href`,
`secondaryLabel/Href`, `eventDateISO` (countdown), selos.

## 4. Estatísticas — `_stats[]` (N itens)
Cada item: `{ icon: people|globe|mic|temple, value (número), title, subtitle }`.
> Os "botões de participantes, potências, palestras, lojas" que você mencionou. Números
> animam de 0 ao valor. Adicionar/remover cards livremente.

## 5. Sobre o Congresso
Textos: `aboutLabel`, `aboutTitle`, `aboutText`, `aboutBtn`, `aboutHref` +
**galeria de fotos** (1 grande + N menores, mosaico). Uploads de imagem.

## 6. Pilares / "Experiência do Congresso" — `pillars[]` (N itens)
`pillarsLabel` + cada pilar `{ icon: connect|learn|transform, title, items: string[] }`.
> É o "textinho de experiência do Congresso" que você citou.

## 7. Palestrantes — `_speakers[]` (N itens)
`speakersLabel`, `speakersBtn/Href` + cada palestrante:
`{ name, role, org, talk, day, time, photo (upload) }`.
Cada card abre **modal** com foto grande + palestra. Foto opcional (fallback monograma).
> A seção que você desenhou como "lugar pra digitar os boxes". Totalmente dinâmica.

## 8. Programação — `_progDays[]` (N dias, cada um com N itens)
`progLabel`, `progBtn/Href` + dias `{ label, date, items[] }`.
Item: `{ type, t1, t2, title, speaker?, org?, subtitle?, desc?, activities?[] }`,
`type ∈ open|talk|debate|coffee|lunch|workshop|results|coquetel`.
Workshop tem `activities: [{ label, text }]`.

## 9. Local do Evento
`localLabel`, `placeName`, `localText`, `localBtn/Href`, `venueName`, `venueAddress`,
`mapBtn`, `mapHref` (link do Google Maps) + foto do venue (upload).

## 10. Informações para o Participante — `_info[]` (N categorias, N itens cada)
`infoLabel` + categoria `{ icon: bed|transport|tourism|food|money, title, text,
linkLabel, modalText, items[] }`; item `{ name, desc, info, address, phone, site }`.
Cada item e o link da categoria abrem **modal** com contatos.

## 11. Patrocinadores — `sponsorGroups[]` (N grupos, N logos)
`sponsorsLabel` + grupo `{ title, logos[] }`; logo `{ id, name, href, src (upload) }`.
Monocromático por padrão, cor no hover.

## 12. Depoimentos — `_testimonials[]` (N itens)
`testiLabel` + `{ text, name, role, photo (opcional) }`. Carrossel paginado.

## 13. FAQ — `_faq[]` (N itens)
`faqLabel` + `{ q, a }`. Accordion.

## 14. CTA final "Inscrições Abertas"
`ctaKicker`, `ctaTitle`, `ctaSubtitle`, `ctaDate`, `ctaLocation`, `ctaBtnLabel/Href` +
retratos: `portraitLeftName/Role`, `portraitRightName/Role` (uploads).

## 15. Rodapé
`footerTagline`, `contactEmail`, `contactPhone`, `contactWhatsapp`, `whatsappHref`,
`instagramHref`, `facebookHref`, `youtubeHref`, `linkedinHref`, `copyright`,
`privacyHref`, `termsHref`.

## 16. Modais legais — `_legal`
`privacy` e `terms` (título + parágrafos). Já vêm com boilerplate LGPD/Termos editável.

---

## Como isso vira o dashboard do site (menu Sites)

No menu **Sites**, ao abrir o site do evento, as abas do dashboard espelham as seções
acima:

```
Site do Congresso (vinculado ao evento)
├── Config          → identidade, DATA do evento, TEMA/CORES, slug, SEO, idiomas
├── Hero            → títulos, botões, countdown
├── Estatísticas    → cards (N)            [dinâmico]
├── Sobre           → textos + galeria
├── Experiência     → pilares (N)          [dinâmico]
├── Palestrantes    → cards + modal (N)    [dinâmico]
├── Programação     → dias e itens (N)     [dinâmico]
├── Local           → textos + foto + mapa
├── Informações     → categorias + itens (N) [dinâmico]
├── Patrocinadores  → grupos + logos (N)   [dinâmico]
├── Depoimentos     → (N)                  [dinâmico]
├── FAQ             → perguntas (N)        [dinâmico]
├── CTA final       → textos + retratos
├── Rodapé          → contatos + redes
└── Legal           → privacidade / termos
```

Blocos marcados **[dinâmico]** = lista com adicionar/editar/remover/reordenar, layout e
animações preparados para **N itens** (o design já foi construído assim).

---

## Armazenamento (sugestão p/ o Claude Code)

- Reaproveitar `landing_blocks` (do `sites-e-cms.md`): um registro por seção, com
  `payload` JSON no formato dos arrays acima. Campos simples ficam no `payload` do bloco
  de config do site.
- Tema/cores: um bloco/registro `theme` (JSON de tokens) no site.
- Uploads (fotos de palestrantes, galeria, logos, venue, retratos) via storage do
  projeto; guardar URL no campo correspondente.
- i18n: campos de texto traduzíveis seguem o `i18n-traducao.md` (PT base + EN/ES,
  tradução automática ao salvar; nomes próprios/marcas `translatable=false`).

---

## O que dizer ao Claude Code

> "O design de referência está em `design_handoff_cms/Landing.dc.html` (alta fidelidade,
> não copiar como produção — recriar em React). O schema do CMS já está no arquivo: props
> de texto no bloco `data-props` (cada campo tem sua `section`) e arrays de conteúdo na
> classe `Component` (`_speakers`, `_progDays`, `_info`, `_testimonials`, `_faq`,
> `sponsorGroups`, `_stats`, `pillars`, `_legal`). Construa o dashboard do site (menu
> Sites) com uma aba por seção conforme `cms-gerenciador.md`, todas as listas dinâmicas
> (N itens, reordenáveis), mais Config com **data do evento** (countdown) e **tema/cores**
> (design tokens). Ligue cada bloco ao site público que renderiza o design 1:1."
