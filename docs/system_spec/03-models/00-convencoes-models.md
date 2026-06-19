# System Spec — Models: Convenções Canónicas

> Padrão obrigatório para todos os Eloquent Models em `app/Models/`. Cada model específico documenta-se em `03-models/<slug>.md`.

---

## Padrão canónico

```php
declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\TipoMovimento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $id
 * @property-read string $nome
 * @property-read string $slug
 * @property-read TipoMovimento $tipo_movimento
 * @property-read \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Support\Carbon $updated_at
 */
#[Table('categorias_documento')]
#[Fillable(['nome', 'slug', 'tipo_movimento'])]
final class CategoriaDocumento extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['tipo_movimento' => TipoMovimento::class];
    }
}
```

---

## Regras obrigatórias

| Regra | Detalhe |
|---|---|
| `HasUuids` como PK | UUIDv7 — nunca ID autoincrement |
| `#[Table('...')]` | Nome explícito da tabela (atributo, não propriedade `$table`) |
| `#[Fillable([...])]` | Atributo, não propriedade `$fillable` |
| `#[Hidden([...])]` | Para campos sensíveis (atributo, não `$hidden`) |
| `casts()` | Método — enums e tipos especiais; nunca propriedade `$casts` |
| `@property-read` | **Obrigatório** para todas as colunas — tipagem completa para Larastan e IA |
| `HasFactory` | Sempre que existe factory |
| `#[UsePolicy(...)]` | Quando a Policy não é descoberta por convenção de nome |

---

## Chaves estrangeiras

- Colunas FK seguem o padrão `id_<entidade>` (ex: `id_categoria`, `id_documento`).
- Tipo `uuid` (correspondem a PKs UUID).

---

## Dimensões e constraints

- Dimensões de strings declaradas na migration (`string(255)`).
- SQLite (testes) **não** suporta CHECK constraints nem índices parciais condicionais — validação correspondente fica no PHP (DTO/Action) e índices parciais são criados condicionalmente apenas em MySQL.

---

## Enums

- Cast de coluna enum via `casts()` → `'coluna' => MeuEnum::class`.
- Enums partilhados vivem em `app/Shared/Enums/` (ver `02-shared/enums.md`).
