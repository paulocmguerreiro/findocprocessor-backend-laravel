---
description: "Cria issue para a camada de lógica (Actions + Controller + FormRequests + Jobs + Events + Listeners + Observers + testes)"
allowed-tools: [Bash, Read]
model: sonnet
effort: high
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

Apresentar checklist **e aguardar resposta antes de avançar**:

```
Selecciona os componentes a incluir nesta issue:

[ ] Actions          — uma por operação (Criar, Actualizar, Eliminar, Listar, …)
[ ] Controller       — dispatch para Actions; zero lógica
[ ] FormRequests     — validação de input + authorize() via Policy por operação;
                       inclui fromRequest() nos DTOs (se DTOs existirem)
[ ] Jobs             — tarefas assíncronas/agendadas disparadas por Actions ou Listeners
[ ] Events           — eventos de domínio disparados dentro das Actions
[ ] Listeners        — reagem a Events (pode ser issue futura)
[ ] Observers        — reagem a eventos Eloquent do Model (created, updated, deleted…)
[ ] Testes de feature — endpoints HTTP (matriz de 3 estados: guest→401 / com-permissão→2xx / sem-permissão→403, ver `07-testing.md`) + 404 + Jobs + Observers
```

Só avançar para o Passo 2b depois de o utilizador confirmar os componentes.

### 2b — Ler spec obrigatório (se FormRequests ou Testes seleccionados)

Antes de avançar para o Passo 3, ler obrigatoriamente:
- `docs/system_spec/04-infra/autorizacao.md` — modelo de permissions, padrão de Policy (`hasPermissionTo`), nunca `hasRole`
- `docs/system_spec/07-testing.md` — matriz de 3 estados; o que `admin`/`utilizador` representam nos testes (configs de permissão, não actores)

Não rascunhar regras de autorização nem matriz de testes sem ter lido estes dois ficheiros.

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

**Se FormRequests seleccionados — perguntar:**
> "Existe já uma `<Entidade>Policy` para esta entidade (criada em /cria-issue-modelo)?"
>
> Se sim: o `authorize()` de cada FormRequest chama a Policy:
> - `CriarXxxRequest` → `$this->authorize('create', Xxx::class)`
> - `ListarXxxsRequest` → `$this->authorize('viewAny', Xxx::class)`
> - `VerXxxRequest` → `$this->authorize('view', $this->route('xxx'))`
> - `ActualizarXxxRequest` → `$this->authorize('update', $this->route('xxx'))`
> - `EliminarXxxRequest` → `$this->authorize('delete', $this->route('xxx'))`
>
> Se não existe Policy: a Policy real (com `hasPermissionTo` + migration de permissões — ver `04-infra/autorizacao.md`) tem de ser criada **antes ou em conjunto** com esta camada. **Não** abrir esta issue com `authorize()` a retornar `true` "temporariamente" — o stub mascara a falta de autorização e os testes passam por engano. Criar primeiro a issue de modelo com a Policy, ou incluir a Policy + migration de permissões no âmbito desta issue.
>
> "Há operações públicas (sem autenticação)? Quais?" — esses FormRequests retornam `true` de forma explícita e documentada (excepção consciente, não default).
>
> "Existem DTOs criados em /cria-issue-modelo ou /cria-issue-persistencia para esta entidade?"
> Se sim: os FormRequests incluem o método `fromRequest()` nos DTOs correspondentes
> (ex: `CriarEntidadeDto::fromRequest(CriarEntidadeRequest $request): self`).

**Se Jobs seleccionados — perguntar:**
> "O que faz o Job? (ex: processar documento, enviar email, sincronizar dados)"
> "É disparado por quê? (Action, Listener, Schedule, Artisan command)"
> "É síncrono (`dispatchSync`) ou assíncrono (queue)? Qual a queue?"
> "Tem retries? Timeout? `$tries` e `$timeout` definidos?"
> "O Job injiecta dependências (repositório, service) ou recebe tudo pelo construtor?"
> "Há cenários de falha a tratar? (ex: `failed()` callback)"

**Se Events seleccionados — perguntar:**
> "Para que operações são disparados eventos?"
> "Que dados o evento transporta (payload)?"
> "Há Listeners/Observers nesta issue ou ficam para uma issue futura?"

**Se Listeners seleccionados — perguntar:**
> "O que faz o Listener quando recebe o evento? (ex: notificação, log, dispatch de Job)"
> "É síncrono ou assíncrono (queue)? Qual a queue?"
> "O Listener está registado em `EventServiceProvider` ou usa `#[ListensTo]`?"

**Se Observers seleccionados — perguntar:**
> "A que eventos Eloquent reage? (`created`, `updated`, `deleted`, `restored`, …)"
> "O que faz o Observer para cada evento? (ex: invalidar cache, disparar Job, log)"
> "O Observer está registado no Model via `#[ObservedBy]` ou no `AppServiceProvider`?"
> "O Observer tem acesso a dependências externas (repositório, service)? Injiectar via construtor."

**Se Testes seleccionados — perguntar:**
> "Há endpoints que requerem autenticação?"
> "Matriz de 3 estados por endpoint/Action protegido (obrigatória — ver `07-testing.md`):
> — Sem autenticação (guest): 401 HTTP / `AuthorizationException` na Action
> — Autenticado COM a permissão: 2xx (happy path)
> — Autenticado SEM a permissão: 403 HTTP / `AuthorizationException` na Action
> `admin` e `utilizador` são configs de permissões, não actores.
> Helpers que materializam cada estado: `criarAdmin()` → COM permissão (escritas);
> `criarUtilizador()` → SEM permissão (escritas), COM permissão (leituras);
> `criarEAutenticarSemRole()` → SEM permissão (até leituras)."
> "Há cenários de erro específicos a testar (ex: entidade não encontrada, permissão negada)?"
> "Se Jobs: testar dispatch (assertDispatched) e execução isolada do Job?"
> "Se Observers: testar que o Observer reage correctamente a cada evento Eloquent?"

### 4 — Verificação de invariantes

Antes de finalizar a issue, verificar conformidade com `docs/system_spec/02-shared/contratos-por-camada.md` — secção "Camada de lógica".

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

## FormRequests (se aplicável)

| FormRequest            | authorize() chama                   | Auth. obrigatória | DTO::fromRequest()              |
|------------------------|-------------------------------------|-------------------|---------------------------------|
| CriarXxxRequest        | create(Xxx::class)                  | sim               | CriarXxxDto::fromRequest()      |
| ListarXxxsRequest      | viewAny(Xxx::class)                 | sim               | —                               |
| VerXxxRequest          | view($this->route('xxx'))           | sim               | —                               |
| ActualizarXxxRequest   | update($this->route('xxx'))         | sim               | ActualizarXxxDto::fromRequest() |
| EliminarXxxRequest     | delete($this->route('xxx'))         | sim               | —                               |

Omitir coluna `DTO::fromRequest()` se não existem DTOs.
Omitir secção completa se FormRequests não seleccionados.

## Jobs (se aplicável)

| Job | Disparado por | Queue | Tries | Timeout | Notas |
|-----|---------------|-------|-------|---------|-------|
| ... | ...           | ...   | ...   | ...     | ...   |

[Omitir se Jobs não seleccionados]

## Events e Listeners (se aplicável)

| Evento | Disparado em | Payload | Listener | Síncrono? |
|--------|--------------|---------|----------|-----------|
| ...    | ...          | ...     | ...      | ...       |

[Omitir se Events não seleccionados]

## Observers (se aplicável)

| Observer | Evento Eloquent | Acção | Registo |
|----------|----------------|-------|---------|
| ...      | created / updated / deleted | ... | #[ObservedBy] / AppServiceProvider |

[Omitir se Observers não seleccionados]

## Critérios de aceitação
- [ ] CA-01: Cada operação tem a sua Action com método `handle()` único
- [ ] CA-02: Controller não contém lógica de negócio — apenas dispatch
- [ ] CA-03: Actions injectam interface do repositório (se existe) ou Eloquent
             directo apenas em CRUD simples (≤ 1 query, sem lógica partilhada)
- [ ] CA-04: `FormRequest::authorize()` **e** `Action::handle()` chamam a Policy correcta (dupla camada) — nunca
             `return true` sem justificação; a Policy usa `hasPermissionTo` (não stub)
- [ ] CA-05: `fromRequest()` implementado nos DTOs correspondentes (se DTOs existem)
- [ ] CA-06: Events são disparados dentro das Actions (nunca no Controller)
- [ ] CA-07: Jobs têm `$tries` e `$timeout` declarados (se assíncronos)
- [ ] CA-08: Observers injectam dependências via construtor (nunca `app()`)
- [ ] CA-09: Testes cobrem a matriz de 3 estados por endpoint/Action protegido (guest → 401 / com-permissão → 2xx / sem-permissão → 403) + 404 — nas duas camadas (HTTP e Action), ver `07-testing.md`
- [ ] CA-10: Testes cobrem dispatch de Jobs (`assertDispatched`) e execução isolada
- [ ] CA-11: Testes cobrem Observer para cada evento Eloquent configurado
- [ ] CA-12: 100% code coverage e 100% type coverage (`composer test`)
[adicionar CAs específicos com base nas operações recolhidas]
[omitir CA-04/CA-05 se FormRequests não seleccionados]
[omitir CA-06 se Events não seleccionados]
[omitir CA-07/CA-10 se Jobs não seleccionados]
[omitir CA-08/CA-11 se Observers não seleccionados]

## Rotas a adicionar
[Lista de rotas ou "a definir no planeia-issue"]

## Impacto técnico
- Afecta: features layer + routes
- SYSTEM_SPEC a actualizar: `docs/system_spec/01-features/<slug>.md`,
  `docs/system_spec/05-routes/<slug>.md`
  [+ `docs/system_spec/04-infra/queue-jobs.md` se Jobs incluídos]
  [+ `docs/system_spec/00-index.md` se feature nova]
- Dependências: [#N — model layer | #M — persistence layer]
- Policy: [#P — model layer com XxxPolicy | "Policy a criar — dívida técnica"]

## Invariantes em risco
- Controller sem lógica (nunca `if`, nunca query directa)
- Actions: uma por operação, método `handle()` único
- Events: disparados na Action, nunca no Controller nem no Model
- `FormRequest::authorize()`: chama `$this->authorize()` com Policy — nunca `return true` hardcoded
- `fromRequest()` nos DTOs: implementado aqui, quando os FormRequests existem
- Jobs assíncronos: `final class implements ShouldQueue`; queue, `$tries` e `$timeout` declarados
- Observers: registado via `#[ObservedBy]` no Model ou em `AppServiceProvider::boot()`

## Contrato OpenAPI
- openapi.yaml afectado: sim — adicionar endpoints
- Breaking change: não (novos endpoints)

## Verificação RGPD/NIS2
- Dados pessoais: [sim — detalhe | não]
- Superfície de ataque: aumentada — novos endpoints expostos

## Fora de âmbito
- Model e Migration: issue /cria-issue-modelo
- Repositório e interface: issue /cria-issue-persistencia
- DTOs e Resource (se ainda não existem): issue /cria-issue-modelo ou /cria-issue-persistencia
- Policy (se ainda não existe): issue separada /cria-issue-modelo com componente Policy
[outros itens fora de âmbito — ex: Listeners ou Observers se adiados para issue futura]
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
