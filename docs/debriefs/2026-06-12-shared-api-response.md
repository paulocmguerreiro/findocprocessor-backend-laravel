# Debrief — Issue #6: ApiResponse + Problem Details

**Data:** 2026-06-12
**Issue:** #6 — feat(shared): envelope universal de resposta JSON — ApiResponse + Problem Details + HTTP codes
**Branch:** feat/shared-api-response
**Autor:** Paulo Guerreiro

---

## O que foi implementado

### `ApiResponse` — `app/Shared/Http/ApiResponse.php`

Factory estática `final` com 4 métodos:

| Método | HTTP | Estrutura |
|---|---|---|
| `devolverSucesso(JsonResource)` | 200 | `{ "data": { ... } }` |
| `devolverCriado(JsonResource)` | 201 | `{ "data": { ... } }` |
| `devolverVazio()` | 204 | body vazio |
| `devolverColeccao(ResourceCollection, array $meta = [])` | 200 | `{ "data": [...], "meta": { ... } }` |

`$meta` é fornecido explicitamente pelo caller — sem total automático. Justificação: com paginação, `collection->count()` daria itens da página, não o total real.

### Exception handler centralizado — `bootstrap/app.php`

Cinco closures `render()` no bloco `withExceptions()`, por ordem de especificidade:

| Excepção no closure | HTTP | `detail` |
|---|---|---|
| `ValidationException` | 422 | "Os dados fornecidos são inválidos." + campo `errors` |
| `NotFoundHttpException` | 404 | "Recurso não encontrado." |
| `AccessDeniedHttpException` | 403 | "Sem permissão para aceder a este recurso." |
| `AuthenticationException` | 401 | "Não autenticado." |
| `Throwable` (fallback) | 500 | "Ocorreu um erro interno. Tente novamente mais tarde." |

Helper local `$problemDetails` (closure) constrói o payload: `['status' => $status, 'detail' => $detail, ...$extra]`.

Todos os handlers verificam `$request->expectsJson()` — devolvem `null` para requests web (HTML).

### Testes

- `tests/Feature/Shared/ApiResponseTest.php` — 4 testes (um por método)
- `tests/Feature/Shared/ExceptionHandlerTest.php` — 5 testes (um por excepção)
- Estratégia: rotas de teste registadas no `beforeEach()` via `Route::get/post/delete(...)` — sem poluir `routes/api.php`

**Resultados:** 39 testes, 100% type coverage, PHPStan nível 9 zero erros.

---

## Decisões tomadas

### D1 — Factory estática (não injectable)
`ApiResponse` é formatação pura sem lógica de negócio — não precisa de interface nem de injecção. Padrão idiomático em Laravel (semelhante ao helper `response()`).

### D2 — `NotFoundHttpException` em vez de `ModelNotFoundException` nos closures
Laravel executa `prepareException()` antes de invocar os render callbacks. `ModelNotFoundException` e `AuthorizationException` são convertidas para tipos Symfony HTTP (`NotFoundHttpException`, `AccessDeniedHttpException`) antes dos callbacks serem chamados. Os closures devem usar os tipos convertidos, não os originais.

### D3 — `$meta` explícito em `devolverColeccao`
O caller conhece o contexto (paginado ou não) e fornece os valores correctos. Evita o problema de `collection->count()` devolver apenas os itens da página corrente em contextos paginados.

### D4 — Stack traces nunca expostos
O handler de `Throwable` usa sempre a mensagem genérica, independentemente de `APP_DEBUG`. Mensagem interna nunca chega ao cliente.

### D5 — `$this->getJson()` / `$this->postJson()` nos testes
Necessário para garantir o header `Accept: application/json`. Sem este header, o Laravel não invoca o handler JSON e devolve HTML — os testes falhariam silenciosamente.

---

## Dificuldades e surpresas

### Tipos Symfony no exception handler
A conversão automática de `ModelNotFoundException` → `NotFoundHttpException` e `AuthorizationException` → `AccessDeniedHttpException` é feita pelo Laravel antes dos callbacks. Não é óbvio — levou à clarificação na spec. Os testes continuam a lançar os tipos Laravel (`ModelNotFoundException`, `AuthorizationException`) nas rotas de teste; o Laravel trata da conversão antes de invocar o callback correcto.

### `$coleccao->collection` — acesso a propriedade interna
`ResourceCollection::$collection` é uma `Illuminate\Support\Collection` mas o PHPStan precisou de `?? new Collection` para satisfazer a análise de tipos. A propriedade pode ser `null` antes de `resolve()` ser chamado.

---

## Aprendizagens — Vertical Slice Architecture

### O que ficou mais claro

**`app/Shared/Http/` como namespace de infra partilhada**
A criação de `app/Shared/Http/ApiResponse.php` clarificou onde vive o código que é partilhado por todas as slices mas não pertence a nenhuma slice em particular. `app/Shared/` organiza-se em sub-namespaces por tipo (`Enums/`, `Contracts/`, `Http/`, `DTOs/`), não por feature. Isto está alinhado com a arquitectura Vertical Slice: cada slice tem tudo o que precisa localmente; o que é genuinamente partilhado vai para `Shared/`.

**O exception handler é infra, não lógica de negócio**
Configurar o handler em `bootstrap/app.php` em vez de numa classe separada reforça que isto é configuração de infra. Em VSA, a lógica de negócio fica nas Actions dentro das slices. O handler é o equivalente ao middleware — define contratos de comunicação, não comportamento de domínio.

**`ApiResponse` como contrato de saída**
Ter um único ponto de saída (`ApiResponse`) para todas as respostas de sucesso garante uniformidade sem acoplamento. Cada slice usa `ApiResponse::devolverSucesso(...)` no seu controller — o controller não sabe nada sobre HTTP codes ou estrutura JSON. Separa a preocupação de formatação HTTP da lógica da slice.

---

## Ficheiros criados/alterados

| Ficheiro | Acção |
|---|---|
| `app/Shared/Http/ApiResponse.php` | Criado |
| `bootstrap/app.php` | Actualizado — `withExceptions()` com 5 closures |
| `tests/Feature/Shared/ApiResponseTest.php` | Criado |
| `tests/Feature/Shared/ExceptionHandlerTest.php` | Criado |

---

## Commits

```
3a37b25 feat(shared): ApiResponse — factory estática de respostas de sucesso
c51219c feat(shared): exception handler centralizado — Problem Details RFC 7807
c82f8d7 test(shared): ApiResponseTest — 4 testes para métodos devolverSucesso/Criado/Vazio/Coleccao
ced1904 test(shared): ExceptionHandlerTest — 5 testes Problem Details por classe de excepção
4aca41f style: aplicar Rector e Pint — tipos em arrow functions e formatação
0def49b style: substituir magic numbers por Response::HTTP_* em ExceptionHandlerTest
d598f9b style: substituir assertStatus(500) por Response::HTTP_INTERNAL_SERVER_ERROR
```

---

## Próximo passo

Issue #5 (`CategoriaDocumento`) — actualizar controller para usar `ApiResponse` (CA-12).
