# Research — 001-fundacao

Decisões técnicas da fundação. Herda e referencia as decisões do material de base
(`base/research.md`), registrando aqui apenas o que é novo ou específico desta spec.

---

## Decisão 1: "Extração do 061" = implementação nova guiada pela referência

**Decisão**: o código-fonte do módulo 061 **não está disponível** neste repositório
(`base/files.zip` contém apenas os mesmos documentos de referência). A "extração"
se materializa como implementação nova de `app/Domain/Events`, seguindo fielmente
`base/data-model.md` e `base/plan.md` — que já descrevem o resultado **pós-extração**
(sem owner polimórfico, sem EventAccessGuard, sem Member/Lodge, tabelas renomeadas).

**Rationale**: os documentos da base já fizeram o trabalho de desmembramento no papel;
implementar direto do modelo limpo é mais seguro do que portar código acoplado.

**Alternativas consideradas**: pedir o código do 061 ao usuário — rejeitado por ora;
o data-model é completo o suficiente e o risco de reintroduzir acoplamento é maior
que o custo de reescrever services (que só chegam nas specs 003+).

**Consequência**: o critério SC-004 (varredura por termos proibidos = 0 ocorrências)
fica trivialmente garantido, mas mantém-se como teste de regressão permanente.

---

## Decisão 2: Layout do repositório — Laravel na raiz + `frontend/`

**Decisão**: aplicação Laravel 12 na **raiz do repositório** (`app/`, `database/`,
`routes/`, `tests/`…) e SPA React 18 + Vite em `frontend/` (padrão do
`base/quickstart.md`: `npm install --prefix frontend`). `base/`, `template/`, `specs/`
e `.specify/` permanecem como diretórios de documentação/referência, ignorados pelo
autoload.

**Rationale**: monorepo simples, um só ciclo de deploy no MVP; o quickstart da base já
assume esse layout. Separar em dois repositórios adicionaria atrito sem benefício
agora.

**Alternativas consideradas**: `backend/` + `frontend/` — rejeitado; Laravel convive
mal com subdiretório em tooling padrão (artisan, deploy, IDE) e a base já fixou o
layout raiz.

---

## Decisão 3: Ambiente dev — Docker para serviços **e para o PHP** (emendada na implementação)

**Decisão (emendada)**: `docker-compose.yml` provê **MySQL 8** (bancos `app` e
`app_test` via init script), **Redis**, **Mailpit** e um serviço **`php`**
(imagem própria `docker/php/Dockerfile`: php:8.3-cli + pdo_mysql + bcmath +
Composer). **PHP/Composer rodam via Docker** — o host só precisa de Docker e
Node 20+. O `Makefile` encapsula tudo:

- `make up` / `make down` — sobe/derruba os serviços Docker
- `make install` — `composer install` (via Docker) + npm + `.env` + key:generate
- `make migrate` / `make fresh` — migrations (+ seed) via Docker
- `make test` — testes no banco `app_test` via Docker
- `make api` — API `:8000` em background (serviço `api`, profile dev)
- `make dev` — API + Vite `:5173`

**Rationale da emenda**: na implementação constatou-se que o host não possui
PHP/Composer. Containerizar o PHP mantém o critério "máquina limpa com ≤ 3 comandos"
(SC-001) sem exigir instalação de runtime no host. Redis usa `predis` (client PHP
puro) para dispensar a extensão phpredis na imagem.

**Alternativas consideradas**: instalar PHP via Homebrew no host — rejeitado: altera
a máquina do usuário e quebra a reprodutibilidade; Laravel Sail — rejeitado: camada
extra sobre o mesmo resultado, e a imagem própria é mínima e explícita.

---

## Decisão 4: Banco de teste dedicado (não SQLite)

**Decisão**: testes Feature rodam em **MySQL 8** (`app_test`), com
`RefreshDatabase`. `phpunit.xml` aponta para o banco de teste; `make test` garante o
container no ar.

**Rationale**: exigência da constituição ("Feature tests (MySQL de teste)") e
necessidade real — as derivações usam SQL específico (janelas de data, contagens) e
os tipos DECIMAL/índices únicos precisam se comportar como em produção.

**Alternativas consideradas**: SQLite em memória — rejeitado: diverge do MySQL em
tipos, `ON DELETE`, precisão decimal e funções de data.

---

## Decisão 5: BaseModel / BaseLookupModel + convenções transversais

**Decisão**: duas classes-base em `app/Domain/Events/Models`:

- `BaseModel`: `SoftDeletes`, auditoria automática de `created_by`/`updated_by` via
  model events (booted `creating`/`updating`), `$guarded = []` com FormRequest como
  fronteira de entrada (specs futuras), casts de data em UTC.
- `BaseLookupModel`: sem soft delete/auditoria (lookups são seeded), `is_active`,
  ordenação por `sort`.

Trait `HasPublicCode` gera `code` único não sequencial (ULID/base32 curto com prefixo,
ex. `ORD-…`, `TCK-…`, `CTY-…`) para orders/tickets/vouchers.

**Rationale**: centraliza os princípios II e V da constituição num único lugar em vez
de repetir em 20 models; o gerador de código público atende FR-006.

**Alternativas consideradas**: pacote `spatie/laravel-activitylog` para auditoria
completa — adiado: a constituição pede activity log, mas a trilha rica (diffs de
campos) só é consumida na spec 008 (trilha de auditoria). Nesta spec entram
`created_by`/`updated_by`/soft delete; o pacote entra na 008 sem conflito (aditivo).
Registrado como dívida consciente no plan.

---

## Decisão 6: Derivações como métodos/escopos, com recontagem transacional preparada

**Decisão**: derivações do princípio II implementadas como **accessors e query
scopes** nos models (nunca colunas editáveis):

- `Event`: `salesOpen` (published + janela + lote vigente + vagas), `currentLot`,
  `ticketsSold`, `available`, `soldOut`
- `TicketLot`: `isCurrent`, `soldOut`, `effectivePrice` (`price_override ?? type.price`);
  lote vigente resolvido por `sort` ASC determinístico entre lotes elegíveis
- `EventShirtSize`: `soldOut` (`stock_quantity !== null && sold_count >= stock_quantity`)
- `Order`: `amountPaid`, `isExpired`
- `Ticket`: `isActive` (status não terminal)

Contagens (`tickets vivos`) contam status **não terminais** (reserved,
awaiting_payment, paid, confirmed, courtesy). `sold_count` em lotes/camisas é cache
recalculável — a fonte de verdade é a contagem; a recontagem dentro de
`DB::transaction` será exercitada pelo fluxo de compra (spec 004), mas os métodos de
recontagem nascem aqui e são testados isoladamente.

**Rationale**: contrato único e testável (ver `contracts/domain-derivations.md`);
evita a classe de bugs de estado divergente.

---

## Decisão 7: RBAC — middleware por papel + Policies mínimas

**Decisão**: `RoleMiddleware` registrado como `require.role` aceita lista
(`require.role:admin,treasury`); usuário precisa de **ao menos um** dos papéis → senão
403 na shape de erro da constituição. `Role` com constantes de slug. `User::hasRole()`
/ `hasAnyRole()` com eager load do pivot. Policies desta spec: `EventPolicy` (admin
gerencia; demais leem o publicado) — as policies específicas de
pagamento/portaria/inscrito entram nas specs que criam esses fluxos. Rotas de teste
internas (`routes/api.php` mínimo + rota fake em teste) exercitam o middleware.

**Rationale**: entrega FR-010/FR-011 sem inventar rotas de negócio que pertencem às
specs 002+.

**Alternativas consideradas**: `spatie/laravel-permission` — rejeitado: 4 papéis fixos
sem permissões granulares; pacote traria tabelas e cache desnecessários (violaria
"complexidade além do necessário" da constituição).

---

## Decisão 8: Estrutura de erros e envelope desde o dia 1

**Decisão**: `ApiExceptionHandler` (render em `bootstrap/app.php`) padroniza:
422 (validação FormRequest), 403 (`AuthorizationException`/middleware), 409
(`DomainRuleViolation` — exceção de domínio criada nesta spec), 404, 401 — todos na
shape `{ message, type, errors?, status }`. Sucesso sempre `{ data }` camelCase via
`ApiResource`/`ApiResponse` helper.

**Rationale**: FR-011/FR-018 e constituição; nascer certo evita retrofit nas 7 specs
seguintes.

---

## Decisão 9: Transições terminais rejeitadas no domínio

**Decisão**: guarda de transição de status como método de domínio
(`Ticket::transitionTo()`, `Order::transitionTo()`) que lança `DomainRuleViolation`
(→ 409) quando o status atual é terminal (cancelled/refunded/used/expired/transferred
para tickets; cancelled/expired/refunded para orders). Nesta spec a guarda existe e é
testada em nível de model; os fluxos que a exercitam via API chegam nas specs 004–007.

**Rationale**: FR-017 e princípio V; regra central demais para ficar espalhada em
services futuros.

---

## Decisão 10: Seeders em duas camadas

**Decisão**: `DatabaseSeeder` chama sempre os **estruturais** (lookups + roles);
os de **demonstração** (`AdminUserSeeder`, `SampleEventSeeder`) só rodam fora de
produção (guard por `app()->environment()`). `SampleEventSeeder` cria: 1 evento
publicado (slug fixo), 3 tipos de ingresso (individual, casal, cortesia), 2 lotes com
janelas/quantidades, 2 modelos de camisa com tamanhos (um com estoque finito), blocos
de landing de todos os tipos.

**Rationale**: FR-013; instalação limpa idêntica em qualquer máquina (SC-002) e base
navegável para as specs 002+.

---

## Riscos / notas

- **Auditoria rica adiada** (Decisão 5): registrar na spec 008 a adoção do activity
  log completo; até lá, `created_by`/`updated_by` + soft delete cumprem o princípio V.
- **`sold_count` como cache**: qualquer escrita futura que incremente deve recontar na
  transação (princípio II) — documentado no contrato de derivações para as specs 004+.
- **Scaffold React mínimo**: nesta spec o frontend é o esqueleto (Vite + React Query +
  roteador com placeholder); nenhuma tela de negócio — evita colisão com as specs
  002/003 que definem as áreas.
- **Renomeação `event_*` → nomes limpos** já nasce assim (não há migração de dados;
  banco novo) — o requisito FR-016 de migrations aditivas vale a partir daqui.
