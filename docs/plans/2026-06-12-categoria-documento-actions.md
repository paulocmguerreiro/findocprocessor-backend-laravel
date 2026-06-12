# Plano — Issue #5: CategoriaDocumento — Actions + Controller

**Data:** 2026-06-12
**Branch:** `feat/categoria-documento-actions`
**Issue:** #5

---

## Fluxo de dados

```
HTTP Request
  → FormRequest (valida input)
  → Controller (constrói DTO a partir de $request->validated())
  → Action (recebe DTO tipado, acede directamente ao Model)
  → Controller (formata com Resource)
  → ApiResponse (devolve JsonResponse)
  → Cliente
```

---

## Tarefas

### T1 — Corrigir `ActualizarCategoriaRequest` (parâmetro de rota)

**Ficheiro:** `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaRequest.php`

O `apiResource` gera `{categorias_documento}` (não `{categoria}`). Corrigir:

```php
// antes
$uuid = $this->route('categoria');
// depois
$uuid = $this->route('categorias_documento');
```

Commit: `fix(categoria): corrigir parâmetro de rota em ActualizarCategoriaRequest`

---

### T2 — `CriarCategoriaDto`

**Ficheiro:** `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php`

DTO `final readonly` com:
- `string $nome`
- `string $slug`
- `TipoMovimento $tipo_movimento`
- `static fromRequest(CriarCategoriaRequest $request): self`

Commit: `feat(categoria): CriarCategoriaDto`

---

### T3 — `ActualizarCategoriaDto`

**Ficheiro:** `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php`

DTO `final readonly` com:
- `?string $nome`
- `?string $slug`
- `?TipoMovimento $tipo_movimento`
- `static fromRequest(ActualizarCategoriaRequest $request): self`

`tipo_movimento` só é convertido para enum se `$request->has('tipo_movimento')`.

Commit: `feat(categoria): ActualizarCategoriaDto`

---

### T4 — `ListarCategoriasAction`

**Ficheiro:** `app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php`

```php
final class ListarCategoriasAction
{
    /** @return \Illuminate\Database\Eloquent\Collection<int, CategoriaDocumento> */
    public function handle(): \Illuminate\Database\Eloquent\Collection
    {
        return CategoriaDocumento::all();
    }
}
```

Commit: `feat(categoria): ListarCategoriasAction`

---

### T5 — `CriarCategoriaAction`

**Ficheiro:** `app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php`

```php
final class CriarCategoriaAction
{
    public function handle(CriarCategoriaDto $dados): CategoriaDocumento
    {
        return CategoriaDocumento::create([
            'nome'           => $dados->nome,
            'slug'           => $dados->slug,
            'tipo_movimento' => $dados->tipo_movimento,
        ]);
    }
}
```

Commit: `feat(categoria): CriarCategoriaAction`

---

### T6 — `VerCategoriaAction`

**Ficheiro:** `app/Features/CategoriaDocumento/Ver/VerCategoriaAction.php`

```php
final class VerCategoriaAction
{
    public function handle(string $idCategoria): CategoriaDocumento
    {
        return CategoriaDocumento::findOrFail($idCategoria);
    }
}
```

`findOrFail` lança `ModelNotFoundException` → 404 pelo exception handler.

Commit: `feat(categoria): VerCategoriaAction`

---

### T7 — `ActualizarCategoriaAction`

**Ficheiro:** `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php`

```php
final class ActualizarCategoriaAction
{
    public function handle(string $idCategoria, ActualizarCategoriaDto $dados): CategoriaDocumento
    {
        $categoria = CategoriaDocumento::findOrFail($idCategoria);

        $campos = array_filter([
            'nome'           => $dados->nome,
            'slug'           => $dados->slug,
            'tipo_movimento' => $dados->tipo_movimento,
        ], fn (mixed $valor): bool => $valor !== null);

        $categoria->fill($campos)->save();

        return $categoria->fresh() ?? $categoria;
    }
}
```

`array_filter` com `!== null` garante que só os campos enviados são actualizados. `fresh()` recarrega o modelo para garantir o cast do enum após save.

Commit: `feat(categoria): ActualizarCategoriaAction`

---

### T8 — `EliminarCategoriaAction`

**Ficheiro:** `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php`

```php
final class EliminarCategoriaAction
{
    public function handle(string $idCategoria): void
    {
        $categoria = CategoriaDocumento::findOrFail($idCategoria);
        $categoria->delete();
    }
}
```

Commit: `feat(categoria): EliminarCategoriaAction`

---

### T9 — `CategoriaDocumentoController`

**Ficheiro:** `app/Features/CategoriaDocumento/CategoriaDocumentoController.php`

Controller `final` com 5 métodos (index, store, show, update, destroy). Actions injectadas via parâmetros do método (service container). Usa `ApiResponse` para todas as respostas.

Parâmetro de rota: `$categorias_documento` (string UUID).

Commit: `feat(categoria): CategoriaDocumentoController`

---

### T10 — Rotas

**Ficheiro:** `routes/api.php`

```php
use App\Features\CategoriaDocumento\CategoriaDocumentoController;

Route::apiResource('categorias-documento', CategoriaDocumentoController::class);
```

Verificar com `php artisan route:list --path=categorias`.

Commit: `feat(categoria): registar rotas apiResource`

---

### T11 — Testes de feature

**Directório:** `tests/Feature/Features/CategoriaDocumento/`

Cinco ficheiros de teste (um por operação):

| Ficheiro | Cenários |
|---|---|
| `ListarCategoriasTest.php` | 200 + estrutura `{data, meta}` + lista vazia |
| `CriarCategoriaTest.php` | 201 + estrutura + 422 (slug duplicado, tipo inválido) |
| `VerCategoriaTest.php` | 200 + estrutura + 404 |
| `ActualizarCategoriaTest.php` | 200 (parcial) + 404 + 422 (slug duplicado) |
| `EliminarCategoriaTest.php` | 204 sem body + 404 |

Todos usam `RefreshDatabase` + `CategoriaDocumento::factory()`.

Commit: `test(categoria): feature tests CRUD — Issue #5`

---

### T12 — Pipeline de qualidade

```bash
composer lint        # Pint
composer refactor    # Rector
composer test        # pipeline completa
```

Corrigir todos os erros antes de finalizar.

Commit: não requerido (lint/refactor já corrigem os ficheiros editados nas tarefas anteriores)

---

## Ordem de execução recomendada

```
T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8 → T9 → T10 → T11 → T12
```

T1 primeiro para corrigir o parâmetro de rota antes de escrever qualquer DTO que dependa dele. T11 e T12 no final para validar tudo junto.

---

## Ficheiros a criar/editar

| Operação | Ficheiro |
|---|---|
| Editar | `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaRequest.php` |
| Criar | `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php` |
| Criar | `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php` |
| Criar | `app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php` |
| Criar | `app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php` |
| Criar | `app/Features/CategoriaDocumento/Ver/VerCategoriaAction.php` |
| Criar | `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php` |
| Criar | `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php` |
| Criar | `app/Features/CategoriaDocumento/CategoriaDocumentoController.php` |
| Editar | `routes/api.php` |
| Criar | `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php` |
| Criar | `tests/Feature/Features/CategoriaDocumento/CriarCategoriaTest.php` |
| Criar | `tests/Feature/Features/CategoriaDocumento/VerCategoriaTest.php` |
| Criar | `tests/Feature/Features/CategoriaDocumento/ActualizarCategoriaTest.php` |
| Criar | `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php` |

**Total:** 1 edição + 14 ficheiros novos
