# Feature Specification: Site do evento — CMS completo + landing pública 1:1 (multi-idioma)

**Feature Branch**: `013-site-cms`

**Created**: 2026-07-06

**Status**: Draft

**Input**: User description: "Recriar a landing de alta fidelidade do handoff em `cms/` (tema escuro azul-marinho + dourado) ligada a um CMS por evento (dashboard 'Site' com uma aba por seção, listas dinâmicas reordenáveis, config com data/tema/cores/SEO), e a landing pública renderizando o design 1:1. Multi-idioma PT/EN/ES. Estrutura de dados nova de site/CMS."

## Visão geral

Cada evento pode ter um **Site** — uma landing page pública de alta fidelidade, gerida por um **CMS** próprio no painel. O CMS é um dashboard com **uma aba por seção** (Config, Navbar, Hero, Estatísticas, Sobre, Experiência/Pilares, Palestrantes, Programação, Local, Informações, Patrocinadores, Depoimentos, FAQ, CTA final, Rodapé, Legal); a maioria das seções é uma **lista dinâmica** (N itens, adicionar/editar/remover/reordenar). A **Config** guarda identidade, **data-alvo do countdown**, **tema/cores** (design tokens), slug, SEO e idiomas.

A **landing pública** renderiza o design do handoff **1:1** (fielmente): countdown ao vivo, contadores animados, carrosséis com modais, timeline de programação, FAQ accordion, seletor de idioma, hovers dourados, responsivo. O conteúdo, as cores e a data vêm do CMS. O site é **multi-idioma** (PT base + EN/ES).

**Referência de design** (fonte de verdade visual): `cms/Landing.dc.html` (alta fidelidade — não é código de produção; recriar na stack do projeto). O schema do conteúdo está em `cms/cms-gerenciador.md` e `cms/README.md`.

**Standalone (constituição I)**: o conteúdo é de um congresso maçônico (triquetra, selos), mas isso é **conteúdo/asset editável**, não acoplamento de código — o domínio permanece sem conceitos de Grande Loja/loja/irmão. A marca vive só nos dados do Site.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Criar o Site do evento e publicá-lo (Priority: P1)

O administrador abre o menu **Site** dentro do evento, cria o Site (slug, identidade, **data do countdown**, **tema/cores**, SEO, idiomas ativos), edita ao menos o essencial (Navbar, Hero) e **publica**. A landing fica acessível publicamente pela URL do slug; enquanto rascunho, não aparece.

**Why this priority**: É a fundação — sem o Site (config + estado publicado) e uma landing mínima no ar, nenhuma seção rica faz sentido. Entrega valor imediato: um site publicável com identidade, data e tema.

**Independent Test**: Criar o Site de um evento, definir slug/data/tema/Hero e publicar; abrir a URL pública e ver o Hero com o countdown correndo e as cores do tema; um Site em rascunho retorna "não encontrado" publicamente.

**Acceptance Scenarios**:

1. **Given** um evento sem Site, **When** o admin cria o Site com um slug único, **Then** o Site nasce em **rascunho** e não é acessível publicamente.
2. **Given** um Site em rascunho com Hero e data preenchidos, **When** o admin **publica**, **Then** a landing fica acessível pela URL do slug e mostra o **countdown** regressivo a partir da data configurada.
3. **Given** o admin altera a **data do countdown**, **When** salva, **Then** a landing recalcula o countdown para a nova data.
4. **Given** o admin altera o **tema/cores**, **When** salva, **Then** a landing inteira passa a usar a nova paleta (fundo, dourado de acento, textos).
5. **Given** um Site cujo evento está **oculto do site** (visibilidade do evento desligada) ou em rascunho, **When** alguém acessa a URL, **Then** recebe "não encontrado" (não vaza rascunho/oculto).
6. **Given** dois eventos, **When** o admin tenta usar o mesmo slug, **Then** o sistema recusa (slug único).

---

### User Story 2 - Editar as seções de conteúdo simples (Priority: P2)

O admin edita as seções de **texto/mídia** (Navbar, Hero, Sobre + galeria, Local + foto/mapa, CTA final, Rodapé, Legal) — cada aba mostra os campos daquela seção; uploads de imagem (logo, selos, galeria, venue, retratos) são guardados e exibidos.

**Why this priority**: Completa a landing com o conteúdo institucional além do Hero. Depende de US1 (Site existente).

**Independent Test**: Preencher Sobre (texto + galeria), Local (endereço + foto + link do mapa) e Rodapé (contatos/redes); abrir a landing e ver cada bloco renderizado com o conteúdo salvo.

**Acceptance Scenarios**:

1. **Given** a aba Sobre, **When** o admin salva texto e envia fotos da galeria (1 grande + N menores), **Then** a landing mostra o mosaico com essas fotos.
2. **Given** a aba Local, **When** o admin preenche endereço e o link do mapa e envia a foto do venue, **Then** a landing mostra o card do local com a foto e o botão "Ver no mapa".
3. **Given** a aba Rodapé, **When** o admin preenche contatos e redes sociais, **Then** a landing mostra os links no rodapé.
4. **Given** a aba Legal, **When** o admin edita Política de Privacidade e Termos, **Then** os modais legais da landing exibem o texto salvo.
5. **Given** um upload inválido (tipo/tamanho fora do permitido), **When** enviado, **Then** o sistema recusa com mensagem clara e não altera o conteúdo.

---

### User Story 3 - Gerir as seções em lista dinâmica (N itens, reordenáveis) (Priority: P3)

O admin gerencia as seções dinâmicas — **Estatísticas, Experiência/Pilares, Palestrantes, Programação, Informações, Patrocinadores, Depoimentos, FAQ** — adicionando, editando, **removendo** e **reordenando** itens; a landing reflete a ordem e a quantidade.

**Why this priority**: É o coração do CMS ("N itens" é o diferencial). Depende de US1.

**Independent Test**: Adicionar 3 palestrantes, reordená-los e remover um; abrir a landing e ver o carrossel com os 2 restantes na nova ordem, cada card abrindo o modal com os dados salvos.

**Acceptance Scenarios**:

1. **Given** a aba Palestrantes, **When** o admin adiciona itens com nome/cargo/org/palestra/dia/hora e foto, **Then** a landing mostra os cards no carrossel e cada card abre um **modal** com foto grande + palestra.
2. **Given** vários itens numa lista, **When** o admin **reordena**, **Then** a landing exibe na nova ordem.
3. **Given** um item, **When** o admin **remove**, **Then** ele some da landing (registro preservado no histórico, não apagado fisicamente).
4. **Given** a aba Programação, **When** o admin cria dias e itens (tipos: abertura/palestra/debate/coffee/almoço/workshop/resultados/coquetel; workshop com atividades), **Then** a landing renderiza a timeline dos dias na ordem correta.
5. **Given** a aba Estatísticas, **When** o admin define itens com ícone/valor/título, **Then** a landing anima os contadores de 0 até o valor ao entrar na tela.
6. **Given** Patrocinadores em grupos com logos, **When** salvos, **Then** a landing mostra os logos monocromáticos com cor no hover, por grupo.

---

### User Story 4 - Multi-idioma PT/EN/ES (Priority: P4)

Os textos do Site são **traduzíveis**. PT é a língua base; ao ativar EN/ES, o admin obtém traduções (automáticas ao salvar quando há provedor configurado, ou preenchidas manualmente) e pode revisá-las. Nomes próprios/marcas podem ser marcados como **não traduzíveis**. O visitante troca o idioma na landing e vê o conteúdo naquele idioma; o SEO respeita o idioma.

**Why this priority**: Amplia o alcance do site; complementar ao conteúdo já editável.

**Independent Test**: Com EN ativo, salvar um texto em PT e ver a tradução EN disponível (auto ou manual); trocar o idioma na landing e conferir o conteúdo em EN; um campo marcado "não traduzir" (ex.: nome do palestrante) permanece igual nos três idiomas.

**Acceptance Scenarios**:

1. **Given** EN e ES ativos, **When** o admin salva um texto em PT, **Then** as versões EN/ES ficam disponíveis (traduzidas automaticamente se houver provedor, ou vazias para preenchimento manual) e editáveis.
2. **Given** um campo marcado como **não traduzível**, **When** salvo, **Then** ele aparece idêntico nos três idiomas.
3. **Given** a landing publicada, **When** o visitante escolhe EN no seletor, **Then** os textos passam a ser exibidos em EN; escolhendo ES, em ES; o padrão é PT.
4. **Given** um idioma **não ativo**, **When** o visitante tenta acessá-lo, **Then** cai no idioma base (PT).
5. **Given** SEO por idioma, **When** a landing é carregada num idioma, **Then** título/descrição refletem aquele idioma.

---

### User Story 5 - Landing pública fiel ao design (1:1) (Priority: P5)

O visitante acessa a URL do Site e vê a landing **idêntica ao design de referência**: navbar fixa (hambúrguer < 1040px), Hero com countdown, contadores animados, carrosséis com modais, timeline, informações com modais, depoimentos, FAQ accordion, CTA final, rodapé, com o tema/cores do CMS e comportamento responsivo.

**Why this priority**: É a entrega visível ao público; consome tudo que as demais histórias produziram.

**Independent Test**: Abrir a landing publicada num desktop e num mobile e comparar com o design de referência: seções na ordem, cores/tipografia, countdown correndo, carrosséis e modais funcionando, navbar virando hambúrguer no mobile.

**Acceptance Scenarios**:

1. **Given** a landing publicada, **When** carregada, **Then** as seções aparecem na ordem do design com o tema aplicado (cores/tipografia/espaçamentos fiéis).
2. **Given** o Hero, **When** exibido, **Then** o countdown corre em tempo real e os selos/marca-d'água aparecem.
3. **Given** as estatísticas, **When** entram na tela, **Then** os números animam de 0 ao valor.
4. **Given** palestrantes/depoimentos, **When** o visitante navega no carrossel, **Then** as setas/dots funcionam e o clique no card abre o modal.
5. **Given** o FAQ, **When** o visitante clica numa pergunta, **Then** ela expande/recolhe (accordion) com o ícone girando.
6. **Given** largura < 1040px, **When** a landing é vista, **Then** a navbar vira hambúrguer e as grades reflowam conforme os breakpoints.

---

### Edge Cases

- **Slug**: único entre Sites; caracteres válidos (URL-safe); alterar o slug muda a URL pública.
- **Data do countdown ausente/passada**: sem data → sem countdown (ou zerado); data no passado → countdown zerado (evento já ocorreu), sem quebrar a página.
- **Seção vazia**: uma lista dinâmica sem itens ou uma seção desativada **não** renderiza aquele bloco (sem espaço vazio).
- **Tradução ausente**: se falta a versão do idioma escolhido, cai no texto base (PT).
- **Upload**: tipos de imagem permitidos e limite de tamanho; rejeição não altera o conteúdo atual.
- **Publicado × visível do evento**: a landing só aparece se o Site está **publicado** E o evento está **visível no site**; qualquer um desligado → não aparece.
- **Provedor de tradução indisponível**: salvar não falha; as traduções ficam para preenchimento manual e um aviso indica que a tradução automática não ocorreu.
- **Reordenação concorrente**: reordenar itens é consistente (a ordem final reflete a última gravação).

## Requirements *(mandatory)*

### Functional Requirements

**Site, publicação e config (US1)**

- **FR-001**: Cada evento PODE ter um **Site** (1:1 com o evento), com **slug único**, estado **publicado/rascunho**, SEO (título/descrição/OG) e **idiomas ativos** (PT sempre; EN/ES opcionais).
- **FR-002**: O Site DEVE guardar a **data-alvo do countdown** (data/hora com fuso) que alimenta o Hero, e o **tema/cores** como um conjunto de tokens (fundo/gradiente, superfície, dourado de acento e hover, textos, azul de destaque) aplicado à landing inteira.
- **FR-003**: O Site DEVE guardar a **identidade**: logo principal, selos e marca-d'água (uploads), reutilizados na navbar/hero/rodapé.
- **FR-004**: A landing pública SÓ DEVE aparecer quando o Site está **publicado** E o evento está **visível no site**; caso contrário, retorna "não encontrado" (não vaza rascunho/oculto).
- **FR-005**: Publicar/despublicar o Site é uma ação explícita registrada no histórico (quem/quando).

**Seções e conteúdo (US2, US3)**

- **FR-006**: O CMS DEVE ter **uma aba por seção**: Config, Navbar, Hero, Estatísticas, Sobre, Experiência/Pilares, Palestrantes, Programação, Local, Informações, Patrocinadores, Depoimentos, FAQ, CTA final, Rodapé, Legal.
- **FR-007**: Cada seção PODE ser **ativada/desativada**; seção desativada não renderiza na landing.
- **FR-008**: As seções **dinâmicas** (Estatísticas, Experiência, Palestrantes, Programação, Informações, Patrocinadores, Depoimentos, FAQ) DEVEM permitir **adicionar, editar, remover e reordenar** itens (N itens), refletindo ordem e quantidade na landing.
- **FR-009**: Cada seção DEVE aceitar os campos do schema do handoff (ex.: Hero com títulos/subtítulo/botões; Palestrante com nome/cargo/org/palestra/dia/hora/foto; Programação com dias e itens tipados; Informações com categorias e itens de contato; Patrocinadores em grupos com logos; Depoimentos, FAQ, CTA, Rodapé, Legal conforme descrito).
- **FR-010**: O CMS DEVE aceitar **uploads de imagem** (logo, selos, marca-d'água, galeria do Sobre, foto do palestrante, foto do venue, logos de patrocinadores, retratos do CTA), validando tipo/tamanho e guardando a referência; imagem opcional tem **fallback** (ex.: monograma do palestrante).
- **FR-011**: Remover um item/seção **não apaga fisicamente** — preserva histórico (soft delete); nada some do registro de auditoria.

**Multi-idioma (US4)**

- **FR-012**: Os campos de texto do Site DEVEM ser **traduzíveis** por idioma ativo; **PT é a base**. Campos podem ser marcados como **não traduzíveis** (nomes próprios/marcas), permanecendo iguais nos idiomas.
- **FR-013**: Ao salvar um texto, o sistema DEVE oferecer a **tradução dos idiomas ativos** — automática quando houver um provedor de tradução configurado (atrás de uma interface trocável) e sempre **editável manualmente**; se o provedor estiver indisponível, salvar não falha e as traduções ficam para preenchimento manual.
- **FR-014**: A landing DEVE exibir o conteúdo no **idioma escolhido** pelo visitante (seletor PT/EN/ES); faltando a tradução, usa o texto **base (PT)**; o **SEO** reflete o idioma.

**Landing pública 1:1 (US5)**

- **FR-015**: A landing DEVE renderizar as seções **na ordem do design**, com o **tema/cores** e a **tipografia** do CMS, em **alta fidelidade** ao handoff.
- **FR-016**: A landing DEVE ter os comportamentos do design: **countdown** ao vivo a partir da data do Site; **contadores** que animam ao entrar na tela; **carrosséis** (palestrantes/depoimentos) com setas/dots e **modais**; **timeline** de programação; **modais** de informações; **FAQ accordion**; **hovers** dourados; **rolagem suave** nas âncoras.
- **FR-017**: A landing DEVE ser **responsiva** conforme os breakpoints do design (navbar → hambúrguer < 1040px; reflow das grades em 760/640px).
- **FR-018**: A landing DEVE respeitar o **estado publicado** e a **visibilidade do evento** (FR-004) e usar a **URL por slug**.

**Permissões e histórico (transversal)**

- **FR-019**: Gerir o Site (todas as abas do CMS) DEVE ser restrito a papéis autorizados: **admin** (e opcionalmente **treasury**); a **landing pública é aberta** a qualquer visitante.
- **FR-020**: Toda criação/edição/remoção/publicação no CMS DEVE registrar **autor e data/hora** (histórico), coerente com a governança do projeto.

### Key Entities *(include if data involved)*

- **Site**: o site de um evento (1:1 com o evento). Guarda slug único, estado publicado/rascunho, SEO por idioma, idiomas ativos, **data do countdown**, **tema/cores** (tokens) e **identidade** (logo/selos/marca-d'água). Deriva a visibilidade pública combinando seu estado com a visibilidade do evento.
- **Seção do Site**: um registro por seção (tipo, ordem, ativo, conteúdo). Seções simples guardam campos de texto/mídia; seções dinâmicas agregam itens filhos.
- **Item de Seção**: registro filho ordenável das seções dinâmicas (ex.: um palestrante, um dia de programação com seus itens, uma categoria de informações com seus itens, um grupo de patrocínio com seus logos, um depoimento, uma pergunta de FAQ).
- **Tradução de Campo**: valor de um campo de texto num idioma (PT base + EN/ES), com marcação de "não traduzível" quando aplicável.
- **Mídia/Upload**: arquivos de imagem referenciados pelas seções (logo, selos, galeria, fotos, logos, retratos), com o tipo/uso e a referência de storage.

Relacionamentos: Evento → 0..1 Site; Site → N Seções; Seção dinâmica → N Itens (alguns com subitens, ex.: dia→itens, categoria→itens, grupo→logos); campos de texto → N Traduções.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um administrador consegue **criar, configurar e publicar** um Site (slug, data, tema, Hero) e ver a landing no ar pela URL, com o countdown correndo e as cores aplicadas.
- **SC-002**: Um Site em **rascunho** ou de evento **oculto** nunca é acessível publicamente (0 vazamentos nos testes).
- **SC-003**: Todas as **seções dinâmicas** permitem adicionar/editar/remover/reordenar, e a landing reflete **ordem e quantidade** exatamente como no CMS.
- **SC-004**: A **paleta e a data** editadas no CMS aparecem na landing em 100% dos casos testados (tema aplicado; countdown recalculado).
- **SC-005**: Com EN/ES ativos, o conteúdo é exibido no idioma escolhido; faltando tradução, cai no PT; campos "não traduzíveis" permanecem idênticos nos três idiomas.
- **SC-006**: A landing pública corresponde ao design de referência nas seções, ordem, tema, tipografia e comportamentos (countdown, contadores, carrosséis/modais, FAQ, responsivo) — verificável contra o `Landing.dc.html`.
- **SC-007**: Nenhuma remoção apaga dados fisicamente; toda ação de gestão fica registrada com autor e data/hora.

## Assumptions

- **Estrutura de dados nova**: será criada uma estrutura própria de **Site/CMS** (site + seções + itens + traduções + mídia), substituindo a landing mínima da spec 003 (`landing_blocks`/editor básico/`EventoPublico`). A migração dos poucos dados de landing existentes é opcional; o novo modelo é a fonte de verdade.
- **Papéis**: gestão do Site = `admin` (e, se desejado, `treasury`); a landing pública é aberta. Nenhum papel novo (constituição I).
- **Standalone**: a temática maçônica (logos/selos/textos) é **conteúdo**, não domínio; nenhum conceito de Grande Loja/loja/irmão entra no código.
- **Provedor de tradução**: a tradução automática usa um provedor externo atrás de uma interface trocável; em dev/sem chave, as traduções são manuais e salvar nunca falha por causa disso.
- **Assets do handoff**: as imagens de `cms/images|speakers|sponsors` são referência/placeholder; o conteúdo real é enviado pelo CMS. Fontes Oswald/Archivo e os design tokens do handoff são o ponto de partida do tema.
- **Fidelidade**: "1:1" significa recriar o visual e os comportamentos do `Landing.dc.html` com as bibliotecas do projeto — não copiar o HTML/runtime do protótipo (que não vai para produção).
- **URL pública**: a landing é servida pela **slug do Site**; domínio próprio/DNS está fora de escopo.
- **Datas/moeda/idioma**: datas no fuso do evento (UTC no banco); textos/UI em pt-BR no painel; a landing pública é multilíngue.
- **Não escopo**: checkout/e-commerce (já existe), domínio próprio, editor visual drag-and-drop livre (as seções são fixas; só os itens são dinâmicos) e analytics avançado.
