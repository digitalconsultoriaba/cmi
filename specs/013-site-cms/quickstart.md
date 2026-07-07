# Quickstart — Validação 013 Site do evento (CMS + landing)

Guia de validação ponta a ponta. Backend/PHP rodam via Docker; frontend via Vite.

## Pré-requisitos

```bash
make up                 # MySQL/Redis/Mailpit
docker compose --profile dev up -d api    # API :8000
cd frontend && npm run dev                # Vite :5173
```

Migrar (aditivo — **nunca** `migrate:fresh` sem autorização):

```bash
docker compose run --rm php php artisan migrate
```

Login admin: usar as credenciais de seed do painel (papel `admin`).

## Fluxo 1 — Criar, configurar e publicar (US1)

1. No painel, abrir um evento → aba **Site**. O Site é criado sob demanda (`GET /admin/events/{event}/site`), já com as seções default e um slug sugerido.
2. Em **Config**: definir slug único, **data do countdown**, **tema/cores** (tokens), SEO (PT), idiomas ativos; salvar (`PUT .../site`).
3. Preencher **Hero** (títulos/subtítulo/botões) e salvar a seção.
4. Clicar **Publicar** (`POST .../site/publish`).
5. Garantir que o evento está **visível no site** (toggle da spec 003).
6. Abrir `http://localhost:5173/site/{slug}` → **esperado**: landing com o Hero, countdown correndo a partir da data, cores do tema aplicadas.

**Checagens**:
- Alterar a data em Config e recarregar a landing → countdown recalcula.
- Trocar o dourado do tema → a landing muda a paleta.
- Despublicar (`POST .../site/unpublish`) → `/site/{slug}` retorna 404.
- Site de evento **oculto** → 404 mesmo publicado.
- Tentar slug já usado por outro site → 422.

## Fluxo 2 — Seções simples + uploads (US2)

1. **Sobre**: texto + galeria (upload via `POST .../site/media`, guarda `path`). Landing mostra o mosaico.
2. **Local**: endereço, `mapHref` (Google Maps) e foto do venue. Landing mostra o card + botão "Ver no mapa".
3. **Rodapé**: contatos e redes. Landing mostra os links.
4. **Legal**: privacidade/termos → modais legais exibem o texto.
5. Upload de tipo inválido → 422, conteúdo intacto.

## Fluxo 3 — Listas dinâmicas (US3)

1. **Palestrantes**: adicionar 3 (`POST .../items`), reordenar (`PATCH .../items/reorder`), remover 1 (`DELETE`). Landing: carrossel com 2 na nova ordem; card abre modal.
2. **Programação**: criar 2 dias e itens tipados (workshop com `activities`); filhos via `parentItemId`. Landing renderiza a timeline por dia.
3. **Estatísticas**: itens com ícone/valor → contadores animam 0→valor ao rolar até a seção.
4. **Patrocinadores**: grupos com logos → grade monocromática, cor no hover.
5. Desativar uma seção (`isActive=false`) ou esvaziar a lista → bloco não renderiza (sem espaço vazio).

## Fluxo 4 — Multi-idioma (US4)

1. Em Config, ativar **EN** e **ES**.
2. Salvar um texto em PT (ex.: Hero). Sem provedor configurado: campos EN/ES ficam vazios (editáveis manualmente); com provedor, vêm preenchidos.
3. Marcar visualmente um campo não traduzível (ex.: nome do palestrante) permanece igual nos três (é escalar por schema).
4. Na landing, trocar idioma no seletor (`?lang=en`) → textos em EN; faltando tradução → cai no PT.
5. Conferir `<title>`/meta por idioma.

## Fluxo 5 — Landing 1:1 (US5)

1. Comparar `/site/{slug}` com `cms/Landing.dc.html`: seções na ordem, cores/tipografia (Oswald/Archivo), espaçamentos.
2. Verificar comportamentos: countdown ao vivo, contadores, carrosséis (setas/dots + modal), timeline em stagger, FAQ accordion (chevron 180°), hovers dourados, rolagem suave.
3. Reduzir a janela < 1040px → navbar vira hambúrguer; grades reflowam em 760/640px.

## Testes automatizados

```bash
make test                                   # suíte completa (MySQL app_test)
docker compose run --rm php php artisan test --testsuite=Feature --filter=Site
```

Cobertura esperada (tests/Feature/Site): publicação/visibilidade pública (404 rascunho/oculto), CRUD e reorder de seções/itens (incl. filhos), preenchimento i18n + fallback PT + campo não traduzível, upload válido/ inválido, RBAC 403 (gate/attendee) e landing pública aberta. **Todos verdes** antes do merge; testes das specs 003/012 permanecem verdes (feature aditiva).
