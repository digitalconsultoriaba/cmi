# Feature Specification: Autenticação do Inscrito (login)

**Feature Branch**: `002-auth-inscrito`

**Created**: 2026-07-03

**Status**: Implemented

**Input**: User description: "002-login — autenticação do inscrito: cadastro com e-mail e senha (com verificação de e-mail e reset), login com Google, sessão de acesso (entrar/sair), consulta da própria conta e rotas protegidas no frontend. Papel de inscrito atribuído automaticamente no cadastro."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Cadastro com e-mail e senha (Priority: P1)

Uma pessoa interessada no evento cria sua conta informando nome, e-mail e senha.
Recebe um e-mail com link de verificação; ao confirmar, a conta fica verificada.
Qualquer provedor de e-mail serve — o cadastro não depende de conta Google.

**Why this priority**: sem conta não há compra (spec 004); o cadastro tradicional é
o caminho universal que funciona para qualquer pessoa.

**Independent Test**: registrar uma conta nova via formulário, receber o link de
verificação (caixa de e-mail de dev), confirmar e ver a conta como verificada.

**Acceptance Scenarios**:

1. **Given** um visitante sem conta, **When** se cadastra com nome, e-mail válido e
   senha adequada, **Then** a conta é criada, recebe o papel de inscrito
   automaticamente e um e-mail de verificação é enviado.
2. **Given** um e-mail já cadastrado, **When** alguém tenta se cadastrar de novo com
   ele, **Then** o cadastro é recusado com mensagem clara (sem revelar dados da conta
   existente).
3. **Given** uma conta recém-criada não verificada, **When** a pessoa clica no link
   do e-mail, **Then** a conta passa a "verificada" e isso fica registrado.
4. **Given** um link de verificação adulterado ou de outra conta, **When** acessado,
   **Then** a verificação é recusada.
5. **Given** senha fraca (curta demais) ou e-mail malformado, **When** submetido,
   **Then** o formulário retorna erros de validação campo a campo em pt-BR.

---

### User Story 2 - Entrar, sair e manter a sessão (Priority: P2)

Quem tem conta entra com e-mail e senha e permanece autenticado durante a navegação;
consegue ver os dados da própria conta (incluindo papéis) e sair. Áreas restritas do
site só aparecem para quem está autenticado; visitantes são direcionados ao login.

**Why this priority**: é a porta de entrada que todas as áreas logadas (specs 003+)
vão usar; sem sessão estável nada mais funciona.

**Independent Test**: fazer login com um usuário do seed, navegar para uma rota
protegida, consultar os próprios dados, sair e confirmar que a rota protegida volta
a exigir login.

**Acceptance Scenarios**:

1. **Given** credenciais corretas, **When** a pessoa entra, **Then** a sessão é
   estabelecida e os dados da própria conta (nome, e-mail, papéis, verificação)
   ficam disponíveis.
2. **Given** credenciais erradas, **When** tenta entrar, **Then** recebe recusa
   genérica ("credenciais inválidas") sem indicar qual campo errou.
3. **Given** tentativas repetidas de login com erro, **When** excedem o limite,
   **Then** novas tentativas são temporariamente bloqueadas (proteção contra força
   bruta).
4. **Given** uma pessoa autenticada, **When** sai (logout), **Then** a sessão é
   encerrada e as rotas protegidas voltam a exigir login.
5. **Given** um visitante não autenticado, **When** acessa uma área restrita do
   site, **Then** é direcionado à tela de login e, após entrar, retorna ao destino.

---

### User Story 3 - Entrar com Google (Priority: P3)

A pessoa entra (ou cria a conta) com um clique usando sua conta Google, sem
gerenciar senha. Se já existir cadastro com o mesmo e-mail, a conta Google é
vinculada ao cadastro existente — nunca duplica.

**Why this priority**: reduz atrito no checkout (decisão da base), mas o produto
funciona sem ele — por isso vem depois do fluxo universal.

**Independent Test**: com o provedor simulado, completar o fluxo de entrada Google
para (a) conta nova e (b) e-mail já cadastrado, verificando criação e vínculo.

**Acceptance Scenarios**:

1. **Given** uma pessoa sem cadastro, **When** entra com Google, **Then** a conta é
   criada com os dados do Google (nome, e-mail, foto), sem senha local, já
   verificada e com papel de inscrito.
2. **Given** um cadastro existente com o mesmo e-mail, **When** essa pessoa entra
   com Google, **Then** o Google é vinculado à conta existente (sem duplicar) e ela
   passa a poder entrar das duas formas.
3. **Given** uma conta criada só pelo Google, **When** tenta entrar com e-mail e
   senha, **Then** recebe orientação a entrar com Google ou definir uma senha pelo
   fluxo de redefinição.
4. **Given** o fluxo Google cancelado ou com erro no provedor, **When** a pessoa
   retorna ao site, **Then** vê mensagem amigável e permanece deslogada.

---

### User Story 4 - Redefinir a senha (Priority: P4)

Quem esqueceu a senha informa o e-mail, recebe um link temporário e define uma nova
senha. Também é o caminho para contas só-Google passarem a ter senha local.

**Why this priority**: essencial para suporte e autonomia, mas depende dos fluxos
anteriores existirem.

**Independent Test**: solicitar redefinição, abrir o link do e-mail de dev, definir
nova senha e entrar com ela.

**Acceptance Scenarios**:

1. **Given** uma conta existente, **When** solicita redefinição, **Then** recebe
   e-mail com link temporário; ao definir a nova senha, consegue entrar com ela.
2. **Given** um e-mail não cadastrado, **When** solicita redefinição, **Then** a
   resposta é a mesma de um e-mail existente (não revela se a conta existe).
3. **Given** um link de redefinição expirado ou já usado, **When** acessado,
   **Then** é recusado com orientação a solicitar um novo.
4. **Given** uma conta só-Google, **When** completa a redefinição, **Then** passa a
   ter senha local além do acesso Google.

---

### Edge Cases

- E-mail com maiúsculas/espaços: normalizado no cadastro e no login (não cria conta
  duplicada por variação de caixa).
- Pessoa verifica o e-mail duas vezes (link reaberto): segunda visita é inócua, sem
  erro assustador.
- Reenvio do e-mail de verificação: disponível, com limite de frequência.
- Conta Google cujo e-mail muda no Google: o vínculo é pelo identificador Google,
  não pelo e-mail — login continua funcionando.
- Sessão expirada no meio da navegação: chamadas retornam "não autenticado" e o
  front direciona ao login sem perder o destino.
- Usuário com papéis adicionais (ex.: admin) entra pelo mesmo fluxo: a resposta da
  conta lista todos os papéis (o painel usa isso nas specs 003+).
- Solicitações repetidas de redefinição: apenas o link mais recente vale.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O sistema MUST permitir cadastro com nome, e-mail (único,
  normalizado) e senha com requisito mínimo de força (ao menos 8 caracteres).
- **FR-002**: Todo cadastro MUST atribuir automaticamente o papel de inscrito
  (`attendee`), sem intervenção manual.
- **FR-003**: O sistema MUST enviar e-mail de verificação com link assinado e
  registrar o momento da verificação; links adulterados MUST ser recusados.
- **FR-004**: O sistema MUST permitir reenvio do e-mail de verificação, com limite
  de frequência.
- **FR-005**: O sistema MUST autenticar por e-mail e senha, mantendo sessão até o
  logout ou expiração; erros de credencial MUST ser genéricos.
- **FR-006**: Tentativas de login MUST ter limitação de taxa (proteção contra força
  bruta), com bloqueio temporário após exceder o limite.
- **FR-007**: O sistema MUST expor consulta da própria conta (nome, e-mail,
  documento/telefone se houver, papéis, estado de verificação) apenas para a pessoa
  autenticada.
- **FR-008**: O sistema MUST permitir logout, invalidando a sessão corrente.
- **FR-009**: O sistema MUST oferecer entrada via Google: conta nova nasce sem senha
  local, já verificada, com papel de inscrito; e-mail já cadastrado MUST ser
  vinculado (nunca duplicado), pelo identificador estável do Google.
- **FR-010**: Conta só-Google que tentar login com senha MUST receber orientação
  (entrar com Google ou definir senha via redefinição) — nunca um erro opaco.
- **FR-011**: O sistema MUST oferecer redefinição de senha por link temporário de
  uso único; a resposta à solicitação MUST ser idêntica para e-mails existentes e
  inexistentes.
- **FR-012**: O frontend MUST proteger rotas restritas: visitante é direcionado ao
  login e retorna ao destino após autenticar; sessão expirada tem o mesmo
  tratamento.
- **FR-013**: Mensagens ao usuário MUST estar em pt-BR e seguir o envelope de
  API/erros da fundação (validação 422, não autenticado 401).
- **FR-014**: Nenhuma credencial (senha, segredos do Google) MUST aparecer em logs,
  respostas de API ou repositório; senhas MUST ser armazenadas com hash forte.
- **FR-015**: As telas de cadastro/login/redefinição MUST funcionar no frontend da
  plataforma (não apenas via API), com validação amigável campo a campo.

### Key Entities

- **Conta de usuário**: já existe na fundação (nome, e-mail único, senha opcional,
  vínculo Google opcional, verificação); esta spec passa a movimentá-la.
- **Vínculo Google**: identificador estável do provedor associado à conta; permite
  entrada sem senha e coexiste com senha local.
- **Sessão**: estado autenticado da pessoa no navegador, do login ao logout ou
  expiração.
- **Token de verificação / redefinição**: links temporários assinados, de uso
  único, entregues por e-mail.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Uma pessoa sem conta completa o cadastro e a verificação de e-mail em
  menos de 3 minutos (ambiente de dev, e-mail local).
- **SC-002**: Login com credenciais válidas conclui em até 5 segundos e dá acesso
  imediato às áreas restritas; logout bloqueia essas áreas imediatamente.
- **SC-003**: Entrada com Google (conta nova ou vínculo) conclui em um único fluxo,
  sem criar conta duplicada em 100% dos casos de e-mail repetido.
- **SC-004**: 100% das tentativas com credenciais erradas recebem resposta genérica;
  após o limite de tentativas, o bloqueio temporário é aplicado.
- **SC-005**: Redefinição de senha funciona de ponta a ponta (solicitar → e-mail →
  nova senha → login) inclusive para contas só-Google; links expirados/reusados são
  recusados em 100% dos casos.
- **SC-006**: A suíte de testes da spec cobre os cenários das 4 user stories e passa
  integralmente; o fluxo Google é coberto com provedor simulado.
- **SC-007**: Nenhum segredo real (credenciais Google) versionado — apenas
  placeholders no exemplo de configuração.

## Assumptions

- A base técnica é a fundação (spec 001): sessão por cookie (Sanctum SPA), envelope
  `{ data }`, shape de erros, papéis seeded e usuários de dev — nada disso é
  redefinido aqui.
- Verificação de e-mail **não bloqueia o login** nesta spec; políticas que exijam
  conta verificada (ex.: concluir compra) serão definidas pelas specs que consomem
  (004+). O estado de verificação já fica disponível na consulta da conta.
- O e-mail de dev usa o Mailpit do ambiente (spec 001); em produção, o provedor SMTP
  real é configuração de deploy, fora desta spec.
- O fluxo Google exige credenciais OAuth (client id/secret) — bloqueador externo do
  ROADMAP. Especificação, telas e testes (com provedor simulado) não dependem delas;
  apenas o teste manual de ponta a ponta com o Google real.
- Login social limita-se ao Google no MVP (decisão da base); outros provedores
  seriam spec nova.
- Exclusão de conta/LGPD (direito ao esquecimento) fica para a Fase 2 do produto,
  conforme ROADMAP.
- Limites de taxa seguem padrões da indústria (ex.: 5 tentativas de login por
  minuto por combinação e-mail+IP; reenvio de verificação a cada 60s) — valores
  exatos são detalhe do plano.
