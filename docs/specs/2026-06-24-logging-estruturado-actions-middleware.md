# Spec — Issue #37: Logging Estruturado

**Data:** 2026-06-24
**Issue:** #37
**Brief:** `docs/briefs/2026-06-24-logging-estruturado-actions-middleware.md`

---

## Contratos por componente

### Middleware `InjectarContextoLog`

```
Localização: app/Http/Middleware/InjectarContextoLog.php
Registo:     bootstrap/app.php → withMiddleware → api group
```

**Contrato:**
- Adiciona `trace_id` (UUID v4) ao Context via `Context::add()`
- Corre para TODOS os pedidos `api/*` (incluindo login, sem auth)
- Não adiciona `user_id` (Auth não está resolvido a este nível)
- Propaga `trace_id` para Jobs automaticamente (Context dehydrate/hydrate do Laravel 13)

```php
final class InjectarContextoLog
{
    public function handle(Request $request, Closure $next): Response
    {
        Context::add('trace_id', Str::uuid()->toString());
        return $next($request);
    }
}
```

---

### DTO `LoginDto`

```
Localização: app/Features/Auth/Login/LoginDto.php
```

**Contrato:**
- Value Object — nunca num estado inválido
- Campos: `email` (string), `password` (string), `ip` (string), `agente` (string)
- `fromRequest(LoginRequest $request): self` — único ponto de construção HTTP
- `ip` vem de `$request->ip()` (REMOTE_ADDR / TrustedProxies), nunca do body
- `agente` vem de `$request->userAgent() ?? 'desconhecido'`

---

### `LoginAction` — logging de tentativas

```
Localização: app/Features/Auth/Login/LoginAction.php
```

**Contrato (alteração de assinatura):**
```
handle(LoginDto $dados): string   // antes: handle(string $email, string $password): string
```

**Padrão de logging (excepção ao padrão geral — única Action com try-catch explícito):**

```
1. Log::info('auth.login.tentativa', ['email' => $dados->email, 'ip' => $dados->ip, 'agente' => $dados->agente])
2. DB::transaction():
   a. Verifica credenciais
   b. Se falha → Log::warning('auth.login.falhou', ['email' => $dados->email, 'ip' => $dados->ip]) + throw ValidationException
   c. Se sucesso → cria token
3. Log::info('auth.login.sucesso', ['id_utilizador' => $utilizador->id, 'ip' => $dados->ip])
   // id_utilizador, não email — mínimo RGPD
```

**`trace_id` aparece automaticamente** em todos estes logs via Context.

---

### Actions de escrita — padrão de logging

Aplica-se a: `CriarCategoriaAction`, `ActualizarCategoriaAction`, `EliminarCategoriaAction`, `CriarEntidadeAction`, `ActualizarEntidadeAction`, `EliminarEntidadeAction`, `ConverterEmEmpresaMaeAction`

**Posição dos logs face ao padrão existente:**

```php
Gate::authorize(...)                          // sem alteração

Log::info('<feature>.<operacao>.inicio', [    // NOVO — após Gate, antes da transação
    'id_utilizador' => Auth::id(),
    // apenas IDs — nunca NIF, nome, email
])

$resultado = DB::transaction(fn () => ...);   // sem alteração interna

Log::info('<feature>.<operacao>.fim', [       // NOVO — após transação (só se commit)
    'id_utilizador' => Auth::id(),
    'id_<entidade>' => $resultado->id,
])

return $resultado;
```

**Convenção de nomes de evento:**

| Action | Evento início | Evento fim |
|---|---|---|
| CriarCategoriaAction | `categoria.criar.inicio` | `categoria.criar.fim` |
| ActualizarCategoriaAction | `categoria.actualizar.inicio` | `categoria.actualizar.fim` |
| EliminarCategoriaAction | `categoria.eliminar.inicio` | `categoria.eliminar.fim` |
| CriarEntidadeAction | `entidade.criar.inicio` | `entidade.criar.fim` |
| ActualizarEntidadeAction | `entidade.actualizar.inicio` | `entidade.actualizar.fim` |
| EliminarEntidadeAction | `entidade.eliminar.inicio` | `entidade.eliminar.fim` |
| ConverterEmEmpresaMaeAction | `entidade.converter-empresa-mae.inicio` | `entidade.converter-empresa-mae.fim` |

**Campos NUNCA em logs:**
- `nif` — dado pessoal RGPD
- `password` / `password_hash`
- `email` (em logs de operação; apenas em `auth.login.tentativa` e `auth.login.falhou`)
- Qualquer valor de token

---

### `bootstrap/app.php` — logging global de excepções

```php
->withExceptions(function (Exceptions $exceptions): void {
    // ... renderers existentes sem alteração ...

    $exceptions->report(function (Throwable $e): bool {
        Log::error('excepção capturada', [
            'exception' => $e::class,
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
        ]);
        return false; // não bloquear o reporting padrão do Laravel
    });
});
```

O `trace_id` aparece automaticamente via Context. `ValidationException` e `AuthorizationException` são excluídas do report padrão do Laravel (são "expected") — o `return false` deixa o Laravel decidir o que reportar conforme a sua lógica interna.

---

### `config/logging.php` — canal daily em produção

```php
'default' => env('LOG_CHANNEL', 'stack'),
```

**`.env` (dev):** `LOG_CHANNEL=stack`
**`.env.production`:** `LOG_CHANNEL=daily`

Canal `daily`: rotação automática, 14 dias de retenção (`LOG_DAILY_DAYS=14`).

---

### `AuthController` — actualização para `LoginDto`

```php
public function login(LoginRequest $pedido, LoginAction $accao): JsonResponse
{
    $token = $accao->handle(LoginDto::fromRequest($pedido));
    return ApiResponse::devolverSucesso(['token' => $token]);
}
```

---

## Contratos de teste

### Padrão dual (Unit + Feature)

**Unit — `tests/Unit/Http/Middleware/InjectarContextoLogTest.php`**
- Verifica que `Context::get('trace_id')` é preenchido após o middleware
- Verifica que é um UUID válido
- Verifica que requests diferentes têm trace_ids diferentes

**Unit — `tests/Unit/Features/Auth/Login/LoginDtoTest.php`**
- `fromRequest()` lê ip de `$request->ip()`, não do body
- `fromRequest()` usa `'desconhecido'` quando user-agent é null

**Feature — `tests/Feature/Features/Auth/LoginActionLogTest.php`**
- `Log::spy()` — tentativa bem-sucedida regista `auth.login.tentativa` (info) e `auth.login.sucesso` (info)
- `Log::spy()` — tentativa falhada regista `auth.login.tentativa` (info) e `auth.login.falhou` (warning)
- Nenhum log contém a password

**Feature — `tests/Feature/Features/CategoriaDocumento/CriarCategoriaLogTest.php`** (exemplo representativo)
- `Log::spy()` — `categoria.criar.inicio` e `categoria.criar.fim` aparecem no log
- `Log::spy()` — ambos contêm `trace_id` via context
- NIF nunca aparece nos logs

---

## SYSTEM_SPEC a actualizar

| Ficheiro | Acção |
|---|---|
| `docs/system_spec/04-infra/logging.md` | Criar — documenta padrão completo |
| `docs/system_spec/00-index.md` | Actualizar — adicionar linha `Logging` na tabela Infra |
