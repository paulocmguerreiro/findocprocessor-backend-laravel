# Spec — Utilizador Feature Slice (CRUD completo)

**Issue:** #68
**Data:** 2026-06-30
**Branch:** feat/utilizador-feature-slice

---

## Estrutura de ficheiros

```
database/migrations/
  YYYY_MM_DD_HHMMSS_add_softdeletes_to_users_table.php
  YYYY_MM_DD_HHMMSS_seed_utilizadores_permissions.php

app/Shared/Cache/
  TagCache.php                           ← adicionar case Utilizadores

app/Models/
  User.php                               ← SoftDeletes + @property-read ?Carbon $deleted_at

app/Policies/
  UtilizadorPolicy.php                   ← adicionar 5 métodos (viewAny, view, create, update, delete)

app/Features/Utilizador/
  UtilizadorResource.php                 ← novo
  UtilizadorController.php               ← adicionar 5 métodos
  Listar/
    CampoOrdenacaoUtilizadores.php       ← novo enum
    ListarUtilizadoresAction.php         ← novo
    ListarUtilizadoresRequest.php        ← novo
  Ver/
    VerUtilizadorAction.php              ← novo
    VerUtilizadorRequest.php             ← novo
  Criar/
    CriarUtilizadorDto.php               ← novo
    CriarUtilizadorAction.php            ← novo
    CriarUtilizadorRequest.php           ← novo
  Actualizar/
    ActualizarUtilizadorDto.php          ← novo
    ActualizarUtilizadorAction.php       ← novo
    ActualizarUtilizadorRequest.php      ← novo
  Eliminar/
    EliminarUtilizadorAction.php         ← novo
    EliminarUtilizadorRequest.php        ← novo

routes/api.php                           ← adicionar apiResource

tests/Feature/Features/Utilizador/
  ListarUtilizadoresTest.php
  VerUtilizadorTest.php
  CriarUtilizadorTest.php
  ActualizarUtilizadorTest.php
  EliminarUtilizadorTest.php

tests/Unit/Features/Utilizador/
  ListarUtilizadoresActionTest.php
  VerUtilizadorActionTest.php
  CriarUtilizadorActionTest.php
  ActualizarUtilizadorActionTest.php
  EliminarUtilizadorActionTest.php
```

---

## Migrations

### add_softdeletes_to_users_table

```php
Schema::table('users', function (Blueprint $table): void {
    $table->softDeletes();
});
```

### seed_utilizadores_permissions

```php
public function up(): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissoes = [
        'utilizadores.ver',
        'utilizadores.criar',
        'utilizadores.actualizar',
        'utilizadores.eliminar',
    ];

    foreach ($permissoes as $nome) {
        Permission::create(['name' => $nome]);
    }

    Role::findByName('admin')->givePermissionTo($permissoes);
    // role 'utilizador' não recebe nenhuma destas permissões
}
```

---

## TagCache

```php
case Utilizadores = 'utilizadores';
```

---

## Model User (actualizações)

Adicionar trait `SoftDeletes` e `@property-read`:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

// @property-read Carbon|null $deleted_at
```

---

## UtilizadorPolicy

```php
final class UtilizadorPolicy
{
    public function atribuirRole(User $utilizador, User $alvo): bool { /* existente */ }

    public function viewAny(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.ver');
    }

    public function view(User $utilizador, User $alvo): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.ver')
            || $utilizador->id === $alvo->id;
    }

    public function create(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.criar');
    }

    public function update(User $utilizador, User $alvo): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.actualizar');
    }

    public function delete(User $utilizador, User $alvo): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.eliminar');
    }
}
```

---

## UtilizadorResource

Campos: `id`, `name`, `email`, `roles` (array de nomes), `deleted_at` (ISO 8601 ou null), `created_at`

```php
final class UtilizadorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'roles'      => $this->roles->pluck('name'),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

---

## CampoOrdenacaoUtilizadores

```php
enum CampoOrdenacaoUtilizadores: string
{
    case Nome       = 'name';
    case Email      = 'email';
    case CriadoEm  = 'created_at';
}
```

---

## Actions — assinaturas

| Action | Assinatura `handle()` | Retorno |
|---|---|---|
| `ListarUtilizadoresAction` | `handle(int $porPagina, CampoOrdenacaoUtilizadores $campo, DirecaoOrdenacao $direcao): CursorPaginator` | `CursorPaginator<int, User>` |
| `VerUtilizadorAction` | `handle(User $utilizador): User` | `User` |
| `CriarUtilizadorAction` | `handle(CriarUtilizadorDto $dados): User` | `User` |
| `ActualizarUtilizadorAction` | `handle(User $utilizador, ActualizarUtilizadorDto $dados): User` | `User` |
| `EliminarUtilizadorAction` | `handle(User $utilizador): void` | `void` |

---

## DTOs

### CriarUtilizadorDto

```php
final readonly class CriarUtilizadorDto
{
    public function __construct(
        public string $nome,
        public string $email,
        public string $password,
        public ?string $role,
    ) {
        if (trim($this->nome) === '') {
            throw new \InvalidArgumentException('nome não pode ser vazio.');
        }
        if (trim($this->email) === '') {
            throw new \InvalidArgumentException('email não pode ser vazio.');
        }
        if (strlen($this->password) < 8) {
            throw new \InvalidArgumentException('password deve ter pelo menos 8 caracteres.');
        }
    }

    public static function fromRequest(CriarUtilizadorRequest $request): self { ... }
}
```

### ActualizarUtilizadorDto

```php
final readonly class ActualizarUtilizadorDto
{
    public function __construct(
        public string $nome,
        public string $email,
        public ?string $password,
    ) {
        if (trim($this->nome) === '') {
            throw new \InvalidArgumentException('nome não pode ser vazio.');
        }
        if (trim($this->email) === '') {
            throw new \InvalidArgumentException('email não pode ser vazio.');
        }
        if ($this->password !== null && strlen($this->password) < 8) {
            throw new \InvalidArgumentException('password deve ter pelo menos 8 caracteres.');
        }
    }

    public static function fromRequest(ActualizarUtilizadorRequest $request): self { ... }
}
```

---

## FormRequests — regras de validação

### ListarUtilizadoresRequest
- `per_page`: sometimes, integer, min:1, max:100
- `sort`: sometimes, string, Rule::in(CampoOrdenacaoUtilizadores)
- `direction`: sometimes, string, Rule::in(DirecaoOrdenacao)
- `cursor`: sometimes, string

### VerUtilizadorRequest
- sem campos de input (route model binding)

### CriarUtilizadorRequest
- `name`: required, string, max:255
- `email`: required, string, email, max:255, Rule::unique('users', 'email')
- `password`: required, `Password::min(8)->letters()->mixedCase()->numbers()->symbols()`, confirmed
- `role`: sometimes, nullable, string, Rule::exists('roles', 'name')

### ActualizarUtilizadorRequest
- `name`: required, string, max:255
- `email`: required, string, email, max:255, Rule::unique('users', 'email')->ignore($this->route('utilizador'))
- `password`: sometimes, nullable, `Password::min(8)->letters()->mixedCase()->numbers()->symbols()`, confirmed

> `Password::min(8)->letters()->mixedCase()->numbers()->symbols()` = `Illuminate\Validation\Rules\Password`.
> Exige: mínimo 8 caracteres, letras maiúsculas e minúsculas, pelo menos um número e um símbolo.
> `confirmed` mantém-se como regra separada (valida `password_confirmation`).

### EliminarUtilizadorRequest
- sem campos de input

---

## EliminarUtilizadorAction — lógica

```
Gate::authorize('delete', $utilizador)          // fora da transação
// Invariante 1: não pode eliminar a si próprio
if (auth()->id() === $utilizador->id) → DomainException
// Invariante 2: não pode eliminar o último com 'utilizadores.eliminar'
if (último utilizador com permissão) → DomainException
DB::transaction():
    $utilizador->tokens()->delete()             // revoga tokens Sanctum
    $utilizador->delete()                       // soft delete
    CacheServico::invalidarCache(TagCache::Utilizadores)
```

Verificação "último com permissão":
```php
$comPermissao = User::permission('utilizadores.eliminar')
    ->whereNot('id', $utilizador->id)
    ->exists();
if (! $comPermissao) {
    throw new \DomainException('Não é possível eliminar o último utilizador com permissão de eliminar.');
}
```

---

## ListarUtilizadoresAction — lógica

```php
Gate::authorize('viewAny', User::class)
// cursor pagination com eager load de roles
User::with('roles')
    ->orderBy($campo->value, $direcao->value)
    ->cursorPaginate($porPagina)
// cache com TagCache::Utilizadores
```

---

## CriarUtilizadorAction — lógica

```
Gate::authorize('create', User::class)          // fora da transação
DB::transaction():
    $utilizador = User::create([nome, email, password])
    if ($dados->role !== null) → $utilizador->assignRole($dados->role)
    CacheServico::invalidarCache(TagCache::Utilizadores)
return $utilizador->load('roles')
```

---

## Controller — mapeamento

| Método | Request | Action | Resposta |
|---|---|---|---|
| `index` | `ListarUtilizadoresRequest` | `ListarUtilizadoresAction` | `ApiResponse::devolverPaginado(UtilizadorResource::collection(...))` |
| `show` | `VerUtilizadorRequest` | `VerUtilizadorAction` | `ApiResponse::devolverSucesso(new UtilizadorResource(...))` |
| `store` | `CriarUtilizadorRequest` | `CriarUtilizadorAction` | `ApiResponse::devolverCriado(new UtilizadorResource(...))` |
| `update` | `ActualizarUtilizadorRequest` | `ActualizarUtilizadorAction` | `ApiResponse::devolverSucesso(new UtilizadorResource(...))` |
| `destroy` | `EliminarUtilizadorRequest` | `EliminarUtilizadorAction` | `ApiResponse::devolverVazio()` |

---

## Rotas

```php
Route::apiResource('utilizadores', UtilizadorController::class);
Route::put('utilizadores/{utilizador}/role', [UtilizadorController::class, 'atribuirRole']);
```

`apiResource` gera: GET /utilizadores, POST /utilizadores, GET /utilizadores/{utilizador}, PUT/PATCH /utilizadores/{utilizador}, DELETE /utilizadores/{utilizador}.

---

## Testes — matriz de cobertura

### Feature (HTTP) — por endpoint

| Endpoint | COM permissão | SEM permissão | Guest |
|---|---|---|---|
| GET /utilizadores | 200 | 403 | 401 |
| GET /utilizadores/{id} (outro) | 200 | 403 | 401 |
| GET /utilizadores/{id} (próprio, sem permissão) | 200 | 200 | 401 |
| POST /utilizadores | 201 | 403 | 401 |
| PUT /utilizadores/{id} | 200 | 403 | 401 |
| DELETE /utilizadores/{id} | 204 | 403 | 401 |

### Unit (Actions directas) — por Action

| Action | Cenários |
|---|---|
| `ListarUtilizadoresAction` | COM permissão → CursorPaginator; SEM permissão → AuthorizationException |
| `VerUtilizadorAction` | COM permissão → User; SEM permissão → AuthorizationException; próprio → User |
| `CriarUtilizadorAction` | COM permissão → User criado; COM role → role atribuído; SEM permissão → AuthorizationException |
| `ActualizarUtilizadorAction` | COM permissão → User actualizado; password opcional → só actualiza se fornecida; SEM permissão → AuthorizationException |
| `EliminarUtilizadorAction` | COM permissão → soft delete + tokens revogados; auto-eliminação → DomainException; último com permissão → DomainException; SEM permissão → AuthorizationException |

---

## Critérios de aceitação (da issue)

- CA-01 a CA-14 conforme issue #68
- Desvio documentado: sem `FiltroEstadoRegisto`, sem `RestaurarAction`, sem `AnonimizarAction` (dívida técnica Padrão B RGPD)
