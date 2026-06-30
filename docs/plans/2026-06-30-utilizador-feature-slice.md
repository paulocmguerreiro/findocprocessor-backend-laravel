# Plano — Utilizador Feature Slice (CRUD completo)

**Issue:** #68
**Data:** 2026-06-30
**Branch:** feat/utilizador-feature-slice
**Spec:** `docs/specs/2026-06-30-utilizador-feature-slice.md`

---

## Modo SDD — Checkpoints obrigatórios

- **A** — Brief aprovado ✅
- **B** — Spec aprovada ✅
- **Por tarefa** — após cada tarefa: `composer lint && composer refactor`
- **②** — Após T8 (antes dos testes): `composer test:types && composer test:arch`
- **D** — Após testes: `composer test` completo
- **E** — Revisão final antes de commitar

---

## Tarefas

### T1 — Migration: `add_softdeletes_to_users_table`

Criar migration que adiciona `deleted_at` à tabela `users`.

```bash
php artisan make:migration add_softdeletes_to_users_table --no-interaction
```

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table): void {
        $table->softDeletes();
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table): void {
        $table->dropSoftDeletes();
    });
}
```

Executar `php artisan migrate` após criar.

---

### T2 — Model User: SoftDeletes + `@property-read`

Adicionar em `app/Models/User.php`:
- `use SoftDeletes;` na lista de traits
- `@property-read Carbon|null $deleted_at` no bloco PHPDoc
- Import `Illuminate\Database\Eloquent\SoftDeletes`

---

### T3 — Migration: `seed_utilizadores_permissions`

```bash
php artisan make:migration seed_utilizadores_permissions --no-interaction
```

Padrão do `04-infra/autorizacao.md`:
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
}
```

Executar `php artisan migrate` após criar.

---

### T4 — TagCache: adicionar `Utilizadores`

Em `app/Shared/Cache/TagCache.php`, adicionar:
```php
case Utilizadores = 'utilizadores';
```

---

### T5 — UtilizadorPolicy: 5 métodos novos

Adicionar a `app/Policies/UtilizadorPolicy.php`:
- `viewAny` — `hasPermissionTo('utilizadores.ver')`
- `view` — `hasPermissionTo('utilizadores.ver') || $utilizador->id === $alvo->id`
- `create` — `hasPermissionTo('utilizadores.criar')`
- `update` — `hasPermissionTo('utilizadores.actualizar')`
- `delete` — `hasPermissionTo('utilizadores.eliminar')`

---

### T6 — UtilizadorResource

Criar `app/Features/Utilizador/UtilizadorResource.php`.

Campos: `id`, `name`, `email`, `roles` (pluck 'name'), `deleted_at` (ISO 8601 ou null), `created_at` (ISO 8601).

---

### T7 — Enum `CampoOrdenacaoUtilizadores`

Criar `app/Features/Utilizador/Listar/CampoOrdenacaoUtilizadores.php`:
```php
enum CampoOrdenacaoUtilizadores: string
{
    case Nome      = 'name';
    case Email     = 'email';
    case CriadoEm = 'created_at';
}
```

---

### T8 — ListarUtilizadoresAction + ListarUtilizadoresRequest

**Request** (`app/Features/Utilizador/Listar/ListarUtilizadoresRequest.php`):
- `authorize()`: `Gate::authorize('viewAny', User::class)`
- `rules()`: `per_page`, `sort`, `direction`, `cursor`
- `messages()`: PT

**Action** (`app/Features/Utilizador/Listar/ListarUtilizadoresAction.php`):
- Injeta `CacheServico`
- `Gate::authorize('viewAny', User::class)`
- `User::with('roles')->orderBy(...)->cursorPaginate($porPagina)` dentro de cache
- Tag: `TagCache::Utilizadores`

---

### T9 — VerUtilizadorAction + VerUtilizadorRequest

**Request** (`app/Features/Utilizador/Ver/VerUtilizadorRequest.php`):
- `authorize()`: `Gate::authorize('view', $this->route('utilizador'))`

**Action** (`app/Features/Utilizador/Ver/VerUtilizadorAction.php`):
- `Gate::authorize('view', $utilizador)`
- `return $utilizador->load('roles')`
- Sem cache (leitura individual — baixo hit-rate)

---

### T10 — CriarUtilizadorDto + CriarUtilizadorAction + CriarUtilizadorRequest

**DTO** (`app/Features/Utilizador/Criar/CriarUtilizadorDto.php`):
- Campos: `nome`, `email`, `password`, `?role`
- Invariantes no construtor
- `fromRequest(CriarUtilizadorRequest $request): self`

**Request** (`app/Features/Utilizador/Criar/CriarUtilizadorRequest.php`):
- `authorize()`: `Gate::authorize('create', User::class)`
- `rules()`: ver spec — usar `Password::min(8)` + `confirmed`
- `messages()`: PT

**Action** (`app/Features/Utilizador/Criar/CriarUtilizadorAction.php`):
- Injeta `CacheServico`
- `Gate::authorize('create', User::class)` fora da transação
- `DB::transaction()`: `User::create(...)` + `assignRole()` se `$dados->role !== null` + `invalidarCache`
- Retorna `$utilizador->load('roles')`

---

### T11 — ActualizarUtilizadorDto + ActualizarUtilizadorAction + ActualizarUtilizadorRequest

**DTO** (`app/Features/Utilizador/Actualizar/ActualizarUtilizadorDto.php`):
- Campos: `nome`, `email`, `?password`
- Invariantes no construtor
- `fromRequest(ActualizarUtilizadorRequest $request): self`

**Request** (`app/Features/Utilizador/Actualizar/ActualizarUtilizadorRequest.php`):
- `authorize()`: `Gate::authorize('update', $this->route('utilizador'))`
- `rules()`: ver spec — `password` com `Password::min(8)` + `confirmed`, `sometimes/nullable`
- `messages()`: PT

**Action** (`app/Features/Utilizador/Actualizar/ActualizarUtilizadorAction.php`):
- Injeta `CacheServico`
- `Gate::authorize('update', $utilizador)` fora da transação
- `DB::transaction()`: `$utilizador->update([nome, email])` + `update(['password' => ...])` se `$dados->password !== null` + `invalidarCache`
- Retorna `$utilizador->refresh()->load('roles')`

---

### T12 — EliminarUtilizadorAction + EliminarUtilizadorRequest

**Request** (`app/Features/Utilizador/Eliminar/EliminarUtilizadorRequest.php`):
- `authorize()`: `Gate::authorize('delete', $this->route('utilizador'))`

**Action** (`app/Features/Utilizador/Eliminar/EliminarUtilizadorAction.php`):
- Injeta `CacheServico`
- `Gate::authorize('delete', $utilizador)` fora da transação
- Invariante 1: `auth()->id() === $utilizador->id` → `DomainException`
- Invariante 2: último com `utilizadores.eliminar` → `DomainException`
- `DB::transaction()`: `tokens()->delete()` + `delete()` + `invalidarCache`

---

### T13 — UtilizadorController: 5 métodos novos

Adicionar a `app/Features/Utilizador/UtilizadorController.php`:
- `index`, `show`, `store`, `update`, `destroy`
- Controller sem lógica — apenas dispatch para Action + retorno ApiResponse

---

### T14 — Rotas

Em `routes/api.php`, dentro do grupo autenticado, substituir a rota manual de `utilizadores` por:

```php
Route::apiResource('utilizadores', UtilizadorController::class);
Route::put('utilizadores/{utilizador}/role', [UtilizadorController::class, 'atribuirRole']);
```

Verificar com `php artisan route:list --path=utilizadores`.

---

## Checkpoint ②

```bash
composer test:types && composer test:arch
```

Zero erros exigidos antes de avançar para os testes.

---

### T15 — Testes Feature (HTTP)

Criar em `tests/Feature/Features/Utilizador/`:

**ListarUtilizadoresTest.php**
- admin (com `utilizadores.ver`) → 200 + estrutura paginada
- utilizador sem permissão → 403
- guest → 401

**VerUtilizadorTest.php**
- admin → 200
- utilizador sem permissão a ver outro → 403
- utilizador sem permissão a ver o próprio → 200 (CA-12)
- guest → 401

**CriarUtilizadorTest.php**
- admin → 201 + utilizador criado
- admin com role → 201 + role atribuído
- utilizador sem permissão → 403
- guest → 401
- email duplicado → 422
- password fraca → 422

**ActualizarUtilizadorTest.php**
- admin → 200 + dados actualizados
- admin sem password → 200 (password não alterada)
- utilizador sem permissão → 403
- guest → 401
- email duplicado (outro utilizador) → 422

**EliminarUtilizadorTest.php**
- admin → 204 + soft delete + tokens revogados
- auto-eliminação → 422 (DomainException → 422)
- utilizador sem permissão → 403
- guest → 401

---

### T16 — Testes Unit (Actions directas)

Criar em `tests/Unit/Features/Utilizador/`:

**ListarUtilizadoresActionTest.php**
- COM permissão → CursorPaginator com users
- SEM permissão → AuthorizationException

**VerUtilizadorActionTest.php**
- COM permissão → User com roles
- SEM permissão → AuthorizationException
- próprio utilizador (sem `utilizadores.ver`) → User (auto-acesso)

**CriarUtilizadorActionTest.php**
- COM permissão → User criado na BD
- COM role → role atribuído
- SEM permissão → AuthorizationException

**ActualizarUtilizadorActionTest.php**
- COM permissão → User actualizado
- password null → password não alterada
- SEM permissão → AuthorizationException

**EliminarUtilizadorActionTest.php**
- COM permissão → soft delete + `deleted_at` preenchido + tokens revogados
- auto-eliminação → DomainException
- último com permissão → DomainException
- SEM permissão → AuthorizationException

---

## Checkpoint D

```bash
composer test
```

100% coverage, 100% type-coverage, zero erros Larastan, zero sugestões Rector.

---

## Checkpoint E — Revisão final

- [ ] Todos os CAs (CA-01 a CA-14) da issue #68 verificados
- [ ] `password` nunca logada em claro
- [ ] `@throws` declarados em todos os métodos que lançam excepção
- [ ] `array shape` no `validated()` de cada FormRequest
- [ ] `strict_types=1` em todos os ficheiros novos
- [ ] system_spec actualizados (Fase 3a)

---

## Ordem de commits sugerida

```
T1+T2      → "feat(migration): add deleted_at to users + SoftDeletes on User — Issue #68"
T3         → "feat(migration): seed utilizadores permissions — Issue #68"
T4+T5      → "feat(auth): TagCache::Utilizadores + UtilizadorPolicy CRUD — Issue #68"
T6+T7      → "feat(utilizador): UtilizadorResource + CampoOrdenacaoUtilizadores — Issue #68"
T8         → "feat(utilizador): ListarUtilizadoresAction + Request — Issue #68"
T9         → "feat(utilizador): VerUtilizadorAction + Request — Issue #68"
T10        → "feat(utilizador): CriarUtilizadorDto + Action + Request — Issue #68"
T11        → "feat(utilizador): ActualizarUtilizadorDto + Action + Request — Issue #68"
T12        → "feat(utilizador): EliminarUtilizadorAction + Request — Issue #68"
T13+T14    → "feat(utilizador): UtilizadorController CRUD + rotas apiResource — Issue #68"
T15+T16    → "test: Feature + Unit tests para Utilizador CRUD — Issue #68"
```
