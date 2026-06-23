# System Spec — Feature: Role

> `App\Features\Role\`
> Issue #50

CRUD completo de roles (gestão de roles do sistema via Spatie Permission). Permite listar, ver, criar, actualizar e eliminar roles, com protecção de roles de sistema.

**Fluxo de dados:**
```
HTTP Request → FormRequest (autoriza + valida) → Controller (constrói DTO ou passa Role via RMB) → Action (autoriza + persiste) → Controller (formata com RoleResource) → ApiResponse
```

**Decisão arquitectural:** Sem Repository — Eloquent directo nas Actions (CRUD simples). Roles de sistema (`admin`, `utilizador`) são protegidos por `DomainException` na `EliminarRoleAction`.

**Autorização:** dupla verificação — FormRequest + Action. Policy: `RolePolicy` registada via `Gate::policy(Role::class, RolePolicy::class)` no `AppServiceProvider` (Spatie Role não é modelo da aplicação — não suporta `#[UsePolicy]`).

---

## Actions

| Classe | Namespace | Assinatura `handle()` | Descrição |
|---|---|---|---|
| `ListarRolesAction` | `App\Features\Role\Listar` | `handle(int $perPage, CampoOrdenacaoRoles $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator<int, Role>` | Devolve página via cursor pagination com permissions eager-loaded |
| `VerRoleAction` | `App\Features\Role\Ver` | `handle(Role): Role` | Devolve role com permissions carregadas via `load('permissions')` |
| `CriarRoleAction` | `App\Features\Role\Criar` | `handle(CriarRoleDto): Role` | Cria role + syncPermissions; devolve com permissions carregadas |
| `ActualizarRoleAction` | `App\Features\Role\Actualizar` | `handle(Role, ActualizarRoleDto): Role` | Actualiza nome (se presente) + syncPermissions (substitui todas) |
| `EliminarRoleAction` | `App\Features\Role\Eliminar` | `handle(Role): void` | Elimina role; lança `DomainException` para roles de sistema |

---

## Enum de ordenação

`CampoOrdenacaoRoles` (`App\Features\Role\Listar`) — enum backed string:

| Case | Value | Descrição |
|---|---|---|
| `Nome` | `'name'` | Ordenar por nome do role |

---

## DTOs

### `CriarRoleDto` — `App\Features\Role\Criar\CriarRoleDto`

`final readonly`. Construtor valida que `nome` não é vazio (`\InvalidArgumentException`).

```php
final readonly class CriarRoleDto
{
    /** @param array<int, string> $permissoes */
    public function __construct(
        public string $nome,
        public array $permissoes,
    ) { /* valida nome não-vazio */ }

    public static function fromRequest(CriarRoleRequest $request): self { ... }
}
```

### `ActualizarRoleDto` — `App\Features\Role\Actualizar\ActualizarRoleDto`

`final readonly`. `nome` é `?string` — se `null`, o nome não é alterado.

```php
final readonly class ActualizarRoleDto
{
    /** @param array<int, string> $permissoes */
    public function __construct(
        public ?string $nome,
        public array $permissoes,
    ) {}

    public static function fromRequest(ActualizarRoleRequest $request): self { ... }
}
```

---

## Resource

`RoleResource` (`App\Features\Role\`) — serializa `id`, `nome`, `permissoes` (ordenadas alfabeticamente).

```php
/** @return array{id: int|string, nome: string, permissoes: array<int, mixed>} */
public function toArray(Request $request): array
```

> Nota: `id` é `int|string` (tipo inferido do modelo Spatie); `permissoes` é `array<int, mixed>` (resultado de `pluck()->sort()->values()->all()`).

---

## Roles de sistema protegidos

`EliminarRoleAction::ROLES_SISTEMA = ['admin', 'utilizador']` — constante privada. Lança `\DomainException('Não é possível eliminar um role de sistema.')` → handler converte para 422.
