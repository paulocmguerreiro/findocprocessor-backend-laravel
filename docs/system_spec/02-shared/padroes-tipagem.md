# System Spec — Shared: Padrões de Tipagem

> Convenções de tipagem aplicáveis a todo o código de domínio. Alvo: Larastan nível 9 sem erros + 100% type coverage.

---

## Regra A — Eliminar `mixed`: `@var` array shape em `validated()`

`$request->validated()` retorna `array<string, mixed>`. Antes de desestruturar, anotar **sempre** com array shape PHPDoc para que o Larastan conheça os tipos exactos das chaves:

```php
/** @var array{nome: string, slug: string, tipo_movimento: string} $dadosValidados */
$dadosValidados = $request->validated();

// chaves opcionais (rules com 'sometimes'):
/** @var array{nome?: string, slug?: string} $dadosValidados */
```

- Usar `array{key: type}` (forma exacta) e **não** `array<string, T>` quando as chaves são conhecidas.
- Chaves opcionais (rules `sometimes`) marcadas com `?`.
- Variável intermédia em camelCase PT com nome significativo (`$dadosValidados`) — nunca `$validated`, `$data`.

---

## Regra B — `@throws` obrigatório em métodos que lançam excepções

Sempre que um método contenha `throw` (directo ou via `DB::transaction()`, `Gate::authorize()`, `findOrFail()`, etc.), declarar `@throws` no PHPDoc. Os callers ficam informados estaticamente (IDE + Larastan) sem inspeccionarem a implementação:

```php
/**
 * @throws \UnexpectedValueException
 */
public static function fromRequest(XxxRequest $request): self { ... }
```

Casos comuns:

| Origem do `throw` | `@throws` a declarar |
|---|---|
| Construtor de DTO (invariantes) | `\InvalidArgumentException` |
| `DB::transaction()` numa Action de escrita | `\Throwable` |
| `findOrFail()` / `Gate::authorize()` | `\Throwable` (ou tipo específico se for o único) |

---

## Outras convenções

- `declare(strict_types=1)` em **todos** os ficheiros PHP.
- `@property-read` obrigatório em todos os Eloquent Models (tipagem completa das colunas — ver `03-models/00-convencoes-models.md`).
- Tipos de retorno e type hints de parâmetros explícitos em todos os métodos.
- Array shapes em PHPDoc para arrays de forma conhecida (ex: `toArray()` de Resources).
- Sem `mixed` no código de domínio — eliminar com array shapes e tipos nativos PHP 8.5.
