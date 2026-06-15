# Spec — refactor(categorias): substituir `fresh()` por `refresh()` em ActualizarCategoriaAction

**Issue:** #15
**Data:** 2026-06-15
**Branch:** `refactor/fresh-refresh-actualizar-categoria`

---

## Contrato público (inalterado)

```
PATCH /api/categorias-documento/{categorias_documento}
→ 200 CategoriaDocumentoResource (estado BD após save)
```

Nenhum campo de resposta, código HTTP, cabeçalho ou comportamento observável é alterado.

---

## Alteração

### `ActualizarCategoriaAction::handle()`

**Antes:**
```php
return $categoria->fresh() ?? $categoria;
```

**Depois:**
```php
$categoria->refresh();
return $categoria;
```

#### Semântica

| | `fresh()` | `refresh()` |
|---|---|---|
| Retorna | Nova instância (`?CategoriaDocumento`) | `static` (a mesma instância, actualizada) |
| Registo inexistente | Devolve `null` (absorvido por `?? $categoria`) | Lança `ModelNotFoundException` (via `findOrFail`) |
| Null check necessário | Sim | Não |
| Instância devolvida | Nova (diferente do `$categoria` original) | A mesma instância `$categoria` |
| Estado após chamada | `$categoria` inalterado | `$categoria` actualizado |

Em runtime, o registo nunca pode ter sido eliminado entre `save()` e `refresh()` — a excepção é impossível neste fluxo. No entanto, `refresh()` usa `findOrFail()` internamente, pelo que **estaticamente pode lançar `ModelNotFoundException`**. Segundo a Regra B do CLAUDE.md (`@throws` obrigatório em métodos que lançam excepções), o `handle()` deve declarar `@throws \Illuminate\Database\Eloquent\ModelNotFoundException` no PHPDoc.

---

## Ficheiros alterados

| Ficheiro | Tipo | Descrição |
|---|---|---|
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php` | alter | Substituição de `fresh() ?? $categoria` por `refresh(); return $categoria` |
| `docs/system_spec/01-features.md` | alter | Actualizar menção de `fresh()` para `refresh()` na tabela de Actions |

---

## Testes

Nenhum teste novo é necessário. Os testes existentes cobrem o contrato público e devem passar sem alteração:

- `tests/Feature/CategoriaDocumento/ActualizarCategoriaTest.php` (ou equivalente)

---

## Qualidade

```bash
composer lint        # Pint
composer refactor    # Rector
composer test        # Pipeline completa (Larastan + Pest)
```

Zero erros esperados em todos.
