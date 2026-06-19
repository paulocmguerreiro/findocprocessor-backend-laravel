---
description: "Cria issue para a camada de modelo (migration + model + factory + policy + DTOs + resource + testes)"
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

Apresentar checklist **e aguardar resposta antes de avançar**:

```
Selecciona os componentes a incluir nesta issue:

[ ] Migration        — criar tabela com campos, tipos e constraints
[ ] Model            — Eloquent model (enums, casts, relações, HasUuids)
[ ] Factory          — estados por status e/ou type
[ ] Policy           — autorização CRUD (viewAny, view, create, update, delete)
[ ] DTOs             — Value Objects para criação e actualização (construtor
                       valida invariantes estruturais)
[ ] Resource         — serialização JSON da resposta API (EntidadeResource)
[ ] Testes           — model (casts, relações, fillable) + factory (states)
                       + policy + DTOs + resource

Todos seleccionados por omissão. Remove o que não se aplica.
```

Só avançar para o Passo 3 depois de o utilizador confirmar os componentes.

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

**Se DTOs seleccionados — perguntar:**
- Os DTOs são para `Criar` e `Actualizar`, ou só para uma operação?
- O `ActualizarDto` segue update completo (todos os campos obrigatórios) ou
  permite actualização parcial (campos opcionais com `?`)?
- Além de trim em strings, há outras invariantes estruturais no construtor?
  (ex: formato mínimo, comprimento, valores negativos)
- Os timestamps devem ser incluídos nos DTOs ou acede ao Model directamente?

**Se Resource seleccionado — perguntar:**
- Quais os campos a expor no JSON? (por omissão: todos excepto timestamps)
- Os timestamps devem ser incluídos no Resource?
- Há campos derivados ou formatados? (ex: enum → `->value`, carbon → format)

**Se Testes seleccionados — perguntar:**
- Há regras de negócio no model que precisam de teste directo?
  (ex: accessors, mutators, scopes)
- Se Policy incluída: cobrir cenários permitido + negado para cada método
- Se DTOs incluídos: cobrir happy path + cada `\InvalidArgumentException`
- Se Resource incluído: cobrir serialização (campos presentes e tipos correctos)

### 4 — Verificação de invariantes

Antes de gerar o body, verificar:
- `HasUuids` como PK? (obrigatório — ver CLAUDE.md)
- `declare(strict_types=1)` mencionado nos ACs?
- `#[Fillable]` e `#[Hidden]` em vez de arrays?
- Enums como PHP 8.5 BackedEnum (string)?
- Se Policy: nome segue convenção `<Entidade>Policy` → Laravel descobre automaticamente; alternativa: anotar o Model com `#[UsePolicy(EntidadePolicy::class)]`
- Policy em `app/Policies/` (fora dos feature slices — é partilhada entre operações)
- Se DTOs: `final readonly class`; construtor valida invariantes e lança
  `\InvalidArgumentException`; `fromRequest()` **não incluído** nesta issue
  (pertence à issue de lógica quando os FormRequests forem criados)
- Se DTOs: campos que precisam de promoção condicional (ex: flag A força flag B)
  **não podem usar constructor promotion** — atribuição manual no corpo do construtor
- Se Resource: `final class extends JsonResource`; `@mixin Model` no PHPDoc;
  `toArray()` com `@return` array shape completo; localização em
  `app/Features/<Entidade>/`

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

## Contrato dos DTOs (se aplicável)

| Campo | Tipo | Obrigatório | Notas |
|-------|------|-------------|-------|
| ...   | ...  | ...         | ...   |

`ActualizarDto`: update completo (todos obrigatórios) ou parcial (campos com `?`)?
`fromRequest()` não incluído — adicionado na issue de lógica.

[Omitir se DTOs não seleccionados]

## Contrato do Resource (se aplicável)

`<Entidade>Resource extends JsonResource` — localização:
`app/Features/<Entidade>/<Entidade>Resource.php`

| Campo | Tipo | Fonte | Notas |
|-------|------|-------|-------|
| ...   | ...  | ...   | ...   |

[Omitir se Resource não seleccionado]

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
- [ ] CA-06: `CriarDto` e `ActualizarDto` são `final readonly class` com
             construtor que valida invariantes
- [ ] CA-07: Construtor lança `\InvalidArgumentException` para cada
             invariante violada (campos vazios após trim, etc.)
- [ ] CA-08: `<Entidade>Resource` serializa todos os campos do contrato com
             tipos correctos
- [ ] CA-09: Testes dos DTOs cobrem happy path + cada excepção do construtor
- [ ] CA-10: Testes do Resource cobrem serialização (campos presentes e tipos)
- [ ] CA-11: 100% code coverage e 100% type coverage (composer test)
[adicionar CAs específicos com base na informação recolhida]
[omitir CA-04/CA-05 se Policy não seleccionada]
[omitir CA-06/CA-07/CA-09 se DTOs não seleccionados]
[omitir CA-08/CA-10 se Resource não seleccionado]

## Impacto técnico
- Afecta: domain layer
- SYSTEM_SPEC a actualizar: `docs/system_spec/03-models/<slug>.md`
  [+ `docs/system_spec/02-shared/enums.md` se novos enums shared]
  [+ `docs/system_spec/05-routes/<slug>.md` se Resource incluído]
- Dependências: [#N | "nenhuma"]

## Invariantes em risco
- HasUuids como PK (nunca ID incremental)
- Enums como PHP 8.5 BackedEnum (string)
- SQLite (testes) não suporta CHECK constraints — validação no PHP
- Policy em app/Policies/ — partilhada, fora dos feature slices
- Policy descoberta por convenção de nome (`<Entidade>Policy`) ou via `#[UsePolicy]` no Model
- DTOs: `final readonly class`; `fromRequest()` não incluído — adicionado na issue de lógica
- DTOs: campos com promoção condicional não usam constructor promotion
- Resource: `final class`; localização em `app/Features/<Entidade>/`

## Contrato OpenAPI
- openapi.yaml afectado: não (camada de modelo — sem endpoints)
- Breaking change: não

## Verificação RGPD/NIS2
- Dados pessoais: [sim — detalhe | não]
- Superfície de ataque: inalterada

## Fora de âmbito
- Repositório e interface (issue separada: /cria-issue-persistencia)
- Actions, Controller e FormRequests com authorize() (issue separada: /cria-issue-logica)
- `fromRequest()` nos DTOs (issue separada: /cria-issue-logica — quando FormRequests existirem)
- Endpoints de API
```

Apresentar ao utilizador:
```
📋 Issue proposta:
Título: feat(laravel): <Entidade> — model layer (<componentes seleccionados>)
Labels: type:feat, stack:laravel, scope:domain, prio:p2
[body completo]
Criar? [s / edita / cancela]
```

O título lista apenas os componentes seleccionados — ex:
- `model layer (migration + model + factory + testes)`
- `model layer (migration + model + factory + DTOs + resource + testes)`
- `model layer (DTOs + resource + testes)`

### 6 — Criar no GitHub

Se `s`:
```bash
gh issue create \
  --repo $GITHUB_REPO \
  --title "feat(laravel): <entidade> — model layer (<componentes seleccionados>)" \
  --body "..." \
  --label "type:feat,stack:laravel,scope:domain,prio:p2"
```

### 7 — Output final

```
✅ Issue #N criada — Model layer: <entidade>
URL: <url>
Próximo: /planeia-issue #N
```
