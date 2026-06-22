# Spec — Issue #50: Role + Utilizador — feature slice

**Issue:** #50
**Slug:** `role-utilizador-feature-slice`
**Branch:** `feat/role-utilizador-feature-slice`

---

## 1. Migration — novas permissions

Ficheiro: `database/migrations/2026_06_22_HHMMSS_seed_roles_permissions_v2.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $novasPermissions = [
            'roles.ver',
            'roles.criar',
            'roles.actualizar',
            'roles.eliminar',
            'utilizadores.atribuir-role',
        ];

        foreach ($novasPermissions as $nome) {
            Permission::create(['name' => $nome]);
        }

        Role::findByName('admin')->givePermissionTo($novasPermissions);
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', [
            'roles.ver',
            'roles.criar',
            'roles.actualizar',
            'roles.eliminar',
            'utilizadores.atribuir-role',
        ])->delete();
    }
};
```

---

## 2. Seeder — actualização

Ficheiro: `database/seeders/RolesPermissionsSeeder.php`

Adicionar as 5 novas permissions à lista passada ao admin no seeder de desenvolvimento:

```php
$todasPermissions = [
    // existentes
    'entidades.ver', 'entidades.criar', 'entidades.actualizar', 'entidades.eliminar',
    'categorias-documento.ver', 'categorias-documento.criar', 'categorias-documento.actualizar', 'categorias-documento.eliminar',
    // novas
    'roles.ver', 'roles.criar', 'roles.actualizar', 'roles.eliminar',
    'utilizadores.atribuir-role',
];
```

---

## 3. AppServiceProvider — registo manual de Policies

Ficheiro: `app/Providers/AppServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\UtilizadorPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(User::class, UtilizadorPolicy::class);
    }
}
```

---

## 4. RolePolicy

Ficheiro: `app/Policies/RolePolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

final class RolePolicy
{
    public function viewAny(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('roles.ver');
    }

    public function view(User $utilizador, Role $role): bool
    {
        return $utilizador->hasPermissionTo('roles.ver');
    }

    public function create(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('roles.criar');
    }

    public function update(User $utilizador, Role $role): bool
    {
        return $utilizador->hasPermissionTo('roles.actualizar');
    }

    public function delete(User $utilizador, Role $role): bool
    {
        return $utilizador->hasPermissionTo('roles.eliminar');
    }
}
```

---

## 5. UtilizadorPolicy

Ficheiro: `app/Policies/UtilizadorPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UtilizadorPolicy
{
    public function atribuirRole(User $utilizador, User $alvo): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.atribuir-role');
    }
}
```

---

## 6. RoleResource

Ficheiro: `app/Features/Role/RoleResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/** @mixin Role */
final class RoleResource extends JsonResource
{
    /**
     * @return array{id: int, nome: string, permissoes: array<int, string>}
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'nome'       => $this->name,
            'permissoes' => $this->permissions->pluck('name')->sort()->values()->all(),
        ];
    }
}
```

---

## 7. Feature Role — Listar

### `CampoOrdenacaoRoles`

Ficheiro: `app/Features/Role/Listar/CampoOrdenacaoRoles.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role\Listar;

enum CampoOrdenacaoRoles: string
{
    case Nome = 'name';
}
```

### `ListarRolesRequest`

Ficheiro: `app/Features/Role/Listar/ListarRolesRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role\Listar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use App\Shared\Enums\DirecaoOrdenacao;

final class ListarRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('viewAny', Role::class);
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'per_page'  => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort'      => ['sometimes', 'string', Rule::in(array_column(CampoOrdenacaoRoles::cases(), 'value'))],
            'direction' => ['sometimes', 'string', Rule::in(array_column(DirecaoOrdenacao::cases(), 'value'))],
            'cursor'    => ['sometimes', 'string'],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'per_page.integer'  => 'O número de registos por página deve ser um número inteiro.',
            'per_page.min'      => 'O número de registos por página deve ser pelo menos 1.',
            'per_page.max'      => 'O número de registos por página não pode ser superior a 100.',
            'sort.in'           => 'O campo de ordenação indicado não é válido.',
            'direction.in'      => 'A direcção de ordenação indicada não é válida.',
        ];
    }
}
```

### `ListarRolesAction`

Ficheiro: `app/Features/Role/Listar/ListarRolesAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role\Listar;

use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class ListarRolesAction
{
    /**
     * @return CursorPaginator<int, Role>
     *
     * @throws AuthorizationException
     */
    public function handle(int $perPage, CampoOrdenacaoRoles $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator
    {
        Gate::authorize('viewAny', Role::class);

        return Role::with('permissions')
            ->orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)
            ->cursorPaginate($perPage);
    }
}
```

---

## 8. Feature Role — Ver

### `VerRoleRequest`

Ficheiro: `app/Features/Role/Ver/VerRoleRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role\Ver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class VerRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('role'));
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
```

### `VerRoleAction`

Ficheiro: `app/Features/Role/Ver/VerRoleAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role\Ver;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class VerRoleAction
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Role $role): Role
    {
        Gate::authorize('view', $role);

        return $role->load('permissions');
    }
}
```

---

## 9. Feature Role — Criar

### `CriarRoleRequest`

Ficheiro: `app/Features/Role/Criar/CriarRoleRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role\Criar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class CriarRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('create', Role::class);
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'nome'          => ['required', 'string', 'max:255', Rule::unique('roles', 'name')],
            'permissoes'    => ['required', 'array'],
            'permissoes.*'  => ['string', Rule::exists(Permission::class, 'name')],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'nome.required'         => 'O nome do role é obrigatório.',
            'nome.unique'           => 'Já existe um role com este nome.',
            'nome.max'              => 'O nome do role não pode ter mais de 255 caracteres.',
            'permissoes.required'   => 'A lista de permissões é obrigatória.',
            'permissoes.array'      => 'As permissões devem ser uma lista.',
            'permissoes.*.exists'   => 'Uma ou mais permissões indicadas não existem.',
        ];
    }
}
```

### `CriarRoleDto`

Ficheiro: `app/Features/Role/Criar/CriarRoleDto.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role\Criar;

final readonly class CriarRoleDto
{
    /**
     * @param array<int, string> $permissoes
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $nome,
        public array $permissoes,
    ) {
        if (trim($this->nome) === '') {
            throw new \InvalidArgumentException('nome não pode ser vazio.');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromRequest(CriarRoleRequest $request): self
    {
        /** @var array{nome: string, permissoes: array<int, string>} $dadosValidados */
        $dadosValidados = $request->validated();

        return new self(
            nome: $dadosValidados['nome'],
            permissoes: $dadosValidados['permissoes'],
        );
    }
}
```

### `CriarRoleAction`

Ficheiro: `app/Features/Role/Criar/CriarRoleAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role\Criar;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class CriarRoleAction
{
    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(CriarRoleDto $dados): Role
    {
        Gate::authorize('create', Role::class);

        return DB::transaction(function () use ($dados): Role {
            $role = Role::create([
                'name'       => $dados->nome,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($dados->permissoes);

            return $role->load('permissions');
        });
    }
}
```

---

## 10. Feature Role — Actualizar

### `ActualizarRoleRequest`

Ficheiro: `app/Features/Role/Actualizar/ActualizarRoleRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role\Actualizar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class ActualizarRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('role'));
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'nome'         => ['sometimes', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissoes'   => ['required', 'array'],
            'permissoes.*' => ['string', Rule::exists(Permission::class, 'name')],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'nome.unique'           => 'Já existe um role com este nome.',
            'nome.max'              => 'O nome do role não pode ter mais de 255 caracteres.',
            'permissoes.required'   => 'A lista de permissões é obrigatória.',
            'permissoes.array'      => 'As permissões devem ser uma lista.',
            'permissoes.*.exists'   => 'Uma ou mais permissões indicadas não existem.',
        ];
    }
}
```

### `ActualizarRoleDto`

Ficheiro: `app/Features/Role/Actualizar/ActualizarRoleDto.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role\Actualizar;

final readonly class ActualizarRoleDto
{
    /**
     * @param array<int, string> $permissoes
     */
    public function __construct(
        public ?string $nome,
        public array $permissoes,
    ) {}

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromRequest(ActualizarRoleRequest $request): self
    {
        /** @var array{nome?: string, permissoes: array<int, string>} $dadosValidados */
        $dadosValidados = $request->validated();

        return new self(
            nome: $dadosValidados['nome'] ?? null,
            permissoes: $dadosValidados['permissoes'],
        );
    }
}
```

### `ActualizarRoleAction`

Ficheiro: `app/Features/Role/Actualizar/ActualizarRoleAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role\Actualizar;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class ActualizarRoleAction
{
    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Role $role, ActualizarRoleDto $dados): Role
    {
        Gate::authorize('update', $role);

        return DB::transaction(function () use ($role, $dados): Role {
            if ($dados->nome !== null) {
                $role->name = $dados->nome;
                $role->save();
            }

            $role->syncPermissions($dados->permissoes);

            return $role->load('permissions');
        });
    }
}
```

---

## 11. Feature Role — Eliminar

### `EliminarRoleRequest`

Ficheiro: `app/Features/Role/Eliminar/EliminarRoleRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role\Eliminar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class EliminarRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('delete', $this->route('role'));
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
```

### `EliminarRoleAction`

Ficheiro: `app/Features/Role/Eliminar/EliminarRoleAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role\Eliminar;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class EliminarRoleAction
{
    private const array ROLES_SISTEMA = ['admin', 'utilizador'];

    /**
     * @throws AuthorizationException
     * @throws \DomainException
     * @throws \Throwable
     */
    public function handle(Role $role): void
    {
        Gate::authorize('delete', $role);

        if (in_array($role->name, self::ROLES_SISTEMA, true)) {
            throw new \DomainException('Não é possível eliminar um role de sistema.');
        }

        DB::transaction(fn (): bool => $role->delete());
    }
}
```

---

## 12. Feature Utilizador — AtribuirRole

### `AtribuirRoleRequest`

Ficheiro: `app/Features/Utilizador/AtribuirRole/AtribuirRoleRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Utilizador\AtribuirRole;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

final class AtribuirRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('atribuirRole', $this->route('utilizador'));
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::exists(Role::class, 'name')],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'role.required' => 'O nome do role é obrigatório.',
            'role.exists'   => 'O role indicado não existe.',
        ];
    }
}
```

### `AtribuirRoleAction`

Ficheiro: `app/Features/Utilizador/AtribuirRole/AtribuirRoleAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Utilizador\AtribuirRole;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AtribuirRoleAction
{
    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(User $utilizador, string $nomeRole): User
    {
        Gate::authorize('atribuirRole', $utilizador);

        DB::transaction(function () use ($utilizador, $nomeRole): void {
            $utilizador->syncRoles([$nomeRole]);
        });

        return $utilizador->load('roles');
    }
}
```

---

## 13. RoleController

Ficheiro: `app/Features/Role/RoleController.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Role;

use App\Features\Role\Actualizar\ActualizarRoleAction;
use App\Features\Role\Actualizar\ActualizarRoleDto;
use App\Features\Role\Actualizar\ActualizarRoleRequest;
use App\Features\Role\Criar\CriarRoleAction;
use App\Features\Role\Criar\CriarRoleDto;
use App\Features\Role\Criar\CriarRoleRequest;
use App\Features\Role\Eliminar\EliminarRoleAction;
use App\Features\Role\Eliminar\EliminarRoleRequest;
use App\Features\Role\Listar\CampoOrdenacaoRoles;
use App\Features\Role\Listar\ListarRolesAction;
use App\Features\Role\Listar\ListarRolesRequest;
use App\Features\Role\Ver\VerRoleAction;
use App\Features\Role\Ver\VerRoleRequest;
use App\Http\Controllers\Controller;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

final class RoleController extends Controller
{
    public function index(ListarRolesRequest $pedido, ListarRolesAction $accao): JsonResponse
    {
        /** @var array{per_page?: string, sort?: string, direction?: string} $parametrosValidados */
        $parametrosValidados = $pedido->validated();

        $porPagina = isset($parametrosValidados['per_page']) ? (int) $parametrosValidados['per_page'] : 15;
        $campoOrdenacao = CampoOrdenacaoRoles::from($parametrosValidados['sort'] ?? CampoOrdenacaoRoles::Nome->value);
        $direcaoOrdenacao = DirecaoOrdenacao::from($parametrosValidados['direction'] ?? DirecaoOrdenacao::Asc->value);

        return ApiResponse::devolverPaginado(
            RoleResource::collection($accao->handle($porPagina, $campoOrdenacao, $direcaoOrdenacao)),
        );
    }

    public function store(CriarRoleRequest $pedido, CriarRoleAction $accao): JsonResponse
    {
        $role = $accao->handle(CriarRoleDto::fromRequest($pedido));

        return ApiResponse::devolverCriado(new RoleResource($role));
    }

    public function show(VerRoleRequest $pedido, Role $role, VerRoleAction $accao): JsonResponse
    {
        return ApiResponse::devolverSucesso(new RoleResource($accao->handle($role)));
    }

    public function update(ActualizarRoleRequest $pedido, Role $role, ActualizarRoleAction $accao): JsonResponse
    {
        $role = $accao->handle($role, ActualizarRoleDto::fromRequest($pedido));

        return ApiResponse::devolverSucesso(new RoleResource($role));
    }

    public function destroy(EliminarRoleRequest $pedido, Role $role, EliminarRoleAction $accao): JsonResponse
    {
        $accao->handle($role);

        return ApiResponse::devolverVazio();
    }
}
```

---

## 14. UtilizadorController

Ficheiro: `app/Features/Utilizador/UtilizadorController.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Utilizador;

use App\Features\Utilizador\AtribuirRole\AtribuirRoleAction;
use App\Features\Utilizador\AtribuirRole\AtribuirRoleRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UtilizadorController extends Controller
{
    public function atribuirRole(AtribuirRoleRequest $pedido, User $utilizador, AtribuirRoleAction $accao): JsonResponse
    {
        /** @var array{role: string} $dadosValidados */
        $dadosValidados = $pedido->validated();

        $accao->handle($utilizador, $dadosValidados['role']);

        return ApiResponse::devolverVazio();
    }
}
```

---

## 15. Rotas

Ficheiro: `routes/api.php` — adicionar dentro do grupo `auth:sanctum`:

```php
use App\Features\Role\RoleController;
use App\Features\Utilizador\UtilizadorController;

// dentro do grupo middleware('auth:sanctum'):
Route::apiResource('roles', RoleController::class);
Route::put('utilizadores/{utilizador}/role', [UtilizadorController::class, 'atribuirRole']);
```

---

## 16. Testes

### Convenções comuns a todos os ficheiros de teste

```php
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});
```

`RefreshDatabase` já está activo via `Pest.php`. Os helpers `criarEAutenticarAdmin()` e `criarEAutenticarUtilizador()` já existem em `tests/Pest.php`.

---

### 16.1 Testes Feature — Listar

Ficheiro: `tests/Feature/Features/Role/ListarRolesTest.php`

Cenários:
- `[200]` admin autentica → lista os roles com permissões
- `[401]` sem autenticação → 401
- `[403]` utilizador sem `roles.ver` → 403

---

### 16.2 Testes Feature — Ver

Ficheiro: `tests/Feature/Features/Role/VerRoleTest.php`

Cenários:
- `[200]` admin vê role existente → retorna id, nome, permissoes
- `[401]` sem autenticação → 401
- `[403]` utilizador sem permissão → 403
- `[404]` role inexistente → 404

---

### 16.3 Testes Feature — Criar

Ficheiro: `tests/Feature/Features/Role/CriarRoleTest.php`

Cenários:
- `[201]` admin cria role com permissões válidas → 201 + role na BD
- `[422]` nome duplicado → 422
- `[422]` permissão inexistente → 422
- `[422]` nome em falta → 422
- `[401]` sem autenticação → 401
- `[403]` utilizador sem `roles.criar` → 403

---

### 16.4 Testes Feature — Actualizar

Ficheiro: `tests/Feature/Features/Role/ActualizarRoleTest.php`

Cenários:
- `[200]` admin actualiza permissões → sync completo (permissões anteriores removidas)
- `[200]` admin actualiza nome + permissões
- `[200]` sem campo `nome` → nome não alterado
- `[422]` permissão inexistente → 422
- `[401]` sem autenticação → 401
- `[403]` utilizador sem `roles.actualizar` → 403
- `[404]` role inexistente → 404

---

### 16.5 Testes Feature — Eliminar

Ficheiro: `tests/Feature/Features/Role/EliminarRoleTest.php`

Cenários:
- `[204]` admin elimina role personalizado → removido da BD
- `[422/400]` tentar eliminar role `admin` → excepção de domínio → 500 (ou handler converte)
- `[401]` sem autenticação → 401
- `[403]` utilizador sem `roles.eliminar` → 403
- `[404]` role inexistente → 404

> **Nota:** `\DomainException` não mapeada no exception handler actual — verificar se o handler converte ou retorna 500. Se necessário, mapear para 422 no `bootstrap/app.php`.

---

### 16.6 Testes Feature — AtribuirRole

Ficheiro: `tests/Feature/Features/Utilizador/AtribuirRoleTest.php`

Cenários:
- `[204]` admin atribui role a utilizador → role alterado na BD
- `[422]` role inexistente → 422
- `[422]` campo role em falta → 422
- `[401]` sem autenticação → 401
- `[403]` utilizador sem `utilizadores.atribuir-role` → 403
- `[404]` utilizador inexistente → 404

---

### 16.7 Testes Unit — CriarRoleAction

Ficheiro: `tests/Unit/Features/Role/CriarRoleActionTest.php`

Cenários:
- Cria role com permissões e retorna Role com `permissions` carregadas
- Lança `AuthorizationException` se utilizador sem permissão `roles.criar`

---

### 16.8 Testes Unit — ActualizarRoleAction

Ficheiro: `tests/Unit/Features/Role/ActualizarRoleActionTest.php`

Cenários:
- Sync de permissões substitui todas (não faz merge)
- Nome só actualizado quando `$dados->nome !== null`
- Lança `AuthorizationException` se sem permissão `roles.actualizar`

---

### 16.9 Testes Unit — EliminarRoleAction

Ficheiro: `tests/Unit/Features/Role/EliminarRoleActionTest.php`

Cenários:
- Elimina role personalizado
- Lança `\DomainException` para `admin`
- Lança `\DomainException` para `utilizador`
- Lança `AuthorizationException` se sem permissão `roles.eliminar`

---

### 16.10 Testes Unit — AtribuirRoleAction

Ficheiro: `tests/Unit/Features/Utilizador/AtribuirRoleActionTest.php`

Cenários:
- `syncRoles()` substitui role anterior
- Retorna utilizador com `roles` carregadas
- Lança `AuthorizationException` se sem permissão `utilizadores.atribuir-role`

---

## 17. Questão pendente — DomainException no exception handler

O `bootstrap/app.php` actual não mapeia `\DomainException`. A tentativa de eliminar um role de sistema vai retornar 500 em vez de uma resposta semântica. **Adicionar handler** no `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions): void {
    // existentes...
    $exceptions->render(function (\DomainException $e, Request $request): JsonResponse {
        return response()->json(['message' => $e->getMessage()], 422);
    });
})
```

Isto mantém a consistência com os outros handlers de erro do projecto.
