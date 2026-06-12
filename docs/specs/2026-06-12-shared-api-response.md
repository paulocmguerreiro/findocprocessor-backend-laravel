# Spec — Issue #6: ApiResponse + Problem Details

**Data:** 2026-06-12
**Issue:** #6
**Branch:** feat/shared-api-response
**Brief:** docs/briefs/2026-06-12-shared-api-response.md

---

## Âmbito

Esta issue implementa **exclusivamente** a infra de resposta HTTP partilhada:

1. `ApiResponse` — factory estática para respostas de sucesso
2. Exception handler centralizado — Problem Details RFC 7807 para todos os erros

Não inclui controller, rotas, nem alterações a features existentes.

---

## Contrato de Resposta

### Sucesso — recurso único (`ApiResponse::success` / `ApiResponse::created`)

```json
// HTTP 200 ou 201
{
  "data": { "id": "...", "nome": "..." }
}
```

### Sucesso — colecção (`ApiResponse::collection`)

```json
// HTTP 200
{
  "data": [ { "id": "...", "nome": "..." } ],
  "meta": { "total": 1 }
}
```

### Sucesso sem corpo (`ApiResponse::noContent`)

```
// HTTP 204 — body vazio
```

### Erro — Problem Details RFC 7807

```json
// HTTP 422 — validação
{
  "status": 422,
  "detail": "Os dados fornecidos são inválidos.",
  "errors": {
    "nome": ["O campo nome é obrigatório."]
  }
}

// HTTP 404
{
  "status": 404,
  "detail": "Recurso não encontrado."
}

// HTTP 403
{
  "status": 403,
  "detail": "Sem permissão para aceder a este recurso."
}

// HTTP 401
{
  "status": 401,
  "detail": "Não autenticado."
}

// HTTP 500
{
  "status": 500,
  "detail": "Ocorreu um erro interno. Tente novamente mais tarde."
}
```

---

## Componente 1 — `ApiResponse`

**Ficheiro:** `app/Shared/Http/ApiResponse.php`
**Namespace:** `App\Shared\Http`

```php
final class ApiResponse
{
    public static function devolverSucesso(JsonResource $recurso): JsonResponse                            // HTTP 200
    public static function devolverCriado(JsonResource $recurso): JsonResponse                             // HTTP 201
    public static function devolverVazio(): JsonResponse                                                   // HTTP 204
    public static function devolverColeccao(ResourceCollection $coleccao, array $meta = []): JsonResponse  // HTTP 200
}
```

**Detalhes de implementação:**

- `devolverSucesso()` / `devolverCriado()` — envolvem o resource em `['data' => $recurso]`; `response()->json()` com o código correcto
- `devolverVazio()` — `new JsonResponse(null, 204)` (body vazio)
- `devolverColeccao()` — `['data' => $coleccao->collection, 'meta' => $meta]`
  - `$meta` é `array<string, string|int>` fornecido pelo caller — sem `total` automático
  - Justificação: com paginação, `collection->count()` dá itens da página actual, não o total real; o caller tem acesso ao paginador e conhece os valores correctos
  - Sem paginação: `['total' => $items->count()]`; com paginação: `['total' => $paginator->total(), 'pagina' => ..., ...]`
- Classe `final` — não é extensível
- `strict_types=1` obrigatório

---

## Componente 2 — Exception Handler

**Ficheiro:** `bootstrap/app.php` — bloco `withExceptions()`

Cinco closures `render()` por ordem de especificidade (mais específico primeiro):

```
ValidationException        → 422 + campo errors
ModelNotFoundException     → 404
AuthorizationException     → 403
AuthenticationException    → 401
Throwable                  → 500 (sem stack trace em produção)
```

**Helper interno** `problemDetails(int $status, string $detail, array $extra = [])`:
- Constrói o array base: `['status' => $status, 'detail' => $detail]`
- Merge de `$extra` (usado para `errors` no 422)
- Retorna `response()->json($payload, $status)`

O helper pode ser uma closure local dentro de `withExceptions()` ou uma função privada — não é exposto publicamente.

---

## Tabela de HTTP Codes

| Situação | Código |
|---|---|
| GET / PUT sucesso | 200 |
| POST criado | 201 |
| DELETE sem corpo | 204 |
| Não autenticado | 401 |
| Sem permissão | 403 |
| Recurso não encontrado | 404 |
| Erro de validação | 422 |
| Erro interno (não tratado) | 500 |

---

## Testes (CA-10)

**Localização:** `tests/Feature/Shared/`

### `ApiResponseTest` — respostas de sucesso

| Teste | Verifica |
|---|---|
| `success() devolve HTTP 200 com wrapper data` | status + estrutura JSON |
| `created() devolve HTTP 201 com wrapper data` | status + estrutura JSON |
| `noContent() devolve HTTP 204 sem corpo` | status + body vazio |
| `collection() devolve HTTP 200 com data e meta.total` | status + estrutura JSON |

### `ExceptionHandlerTest` — Problem Details

| Teste | Excepção | HTTP | Verifica |
|---|---|---|---|
| `ValidationException → 422 com errors` | `ValidationException` | 422 | `type`, `status`, `errors` |
| `ModelNotFoundException → 404` | `ModelNotFoundException` | 404 | `type`, `status`, `detail` |
| `AuthorizationException → 403` | `AuthorizationException` | 403 | `type`, `status`, `detail` |
| `AuthenticationException → 401` | `AuthenticationException` | 401 | `type`, `status`, `detail` |
| `Throwable genérico → 500` | `\Exception` genérica | 500 | `type`, `status`; sem `trace` |

**Estratégia de teste:**
- Rotas de teste (`Route::get('/test-...')`) definidas no `setUp()` dos testes, usando `$this->app->make('router')` — sem poluir `routes/api.php`
- Cada rota lança a excepção correspondente via closure
- `$this->getJson()` / `$this->postJson()` para garantir header `Accept: application/json`

---

## Ficheiros Afectados

| Ficheiro | Acção |
|---|---|
| `app/Shared/Http/ApiResponse.php` | **Criar** |
| `bootstrap/app.php` | **Actualizar** — adicionar closures em `withExceptions()` |
| `tests/Feature/Shared/ApiResponseTest.php` | **Criar** |
| `tests/Feature/Shared/ExceptionHandlerTest.php` | **Criar** |

---

## System Spec a Actualizar (Fase 3)

| Ficheiro | Secção |
|---|---|
| `docs/system_spec/02-shared.md` | Adicionar `ApiResponse` em `app/Shared/Http/` |
| `docs/system_spec/02-shared.md` | Adicionar mapeamento de excepções → Problem Details |

---

## Invariantes

- `ApiResponse` não contém lógica de negócio
- Stack traces nunca incluídos na resposta (independentemente de `APP_DEBUG`)
- Mensagens de `detail` em português de Portugal
- `type` e `title` em inglês (RFC 7807 usa inglês nos títulos standard)
- Todos os erros de API passam pelo handler — nunca `response()->json(['error' => ...])` manual
