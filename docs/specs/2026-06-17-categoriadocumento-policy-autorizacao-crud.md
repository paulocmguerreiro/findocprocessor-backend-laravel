# Spec — Issue #25: CategoriaDocumento Policy de autorização CRUD

**Data:** 2026-06-17
**Slug:** categoriadocumento-policy-autorizacao-crud
**Brief:** docs/briefs/2026-06-17-categoriadocumento-policy-autorizacao-crud.md

---

## Contratos

### `CategoriaDocumentoPolicy`

```
Namespace: App\Policies
Ficheiro:  app/Policies/CategoriaDocumentoPolicy.php
```

| Método | Assinatura | Retorna |
|---|---|---|
| `viewAny` | `viewAny(?User $user): bool` | `true` |
| `view` | `view(?User $user, CategoriaDocumento $categoriaDocumento): bool` | `true` |
| `create` | `create(?User $user): bool` | `true` |
| `update` | `update(?User $user, CategoriaDocumento $categoriaDocumento): bool` | `true` |
| `delete` | `delete(?User $user, CategoriaDocumento $categoriaDocumento): bool` | `true` |

- `final class` com `strict_types=1`
- Auto-discover por convenção de nome — sem binding manual em `AppServiceProvider`
- Nenhum método lança excepção nesta fase

---

### FormRequests — actualizações a `authorize()`

Todos os FormRequests substituem `return true` por delegação à Policy via `$this->authorize()`:

| FormRequest | `authorize()` chama | Nota |
|---|---|---|
| `ListarCategoriasRequest` | `$this->authorize('viewAny', CategoriaDocumento::class)` | Sem modelo |
| `CriarCategoriaRequest` | `$this->authorize('create', CategoriaDocumento::class)` | Sem modelo |
| `VerCategoriaRequest` *(novo)* | `$this->authorize('view', $this->route('categorias_documento'))` | Route Model Binding |
| `ActualizarCategoriaRequest` | `$this->authorize('update', $this->route('categorias_documento'))` | Route Model Binding |
| `EliminarCategoriaRequest` *(novo)* | `$this->authorize('delete', $this->route('categorias_documento'))` | Route Model Binding |

`$this->authorize()` não retorna — lança `AuthorizationException` (403) se negado. O retorno de `authorize(): bool` é satisfeito pelo facto de o Gate nunca lançar excepção nesta fase (Policy retorna sempre `true`).

**`VerCategoriaRequest` e `EliminarCategoriaRequest`** são FormRequests mínimos — apenas `authorize()`, sem `rules()` (sem corpo de request para validar).

---

### Actions — `Gate::authorize()` por método

| Action | Chamada `Gate::authorize()` | Posição em `handle()` |
|---|---|---|
| `ListarCategoriasAction` | `Gate::authorize('viewAny', CategoriaDocumento::class)` | Início, antes da query |
| `CriarCategoriaAction` | `Gate::authorize('create', CategoriaDocumento::class)` | Início, antes do `create()` |
| `VerCategoriaAction` | `Gate::authorize('view', $categoria)` | Após resolução do modelo |
| `ActualizarCategoriaAction` | `Gate::authorize('update', $categoria)` | Após resolução do modelo |
| `EliminarCategoriaAction` | `Gate::authorize('delete', $categoria)` | Após resolução do modelo |

**PHPDoc obrigatório** em cada `handle()` com `@throws \Illuminate\Auth\Access\AuthorizationException`.

**`use Illuminate\Support\Facades\Gate;`** adicionado a cada Action.

Ordem para Actions com `CategoriaDocumento|string`:
```
1. Resolver modelo (findOrFail se string, usar instância se model)
2. Gate::authorize('view'|'update'|'delete', $categoria)
3. Lógica de negócio
```

---

### Controller — `show` e `destroy` actualizados

```php
// Antes
public function show(CategoriaDocumento $categorias_documento, VerCategoriaAction $accao): JsonResponse

// Depois
public function show(VerCategoriaRequest $pedido, CategoriaDocumento $categorias_documento, VerCategoriaAction $accao): JsonResponse
```

```php
// Antes
public function destroy(CategoriaDocumento $categorias_documento, EliminarCategoriaAction $accao): JsonResponse

// Depois
public function destroy(EliminarCategoriaRequest $pedido, CategoriaDocumento $categorias_documento, EliminarCategoriaAction $accao): JsonResponse
```

`$pedido` é injectado mas não usado explicitamente no corpo do método — a sua presença activa o `authorize()` do FormRequest antes de o Controller executar.

---

## Critérios de aceitação

### CA-01 — Policy criada

- `app/Policies/CategoriaDocumentoPolicy.php` existe
- 5 métodos com `?User $user` nullable
- Todos retornam `true`
- `final class`, `strict_types=1`

### CA-02 — FormRequests delegam à Policy

- Nenhum FormRequest tem `return true` hardcoded
- `VerCategoriaRequest` e `EliminarCategoriaRequest` existem
- Controller usa os 5 FormRequests (incluindo novos em `show`/`destroy`)

### CA-03 — Actions verificam a Policy

- Todas as 5 Actions chamam `Gate::authorize()` em `handle()`
- `@throws \Illuminate\Auth\Access\AuthorizationException` presente no PHPDoc
- Para Actions com modelo: `Gate::authorize()` após resolução do modelo

### CA-04 — Testes de feature

Cenários por endpoint (×5):

| Cenário | Método | Resultado esperado |
|---|---|---|
| Guest (sem autenticação) | GET /api/categorias-documento | 200 |
| Guest | POST /api/categorias-documento | 201 |
| Guest | GET /api/categorias-documento/{id} | 200 |
| Guest | PATCH /api/categorias-documento/{id} | 200 |
| Guest | DELETE /api/categorias-documento/{id} | 204 |

Nota: testes de 403 não são aplicáveis nesta fase — a Policy retorna sempre `true`.

### CA-05 — Qualidade

- `composer test` passa sem erros
- Larastan nível 9 — zero erros
- 100% type coverage e 100% code coverage

---

## Ficheiros a criar/modificar

### Criar

```
app/Policies/CategoriaDocumentoPolicy.php
app/Features/CategoriaDocumento/Ver/VerCategoriaRequest.php
app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaRequest.php
```

### Modificar

```
app/Features/CategoriaDocumento/Listar/ListarCategoriasRequest.php   — authorize()
app/Features/CategoriaDocumento/Criar/CriarCategoriaRequest.php      — authorize()
app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaRequest.php — authorize()
app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php    — Gate::authorize()
app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php       — Gate::authorize()
app/Features/CategoriaDocumento/Ver/VerCategoriaAction.php           — Gate::authorize()
app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php — Gate::authorize()
app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php — Gate::authorize()
app/Features/CategoriaDocumento/CategoriaDocumentoController.php     — show, destroy
```

### Testes

Adicionar cenários de autorização aos testes de feature existentes ou criar novo ficheiro dedicado.

---

## Não entra nesta issue

- Autenticação obrigatória (`User` não nullable)
- Autorização por role/ownership
- Registo manual da Policy em `AppServiceProvider`
- Alterações ao openapi.yaml
