# System Spec — 02: Shared

> Actualizado automaticamente após cada Issue pela Fase 3 (documenta-issue).

## States (app/Shared/States/)

Ciclo:
```
PENDING → AGUARDA_ENVIO → ENVIADO → AGUARDA_RESPOSTA → DONE
                                                      ↘ ERROR
                                                      ↘ PERIGOSO
```

_Implementações pendentes._

## Contracts (app/Shared/Contracts/)

_Vazio até à primeira issue implementada._

## DTOs (app/Shared/DTOs/)

_Vazio até à primeira issue implementada._

## Enums (app/Shared/Enums/)

### `TipoMovimento` — `App\Shared\Enums\TipoMovimento`

PHP 8.1 backed enum (string). Classifica o tipo de movimento contabilístico de uma categoria de documento.

```php
enum TipoMovimento: string
{
    case Debito  = 'debito';
    case Credito = 'credito';
    case Neutro  = 'neutro';
}
```

- Valores na BD: `'debito'`, `'credito'`, `'neutro'` (lowercase)
- Cases em TitleCase PT per convenção CLAUDE.md
- Usado em: `CategoriaDocumento::$tipo_movimento` (cast Eloquent)

---

`DocumentStatus` — PHP 8.1 backed enum (string). _Pendente._

## Resources (app/Features/<Feature>/

JsonResources vivem dentro da slice, não em `app/Http/Resources/`.

### `CategoriaDocumentoResource` — `App\Features\CategoriaDocumento\CategoriaDocumentoResource`

Formata a resposta JSON de todos os endpoints que retornem uma `CategoriaDocumento`.

```json
{
  "id": "019741b2-...",
  "nome": "Fatura de Fornecedor",
  "slug": "fatura-de-fornecedor",
  "tipo_movimento": "debito"
}
```

- `tipo_movimento` exposto como string via `->value` (nunca o enum em bruto)
- Timestamps omitidos intencionalmente
- PHPDoc `array{id: string, nome: string, slug: string, tipo_movimento: string}` em `toArray()`

---

## FormRequests (app/Features/<Feature>/<Acção>/

FormRequests vivem dentro da slice, co-localizados com a acção correspondente.

### `CriarCategoriaRequest` — `App\Features\CategoriaDocumento\Criar\CriarCategoriaRequest`

| Campo | Regras |
|---|---|
| `nome` | `required`, `string`, `max:255` |
| `slug` | `required`, `string`, `max:255`, `Rule::unique('categorias_documento', 'slug')` |
| `tipo_movimento` | `required`, `string`, `Rule::in(TipoMovimento::cases())` |

### `ActualizarCategoriaRequest` — `App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaRequest`

| Campo | Regras |
|---|---|
| `nome` | `sometimes`, `string`, `max:255` |
| `slug` | `sometimes`, `string`, `max:255`, `Rule::unique(...)->ignore($uuid)` |
| `tipo_movimento` | `sometimes`, `string`, `Rule::in(TipoMovimento::cases())` |

- `$uuid` via `$this->route('categoria')` — exclui o registo actual da validação de unicidade
- Mensagens em português de Portugal via `messages()`; sem entradas `*.required` (campos são `sometimes`)

---

## Exceptions (app/Shared/Exceptions/)

_Vazio até à primeira issue implementada._
