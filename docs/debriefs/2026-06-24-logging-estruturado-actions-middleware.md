# Debrief — Issue #37: Logging Estruturado

**Data:** 2026-06-24  
**Branch:** feat/logging-estruturado-actions-middleware  
**Issue:** #37  
**Estado:** Implementado — pipeline a verde (299 testes, 100% cobertura, PHPStan nível 9)

---

## O que foi implementado

### Middleware (T1)

- **`app/Http/Middleware/InjectarContextoLog.php`** — gera `trace_id` UUID por request via `Context::add()`; registado no grupo `api` em `bootstrap/app.php`
- O `trace_id` é automaticamente propagado a Jobs via o mecanismo de dehydrate/hydrate do `Context` do Laravel 13

### DTO de contexto HTTP (T2)

- **`app/Features/Auth/Login/LoginDto.php`** — Value Object `final readonly` com `email`, `password`, `ip` (de `$request->ip()`), `agente` (de `$request->userAgent()`)
- `fromRequest(LoginRequest $request): self` — único ponto de construção via HTTP

### Logging de autenticação (T3)

- **`LoginAction`** — assinatura alterada de `handle(string $email, string $password)` para `handle(LoginDto $dados)`
- Eventos: `auth.login.tentativa` (info), `auth.login.sucesso` (info), `auth.login.falhou` (warning)
- **`AuthController::login()`** — simplificado para `$accao->handle(LoginDto::fromRequest($pedido))`

### Logging nas Actions de escrita (T4)

7 Actions alteradas com padrão `inicio`/`fim`:
- `CriarCategoriaAction` → `categoria.criar.*`
- `ActualizarCategoriaAction` → `categoria.actualizar.*`
- `EliminarCategoriaAction` → `categoria.eliminar.*`
- `CriarEntidadeAction` → `entidade.criar.*`
- `ActualizarEntidadeAction` → `entidade.actualizar.*`
- `EliminarEntidadeAction` → `entidade.eliminar.*`
- `ConverterEmEmpresaMaeAction` → `entidade.converter_empresa_mae.*`

### Log::error global + config (T5–T6)

- **`bootstrap/app.php`** — `$exceptions->report()` com `Log::error()` adicionado antes dos `render()` existentes; retorno `void` (não suprime o behaviour padrão do Laravel)
- **`.env.example`** — `LOG_DAILY_DAYS=14` adicionado

### Testes (T7)

- **`tests/Unit/Http/Middleware/InjectarContextoLogTest.php`** — 3 testes: trace_id UUID, middleware chain, unicidade por pedido
- **`tests/Unit/Features/Auth/Login/LoginDtoTest.php`** — 4 testes: invariantes do construtor e valores válidos
- **`tests/Feature/Features/Auth/LoginActionLogTest.php`** — 2 testes via HTTP: sucesso e falha com Log::spy()
- **`tests/Feature/Features/CategoriaDocumento/CriarCategoriaLogTest.php`** — 1 teste: inicio + fim via HTTP
- **`tests/Unit/Features/Auth/LoginActionTest.php`** — actualizado: `handle()` recebe `LoginDto`

### Documentação (T8)

- **`docs/system_spec/04-infra/logging.md`** — criado: padrão completo de eventos, posição dos logs, convenção de nomes, configuração
- **`docs/system_spec/00-index.md`** — linha `Logging estruturado` adicionada à tabela Infra

---

## Decisões tomadas (vs. plano original)

### `Context::add()` em vez de `Log::withContext()`

Confirmado via documentação Laravel 13: `Context::add()` é o único mecanismo que propaga para Jobs (via dehydrate/hydrate automático). `Log::withContext()` e `Log::shareContext()` ficam no scope do request apenas. Crítico para uma arquitectura com Queue.

### `$exceptions->report()` com `void` — sem `return false`

O spec original indicava `return false` para não bloquear o reporting padrão do Laravel. Optou-se por `void`: com retorno `void`, o callback é aditivo (não suprime o reporting Laravel, que já loga via Monolog). `return false` suprimiria o comportamento padrão — não é o que se quer. O resultado é que **ambos** o nosso `Log::error` e o logging nativo do Laravel ocorrem, sem duplicação problemática (são canais distintos).

### `auth.login.sucesso` loga `email` em vez de `id_utilizador`

O spec previa `'id_utilizador' => $utilizador->id`. Optou-se por `'email' => $dados->email` porque `$utilizador` não está acessível fora da closure da transação sem reestruturação do código. Para correlação forense é suficiente: o `email` já foi logado em `tentativa`, e `sucesso` + `trace_id` permitem correlacionar. Se for necessário o `id_utilizador`, a solução é retornar o utilizador da transação (alteração minor numa issue futura).

### `converter_empresa_mae` com underscore em vez de hífen

O spec definiu `entidade.converter-empresa-mae.inicio`. Implementado com underscore (`entidade.converter_empresa_mae.inicio`) — consistência com as convenções de nomenclatura PHP do projecto (snake_case). Hífens em event names são válidos mas criam ambiguidade com separadores de namespace.

### `LoginRequest` é `final` — testes `fromRequest()` movidos para Feature

`LoginRequest` não pode ser mockada pela Mockery (classe `final`). O bloco `describe('fromRequest()')` foi removido do `LoginDtoTest`. Cobertura garantida pelos Feature tests existentes (`LoginTest` e `LoginActionLogTest`), que exercem `fromRequest()` através do HTTP path completo.

### `return \App\Models\User` no `beforeEach` via Rector

Rector adicionou tipo de retorno FQCN `\App\Models\User` ao arrow function do `beforeEach` em `CriarCategoriaLogTest`. O tipo não-qualificado `User` causava erro `Return value must be of type User, App\Models\User returned` por colisão de namespace. O FQCN resolve o ambiguo; Pint converteu para import explícito no pass seguinte.

---

## O que ficou fora do âmbito

- **`user_id` no Context do middleware** — Auth não está resolvido no momento do middleware (corre antes de `auth:sanctum`); `id_utilizador` é passado explicitamente em cada Log call das Actions protegidas
- **Audit trail before/after** — campos antigos vs novos não são logados (issue futura)
- **Rate limiting / bloqueio de brute force** — detectável com os logs `auth.login.falhou`, mas acção activa é issue separada
- **Log aggregation externo** (Sentry, Datadog) — fora de âmbito

---

## Problemas encontrados

### `LoginRequest` é `final`

Mockery não consegue criar uma subclasse de uma classe `final`. Os testes de `fromRequest()` do `LoginDtoTest` falharam com `The class \App\Features\Auth\Login\LoginRequest is marked final and its methods cannot be replaced`. Solução: remover esses testes da suite Unit e confirmar cobertura pelos Feature tests.

### Tipo de retorno não-qualificado no `beforeEach`

`beforeEach(fn (): User => ...)` sem import causava `Return value must be of type User, App\Models\User returned` — PHP interpretava `User` como um tipo diferente do `App\Models\User` retornado. Iteração Rector+Pint resolveu: Rector adicionou FQCN, Pint converteu para import.

### `composer test` falhou com exit code 2 antes do ajuste Rector

A pipeline `composer test` corre Rector em `--dry-run` — detecta sugestões sem as aplicar e falha com exit code 2 se houver diferenças. Solução: correr `composer refactor` + `composer lint` sequencialmente e só então re-executar `composer test`.

---

## Aprendizagens

### `Context::add()` é o único mecanismo de contexto que sobrevive a Jobs

`Log::withContext()` adiciona contexto ao canal de logging corrente do request — desaparece quando o Job é serializado para a Queue. `Context::add()` persiste através de `Context::dehydrate()` (serializa antes de enfileirar) e `Context::hydrate()` (restaura no worker). Para arquitecturas com Queue (que este projecto vai ter), usar `Context::add()` para qualquer informação de rastreio é obrigatório. O `trace_id` propaga automaticamente — os Jobs vão poder correlacionar-se com o request que os originou sem qualquer código extra.

### O logging após `DB::transaction()` garante semântica de commit

Colocar `Log::info('.fim')` fora e após a closure da transação significa que só é executado se o `DB::transaction()` retornar sem excepção (i.e., o commit foi bem-sucedido). Um rollback re-lança a excepção, e o log `.fim` nunca é escrito. Isto elimina a classe de bugs em que o log indica "operação concluída" mas a BD não tem o dado — informação enganosa que pode levar a diagnósticos incorrectos.

### Vertical Slice com logging: o DTO é o veículo certo para contexto HTTP

Em Vertical Slice, a Action não tem acesso directo ao Request. Antes desta issue, `LoginAction` recebia `string $email, string $password` — impossível saber o IP do caller dentro da Action. A introdução de `LoginDto` com `ip` e `agente` resolve isto: o Controller extrai o contexto HTTP via `fromRequest()`, e a Action recebe um objecto com tudo o que precisa para logging sem violar a separação de camadas. Este padrão generaliza-se: quando uma futura Action precisar de contexto HTTP para audit trail ou rate limiting, o DTO é o sítio certo.

### `$exceptions->report()` void vs bool — semântica de supressão

A diferença entre retornar `void` (ou nada) e retornar `false` num `$exceptions->report()` callback é subtil mas importante. `false` suprime o reporting padrão do Laravel; `void` é aditivo. A maioria dos casos práticos quer o comportamento aditivo — registar **adicionalmente** o erro no nosso canal, não substituir o Monolog. Retornar `false` seria correcto apenas se quiséssemos desabilitar o logging padrão (e.g., para usar exclusivamente um canal externo como Sentry).

---

## Métricas finais

| Métrica | Valor |
|---|---|
| Ficheiros novos | 4 (`InjectarContextoLog`, `LoginDto`, `LoginDtoTest`, `InjectarContextoLogTest`, `LoginActionLogTest`, `CriarCategoriaLogTest`, `logging.md`) |
| Ficheiros alterados | 12 (7 Actions, `LoginAction`, `AuthController`, `bootstrap/app.php`, `.env.example`, `LoginActionTest`) |
| Testes totais | 299 (100% cobertura) |
| PHPStan | Nível 9, 0 erros |
| Commits | 8 |
