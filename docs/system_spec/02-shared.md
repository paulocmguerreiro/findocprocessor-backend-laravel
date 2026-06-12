# System Spec — 02: Shared

> Actualizado automaticamente após cada Issue pela Fase 3 (documenta-issue).

## States (app/Shared/States/)

Ciclo:
```
PENDING → AGUARDA_ENVIO → ENVIADO → AGUARDA_RESPOSTA → DONE
                                                      ↘ ERROR
                                                      ↘ PERIGOSO
```

_Implementações pendentes._

## Contracts (app/Shared/Contracts/)

_Vazio até à primeira issue implementada._

## DTOs (app/Shared/DTOs/)

_Vazio até à primeira issue implementada._

## Enums (app/Shared/Enums/)

### `TipoMovimento` — `App\Shared\Enums\TipoMovimento`

PHP 8.1 backed enum (string). Classifica o tipo de movimento contabilístico de uma categoria de documento.

```php
enum TipoMovimento: string
{
    case Debito  = 'debito';
    case Credito = 'credito';
    case Neutro  = 'neutro';
}
```

- Valores na BD: `'debito'`, `'credito'`, `'neutro'` (lowercase)
- Cases em TitleCase PT per convenção CLAUDE.md
- Usado em: `CategoriaDocumento::$tipo_movimento` (cast Eloquent)

---

`DocumentStatus` — PHP 8.1 backed enum (string). _Pendente._

## Resources (app/Features/<Feature>/

JsonResources vivem dentro da slice, não em `app/Http/Resources/`.

### `CategoriaDocumentoResource` — `App\Features\CategoriaDocumento\CategoriaDocumentoResource`

Formata a resposta JSON de todos os endpoints que retornem uma `CategoriaDocumento`.

```json
{
  "id": "019741b2-...",
  "nome": "Fatura de Fornecedor",
  "slug": "fatura-de-fornecedor",
  "tipo_movimento": "debito"
}
```

- `tipo_movimento` exposto como string via `->value` (nunca o enum em bruto)
- Timestamps omitidos intencionalmente
- PHPDoc `array{id: string, nome: string, slug: string, tipo_movimento: string}` em `toArray()`

---

## FormRequests (app/Features/<Feature>/<Acção>/

FormRequests vivem dentro da slice, co-localizados com a acção correspondente.

### `CriarCategoriaRequest` — `App\Features\CategoriaDocumento\Criar\CriarCategoriaRequest`

| Campo | Regras |
|---|---|
| `nome` | `required`, `string`, `max:255` |
| `slug` | `required`, `string`, `max:255`, `Rule::unique('categorias_documento', 'slug')` |
| `tipo_movimento` | `required`, `string`, `Rule::in(TipoMovimento::cases())` |

### `ActualizarCategoriaRequest` — `App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaRequest`

| Campo | Regras |
|---|---|
| `nome` | `sometimes`, `string`, `max:255` |
| `slug` | `sometimes`, `string`, `max:255`, `Rule::unique(...)->ignore($uuid)` |
| `tipo_movimento` | `sometimes`, `string`, `Rule::in(TipoMovimento::cases())` |

- `$uuid` via `$this->route('categorias_documento')` — exclui o registo actual da validação de unicidade (parâmetro gerado pelo `apiResource`)
- Mensagens em português de Portugal via `messages()`; sem entradas `*.required` (campos são `sometimes`)

---

## HTTP (app/Shared/Http/)

### `ApiResponse` — `App\Shared\Http\ApiResponse`

Factory estática `final` para respostas de sucesso. Único ponto de saída de respostas nos controllers.

| Método | HTTP | Estrutura |
|---|---|---|
| `devolverSucesso(JsonResource $recurso): JsonResponse` | 200 | `{ "data": { ... } }` |
| `devolverCriado(JsonResource $recurso): JsonResponse` | 201 | `{ "data": { ... } }` |
| `devolverVazio(): JsonResponse` | 204 | body vazio |
| `devolverColeccao(ResourceCollection $coleccao, array $meta = []): JsonResponse` | 200 | `{ "data": [...], "meta": { ... } }` |

- `$meta` é fornecido explicitamente pelo caller (`['total' => N]`, ou com campos de paginação)
- Não injectable — formatação pura sem lógica de negócio
- Classe `final` — não extensível

---

## Exception Handler (bootstrap/app.php)

Configurado via `withExceptions()`. Cinco closures `render()` por ordem de especificidade.

Todos os handlers verificam `$request->expectsJson()` — devolvem `null` para requests HTML.

**Payload de erro (Problem Details RFC 7807 simplificado):**

```json
{ "status": <int>, "detail": "<string PT>" }
// 422 inclui também: "errors": { "campo": ["mensagem"] }
```

**Mapeamento de excepções:**

| Excepção no closure | HTTP | `detail` |
|---|---|---|
| `ValidationException` | 422 | "Os dados fornecidos são inválidos." + `errors` por campo |
| `NotFoundHttpException` | 404 | "Recurso não encontrado." |
| `AccessDeniedHttpException` | 403 | "Sem permissão para aceder a este recurso." |
| `AuthenticationException` | 401 | "Não autenticado." |
| `Throwable` (fallback) | 500 | "Ocorreu um erro interno. Tente novamente mais tarde." |

> **Nota:** O Laravel converte `ModelNotFoundException` → `NotFoundHttpException` e `AuthorizationException` → `AccessDeniedHttpException` antes de invocar os callbacks (`prepareException()`). Os closures usam os tipos Symfony convertidos.

Stack traces nunca incluídos na resposta.

---

## Exceptions (app/Shared/Exceptions/)

_Vazio até à primeira issue implementada._
