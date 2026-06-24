# System Spec — Infra: Logging Estruturado

> Issue #37 | Branch: `feat/logging-estruturado-actions-middleware`

## Visão geral

Logging estruturado com `trace_id` por request, eventos de início/fim nas Actions de escrita, e logging de tentativas de autenticação. Contexto injectado via middleware; `trace_id` propagado automaticamente para Jobs via `Illuminate\Support\Facades\Context`.

---

## Middleware — `InjectarContextoLog`

**Ficheiro:** `app/Http/Middleware/InjectarContextoLog.php`
**Registado em:** grupo `api` via `bootstrap/app.php` → `$middleware->api(append: [...])`

```php
Context::add('trace_id', Str::uuid()->toString());
return $next($request);
```

O `trace_id` é automaticamente anexado a todos os `Log::*` do mesmo request como metadata (comportamento nativo do Laravel Context).

---

## DTO — `LoginDto`

**Ficheiro:** `app/Features/Auth/Login/LoginDto.php`

Value Object `final readonly` com campos de contexto HTTP para logging:

| Campo | Origem |
|---|---|
| `email` | `$request->validated()['email']` |
| `password` | `$request->validated()['password']` |
| `ip` | `$request->ip() ?? 'desconhecido'` |
| `agente` | `$request->userAgent() ?? 'desconhecido'` |

Construtor valida invariantes: `email` e `password` não podem ser vazios.

---

## Eventos de log por domínio

### Auth (`LoginAction`)

| Evento | Nível | Contexto |
|---|---|---|
| `auth.login.tentativa` | `info` | `email`, `ip` |
| `auth.login.sucesso` | `info` | `email` |
| `auth.login.falhou` | `warning` | `email`, `ip` |

A password **nunca** é logada.

### CategoriaDocumento

| Evento | Nível | Contexto |
|---|---|---|
| `categoria.criar.inicio` | `info` | `id_utilizador` |
| `categoria.criar.fim` | `info` | `id_utilizador`, `id_categoria` |
| `categoria.actualizar.inicio` | `info` | `id_utilizador` |
| `categoria.actualizar.fim` | `info` | `id_utilizador`, `id_categoria` |
| `categoria.eliminar.inicio` | `info` | `id_utilizador` |
| `categoria.eliminar.fim` | `info` | `id_utilizador` |

### Entidade

| Evento | Nível | Contexto |
|---|---|---|
| `entidade.criar.inicio` | `info` | `id_utilizador` |
| `entidade.criar.fim` | `info` | `id_utilizador`, `id_entidade` |
| `entidade.actualizar.inicio` | `info` | `id_utilizador` |
| `entidade.actualizar.fim` | `info` | `id_utilizador`, `id_entidade` |
| `entidade.eliminar.inicio` | `info` | `id_utilizador` |
| `entidade.eliminar.fim` | `info` | `id_utilizador` |
| `entidade.converter_empresa_mae.inicio` | `info` | `id_utilizador` |
| `entidade.converter_empresa_mae.fim` | `info` | `id_utilizador`, `id_entidade` |

---

## Padrão de logging nas Actions

```php
// após Gate::authorize, antes de DB::transaction
Log::info('<dominio>.<operacao>.inicio', ['id_utilizador' => Auth::id()]);

$resultado = DB::transaction(function () use (...): ModelType { ... });

// após DB::transaction retornar
Log::info('<dominio>.<operacao>.fim', ['id_utilizador' => Auth::id(), 'id_<entidade>' => $resultado->id]);

return $resultado;
```

Actions com `void` (Eliminar*): omitir `id_<entidade>` no evento `.fim`.

---

## Log::error global

**Ficheiro:** `bootstrap/app.php` → `$exceptions->report()`

```php
$exceptions->report(function (Throwable $e): void {
    Log::error($e->getMessage(), [
        'exception' => $e::class,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
});
```

Registado antes dos `$exceptions->render()`. Não é chamado para `ValidationException` (não reportável por omissão no Laravel).

---

## Configuração

| Var | Valor por omissão | Descrição |
|---|---|---|
| `LOG_CHANNEL` | `stack` | Canal activo |
| `LOG_LEVEL` | `debug` | Nível mínimo |
| `LOG_DAILY_DAYS` | `14` | Retenção do canal `daily` |

Canal `daily` já configurado em `config/logging.php`. Em produção, mudar `LOG_STACK=daily` para rotação automática.
