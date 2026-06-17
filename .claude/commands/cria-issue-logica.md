---
description: "Cria issue para a camada de lógica (Actions + Controller + Events + Listeners + testes)"
allowed-tools: [Bash, Read]
---

# /cria-issue-logica

Cria uma Issue no GitHub para a **camada de lógica** do stack Laravel (Vertical Slice).
Guia o utilizador na selecção de componentes e recolha de informação antes de criar a issue.

## Argumentos

- `$ARGUMENTS`: nome da entidade ou feature (ex: `"FinancialDocument"`) — opcional.

## Passos

### 1 — Identificar a entidade / feature

Se `$ARGUMENTS` omitido → perguntar:
> "Qual é a entidade / feature desta issue? (ex: FinancialDocument, UserProfile)"

Verificar se existem issues de model layer e persistence layer para esta entidade (referenciar como dependências).
Se existir uma `<Entidade>Policy` (criada em /cria-issue-modelo), confirmar que será usada nos FormRequests desta issue.

### 2 — Selecção de componentes

Apresentar checklist:

```
Selecciona os componentes a incluir nesta issue:

[ ] Actions          — uma por operação (Create, Update, Delete, List, …)
[ ] Controller       — dispatch para Actions; zero lógica
[ ] Form Requests    — validação de input + authorize() via Policy por operação
[ ] API Resources    — formatação do output (se necessário)
[ ] Events           — disparados dentro das Actions
[ ] Listeners        — reagem a Events (pode ser issue futura)
[ ] Observers        — reagem a eventos Eloquent (pode ser issue futura)
[ ] Testes de feature — Actions + Controller + Events + autorização (endpoints HTTP)
```

### 3 — Recolha de informação (adaptar ao seleccionado)

**Sempre perguntar — Operações:**
> "Quais as operações a implementar?"
> Exemplos: Criar, Listar (com filtros?), Ver detalhe, Actualizar, Eliminar, Mudar estado.
>
> Para cada operação:
> - Nome (ex: CreateFinancialDocumentAction)
> - Input (campos do request ou parâmetros)
> - Output (response format, status HTTP)
> - Regras de negócio específicas desta operação

**Se Form Requests seleccionados — perguntar:**
> "Existe já uma `<Entidade>Policy` para esta entidade (criada em /cria-issue-modelo)?"
>
> Se sim: o `authorize()` de cada FormRequest chama a Policy:
> - `CriarXxxRequest` → `$this->authorize('create', Xxx::class)`
> - `ListarXxxsRequest` → `$this->authorize('viewAny', Xxx::class)`
> - `VerXxxRequest` → `$this->authorize('view', $this->route('xxx'))`
> - `ActualizarXxxRequest` → `$this->authorize('update', $this->route('xxx'))`
> - `EliminarXxxRequest` → `$this->authorize('delete', $this->route('xxx'))`
>
> Se não existe Policy: indicar que `authorize()` retorna `true` temporariamente e criar issue separada para a Policy.
>
> "Há operações públicas (sem autenticação)? Quais?" — esses FormRequests retornam `true` de forma explícita e documentada.

**Se Events seleccionados — perguntar:**
> "Para que operações são disparados eventos?"
> "Que dados o evento transporta (payload)?"
> "Há Listeners/Observers nesta issue ou ficam para uma issue futura?"

**Se Listeners seleccionados — perguntar:**
> "O que faz o Listener quando recebe o evento? (ex: notificação, log, job)"
> "É síncrono ou assíncrono (queue)?"

**Se Testes seleccionados — perguntar:**
> "Há endpoints que requerem autenticação?"
> "Há cenários de autorização a testar? (ex: utilizador sem permissão recebe 403)"
> "Há cenários de erro específicos a testar (ex: entidade não encontrada, permissão negada)?"

### 4 — Verificação de invariantes

Antes de gerar o body, verificar:
- Controller tem zero lógica — apenas dispatch?
- Cada operação tem a sua própria Action (não uma Action multi-propósito)?
- Actions injectam `*RepositoryInterface`, nunca o Model directamente?
- Events são disparados na Action (nunca no Controller nem no Model)?
- `FormRequest::authorize()` chama a Policy via `$this->authorize()` — nunca `return true` hardcoded sem justificação?
- Se não existe Policy ainda, `return true` é aceitável temporariamente mas deve ser documentado como dívida técnica?

### 5 — Gerar e propor issue

Gerar body no formato padrão do `/cria-issue`:

```markdown
## Contexto
[Porquê esta feature — que casos de uso implementa]

## Componentes
[Lista dos componentes seleccionados]

## Operações

| Operação  | Action                   | Método HTTP | Rota        | Evento disparado | Policy method     |
|-----------|--------------------------|-------------|-------------|-----------------|-------------------|
| Criar     | CriarXxxAction           | POST        | /api/xxs    | XxxCriado       | create()          |
| Listar    | ListarXxsAction          | GET         | /api/xxs    | —               | viewAny()         |
| Ver       | VerXxxAction             | GET         | /api/xxs/{id} | —             | view()            |
| Actualizar | ActualizarXxxAction     | PATCH       | /api/xxs/{id} | XxxActualizado | update()         |
| Eliminar  | EliminarXxxAction        | DELETE      | /api/xxs/{id} | XxxEliminado  | delete()          |

## Autorização (FormRequests → Policy)

| FormRequest              | authorize() chama         | Autenticação obrigatória |
|--------------------------|--------------------------|--------------------------|
| CriarXxxRequest          | create(Xxx::class)        | sim                      |
| ListarXxsRequest         | viewAny(Xxx::class)       | sim                      |
| VerXxxRequest            | view($this->route('xxx')) | sim                      |
| ActualizarXxxRequest     | update($this->route('xxx')) | sim                    |
| EliminarXxxRequest       | delete($this->route('xxx')) | sim                    |

[Omitir ou adaptar se não há Policy ou se há operações públicas]

## Events (se aplicável)

| Evento | Disparado em | Payload | Listener |
|--------|--------------|---------|----------|
| ...    | ...          | ...     | ...      |

## Critérios de aceitação
- [ ] CA-01: Cada operação tem a sua Action com método `handle()` único
- [ ] CA-02: Controller não contém lógica de negócio — apenas dispatch
- [ ] CA-03: Actions injectam a interface do repositório (nunca Eloquent directo)
- [ ] CA-04: Events são disparados dentro das Actions
- [ ] CA-05: `FormRequest::authorize()` chama a Policy correcta — nunca `return true` sem justificação
- [ ] CA-06: Testes de feature cobrem todos os endpoints (happy path + 403 + 404)
- [ ] CA-07: 100% code coverage e 100% type coverage (composer test)
[adicionar CAs específicos com base nas operações recolhidas]

## Rotas a adicionar
[Lista de rotas ou "a definir no planeia-issue"]

## Impacto técnico
- Afecta: features layer + routes
- SYSTEM_SPEC a actualizar: docs/system_spec/01-features.md, docs/system_spec/05-routes.md
- Dependências: [#N — model layer | #M — persistence layer]
- Policy: [#P — model layer com XxxPolicy | "Policy a criar — dívida técnica"]

## Invariantes em risco
- Controller sem lógica (nunca `if`, nunca query directa)
- Actions: uma por operação, método `handle()` único
- Events: disparados na Action, nunca no Controller
- `FormRequest::authorize()`: chama `$this->authorize()` com Policy — nunca `return true` hardcoded

## Contrato OpenAPI
- openapi.yaml afectado: sim — adicionar endpoints
- Breaking change: não (novos endpoints)

## Verificação RGPD/NIS2
- Dados pessoais: [sim — detalhe | não]
- Superfície de ataque: aumentada — novos endpoints expostos

## Fora de âmbito
- Model e Migration: issue /cria-issue-modelo
- Repositório: issue /cria-issue-persistencia
- Policy (se ainda não existe): issue separada /cria-issue-modelo com componente Policy
[outros itens fora de âmbito — ex: Listeners se adiados]
```

Apresentar ao utilizador:
```
📋 Issue proposta:
Título: feat(laravel): <Entidade> — feature slice (<componentes>)
Labels: type:feat, stack:laravel, scope:api, prio:p2
[body completo]
Criar? [s / edita / cancela]
```

### 6 — Criar no GitHub

Se `s`:
```bash
gh issue create \
  --repo $GITHUB_REPO \
  --title "feat(laravel): <entidade> — feature slice (<componentes>)" \
  --body "..." \
  --label "type:feat,stack:laravel,scope:api,prio:p2"
```

### 7 — Output final

```
✅ Issue #N criada — Feature slice: <entidade>
URL: <url>
Próximo: /planeia-issue #N
```
