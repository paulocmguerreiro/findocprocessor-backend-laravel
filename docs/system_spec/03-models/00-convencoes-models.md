# System Spec — Models: Convenções Canónicas

> Padrão obrigatório para todos os Eloquent Models em `app/Models/`. Cada model específico documenta-se em `03-models/<slug>.md`.

---

## Padrão canónico

```php
declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use App\Shared\Enums\TipoMovimento;
use App\Policies\CategoriaDocumentoPolicy;
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
#[UsePolicy(CategoriaDocumentoPolicy::class)]
class CategoriaDocumento extends Model
{
    use HasFactory;
    use HasUuids;

    use RegistaActividade;

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

| Regra                | Detalhe                                                                     |
| -------------------- | --------------------------------------------------------------------------- |
| `HasUuids` como PK   | UUIDv7 — nunca ID autoincrement                                             |
| `#[Table('...')]`    | Nome explícito da tabela (atributo, não propriedade `$table`)               |
| `#[Fillable([...])]` | Atributo, não propriedade `$fillable`                                       |
| `#[Hidden([...])]`   | Para campos sensíveis (atributo, não `$hidden`)                             |
| `casts()`            | Método — enums e tipos especiais; nunca propriedade `$casts`                |
| `@property-read`     | **Obrigatório** para todas as colunas — tipagem completa para Larastan e IA |
| `HasFactory`         | Sempre que existe factory                                                   |
| `#[UsePolicy(...)]`  | Liga explicitamente a Policy ao Model (preferido à descoberta por convenção de nome) |

> **Models do domínio não são `final`** — o ArchTest "actions are final" não cobre Models, e o Eloquent precisa de estender `Model` sem restringir a subclasse. Coerente entre `Documento`, `Entidade`, `CategoriaDocumento`.

---

## Estrutura canónica de um doc de Model

Cada `03-models/<slug>.md` segue esta ordem de secções (modelo de referência: `documento.md`). Models simples omitem as secções não aplicáveis, mas mantêm a ordem e os títulos:

| Secção | Conteúdo | Obrigatória |
|---|---|---|
| `## Tabela <nome>` (ou `## Colunas`) | Tabela Coluna / Tipo BD / Nullable / Notas | Sim |
| `## Model <Nome>` / `## Traits e atributos` | Atributos `#[Table]`/`#[Fillable]`/`#[Hidden]`/**`#[UsePolicy]`**, traits, `@property-read`, `casts()` | Sim |
| `### Relações` | `belongsTo`/`hasMany`/… com FK | Se existirem |
| `### Scopes` | Query scopes | Se existirem |
| `## Factory` / `## Factory states` | States da factory | Se existir factory |
| `## Policy` | Policy associada (ou referência a `04-infra/autorizacao.md`) | Se o Model tem Policy |
| `## Notas arquitecturais` | Decisões/excepções (ex.: PK inteira do `User`) | Opcional |

> **`#[UsePolicy]` é sempre documentado** na secção de atributos quando o Model o tem. Models de terceiros (ex.: `Spatie\…\Role`) registam a Policy via `Gate::policy()` no `AppServiceProvider` — documentar na secção `## Policy`.

---

## Chaves estrangeiras

- Colunas FK seguem o padrão `id_<entidade>` (ex: `id_categoria`, `id_documento`).
- Tipo `uuid` (correspondem a PKs UUID).

---

## Dimensões e constraints

- Dimensões de strings declaradas na migration (`string(255)`).

---

## Enums

- Cast de coluna enum via `casts()` → `'coluna' => MeuEnum::class`.
- Enums partilhados vivem em `app/Shared/Enums/` (ver `02-shared/enums.md`).

---

## SoftDelete — quando usar e padrão obrigatório

SoftDelete **só se aplica a tabelas pai/transversais** — tabelas referenciadas por FK
de outras tabelas. Nunca aplicar a tabelas folha.

| Tabela | SoftDelete | Motivo |
|---|---|---|
| `entidades` | ✅ | Referenciada por `documentos.id_fornecedor` / `id_cliente` |
| `categorias_documento` | ✅ | Referenciada por `documentos.id_categoria` |
| `users` | ✅ | Referenciada por `documentos.id_responsavel` / `etapas_documento.id_utilizador` |
| `documentos` | ❌ | Pai de `etapas_documento`, mas eliminação de documentos é hard (eliminação de negócio explícita) |
| `etapas_documento` | ❌ | Tabela folha (histórico append-only) |

**Padrão de implementação obrigatório:** Padrão B — ver `02-shared/soft-delete.md`.

Quando um model tem `SoftDeletes`:
- Usar trait `SoftDeletes` + `@property-read ?Carbon $deleted_at`
- `EliminarAction` tenta `forceDelete()` com fallback para `delete()` soft
- `RestaurarAction` exposta via `PATCH /<recurso>/{id}/restaurar`
- `ListarAction` aceita `FiltroEstadoRegisto` (todos/somente_ativos/somente_inativos)
- Relações nas tabelas filhas usam `->withTrashed()`
- FKs das tabelas filhas: `restrictOnDelete` (nunca `nullOnDelete`)
