# Brief — Issue #6: ApiResponse + Problem Details

**Data:** 2026-06-12
**Issue:** #6 — feat(shared): envelope universal de resposta JSON — ApiResponse + Problem Details + HTTP codes
**Branch:** feat/shared-api-response
**Autor:** Paulo Guerreiro

---

## Contexto

A feature `CategoriaDocumento` (Issue #5) foi implementada sem um contrato de resposta uniforme — os controllers devolvem directamente `JsonResource` sem envelope `{ "data": ... }` e os erros não seguem nenhum standard. Esta issue resolve esse problema ao introduzir:

1. **`ApiResponse`** — factory estática em `app/Shared/Http/ApiResponse.php` que encapsula todas as respostas de sucesso
2. **Exception handler centralizado** — em `bootstrap/app.php` via `withExceptions()`, que converte as excepções mais comuns em **Problem Details (RFC 7807)**

O frontend e o backend .NET ainda não estão implementados, pelo que não há breaking changes. O Issue #5 (`CategoriaDocumento`) será actualizado para usar `ApiResponse`.

---

## Objectivo

Criar infra de resposta HTTP uniforme para todos os controllers actuais e futuros:

- Sucesso: `{ "data": ... }` (recurso único) ou `{ "data": [...], "meta": { "total": N } }` (colecção)
- Erro: Problem Details RFC 7807 simplificado — `status`, `detail` (e `errors` para 422); `type` e `title` omitidos por serem deriváveis do `status`
- HTTP codes semânticos: 200, 201, 204, 400, 401, 403, 404, 422, 500

---

## Decisões Técnicas

### `ApiResponse` — factory estática (não injectable)
`ApiResponse` não contém lógica de negócio — é pura formatação. Factory estática é idiomático para este padrão em Laravel (semelhante ao `response()` helper). Não precisa de interface nem de injecção.

### Exception handler em `bootstrap/app.php`
Laravel 13 usa `withExceptions()` em `bootstrap/app.php`. Não existe `app/Exceptions/Handler.php` separado — o handler é definido inline via closures `render()`.

**Excepções mapeadas:**
| Excepção | HTTP | Nota |
|---|---|---|
| `ValidationException` | 422 | inclui campo `errors` com erros por campo |
| `ModelNotFoundException` | 404 | message genérica ("Recurso não encontrado.") |
| `AuthorizationException` | 403 | message genérica |
| `AuthenticationException` | 401 | message genérica |
| `Throwable` (fallback) | 500 | sem stack trace em produção (`APP_DEBUG=false`) |

### CA-12 — Fora de âmbito
O controller de `CategoriaDocumento` é criado na Issue #5. Esta issue (#6) define apenas a infra de resposta (`ApiResponse` + exception handler) para que a Issue #5 a possa usar. CA-12 é verificado quando a Issue #5 for implementada.

### Localização
- `app/Shared/Http/ApiResponse.php` — nova classe
- `app/Shared/Http/` — directório novo dentro da estrutura `app/Shared/` já existente
- `bootstrap/app.php` — actualizado (exception handler)

---

## Invariantes

- `ApiResponse` não contém lógica de negócio — apenas formatação
- Stack traces nunca expostos em produção
- Mensagens de erro em português de Portugal; `type` e `title` em inglês (RFC 7807)
- `ApiResponse` é o único ponto de saída de respostas de sucesso nos controllers

---

## Critérios de Aceitação (resumo)

- CA-01 a CA-04: Métodos `ApiResponse` devolvem estrutura correcta
- CA-05 a CA-09: Exception handler mapeia cada classe de excepção para Problem Details
- CA-10: Testes de feature para cada tipo de resposta
- CA-11: 100% type coverage (`composer test`)
- CA-12: Fora de âmbito — verificado na Issue #5 quando o controller for criado
