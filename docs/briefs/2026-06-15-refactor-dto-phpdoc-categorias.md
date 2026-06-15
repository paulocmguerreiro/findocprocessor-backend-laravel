# Brief — Issue #16: refactor(categorias): @var array shape + @throws nos DTOs

**Data:** 2026-06-15
**Issue:** #16
**Slug:** refactor-dto-phpdoc-categorias
**Branch:** refactor/dto-phpdoc-categorias
**Tipo:** refactor

---

## Contexto

Após a formalização das Regras A e B em `CLAUDE.md` (commit `a00e84c`), os dois DTOs da feature `CategoriaDocumento` estão incompletos do ponto de vista de tipagem estática:

- `CriarCategoriaDto::fromRequest()` — falta `@var` array shape antes de `validated()` e `@throws` no PHPDoc
- `ActualizarCategoriaDto::fromRequest()` — idem

O comportamento runtime (o `if/throw`) já existe em ambos. Trata-se apenas de adicionar anotações PHPDoc que o Larastan e os callers necessitam para inferir tipos correctamente sem `mixed`.

---

## Ficheiros afectados

| Ficheiro | Alteração |
|---|---|
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php` | `@var array{nome: string, slug: string, tipo_movimento: string}` + `@throws \UnexpectedValueException` |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php` | `@var array{nome?: string, slug?: string, tipo_movimento?: string}` + `@throws \UnexpectedValueException` |

---

## Estado actual

```php
// CriarCategoriaDto — sem anotações
public static function fromRequest(CriarCategoriaRequest $request): self
{
    $validated = $request->validated();   // <-- sem @var
    $nome = $validated['nome'] ?? null;
    // ...
    throw new \UnexpectedValueException(...);  // <-- sem @throws no PHPDoc
}
```

---

## Decisões de design

- **Repository:** dispensável — sem queries Eloquent nesta issue
- **SYSTEM_SPEC:** nenhum ficheiro a actualizar — refactor de anotações, contrato inalterado
- **Testes existentes:** inalterados — comportamento runtime não muda
- **Larastan nível 9:** deve ficar verde após as anotações (elimina `mixed` nas variáveis derivadas)

---

## Critérios de aceitação

- CA-01: `CriarCategoriaDto::fromRequest()` tem `@var array{nome: string, slug: string, tipo_movimento: string} $validated`
- CA-02: `ActualizarCategoriaDto::fromRequest()` tem `@var array{nome?: string, slug?: string, tipo_movimento?: string} $validated`
- CA-03: Ambos os métodos têm `@throws \UnexpectedValueException` no PHPDoc do método
- CA-04: `composer test:types` verde — zero erros Larastan

---

## Fora de âmbito

- Não alterar regras de validação nos FormRequests
- Não alterar testes existentes
- Não auditar outros DTOs do projecto (issue separada)
