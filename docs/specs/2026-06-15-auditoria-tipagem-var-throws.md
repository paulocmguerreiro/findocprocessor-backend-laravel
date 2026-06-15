# Spec — Issue #17: Auditoria de tipagem (@var + @throws)

**Data:** 2026-06-15
**Brief:** `docs/briefs/2026-06-15-auditoria-tipagem-var-throws.md`

---

## Critérios de aceitação

- **CA-01:** Todos os `$request->validated()` em `app/` têm `@var` array shape PHPDoc.
- **CA-02:** Todos os métodos que lancem excepções (explícitas ou via `findOrFail()`) em `app/` têm `@throws` no PHPDoc.
- **CA-03:** `composer test:types` verde — zero erros Larastan após as adições.
- **CA-04:** `composer test` verde — pipeline completa.

---

## Contrato das alterações

### `EliminarCategoriaAction::handle()`

```php
/**
 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<\App\Models\CategoriaDocumento>
 */
public function handle(CategoriaDocumento|string $idCategoria): void
```

### `VerCategoriaAction::handle()`

```php
/**
 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<\App\Models\CategoriaDocumento>
 */
public function handle(CategoriaDocumento|string $idCategoria): CategoriaDocumento
```

---

## Ficheiros alterados

| Ficheiro | Alteração |
|---|---|
| `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php` | Adicionar `@throws ModelNotFoundException` |
| `app/Features/CategoriaDocumento/Ver/VerCategoriaAction.php` | Adicionar `@throws ModelNotFoundException` |

## Ficheiros inalterados (já conformes)

| Ficheiro | Motivo |
|---|---|
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php` | `@var` + `@throws` já presentes |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php` | `@var` + `@throws` já presentes |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php` | `@throws ModelNotFoundException` já presente |

---

## Sem impacto em

- OpenAPI / `openapi.yaml`
- Testes (sem alterações de comportamento)
- SYSTEM_SPEC (sem mudança de contrato)
- RGPD/NIS2
