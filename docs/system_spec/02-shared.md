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

## Exceptions (app/Shared/Exceptions/)

_Vazio até à primeira issue implementada._
