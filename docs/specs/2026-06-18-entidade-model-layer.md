# Spec — Issue #27: Entidade — model layer

**Data:** 2026-06-18
**Slug:** `entidade-model-layer`
**Branch:** `feat/entidade-model-layer`

---

## Migration — `create_entidades_table`

```php
Schema::create('entidades', function (Blueprint $table) {
    $table->uuid('id')->primary()->comment('Identificador unico UUID v7');
    $table->string('nome', 255)->index()->comment('Nome da entidade');
    $table->string('nif', 255)->index()->comment('Numero de Identificacao Fiscal');
    $table->boolean('e_cliente')->default(false)->index()->comment('Verdadeiro se a entidade e cliente');
    $table->boolean('e_fornecedor')->default(false)->index()->comment('Verdadeiro se a entidade e fornecedor');
    $table->boolean('e_empresa_aplicacao')->default(false)->index()->comment('Verdadeiro se e a empresa mae da aplicacao');
    $table->timestamps();
});

// Indice parcial unico MySQL — garante unicidade de e_empresa_aplicacao = true em producao
if (DB::getDriverName() === 'mysql') {
    DB::statement(
        'CREATE UNIQUE INDEX unica_empresa_mae_idx ON entidades (e_empresa_aplicacao) WHERE (e_empresa_aplicacao = 1)'
    );
}
```

`down()`:
```php
if (DB::getDriverName() === 'mysql') {
    DB::statement('DROP INDEX IF EXISTS unica_empresa_mae_idx ON entidades');
}
Schema::dropIfExists('entidades');
```

---

## Model — `app/Models/Entidade.php`

```php
declare(strict_types=1);

namespace App\Models;

use App\Policies\EntidadePolicy;
use Database\Factories\EntidadeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property-read string $id
 * @property-read string $nome
 * @property-read string $nif
 * @property-read bool   $e_cliente
 * @property-read bool   $e_fornecedor
 * @property-read bool   $e_empresa_aplicacao
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[Table('entidades')]
#[Fillable(['nome', 'nif', 'e_cliente', 'e_fornecedor', 'e_empresa_aplicacao'])]
#[UsePolicy(EntidadePolicy::class)]
class Entidade extends Model
{
    /** @use HasFactory<EntidadeFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'e_cliente'           => 'boolean',
            'e_fornecedor'        => 'boolean',
            'e_empresa_aplicacao' => 'boolean',
        ];
    }

    public function scopeWhereCliente($query): void
    {
        $query->where('e_cliente', true);
    }

    public function scopeWhereFornecedor($query): void
    {
        $query->where('e_fornecedor', true);
    }

    public function scopeWhereEmpresaAplicacao($query): void
    {
        $query->where('e_empresa_aplicacao', true);
    }
}
```

---

## Factory — `database/factories/EntidadeFactory.php`

```php
class EntidadeFactory extends Factory
{
    protected $model = Entidade::class;

    public function definition(): array
    {
        return [
            'nome'                => $this->faker->company(),
            'nif'                 => $this->faker->numerify('#########'),
            'e_cliente'           => false,
            'e_fornecedor'        => false,
            'e_empresa_aplicacao' => false,
        ];
    }

    public function cliente(): static
    {
        return $this->state(['e_cliente' => true, 'e_fornecedor' => false, 'e_empresa_aplicacao' => false]);
    }

    public function fornecedor(): static
    {
        return $this->state(['e_cliente' => false, 'e_fornecedor' => true, 'e_empresa_aplicacao' => false]);
    }

    public function clienteEFornecedor(): static
    {
        return $this->state(['e_cliente' => true, 'e_fornecedor' => true, 'e_empresa_aplicacao' => false]);
    }

    public function empresaAplicacao(): static
    {
        // empresa mãe é obrigatoriamente cliente e fornecedor: emite documentos (fornecedor) e recebe-os (cliente)
        return $this->state(['e_cliente' => true, 'e_fornecedor' => true, 'e_empresa_aplicacao' => true]);
    }
}
```

---

## Policy — `app/Policies/EntidadePolicy.php`

```php
final class EntidadePolicy
{
    public function viewAny(?User $utilizador): bool   { return true; }
    public function view(?User $utilizador, Entidade $entidade): bool   { return true; }
    public function create(?User $utilizador): bool    { return true; }
    public function update(?User $utilizador, Entidade $entidade): bool { return true; }
    public function delete(?User $utilizador, Entidade $entidade): bool { return true; }
}
```

---

## Testes

### `tests/Feature/Models/EntidadeTest.php`

| Teste | Comportamento |
|---|---|
| casts boolean nas tres flags | `e_cliente`, `e_fornecedor`, `e_empresa_aplicacao` sao `bool` depois de criar |
| fillable contem os campos esperados | `getFillable()` lista os 5 campos |
| scope whereCliente retorna so clientes | cria cliente + fornecedor; scope retorna apenas cliente |
| scope whereFornecedor retorna so fornecedores | cria fornecedor + cliente; scope retorna apenas fornecedor |
| scope whereEmpresaAplicacao retorna so empresa mae | cria empresaAplicacao + cliente; scope retorna apenas empresa |
| scope whereCliente exclui nao-clientes | entidade `fornecedor` nao aparece em `whereCliente()` |

### `tests/Feature/Policies/EntidadePolicyTest.php`

| Teste | Comportamento |
|---|---|
| utilizador autenticado pode viewAny | `assertTrue` |
| utilizador autenticado pode view | `assertTrue` |
| utilizador autenticado pode create | `assertTrue` |
| utilizador autenticado pode update | `assertTrue` |
| utilizador autenticado pode delete | `assertTrue` |
| guest nao pode viewAny | `assertFalse` (sem utilizador) |
| guest nao pode view | `assertFalse` |
| guest nao pode create | `assertFalse` |
| guest nao pode update | `assertFalse` |
| guest nao pode delete | `assertFalse` |

> **Nota:** A policy retorna `true` para qualquer utilizador autenticado. O teste "guest nao pode" verifica que `Gate::forUser(null)->allows(...)` retorna `false` — o Laravel bloqueia guests por default mesmo com `?User`.

---

## Criterios de aceitacao (checklist)

- [ ] CA-01: Migration cria tabela `entidades` com todos os campos, defaults e indices individuais
- [ ] CA-02: Migration cria `unica_empresa_mae_idx` em MySQL; `down()` remove condicionalmente
- [ ] CA-03: Model usa `HasUuids`, casts boolean nas 3 flags, `@property-read` completo
- [ ] CA-04: Scopes `whereCliente`, `whereFornecedor`, `whereEmpresaAplicacao` filtram correctamente
- [ ] CA-05: Factory com 4 states: `cliente`, `fornecedor`, `clienteEFornecedor`, `empresaAplicacao`
- [ ] CA-06: `EntidadePolicy` cobre `viewAny`, `view`, `create`, `update`, `delete`
- [ ] CA-07: Testes Policy — permitido + negado por metodo
- [ ] CA-08: Testes Model — scopes e casts
- [ ] CA-09: `strict_types=1` em todos os ficheiros
- [ ] CA-10: `composer test` verde (100% coverage + types)
