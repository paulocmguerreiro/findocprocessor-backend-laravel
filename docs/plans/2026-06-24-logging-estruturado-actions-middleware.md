# Plano — Issue #37: Logging Estruturado

**Data:** 2026-06-24
**Issue:** #37
**Branch:** feat/logging-estruturado-actions-middleware
**Spec:** `docs/specs/2026-06-24-logging-estruturado-actions-middleware.md`

---

## Tarefas

### T1 — Middleware `InjectarContextoLog` + registo no grupo api

**Ficheiros:**
- Criar: `app/Http/Middleware/InjectarContextoLog.php`
- Alterar: `bootstrap/app.php` — registar no grupo `api`

**Implementação:**
```php
// InjectarContextoLog.php
Context::add('trace_id', Str::uuid()->toString());
return $next($request);

// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->api(append: [
        \App\Http\Middleware\InjectarContextoLog::class,
    ]);
})
```

**Verificação:** `composer lint && composer refactor`

---

### T2 — `LoginDto` com campos de contexto HTTP

**Ficheiros:**
- Criar: `app/Features/Auth/Login/LoginDto.php`

**Implementação:** Value Object com `fromRequest(LoginRequest $request): self`
- `ip` → `$request->ip()`
- `agente` → `$request->userAgent() ?? 'desconhecido'`
- `email`, `password` → `$request->validated()`

**Verificação:** `composer lint && composer refactor`

---

### T3 — Logging na `LoginAction` + actualização `AuthController`

**Ficheiros:**
- Alterar: `app/Features/Auth/Login/LoginAction.php` — nova assinatura `handle(LoginDto $dados)`, try-catch com Log::info/warning
- Alterar: `app/Features/Auth/AuthController.php` — `LoginDto::fromRequest($pedido)`

**Verificação:** `composer test:types`

---

### T4 — Logging nas Actions de escrita (7 Actions)

**Ficheiros a alterar:**
1. `app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php`
2. `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php`
3. `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php`
4. `app/Features/Entidade/Criar/CriarEntidadeAction.php`
5. `app/Features/Entidade/Actualizar/ActualizarEntidadeAction.php`
6. `app/Features/Entidade/Eliminar/EliminarEntidadeAction.php`
7. `app/Features/Entidade/EmpresaMae/ConverterEmEmpresaMaeAction.php`

**Padrão por Action:**
```php
// após Gate::authorize, antes de DB::transaction
Log::info('<feature>.<operacao>.inicio', ['id_utilizador' => Auth::id()])

// após DB::transaction retornar
Log::info('<feature>.<operacao>.fim', ['id_utilizador' => Auth::id(), 'id_<entidade>' => $resultado->id])
```

**Verificação:** `composer test:types`

---

### T5 — Log::error global em `bootstrap/app.php`

**Ficheiros:**
- Alterar: `bootstrap/app.php` — adicionar `$exceptions->report()` dentro de `withExceptions()`

**Nota:** adicionar ANTES dos `$exceptions->render()` existentes para clareza.

**Verificação:** `composer lint`

---

### T6 — Canal `daily` em `config/logging.php`

**Ficheiros:**
- Alterar: `config/logging.php` — confirmar `default` usa `env('LOG_CHANNEL', 'stack')`
- Documentar: `.env.example` — adicionar `LOG_CHANNEL=stack` e `LOG_DAILY_DAYS=14`

**Verificação:** visual

---

### T7 — Testes

**Ficheiros a criar:**
- `tests/Unit/Http/Middleware/InjectarContextoLogTest.php`
- `tests/Unit/Features/Auth/Login/LoginDtoTest.php`
- `tests/Feature/Features/Auth/LoginActionLogTest.php`
- `tests/Feature/Features/CategoriaDocumento/CriarCategoriaLogTest.php`

**Padrão Feature tests:**
```php
use Illuminate\Support\Facades\Log;
Log::spy();
// ... executa action ...
Log::shouldHaveReceived('info')->withArgs(fn ($msg) => $msg === 'categoria.criar.inicio');
Log::shouldHaveReceived('info')->withArgs(fn ($msg) => $msg === 'categoria.criar.fim');
```

**Verificação:** `composer test`

---

### T8 — System spec + índice

**Ficheiros:**
- Criar: `docs/system_spec/04-infra/logging.md`
- Alterar: `docs/system_spec/00-index.md` — adicionar linha Logging na tabela Infra

---

## Ordem de execução

```
T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8
```

T3 depende de T2 (LoginDto). T4-T6 são independentes entre si (podem ser feitas em qualquer ordem após T1). T7 depende de T1-T6.

---

## Commit strategy

```
T1:    feat(infra): middleware InjectarContextoLog — trace_id via Context facade
T2-T3: feat(auth): LoginDto com ip/agente + logging tentativas de autenticação
T4:    feat(infra): logging inicio/fim nas Actions de escrita
T5-T6: feat(infra): log::error global + canal daily em produção
T7:    test(infra): testes de logging — middleware, LoginAction, CriarCategoriaAction
T8:    docs(process): system_spec logging estruturado — Issue #37
```

---

## Verificação final

```bash
composer test        # pipeline completa — deve passar a 100%
```

Verificar manualmente em `storage/logs/laravel.log`:
- Request de login bem-sucedido gera `auth.login.tentativa` + `auth.login.sucesso` com `trace_id`
- Request de login falhado gera `auth.login.tentativa` + `auth.login.falhou` (warning)
- `composer test:types` sem erros (Larastan nível 9)
