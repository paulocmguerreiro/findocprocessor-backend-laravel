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

PHP 8.1 backed enum (string). Representa o estado de processamento de um documento. Cases em TitleCase PT; values em UPPER_SNAKE (alinhados com o ciclo de estados).

```php
enum DocumentStatus: string
{
    case Pending         = 'PENDING';
    case AguardaEnvio    = 'AGUARDA_ENVIO';
    case Enviado         = 'ENVIADO';
    case AguardaResposta = 'AGUARDA_RESPOSTA';
    case Done            = 'DONE';
    case Error           = 'ERROR';
    case Perigoso        = 'PERIGOSO';
}
```

Ciclo de estados (transições permitidas):
```
PENDING → AGUARDA_ENVIO → ENVIADO → AGUARDA_RESPOSTA → DONE
                                                      ↘ ERROR
                                                      ↘ PERIGOSO
```

- Valores na BD: `'PENDING'`, `'AGUARDA_ENVIO'`, `'ENVIADO'`, `'AGUARDA_RESPOSTA'`, `'DONE'`, `'ERROR'`, `'PERIGOSO'`
- Detalhe das transições e semântica de cada estado em `02-shared/estados.md`
- Usado em: `Document::$status` (cast Eloquent) — feature pendente
