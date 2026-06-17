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

**Se Testes seleccionados — perguntar:**
> "Há regras de validação no repositório (ex: amount >= 0, currency ISO 4217)?
>   Se sim, quais os cenários de erro a testar?"

### 4 — Verificação de invariantes

Antes de gerar o body, verificar:
- Interface usa tipos nativos PHP 8.5 (sem `mixed`, sem `array` não tipado)?
- `final readonly class` para a implementação?
- Implementação injiecta Model via construtor (não acede ao Facade)?
- Binding registado em `AppServiceProvider`?

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

## Critérios de aceitação
- [ ] CA-01: Interface declara todos os métodos com tipos completos
- [ ] CA-02: Implementação Eloquent satisfaz a interface (Larastan nível 9)
- [ ] CA-03: Binding registado — injecção via interface funciona
- [ ] CA-04: Testes cobrem todos os métodos (happy path + edge cases)
- [ ] CA-05: 100% code coverage e 100% type coverage (composer test)
[adicionar CAs específicos com base nos use cases recolhidos]

## Impacto técnico
- Afecta: infra layer (repositório) + AppServiceProvider
- SYSTEM_SPEC a actualizar: docs/system_spec/04-infra.md
- Dependências: [#N — model layer | "model deve existir"]

## Invariantes em risco
- Actions injectam interface, nunca a implementação concreta
- `final readonly class` para o EloquentRepository
- SQLite (testes) — sem CHECK constraints; validação no PHP

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
