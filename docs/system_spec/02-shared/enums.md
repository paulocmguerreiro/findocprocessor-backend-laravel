# System Spec — Shared: Enums

> `app/Shared/Enums/`

Enums partilhados entre features. Todos PHP 8.1 backed enums (string). Cases em TitleCase PT per convenção CLAUDE.md.

---

## `TipoMovimento` — `App\Shared\Enums\TipoMovimento`

Classifica o tipo de movimento contabilístico de uma categoria de documento.

```php
enum TipoMovimento: string
{
    case Debito  = 'debito';
    case Credito = 'credito';
    case Neutro  = 'neutro';
}
```

- Valores na BD: `'debito'`, `'credito'`, `'neutro'` (lowercase)
- Usado em: `CategoriaDocumento::$tipo_movimento` (cast Eloquent)

---

## `DirecaoOrdenacao` — `App\Shared\Enums\DirecaoOrdenacao`

Direcção de ordenação genérica — reutilizável em todas as listagens do sistema.

```php
enum DirecaoOrdenacao: string
{
    case Asc  = 'asc';
    case Desc = 'desc';
}
```

- Valores na query string: `'asc'`, `'desc'`
- Usado em: `ListarCategoriasAction::handle()`, `ListarEntidadesAction::handle()`

---

## `DocumentStatus` — `App\Shared\Enums\DocumentStatus`

_Pendente — implementado com a feature Document._

PHP 8.1 backed enum (string). Ciclo de estados do documento:
```
PENDING → AGUARDA_ENVIO → ENVIADO → AGUARDA_RESPOSTA → DONE
                                                      ↘ ERROR
                                                      ↘ PERIGOSO
```
