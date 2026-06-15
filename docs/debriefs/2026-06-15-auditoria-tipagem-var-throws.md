# Debrief — Issue #17: Auditoria de tipagem (@var + @throws)

**Data:** 2026-06-15
**Branch:** `chore/auditoria-tipagem-var-throws`
**Issue:** [#17](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/17)
**Duração:** ~30 minutos

---

## O que foi feito

Auditoria completa de `app/` para conformidade com as Regras A e B do CLAUDE.md.

### Resultado da varredura

| Regra | Ficheiro | Estado inicial | Acção |
|---|---|---|---|
| A (`@var` em `validated()`) | `CriarCategoriaDto::fromRequest()` | ✓ conforme | — |
| A (`@var` em `validated()`) | `ActualizarCategoriaDto::fromRequest()` | ✓ conforme | — |
| B (`@throws` com `throw` explícito) | `CriarCategoriaDto::fromRequest()` | ✓ conforme | — |
| B (`@throws` com `throw` explícito) | `ActualizarCategoriaDto::fromRequest()` | ✓ conforme | — |
| B (`@throws` via `findOrFail()`) | `ActualizarCategoriaAction::handle()` | ✓ conforme | — |
| B (`@throws` via `findOrFail()`) | `EliminarCategoriaAction::handle()` | ✗ ausente | adicionado |
| B (`@throws` via `findOrFail()`) | `VerCategoriaAction::handle()` | ✗ ausente | adicionado |
| `@var` após `findOrFail()` | `EliminarCategoriaAction::handle()` | ✗ ausente | adicionado |

### Ficheiros alterados

**`EliminarCategoriaAction.php`** — dois PHPDocs adicionados:
```php
/**
 * @throws ModelNotFoundException<CategoriaDocumento>
 */
public function handle(CategoriaDocumento|string $idCategoria): void
{
    /** @var CategoriaDocumento $categoria */
    $categoria = is_string($idCategoria)
        ? CategoriaDocumento::findOrFail($idCategoria)
        : $idCategoria;
    // ...
}
```

**`VerCategoriaAction.php`** — um PHPDoc adicionado:
```php
/**
 * @throws ModelNotFoundException<CategoriaDocumento>
 */
public function handle(CategoriaDocumento|string $idCategoria): CategoriaDocumento
{
    return is_string($idCategoria)
        ? CategoriaDocumento::findOrFail($idCategoria)
        : $idCategoria;
}
```

`VerCategoriaAction` não precisou de `@var` — retorna directamente sem variável intermédia.

---

## Decisões tomadas

### Extensão da Regra B a `findOrFail()`
A issue dizia "métodos que contenham `throw`". `EliminarCategoriaAction` e `VerCategoriaAction` não têm `throw` explícito, mas chamam `findOrFail()` que lança `ModelNotFoundException`. Optou-se por incluí-las porque:
1. `ActualizarCategoriaAction` já documentava `@throws ModelNotFoundException` — precedente interno.
2. O comportamento observable para o caller é idêntico: a excepção propaga-se.
3. O handler global converte `ModelNotFoundException` → `NotFoundHttpException`, mas isso não isenta a action de declarar o contrato.

### `@var CategoriaDocumento $categoria` em `EliminarCategoriaAction`
Identificado durante a revisão do checkpoint ②. `ActualizarCategoriaAction` já tinha este padrão. O `@var` antes do assignment de `findOrFail()` é uma convenção separada da Regra A (que é só para `validated()`) — serve para que o IDE e o Larastan conheçam o tipo concreto do modelo quando o resultado é atribuído a uma variável.

### Pint aplicou `fully_qualified_strict_types`
Ao adicionar os `@throws` com FQCN (`\Illuminate\Database\Eloquent\ModelNotFoundException`), o Pint moveu para `use ModelNotFoundException` no topo e simplificou o PHPDoc para `ModelNotFoundException<CategoriaDocumento>`. Resultado mais limpo.

---

## Resultados de qualidade

| Ferramenta | Resultado |
|---|---|
| Rector dry-run | ✅ 0 sugestões |
| Pint | ✅ sem alterações pendentes |
| Larastan nível 9 | ✅ 0 erros |
| Pest (unit + feature) | ✅ 62/62 testes |
| Type coverage | ✅ 100% |
| Coverage | ✅ 100% |

---

## Aprendizagens

### `@var` vs `@throws` — dois padrões distintos

Esta issue ajudou a clarificar que há dois padrões de PHPDoc com propósitos diferentes:

- **`@var`** (Regra A) — anota o tipo de uma variável quando a inferência estática é insuficiente. Aplica-se a `$request->validated()` (que retorna `array<string, mixed>`) e a variáveis atribuídas por `findOrFail()` quando guardadas numa variável local (o tipo genérico `Model` precisa de ser estreitado para o modelo concreto).

- **`@throws`** (Regra B) — documenta que um método pode lançar uma excepção, seja por `throw` explícito ou por chamar um método que lança (ex: `findOrFail()`). Informa os callers sem que tenham de inspeccionar a implementação.

A distinção importante: `VerCategoriaAction` não precisou de `@var` porque não cria uma variável intermédia — retorna directamente. `EliminarCategoriaAction` precisou de ambos (`@throws` + `@var`) porque tem a variável `$categoria`.

### Consistência como critério de auditoria

A varredura mostrou que o critério "o código tem `throw`?" é necessário mas não suficiente para identificar omissões. O critério mais robusto é: *"existe um padrão equivalente noutro ficheiro da mesma feature?"*. `ActualizarCategoriaAction` foi o padrão de referência para `EliminarCategoriaAction` e `VerCategoriaAction`.

---

## Fora de âmbito (confirmado)

- Lógica de negócio — inalterada
- Testes — inalterados
- SYSTEM_SPEC — sem mudança de contrato (anotações puras)
- OpenAPI — não afectado
