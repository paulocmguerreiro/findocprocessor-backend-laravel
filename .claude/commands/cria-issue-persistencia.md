---
description: "Cria issue para a camada de persistência (Repository interface + Eloquent Repository + Services + Service Provider + testes)"
allowed-tools: [Bash, Read]
effort: high
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

Apresentar checklist **e aguardar resposta antes de avançar**:

```
Selecciona os componentes a incluir nesta issue:

[ ] Interface do Repositório    — contrato tipado com os métodos necessários
[ ] Eloquent Repository         — implementação que satisfaz a interface
[ ] Service(s)                  — classes de serviço que encapsulam lógica complexa,
                                  integrações externas ou operações transversais
                                  (ex: cache, API externa, cálculo partilhado)
[ ] Service Provider binding    — registar interface(s) → implementação(ões) no container
[ ] Policy com repositório      — apenas se a Policy precisa de queries para autorização
                                  (ex: verificar ownership, contar recursos por utilizador)
[ ] Testes de feature           — repositório + services contra SQLite in-memory
```

Só avançar para o Passo 3 depois de o utilizador confirmar os componentes.

### 3 — Recolha de informação (adaptar ao seleccionado)

**Sempre perguntar — Use cases / operações do repositório:**
> "Quais as operações que o repositório deve suportar?"
> Exemplos: listar (com/sem filtros), criar, actualizar, marcar estado, contar, eliminar.
>
> Para cada operação:
> - Nome do método (PT: `listar`, `obterPorId`, `criar`, `actualizar`, `eliminar`, …)
> - Parâmetros de input (tipos)
> - Output (tipo de retorno: `Model`, `Collection`, `CursorPaginator`, `int`, `bool`, `?Model`…)
> - Filtros opcionais?
> - Paginação? (cursor obrigatório — nunca OFFSET)

**Se Services seleccionados — perguntar:**
> "O que faz o Service? Qual a responsabilidade única que justifica existir fora do repositório?"
>   Exemplos: encapsular chamadas a API externa, cálculo partilhado entre Actions,
>             invalidação de cache, orquestração de múltiplos repositórios.
> "O Service expõe uma interface ou é uma classe concreta directamente?"
>   (interface → binding no Service Provider; classe concreta → injecção directa)
> "O Service tem estado ou é stateless?"
> "Que dependências injiecta? (repositório, HTTP client, cache, …)"

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

Antes de finalizar a issue, verificar conformidade com `docs/system_spec/02-shared/contratos-por-camada.md` — secção "Camada de persistência" e `docs/system_spec/04-infra/repositories.md`.

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

## Contrato dos Services (se aplicável)

| Service / Interface | Método | Input | Output | Responsabilidade |
|--------------------|--------|-------|--------|------------------|
| ...                | ...    | ...   | ...    | ...              |

[Omitir se Services não seleccionados]

## Policy com repositório (se aplicável)

| Método Policy | Query no repositório       | Decisão                              |
|---------------|---------------------------|--------------------------------------|
| update()      | obterPorId($id)->id_user  | só o owner pode editar               |
| delete()      | obterPorId($id)->id_user  | só o owner pode eliminar             |

[Omitir se Policy não seleccionada ou se não precisa de queries]

## Critérios de aceitação
- [ ] CA-01: Interface do repositório declara todos os métodos com tipos completos
- [ ] CA-02: EloquentRepository satisfaz a interface (Larastan nível 9)
- [ ] CA-03: Binding(s) registados — injecção via interface funciona
- [ ] CA-04: Paginação usa `cursorPaginate()` — nunca `paginate()` com OFFSET
- [ ] CA-05: Testes do repositório cobrem todos os métodos (happy path + edge cases)
- [ ] CA-06: Service(s) satisfazem o contrato declarado (interface ou classe concreta)
- [ ] CA-07: Testes dos Services cobrem happy path + cenários de falha
- [ ] CA-08: Policy injiecta a interface do repositório e toma decisão correcta
- [ ] CA-09: Testes da Policy cobrem owner correcto (permitido) e owner errado (negado)
- [ ] CA-10: 100% code coverage e 100% type coverage (`composer test`)
[adicionar CAs específicos com base nos use cases recolhidos]
[omitir CA-06/CA-07 se Services não seleccionados]
[omitir CA-08/CA-09 se Policy com repositório não seleccionada]

## Impacto técnico
- Afecta: infra layer (repositório + services) + AppServiceProvider
- SYSTEM_SPEC a actualizar: `docs/system_spec/04-infra/repositories.md`
- Dependências: [#N — model layer | "model deve existir"]
- Policy base: [#N — criada em /cria-issue-modelo | "criada nesta issue"]

## Invariantes em risco
- Actions injectam interface, nunca a implementação concreta
- `final class` para o EloquentRepository (não `readonly` — Eloquent não é imutável)
- Paginação: `cursorPaginate()` obrigatório — nunca OFFSET
- SQLite (testes) — sem CHECK constraints; validação no PHP
- Services com interface: binding obrigatório no AppServiceProvider
- Policy injiecta interface do repositório (nunca EloquentRepository directo)

## Contrato OpenAPI
- openapi.yaml afectado: não (camada de repositório — sem endpoints)
- Breaking change: não

## Verificação RGPD/NIS2
- Dados pessoais: [sim — detalhe | não]
- Superfície de ataque: inalterada

## Fora de âmbito
- DTOs e Resource (issue separada: /cria-issue-modelo)
- Actions, Controller, FormRequests e Events (issue separada: /cria-issue-logica)
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
