---
description: "Cria issue para a camada de modelo (migration + model + factory + policy + testes)"
allowed-tools: [Bash, Read]
---

# /cria-issue-modelo

Cria uma Issue no GitHub para a **camada de modelo** do stack Laravel (Vertical Slice).
Guia o utilizador na selecção de componentes e recolha de informação antes de criar a issue.

## Argumentos

- `$ARGUMENTS`: nome da entidade ou descrição curta (ex: `"FinancialDocument"`) — opcional.

## Passos

### 1 — Identificar a entidade

Se `$ARGUMENTS` omitido → perguntar:
> "Qual é a entidade / objectivo desta issue? (ex: FinancialDocument, UserProfile)"

### 2 — Selecção de componentes

Apresentar checklist:

```
Selecciona os componentes a incluir nesta issue:

[ ] Migration       — criar tabela com campos, tipos e constraints
[ ] Model           — Eloquent model (enums, casts, relações, HasUuids)
[ ] Factory         — estados por status e/ou type
[ ] Policy          — autorização CRUD (viewAny, view, create, update, delete)
[ ] Testes unitários — model (casts, relações, fillable) + factory (states) + policy

Todos seleccionados por omissão. Remove o que não se aplica.
```

### 3 — Recolha de informação (adaptar ao seleccionado)

**Se Migration seleccionada — perguntar:**
- Quais os campos? (nome, tipo, obrigatório, default)
- Há relações (FK)? Para que tabelas? Cascade?
- UUID ou ID incremental como PK?
- Algum índice específico além da PK?

**Se Model seleccionado — perguntar:**
- Há enums? Quais os valores?
- Campos sensíveis a esconder (`#[Hidden]`)?
- Relações adicionais além das FKs da migration?

**Se Factory seleccionada — perguntar:**
- Que estados (states) são necessários? (ex: por status, por type)
- Há campos que devem ter valores fixos em alguns states?

**Se Policy seleccionada — perguntar:**
- Quem pode fazer o quê? (ex: qualquer utilizador autenticado, só owner, só admin)
- Que operações CRUD esta entidade expõe? (viewAny, view, create, update, delete)
- Há regras de ownership? (ex: só o criador pode editar)
- Há papéis/permissões (roles) envolvidos? (ex: `admin`, `gestor`)
- Alguma operação é pública (sem autenticação)?

**Se Testes seleccionados — perguntar:**
- Há regras de negócio no model que precisam de teste directo?
  (ex: accessors, mutators, scopes)
- Se Policy incluída: cobrir cenários permitido + negado para cada método

### 4 — Verificação de invariantes

Antes de gerar o body, verificar:
- `HasUuids` como PK? (obrigatório — ver CLAUDE.md)
- `declare(strict_types=1)` mencionado nos ACs?
- `#[Fillable]` e `#[Hidden]` em vez de arrays?
- Enums como PHP 8.5 BackedEnum (string)?
- Se Policy: nome segue convenção `<Entidade>Policy` → Laravel descobre automaticamente; alternativa: anotar o Model com `#[UsePolicy(EntidadePolicy::class)]`
- Policy em `app/Policies/` (fora dos feature slices — é partilhada entre operações)

### 5 — Gerar e propor issue

Gerar body no formato padrão do `/cria-issue`:

```markdown
## Contexto
[Porquê esta issue — o que representa a entidade no domínio]

## Componentes
[Lista dos componentes seleccionados com breve descrição]

## Modelo de dados
| Campo | Tipo | Obrigatório | Default | Notas |
|-------|------|-------------|---------|-------|
| ...   | ...  | ...         | ...     | ...   |

## Relações
[Tabela de relações ou "nenhuma"]

## Factory states
[Lista de states ou "apenas estado base"]

## Policy de autorização
| Método      | Quem pode          | Notas                          |
|-------------|-------------------|--------------------------------|
| viewAny()   | [utilizador auth] | [listagem geral]               |
| view()      | [utilizador auth] | [ver detalhe]                  |
| create()    | [utilizador auth] | [criar nova entidade]          |
| update()    | [owner / admin]   | [só o criador ou admin]        |
| delete()    | [owner / admin]   | [só o criador ou admin]        |

[Omitir se Policy não seleccionada]

## Critérios de aceitação
- [ ] CA-01: Migration cria a tabela com todos os campos e constraints
- [ ] CA-02: Model tem casts correctos para enums e tipos especiais
- [ ] CA-03: Factory produz instâncias válidas para cada state definido
- [ ] CA-04: Policy cobre todos os métodos CRUD com regras correctas
- [ ] CA-05: Testes da Policy cobrem cenários permitido + negado por método
- [ ] CA-06: 100% code coverage e 100% type coverage (composer test)
[adicionar CAs específicos com base na informação recolhida]
[omitir CA-04 e CA-05 se Policy não seleccionada]

## Impacto técnico
- Afecta: domain layer
- SYSTEM_SPEC a actualizar: docs/system_spec/03-models.md
- Dependências: [#N | "nenhuma"]

## Invariantes em risco
- HasUuids como PK (nunca ID incremental)
- Enums como PHP 8.5 BackedEnum (string)
- SQLite (testes) não suporta CHECK constraints — validação no PHP
- Policy em app/Policies/ — partilhada, fora dos feature slices
- Policy descoberta por convenção de nome (`<Entidade>Policy`) ou via `#[UsePolicy]` no Model

## Contrato OpenAPI
- openapi.yaml afectado: não (camada de modelo — sem endpoints)
- Breaking change: não

## Verificação RGPD/NIS2
- Dados pessoais: [sim — detalhe | não]
- Superfície de ataque: inalterada

## Fora de âmbito
- Repositório e interface (issue separada: /cria-issue-persistencia)
- Actions, Controller e FormRequests com authorize() (issue separada: /cria-issue-logica)
- Endpoints de API
```

Apresentar ao utilizador:
```
📋 Issue proposta:
Título: feat(laravel): <Entidade> — model layer (<componentes>)
Labels: type:feat, stack:laravel, scope:domain, prio:p2
[body completo]
Criar? [s / edita / cancela]
```

### 6 — Criar no GitHub

Se `s`:
```bash
gh issue create \
  --repo $GITHUB_REPO \
  --title "feat(laravel): <entidade> — model layer (<componentes>)" \
  --body "..." \
  --label "type:feat,stack:laravel,scope:domain,prio:p2"
```

### 7 — Output final

```
✅ Issue #N criada — Model layer: <entidade>
URL: <url>
Próximo: /planeia-issue #N
```
