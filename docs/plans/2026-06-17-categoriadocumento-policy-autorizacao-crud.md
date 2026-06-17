# Plano — Issue #25: CategoriaDocumento Policy de autorização CRUD

**Data:** 2026-06-17
**Slug:** categoriadocumento-policy-autorizacao-crud
**Spec:** docs/specs/2026-06-17-categoriadocumento-policy-autorizacao-crud.md

---

## Tarefas

### T1 — Criar `CategoriaDocumentoPolicy`

**Ficheiro:** `app/Policies/CategoriaDocumentoPolicy.php`

```bash
php artisan make:policy CategoriaDocumentoPolicy --model=CategoriaDocumento --no-interaction
```

Ajustar o ficheiro gerado:
- `final class`
- `strict_types=1`
- `?User $user` em todos os métodos (substituir `User` por `?User`)
- Remover `restore` e `forceDelete` (fora de âmbito)
- Todos os métodos retornam `true`

Verificar: `composer lint && composer refactor`

---

### T2 — Criar `VerCategoriaRequest`

**Ficheiro:** `app/Features/CategoriaDocumento/Ver/VerCategoriaRequest.php`

```php
final class VerCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->authorize('view', $this->route('categorias_documento'));
        return true;
    }

    public function rules(): array { return []; }
}
```

Verificar: `composer lint && composer refactor`

---

### T3 — Criar `EliminarCategoriaRequest`

**Ficheiro:** `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaRequest.php`

Mesmo padrão que T2 com `'delete'` em vez de `'view'`.

Verificar: `composer lint && composer refactor`

---

### T4 — Actualizar 3 FormRequests existentes

Substituir `return true` por delegação à Policy em:

- `ListarCategoriasRequest::authorize()` → `$this->authorize('viewAny', CategoriaDocumento::class)`
- `CriarCategoriaRequest::authorize()` → `$this->authorize('create', CategoriaDocumento::class)`
- `ActualizarCategoriaRequest::authorize()` → `$this->authorize('update', $this->route('categorias_documento'))`

Padrão para os 3:
```php
public function authorize(): bool
{
    $this->authorize('ability', ...);
    return true;
}
```

Verificar: `composer lint && composer refactor`

---

### T5 — Actualizar Controller (`show` e `destroy`)

**Ficheiro:** `app/Features/CategoriaDocumento/CategoriaDocumentoController.php`

Adicionar `VerCategoriaRequest` e `EliminarCategoriaRequest` como primeiro parâmetro nos métodos `show` e `destroy`:

```php
public function show(VerCategoriaRequest $pedido, CategoriaDocumento $categorias_documento, VerCategoriaAction $accao): JsonResponse

public function destroy(EliminarCategoriaRequest $pedido, CategoriaDocumento $categorias_documento, EliminarCategoriaAction $accao): JsonResponse
```

Adicionar imports dos novos FormRequests.

Verificar: `composer lint && composer refactor`

---

### T6 — Actualizar 5 Actions com `Gate::authorize()`

Para cada Action, adicionar:
1. `use Illuminate\Support\Facades\Gate;`
2. `@throws \Illuminate\Auth\Access\AuthorizationException` no PHPDoc de `handle()`
3. Chamada `Gate::authorize()` na posição correcta

**`ListarCategoriasAction`** — antes da query:
```php
Gate::authorize('viewAny', CategoriaDocumento::class);
```

**`CriarCategoriaAction`** — antes do `create()`:
```php
Gate::authorize('create', CategoriaDocumento::class);
```

**`VerCategoriaAction`** — após resolução do modelo, antes do `return`:
```php
$categoria = is_string($idCategoria)
    ? CategoriaDocumento::findOrFail($idCategoria)
    : $idCategoria;

Gate::authorize('view', $categoria);

return $categoria;
```

**`ActualizarCategoriaAction`** — após resolução do modelo, antes do `fill()`:
```php
$categoria = is_string($idCategoria) ? CategoriaDocumento::findOrFail($idCategoria) : $idCategoria;
Gate::authorize('update', $categoria);
// ... fill, save, refresh
```

**`EliminarCategoriaAction`** — após resolução do modelo, antes do `delete()`:
```php
$categoria = is_string($idCategoria) ? CategoriaDocumento::findOrFail($idCategoria) : $idCategoria;
Gate::authorize('delete', $categoria);
$categoria->delete();
```

Verificar: `composer lint && composer refactor`

---

### T7 — Testes de feature (autorização)

Adicionar cenários de guest a cada ficheiro de teste de feature existente:

| Ficheiro | Teste a adicionar |
|---|---|
| `ListarCategoriasTest.php` | `it('guest pode listar categorias', ...)` |
| `CriarCategoriaTest.php` | `it('guest pode criar categoria', ...)` |
| `VerCategoriaTest.php` | `it('guest pode ver categoria', ...)` |
| `ActualizarCategoriaTest.php` | `it('guest pode actualizar categoria', ...)` |
| `EliminarCategoriaTest.php` | `it('guest pode eliminar categoria', ...)` |

Padrão (sem `actingAs()` — simula guest):
```php
it('guest pode listar categorias', function (): void {
    $this->getJson('/api/categorias-documento')
        ->assertOk();
});
```

Verificar: `composer test`

---

### T8 — Pipeline completa

```bash
composer test
```

Corrigir todos os erros antes de avançar.

---

## Ordem de execução

```
T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8
```

T1-T3 podem ser feitas em qualquer ordem entre si. T5 depende de T2 e T3. T7 depende de T1-T6.

---

## Commit após todas as tarefas

```bash
git add app/Policies/ app/Features/CategoriaDocumento/ tests/
git commit -m "feat(auth): CategoriaDocumentoPolicy + Gate::authorize nas Actions — Issue #25"
```
