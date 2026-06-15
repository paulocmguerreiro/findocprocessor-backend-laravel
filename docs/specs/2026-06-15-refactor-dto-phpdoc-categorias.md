# Spec — Issue #16: refactor(categorias): @var array shape + @throws nos DTOs

**Data:** 2026-06-15
**Issue:** #16
**Brief:** [2026-06-15-refactor-dto-phpdoc-categorias.md](../briefs/2026-06-15-refactor-dto-phpdoc-categorias.md)

---

## Alterações especificadas

### T1 — `CriarCategoriaDto::fromRequest()`

**Ficheiro:** `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php`

Adicionar `@throws \UnexpectedValueException` ao PHPDoc do método e `@var` array shape antes de `$request->validated()`:

```php
/**
 * @throws \UnexpectedValueException
 */
public static function fromRequest(CriarCategoriaRequest $request): self
{
    /** @var array{nome: string, slug: string, tipo_movimento: string} $validated */
    $validated = $request->validated();
    $nome = $validated['nome'] ?? null;
    $slug = $validated['slug'] ?? null;
    $tipoMovimento = $validated['tipo_movimento'] ?? null;

    if (! is_string($nome) || ! is_string($slug) || ! is_string($tipoMovimento)) {
        throw new \UnexpectedValueException('Dados inválidos após validação.');
    }

    return new self(
        nome: $nome,
        slug: $slug,
        tipo_movimento: TipoMovimento::from($tipoMovimento),
    );
}
```

**Nota sobre o array shape de `CriarCategoriaRequest`:** todos os campos são `required`, logo sem `?` — tipos não-nullable.

### T2 — `ActualizarCategoriaDto::fromRequest()`

**Ficheiro:** `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php`

Idem, com campos opcionais (`sometimes`) marcados com `?` no array shape:

```php
/**
 * @throws \UnexpectedValueException
 */
public static function fromRequest(ActualizarCategoriaRequest $request): self
{
    /** @var array{nome?: string, slug?: string, tipo_movimento?: string} $validated */
    $validated = $request->validated();
    $nome = $validated['nome'] ?? null;
    $slug = $validated['slug'] ?? null;
    $tipoMovimento = $validated['tipo_movimento'] ?? null;

    if (
        ($nome !== null && ! is_string($nome)) ||
        ($slug !== null && ! is_string($slug)) ||
        ($tipoMovimento !== null && ! is_string($tipoMovimento))
    ) {
        throw new \UnexpectedValueException('Dados inválidos após validação.');
    }

    return new self(
        nome: $nome,
        slug: $slug,
        tipo_movimento: is_string($tipoMovimento) ? TipoMovimento::from($tipoMovimento) : null,
    );
}
```

**Nota sobre campos opcionais:** `nome?`, `slug?`, `tipo_movimento?` — chaves podem estar ausentes quando o campo não foi enviado (`sometimes`). O `?` no array shape reflecte exactamente as regras do FormRequest.

---

## Invariantes

- Comportamento runtime inalterado — o `if/throw` não é alterado
- Nenhum teste alterado
- SYSTEM_SPEC: nenhum ficheiro a actualizar
- openapi.yaml: não afectado

---

## Critérios de aceitação

| CA | Verificação |
|---|---|
| CA-01 | `CriarCategoriaDto` tem `@var array{nome: string, slug: string, tipo_movimento: string} $validated` |
| CA-02 | `ActualizarCategoriaDto` tem `@var array{nome?: string, slug?: string, tipo_movimento?: string} $validated` |
| CA-03 | Ambos os métodos têm `@throws \UnexpectedValueException` no PHPDoc |
| CA-04 | `composer test:types` — zero erros Larastan |
