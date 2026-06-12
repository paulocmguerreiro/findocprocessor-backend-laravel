# Plano — Issue #6: ApiResponse + Problem Details

**Data:** 2026-06-12
**Issue:** #6
**Branch:** feat/shared-api-response
**Spec:** docs/specs/2026-06-12-shared-api-response.md

---

## Tarefas

### T1 — Criar `app/Shared/Http/ApiResponse.php`

Criar a classe `ApiResponse` com os 4 métodos estáticos.

**Ficheiro:** `app/Shared/Http/ApiResponse.php`

```php
<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class ApiResponse
{
    public static function devolverSucesso(JsonResource $recurso): JsonResponse { }
    public static function devolverCriado(JsonResource $recurso): JsonResponse { }
    public static function devolverVazio(): JsonResponse { }
    public static function devolverColeccao(ResourceCollection $coleccao): JsonResponse { }
}
```

**Detalhes:**
- `devolverSucesso` → `response()->json(['data' => $recurso], 200)`
- `devolverCriado` → `response()->json(['data' => $recurso], 201)`
- `devolverVazio` → `response()->noContent()`
- `devolverColeccao` → `response()->json(['data' => $coleccao->collection, 'meta' => ['total' => $coleccao->collection->count()]], 200)`

**Verificação:** `composer test:types` sem erros.

---

### T2 — Adicionar exception handler em `bootstrap/app.php`

Preencher o bloco `withExceptions()` com 5 closures `render()`.

**Ordem obrigatória** (mais específico primeiro):
1. `ValidationException` → 422
2. `ModelNotFoundException` → 404
3. `AuthorizationException` → 403
4. `AuthenticationException` → 401
5. `Throwable` → 500

Helper local `$problemDetails = fn(...) => ...` definido antes das closures, dentro do bloco `withExceptions()`.

**Payload de erro:**
```php
['status' => $status, 'detail' => $detail, ...$extra]
```

**Nota para Throwable/500:** Usar `app()->isProduction()` para decidir se inclui `$e->getMessage()` no `detail` ou uma mensagem genérica. Em produção: mensagem genérica. Em desenvolvimento: pode incluir a mensagem real.

**Verificação:** `composer test:types` sem erros.

---

### T3 — Criar `tests/Feature/Shared/ApiResponseTest.php`

4 testes — um por método de `ApiResponse`.

**Estratégia:** Registar rotas de teste no `setUp()` via `Route::get/post(...)` antes de cada teste.

| Teste | Rota de teste | Verifica |
|---|---|---|
| `devolverSucesso devolve 200 com wrapper data` | `GET /test-sucesso` | `status(200)` + `json(['data' => [...]])` |
| `devolverCriado devolve 201 com wrapper data` | `POST /test-criado` | `status(201)` + `json(['data' => [...]])` |
| `devolverVazio devolve 204 sem corpo` | `DELETE /test-vazio` | `status(204)` + body vazio |
| `devolverColeccao devolve 200 com data e meta.total` | `GET /test-coleccao` | `status(200)` + `json(['data' => [...], 'meta' => ['total' => N]])` |

**Verificação:** `composer test:types` + `php artisan test --filter ApiResponseTest` passam.

---

### T4 — Criar `tests/Feature/Shared/ExceptionHandlerTest.php`

5 testes — um por excepção mapeada.

**Estratégia:** Mesma abordagem — rotas de teste no `setUp()`, cada uma lança a excepção correspondente.

| Teste | Excepção lançada | HTTP | Verifica |
|---|---|---|---|
| `ValidationException mapeia para 422` | `ValidationException::withMessages(...)` | 422 | `status(422)` + `json(['status' => 422])` + chave `errors` presente |
| `ModelNotFoundException mapeia para 404` | `new ModelNotFoundException` | 404 | `status(404)` + `json(['status' => 404, 'detail' => '...'])` |
| `AuthorizationException mapeia para 403` | `new AuthorizationException` | 403 | `status(403)` + `json(['status' => 403])` |
| `AuthenticationException mapeia para 401` | `new AuthenticationException` | 401 | `status(401)` + `json(['status' => 401])` |
| `Throwable genérico mapeia para 500 sem stack trace` | `new \RuntimeException('segredo')` | 500 | `status(500)` + `json(['status' => 500])` + ausência de chave `trace` |

**Nota:** Usar `$this->getJson()` / `$this->postJson()` para garantir header `Accept: application/json` (sem este header, o handler não retorna JSON).

**Verificação:** `composer test:types` + `php artisan test --filter ExceptionHandlerTest` passam.

---

### T5 — Qualidade e pipeline completa

```bash
composer lint       # Pint — formatar
composer refactor   # Rector — modernizar
composer test       # Pipeline completa
```

Corrigir todos os erros antes de finalizar.

---

## Ordem de execução

```
T1 → T3 (testa ApiResponse imediatamente)
T2 → T4 (testa handler imediatamente)
T5 (pipeline final)
```

T1+T2 podem ser feitos em paralelo; T3 depende de T1, T4 depende de T2.

---

## Ficheiros criados/alterados

| Ficheiro | Acção |
|---|---|
| `app/Shared/Http/ApiResponse.php` | Criar |
| `bootstrap/app.php` | Alterar — `withExceptions()` |
| `tests/Feature/Shared/ApiResponseTest.php` | Criar |
| `tests/Feature/Shared/ExceptionHandlerTest.php` | Criar |

---

## Riscos e notas

- **`devolverColeccao`:** `$coleccao->collection` é a `Illuminate\Support\Collection` interna — verificar tipo no PHPStan se der erro de acesso a propriedade protected.
- **`Throwable` handler:** Deve ser o último `render()` — senão intercepta todas as excepções antes das específicas.
- **`Accept: application/json`:** O Laravel só usa o handler JSON quando o request tem este header; os testes devem usar `getJson`/`postJson` (não `get`/`post`).
- **Arch test:** Verificar se o teste arquitectural existente em `tests/Arch` tem regras que afectem `app/Shared/Http/` — se sim, garantir conformidade.
