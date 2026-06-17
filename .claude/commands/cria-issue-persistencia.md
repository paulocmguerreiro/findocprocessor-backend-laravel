---
description: "Cria issue para a camada de persistência (Repository interface + Eloquent + DTOs + testes)"
allowed-tools: [Bash, Read]
---

# /cria-issue-persistencia

Cria uma Issue no GitHub para a **camada de persistência** do stack Laravel (Vertical Slice).
Guia o utilizador na selecção de componentes e recolha de informação antes de criar a issue.

## Argumentos

- `$ARGUMENTS`: nome da entidade (ex: `"FinancialDocument"`) — opcional.

## Passos

### 1 — Identificar a entidade

Se `$ARGUMENTS` omitido → perguntar:
> "Qual é a entidade / objectivo desta issue? (ex: FinancialDocument, UserProfile)"

Verificar se existe issue de model layer para esta entidade (para referenciar como dependência).

### 2 — Selecção de componentes

Apresentar checklist:

```
Selecciona os componentes a incluir nesta issue:

[ ] Interface do Repositório    — contrato tipado com os métodos necessários
[ ] Eloquent Repository         — implementação que satisfaz a interface
[ ] DTOs                        — objectos de transferência (se necessário)
[ ] Service Provider binding    — registar interface → implementação no container
[ ] Policy com repositório      — apenas se a Policy precisa de queries para autorização
                                  (ex: verificar ownership, contar recursos por utilizador)
[ ] Testes de feature           — repositório contra SQLite in-memory
```

### 3 — Recolha de informação (adaptar ao seleccionado)

**Sempre perguntar — Use cases / operações:**
> "Quais as operações que o repositório deve suportar?"
> Exemplos: listar (com/sem filtros), criar, actualizar, marcar estado, contar, eliminar.
>
> Para cada operação:
> - Nome do método
> - Parâmetros de input (tipos)
> - Output (tipo de retorno: Model, Collection, Paginator, int, bool…)
> - Filtros opcionais?
> - Paginação?

**Se DTOs seleccionados — perguntar:**
> "Os DTOs são para input (dados de criação/actualização) ou output (resposta formatada)?"
> "Há campos opcionais nos DTOs?"

**Se Policy com repositório seleccionada — perguntar:**
> "A Policy já existe (criada em /cria-issue-modelo) ou é criada nesta issue?"
> "Que query precisa a Policy para decidir a autorização?"
>   Exemplos: verificar se `id_utilizador` do registo corresponde ao utilizador autenticado;
>             contar quantos recursos o utilizador já criou (limite por plano).
> "Que métodos da Policy precisam de acesso ao repositório? (view, update, delete…)"
> "O repositório já expõe o método necessário ou é preciso adicionar um novo?"

**Se Testes seleccionados — perguntar:**
> "Há regras de validação no repositório (ex: amount >= 0, currency ISO 4217)?
>   Se sim, quais os cenários de erro a testar?"
> "Se Policy incluída: cobrir cenários em que a query retorna owner correcto vs. owner errado."

### 4 — Verificação de invariantes

Antes de gerar o body, verificar:
- Interface usa tipos nativos PHP 8.5 (sem `mixed`, sem `array` não tipado)?
- `final readonly class` para a implementação?
- Implementação injiecta Model via construtor (não acede ao Facade)?
- Binding registado em `AppServiceProvider`?
- Se Policy com repositório: a Policy injiecta a **interface** (nunca o Eloquent Repository directamente)?
- Se Policy com repositório: o método do repositório usado é um método já existente ou um novo? (novo método → CA adicional)

### 5 — Gerar e propor issue

Gerar body no formato padrão do `/cria-issue`:

```markdown
## Contexto
[Porquê este repositório — que operações de domínio abstrai]

## Componentes
[Lista dos componentes seleccionados]

## Contrato do Repositório

| Método | Input | Output | Notas |
|--------|-------|--------|-------|
| ...    | ...   | ...    | ...   |

## DTOs (se aplicável)
[Estrutura dos DTOs ou "não aplicável"]

## Policy com repositório (se aplicável)

| Método Policy | Query no repositório       | Decisão                              |
|---------------|---------------------------|--------------------------------------|
| update()      | obterPorId($id)->id_user  | só o owner pode editar               |
| delete()      | obterPorId($id)->id_user  | só o owner pode eliminar             |

[Omitir se Policy não seleccionada ou se não precisa de queries]

## Critérios de aceitação
- [ ] CA-01: Interface declara todos os métodos com tipos completos
- [ ] CA-02: Implementação Eloquent satisfaz a interface (Larastan nível 9)
- [ ] CA-03: Binding registado — injecção via interface funciona
- [ ] CA-04: Testes cobrem todos os métodos (happy path + edge cases)
- [ ] CA-05: Policy injiecta a interface do repositório e toma decisão correcta (se aplicável)
- [ ] CA-06: Testes da Policy cobrem owner correcto (permitido) e owner errado (negado) (se aplicável)
- [ ] CA-07: 100% code coverage e 100% type coverage (composer test)
[adicionar CAs específicos com base nos use cases recolhidos]
[omitir CA-05 e CA-06 se Policy não seleccionada]

## Impacto técnico
- Afecta: infra layer (repositório) + AppServiceProvider
- SYSTEM_SPEC a actualizar: docs/system_spec/04-infra.md
- Dependências: [#N — model layer | "model deve existir"]
- Policy base: [#N — criada em /cria-issue-modelo | "criada nesta issue"]

## Invariantes em risco
- Actions injectam interface, nunca a implementação concreta
- `final readonly class` para o EloquentRepository
- SQLite (testes) — sem CHECK constraints; validação no PHP
- Policy injiecta interface do repositório (nunca Eloquent directo)

## Contrato OpenAPI
- openapi.yaml afectado: não (camada de repositório — sem endpoints)
- Breaking change: não

## Verificação RGPD/NIS2
- Dados pessoais: [sim — detalhe | não]
- Superfície de ataque: inalterada

## Fora de âmbito
- Actions, Controller e Events (issue separada: /cria-issue-logica)
- Endpoints de API
- Model e Migration (issue separada: /cria-issue-modelo)
- Policy sem necessidade de queries (issue separada: /cria-issue-modelo com componente Policy)
- Chamada à Policy nos FormRequests (issue separada: /cria-issue-logica)
```

Apresentar ao utilizador:
```
📋 Issue proposta:
Título: feat(laravel): <Entidade> — persistence layer (<componentes>)
Labels: type:feat, stack:laravel, scope:infra, prio:p2
[body completo]
Criar? [s / edita / cancela]
```

### 6 — Criar no GitHub

Se `s`:
```bash
gh issue create \
  --repo $GITHUB_REPO \
  --title "feat(laravel): <entidade> — persistence layer (<componentes>)" \
  --body "..." \
  --label "type:feat,stack:laravel,scope:infra,prio:p2"
```

### 7 — Output final

```
✅ Issue #N criada — Persistence layer: <entidade>
URL: <url>
Próximo: /planeia-issue #N
```
