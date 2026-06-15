# Plano — Issue #17: Auditoria de tipagem (@var + @throws)

**Data:** 2026-06-15
**Spec:** `docs/specs/2026-06-15-auditoria-tipagem-var-throws.md`

---

## T1 — Adicionar `@throws` a `EliminarCategoriaAction`

**Ficheiro:** `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php`

Adicionar PHPDoc ao método `handle()`:

```php
/**
 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<\App\Models\CategoriaDocumento>
 */
public function handle(CategoriaDocumento|string $idCategoria): void
```

Verificação: `composer lint && composer refactor`

---

## T2 — Adicionar `@throws` a `VerCategoriaAction`

**Ficheiro:** `app/Features/CategoriaDocumento/Ver/VerCategoriaAction.php`

Adicionar PHPDoc ao método `handle()`:

```php
/**
 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<\App\Models\CategoriaDocumento>
 */
public function handle(CategoriaDocumento|string $idCategoria): CategoriaDocumento
```

Verificação: `composer lint && composer refactor`

---

## T3 — Pipeline completa

```bash
composer test
```

Zero erros em: Rector dry-run + Pint + Larastan + Pest.

---

## T4 — Commit

```bash
git add app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php \
        app/Features/CategoriaDocumento/Ver/VerCategoriaAction.php
git commit -m "refactor(tipagem): @throws ModelNotFoundException em Eliminar e Ver actions — Issue #17"
```

---

## Ordem de execução

T1 → T2 (paralelos se necessário) → T3 → T4

**Estimativa:** ~10 minutos (alterações mínimas, sem risco de regressão).
