# Handoff — Landing Page + CMS · Congresso Internacional da Maçonaria 2026

## Visão geral
Landing page institucional de página única (tema escuro, azul-marinho profundo com
dourado) para o Congresso Internacional da Maçonaria 2026. Este pacote contém o
**design de referência em HTML** e as imagens usadas. O objetivo do handoff é
**recriar este design no ambiente/codebase de produção** e construir um
**gerenciador de conteúdo (CMS)** que alimente todos os blocos dinâmicos.

## Sobre os arquivos de design
Os arquivos deste pacote são **referências de design feitas em HTML** — um protótipo
de alta fidelidade que mostra o visual e o comportamento pretendidos, **não é código
de produção para copiar diretamente**. A tarefa é **recriar este design no ambiente
existente** do projeto (React, Vue, Next, etc., usando os padrões e bibliotecas já
estabelecidos) ou, se ainda não houver ambiente, escolher o framework mais adequado
e implementar ali — conectando cada bloco ao CMS.

> Observação técnica: `Landing.dc.html` é um "Design Component" (runtime próprio via
> `support.js`). Ele **não** deve ir para produção como está; serve como fonte de
> verdade visual. Toda a lógica (dados de exemplo, estado, animações) está na classe
> `Component` dentro do próprio arquivo — é a melhor referência para o schema do CMS.

## Fidelidade
**Alta fidelidade (hifi)** — cores, tipografia, espaçamentos, hovers e interações
são finais. Recriar pixel-a-pixel usando as bibliotecas do codebase.

## Tipografia
- Títulos / números / rótulos: **Oswald** (500/600/700), caixa alta, tracking generoso.
- Corpo de texto: **Archivo** (400/500/600/700).
- Ambas do Google Fonts (já importadas no `<helmet>` do HTML).

## Design tokens (cores)
| Uso | Hex |
|---|---|
| Fundo (topo → base) | `#071E2E` → `#081127` → `#0B1533` |
| Superfície de card (roxo profundo) | `#191376` (com opacidades) |
| Azul ardósia (apoio) | `#466089` |
| Dourado ocre (acento principal) | `#CCA54D` (hover mais claro `#E3C173`) |
| Texto claro | `#EAECF0` / títulos `#F1F3F7` / creme `#F5F1E6` |
| Texto secundário | `#B9C6D4` / `#8FA2B6` |
| Azul claro (coffee/almoço/coquetel) | `#7FA0C8` |

Raio de borda: 4–14px. Sombra de destaque: `0 20px 44px rgba(0,0,0,.42)`.
Breakpoints (largura da janela): **1040px** (navbar vira hambúrguer + grades maiores),
**760px** (programação vira 1 coluna), **640/600px** (carrosséis/cards 1 coluna).

---

## Seções (na ordem da página)
1. **Navbar** (fixa, translúcida) — logo + nome, âncoras (O Congresso, Programação,
   Palestrantes, Local), botão dourado "Inscreva-se". Vira hambúrguer < 1040px.
2. **Hero** — título em 3 linhas, subtítulo, data + local, 2 botões, 2 selos,
   **countdown regressivo** ao vivo até a data-alvo. Logo como marca-d'água no fundo.
3. **Estatísticas** — 4 contadores que animam de 0 ao valor ao entrar na tela.
4. **Sobre o Congresso** — texto + galeria mosaico (1 foto grande + 4 menores).
5. **Palestrantes** — carrossel (4/2/1 por página) com modal de detalhes ao clicar.
6. **Pilares** — Conectar / Aprender / Transformar (colunas com ícone + lista).
7. **Programação** — timeline vertical de 2 dias.
8. **Local** — texto + card com foto, endereço e **mapa Google incorporado**.
9. **Informações para o Participante** — cards de categoria (hospedagem/transporte/
   turismo) com itens internos; item e categoria abrem modal.
10. **Patrocinadores** — grade de logos monocromáticos (cor no hover).
11. **Depoimentos** — carrossel com paginação.
12. **FAQ** — accordion (sanfona).
13. **CTA final "Inscrições Abertas"** — título + data/local + botão.
14. **Rodapé** — marca, colunas de links, contatos, redes sociais, copyright.

---

## Modelo de dados do CMS (o mais importante)
Todos os blocos abaixo devem ser **editáveis no gerenciador**. Os dados de exemplo
estão na classe `Component` do `Landing.dc.html` (arrays `_speakers`, `_progDays`,
`_testimonials`, `_faq`, `_info`, e `sponsorGroups`). Campos de texto simples (títulos,
rótulos, botões, contatos, data-alvo do countdown) estão como `props` no bloco
`data-props` do mesmo arquivo.

### Configurações gerais (texto simples)
- `eventName`, `titleLine1/2/3`, `subtitle`, `dateText`, `locationText`
- `eventDateISO` — **data-alvo do countdown** (ISO 8601, ex.: `2026-09-18T14:00:00-03:00`)
- Botões: `primaryLabel/Href`, `secondaryLabel/Href`, `ctaLabel/Href` (navbar)
- Textos de seção: `aboutLabel/Title/Text/Btn`, `speakersLabel`, `pillarsLabel`,
  `progLabel/Btn`, `localLabel/…`, `infoLabel`, `sponsorsLabel`, `testiLabel`, `faqLabel`
- CTA final: `ctaKicker`, `ctaTitle`, `ctaSubtitle`, `ctaDate`, `ctaLocation`, `ctaBtnLabel/Href`
- Rodapé: `footerTagline`, `contactEmail`, `contactPhone`, `contactWhatsapp`,
  `whatsappHref`, `instagramHref/facebookHref/youtubeHref/linkedinHref`, `copyright`

### Palestrantes (`_speakers[]`) — N itens
```
{ name, role, org, talk, day, time, photo (url) }
```
Cada card abre um modal com foto grande + palestra. `photo` opcional (há fallback de
monograma). Fotos em `/speakers/`.

### Estatísticas (`_stats[]`) — N itens
```
{ icon: 'people'|'globe'|'mic'|'temple', value: number, title, subtitle }
```

### Pilares (`pillars[]`) — N itens
```
{ icon: 'connect'|'learn'|'transform', title, items: string[] }
```

### Programação (`_progDays[]`) — N dias, cada um com N itens
```
Dia: { label, date, items[] }
Item: { type, t1, t2, title, speaker?, org?, subtitle?, desc?, activities?[] }
type ∈ open | talk | debate | coffee | lunch | workshop | results | coquetel
activities (só workshop): [{ label, text }]
```

### Local
Props de texto (`placeName`, `localText`, `venueName`, `venueAddress`, `mapBtn`,
`mapHref`) + foto do venue (`/images/hotel.jpg`) + **mapa incorporado** (iframe do
Google Maps por query do endereço).

### Informações para o Participante (`_info[]`) — N categorias, cada uma com N itens
```
Categoria: { icon, title, text, linkLabel, modalText, items[] }
icon ∈ bed | transport | tourism | food | money (fallback: losango)
Item: { name, desc, info, address, phone, site }
```
Cada item e o link da categoria abrem um modal com texto + contatos.

### Patrocinadores (`sponsorGroups[]`) — N grupos, cada um com N logos
```
Grupo: { title, logos[] }
Logo: { id, name, href, src (url) }
```
Logos em `/sponsors/` (placeholders atuais). Monocromático por padrão, cor no hover.

### Depoimentos (`_testimonials[]`) — N itens
```
{ text, name, role, photo (boolean/url) }
```

### FAQ (`_faq[]`) — N itens
```
{ q, a }
```

---

## Interações e comportamento
- **Countdown**: regressivo, atualiza a cada 1s, calculado a partir de `eventDateISO`.
- **Contadores de estatísticas**: animam 0→valor via IntersectionObserver ao entrar na tela.
- **Palestrantes**: carrossel manual (setas + dots); clique no card abre modal; fecha no X/fora.
- **Programação**: entrada com fade/slide em stagger ao entrar na tela.
- **Informações**: item e link de categoria abrem modal com contatos.
- **Depoimentos**: carrossel paginado (setas + dots).
- **FAQ**: accordion, expande/recolhe com transição; ícone chevron gira 180°.
- **Hover**: botões dourados clareiam + elevam + brilho; links viram dourado.
- **Rolagem suave** nas âncoras; navbar fixa com offset (`scroll-margin-top`).
- **Responsivo**: breakpoints acima; grades reflow; navbar → hambúrguer.

## Assets
- `/images/logo-principal.png` — logo (triquetra) · navbar, rodapé, marca-d'água do hero
- `/images/logo-cmi.png`, `/images/logo-glmees.png` — selos (CMI e Grande Loja do ES)
- `/images/hotel.jpg` — foto do venue
- `/speakers/*.jpg` — fotos reais de palestrantes; `/speakers/speaker-N.png` — monogramas placeholder
- `/sponsors/*.png` — logos placeholder dos patrocinadores

## Arquivos
- `Landing.dc.html` — design de referência completo (template + lógica + props/CMS schema)
- `support.js`, `image-slot.js` — runtime do protótipo (não usar em produção)
- `images/`, `speakers/`, `sponsors/` — assets

## Extras (versão atual)
- **Seletor de idioma** na navbar (canto direito): controle **visual apenas** com
  bandeira + sigla (PT 🇧🇷 / EN 🇺🇸 / ES 🇪🇸), dropdown no desktop e linha de botões no
  mobile. Marcado como `data-component="language-switcher"`. A lógica de tradução
  (i18n) deve ser ligada pelo sistema — hoje só guarda o idioma selecionado em estado.
- **Modais legais** no rodapé: "Política de Privacidade" e "Termos de Uso" abrem modal
  com texto padrão (boilerplate) — editável no array `_legal` (`privacy` / `terms`).
- A seção **Local** não tem mais mapa incorporado; o botão "Ver no mapa" (`mapHref`)
  leva ao Google Maps.

