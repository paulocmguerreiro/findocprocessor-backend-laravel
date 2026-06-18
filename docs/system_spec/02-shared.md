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

## DTOs (app/Features/CategoriaDocumento/)

DTOs vivem dentro da slice, co-localizados com a acção correspondente. Todos `final readonly`.

**Padrão Value Object (obrigatório desde issue #28):** o construtor valida invariantes estruturais; `fromRequest()` só mapeia. Ver CLAUDE.md — secção "Padrão obrigatório nos DTOs".

| Camada | Responsabilidade |
|---|---|
| `FormRequest` | required, formato, unicidade BD, regras HTTP |
| DTO (construtor) | invariantes estruturais — não-vazio, formato mínimo |
| Action | regras de negócio — unicidade entre entidades, consistência |

### `CriarCategoriaDto` — `App\Features\CategoriaDocumento\Criar\CriarCategoriaDto`

```php
final readonly class CriarCategoriaDto
{
    /** @throws \InvalidArgumentException */
    public function __construct(
        public string $nome,
        public string $slug,
        public TipoMovimento $tipoMovimento,
    ) {
        if (trim($this->nome) === '') { throw new \InvalidArgumentException('nome não pode ser vazio.'); }
        if (trim($this->slug) === '') { throw new \InvalidArgumentException('slug não pode ser vazio.'); }
    }

    /** @throws \InvalidArgumentException */
    public static function fromRequest(CriarCategoriaRequest $request): self
    {
        /** @var array{nome: string, slug: string, tipo_movimento: string} $dadosValidados */
        $dadosValidados = $request->validated();
        return new self(nome: $dadosValidados['nome'], slug: $dadosValidados['slug'], tipoMovimento: TipoMovimento::from($dadosValidados['tipo_movimento']));
    }
}
```

- `fromRequest()` só mapeia — sem guards `is_string()` redundantes
- Construtor lança `\InvalidArgumentException` para invariantes estruturais

### `ActualizarCategoriaDto` — `App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaDto`

```php
final readonly class ActualizarCategoriaDto
{
    /** @throws \InvalidArgumentException */
    public function __construct(
        public string $nome,
        public string $slug,
        public TipoMovimento $tipoMovimento,
    ) {
        if (trim($this->nome) === '') { throw new \InvalidArgumentException('nome não pode ser vazio.'); }
        if (trim($this->slug) === '') { throw new \InvalidArgumentException('slug não pode ser vazio.'); }
    }

    /** @throws \InvalidArgumentException */
    public static function fromRequest(ActualizarCategoriaRequest $request): self
    {
        /** @var array{nome: string, slug: string, tipo_movimento: string} $dadosValidados */
        $dadosValidados = $request->validated();
        return new self(nome: $dadosValidados['nome'], slug: $dadosValidados['slug'], tipoMovimento: TipoMovimento::from($dadosValidados['tipo_movimento']));
    }
}
```

- Campos não-nullable — update completo (PUT); construtor valida invariantes incondicionalmente
- Estrutura idêntica ao `CriarCategoriaDto` (Issue #30)
- Array shape sem `?` — todos os campos são `required` no FormRequest

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

### `DirecaoOrdenacao` — `App\Shared\Enums\DirecaoOrdenacao`

PHP 8.1 backed enum (string). Direcção de ordenação genérica — reutilizável em todas as listagens do sistema.

```php
enum DirecaoOrdenacao: string
{
    case Asc  = 'asc';
    case Desc = 'desc';
}
```

- Valores na query string: `'asc'`, `'desc'`
- Cases em TitleCase PT per convenção CLAUDE.md
- Usado em: `ListarCategoriasAction::handle()`, `ListarCategoriasRequest` (param `direction`)

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
| `nome` | `required`, `string`, `max:255` |
| `slug` | `required`, `string`, `max:255`, `Rule::unique(...)->ignore($uuid)` |
| `tipo_movimento` | `required`, `string`, `Rule::in(TipoMovimento::cases())` |

- `$uuid` via `$this->route('categorias_documento')` — exclui o registo actual da validação de unicidade (parâmetro gerado pelo `apiResource`)
- Mensagens em português de Portugal via `messages()`; inclui entradas `*.required` para os 3 campos (Issue #30 — semântica PUT)

---

## HTTP (app/Shared/Http/)

### `ApiResponse` — `App\Shared\Http\ApiResponse`

Factory estática `final` para respostas de sucesso. Único ponto de saída de respostas nos controllers.

| Método | HTTP | Estrutura |
|---|---|---|
| `devolverSucesso(JsonResource $recurso): JsonResponse` | 200 | `{ "data": { ... } }` |
| `devolverCriado(JsonResource $recurso): JsonResponse` | 201 | `{ "data": { ... } }` |
| `devolverVazio(): JsonResponse` | 204 | body vazio |
| `devolverPaginado(AnonymousResourceCollection $coleccao): JsonResponse` | 200 | `{ "data": [...], "links": {...}, "meta": {...} }` — cursor pagination |
| `devolverColeccao(ResourceCollection $coleccao, array $meta = []): JsonResponse` | 200 | `{ "data": [...], "meta": { ... } }` |

- `devolverPaginado` delega em `$coleccao->response()` — o Laravel resolve automaticamente `links` e `meta` do `CursorPaginator`
- `$meta` de `devolverColeccao` é fornecido explicitamente pelo caller (uso para colecções não paginadas)
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
