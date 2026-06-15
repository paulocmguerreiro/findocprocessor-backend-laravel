# Plano — refactor(categorias): substituir `fresh()` por `refresh()` em ActualizarCategoriaAction

**Issue:** #15
**Data:** 2026-06-15
**Branch:** `refactor/fresh-refresh-actualizar-categoria`

---

## Tarefas

### T1 — Substituir `fresh()` por `refresh()` e adicionar `@throws`

**Ficheiro:** `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php`

Substituir a linha final de `handle()`:

```php
// antes
return $categoria->fresh() ?? $categoria;

// depois
$categoria->refresh();
return $categoria;
```

Adicionar PHPDoc ao método `handle()` com `@throws \Illuminate\Database\Eloquent\ModelNotFoundException`
(Regra B — `refresh()` usa `findOrFail()` internamente; impossível em runtime mas declarado estaticamente).

Resultado esperado:

```php
/**
 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
 */
public function handle(CategoriaDocumento|string $idCategoria, ActualizarCategoriaDto $dados): CategoriaDocumento
{
    $categoria = is_string($idCategoria)
        ? CategoriaDocumento::findOrFail($idCategoria)
        : $idCategoria;

    $campos = array_filter([
        'nome'           => $dados->nome,
        'slug'           => $dados->slug,
        'tipo_movimento' => $dados->tipo_movimento,
    ], fn (mixed $valor): bool => $valor !== null);

    $categoria->fill($campos)->save();

    $categoria->refresh();

    return $categoria;
}
```

Commit: `refactor(categorias): substituir fresh() por refresh() em ActualizarCategoriaAction`

---

### T2 — Actualizar system_spec

**Ficheiro:** `docs/system_spec/01-features.md`

Na tabela de Actions, linha `ActualizarCategoriaAction`, corrigir a descrição de:
> `devolve fresh()`

para:
> `devolve refresh()` (actualiza instância existente)

Commit incluído no mesmo commit da T1, ou commit separado de docs.

---

### T3 — Qualidade e pipeline

```bash
composer lint        # Pint — formatar
composer refactor    # Rector — modernizar
composer test        # Pipeline completa (Larastan nível 9 + Pest)
```

Zero erros esperados. Testes existentes passam sem alteração.

---

## Ordem de execução

```
T1 (código) → T2 (docs) → T3 (qualidade)
```

## Estimativa

~15 minutos — alteração de 1 linha + PHPDoc + doc update.
