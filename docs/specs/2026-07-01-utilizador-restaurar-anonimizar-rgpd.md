# Spec — Issue #73: Utilizador Restaurar + RGPD Anonimização

**Data:** 2026-07-01
**Slug:** `utilizador-restaurar-anonimizar-rgpd`
**Brief:** `docs/briefs/2026-07-01-utilizador-restaurar-anonimizar-rgpd.md`

---

## Contratos por camada

### Rotas (`routes/api.php`)

```php
// Dentro do grupo auth:sanctum — após a rota atribuirRole existente
Route::patch('utilizadores/{utilizador}/restaurar', [UtilizadorController::class, 'restaurar'])
    ->withTrashed();
Route::post('utilizadores/{utilizador}/anonimizar', [UtilizadorController::class, 'anonimizar']);
```

### Controller (`app/Features/Utilizador/UtilizadorController.php`)

```php
public function restaurar(
    RestaurarUtilizadorRequest $pedido,
    User $utilizador,
    RestaurarUtilizadorAction $accao
): JsonResponse {
    return ApiResponse::devolverSucesso(new UtilizadorResource($accao->handle($utilizador)));
}

public function anonimizar(
    AnonimizarUtilizadorRequest $pedido,
    User $utilizador,
    AnonimizarUtilizadorAction $accao
): JsonResponse {
    $accao->handle($utilizador);

    return ApiResponse::devolverVazio();
}
```

### FormRequests

**`RestaurarUtilizadorRequest`** (`app/Features/Utilizador/Restaurar/`)

```php
final class RestaurarUtilizadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('restore', $this->route('utilizador'));
        return true;
    }

    public function rules(): array { return []; }
}
```

**`AnonimizarUtilizadorRequest`** (`app/Features/Utilizador/Anonimizar/`)

```php
final class AnonimizarUtilizadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('anonimizar', $this->route('utilizador'));
        return true;
    }

    public function rules(): array { return []; }
}
```

### Actions

**`RestaurarUtilizadorAction`** (`app/Features/Utilizador/Restaurar/`)

```php
final readonly class RestaurarUtilizadorAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws ModelNotFoundException<User>
     * @throws AuthorizationException
     * @throws \DomainException
     * @throws \Throwable
     */
    public function handle(User|int $utilizador): User
    {
        $utilizador = is_int($utilizador)
            ? User::withTrashed()->findOrFail($utilizador)
            : $utilizador;

        Gate::authorize('restore', $utilizador);

        if (! $utilizador->trashed()) {
            throw new \DomainException('Utilizador não está inactivo.');
        }

        if (str_starts_with($utilizador->email, 'anonimizado+')) {
            throw new \DomainException('Utilizador anonimizado não pode ser restaurado.');
        }

        DB::transaction(function () use ($utilizador): void {
            $utilizador->restore();
            $this->cache->invalidarCache(TagCache::Utilizadores);
        });

        return $utilizador->load('roles');
    }
}
```

**`AnonimizarUtilizadorAction`** (`app/Features/Utilizador/Anonimizar/`)

```php
final readonly class AnonimizarUtilizadorAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws AuthorizationException
     * @throws \DomainException
     * @throws \Throwable
     */
    public function handle(User $utilizador): void
    {
        Gate::authorize('anonimizar', $utilizador);

        if (Auth::id() === $utilizador->id) {
            throw new \DomainException('Não é possível anonimizar o próprio utilizador.');
        }

        if (str_starts_with($utilizador->email, 'anonimizado+')) {
            throw new \DomainException('Utilizador já está anonimizado.');
        }

        DB::transaction(function () use ($utilizador): void {
            $utilizador->tokens()->delete();

            // saveQuietly() suprime o evento 'updated' do RegistaActividade, que
            // registaria old.name/old.email (PII) no activity_log. O evento de
            // anonimização é registado manualmente a seguir, sem campos.
            $utilizador->forceFill([
                'name'               => 'Utilizador #' . $utilizador->id,
                'email'              => 'anonimizado+' . $utilizador->id . '@removido.invalid',
                'password'           => Hash::make(Str::random(64)),
                'remember_token'     => null,
                'email_verified_at'  => null,
            ])->saveQuietly();

            activity()
                ->performedOn($utilizador)
                ->causedBy(Auth::user())
                ->event('rgpd.anonimizacao')
                ->log('utilizador anonimizado');

            $utilizador->delete();

            $this->cache->invalidarCache(TagCache::Utilizadores);
        });
    }
}
```

### Model `User` — adicionar `RegistaActividade`

```php
// app/Models/User.php — traits a adicionar
use App\Models\Concerns\RegistaActividade;

// Método a adicionar
protected function atributosExcluidosDaActividade(): array
{
    return ['password', 'remember_token'];
}
```

`RegistaActividade` usa `logFillable()->logExcept([...])->logOnlyDirty()->dontSubmitEmptyLogs()`.
Excluir `password` e `remember_token` — credenciais nunca devem aparecer em audit.
`name` e `email` **são** auditados no CRUD normal (ActualizarUtilizador etc.) — valores antigos
e novos ficam registados, o que é correcto para rastreio de alterações administrativas.

Durante a anonimização, o `saveQuietly()` na Action suprime o evento `updated` automático
(que conteria `old.name`/`old.email` com PII); em substituição, a Action regista manualmente
o evento `rgpd.anonimizacao` via `activity()->performedOn()->log()` sem campos.

> **system_spec a actualizar:** `04-infra/audit-trail.md` — adicionar `User` à tabela de
> modelos auditados com campos excluídos `['password', 'remember_token']`.

### Policy (`app/Policies/UtilizadorPolicy.php`)

```php
// Método novo — restore
public function restore(User $autenticado, User $alvo): bool
{
    return $autenticado->hasPermissionTo('utilizadores.eliminar');
}

// Método novo — anonimizar
public function anonimizar(User $autenticado, User $alvo): bool
{
    return $autenticado->hasPermissionTo('utilizadores.anonimizar');
}
```

### Migration (`database/migrations/`)

Nome: `2026_07_01_<ts>_seed_utilizadores_anonimizar_permission.php`

```php
public function up(): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::create(['name' => 'utilizadores.anonimizar']);
    Role::findByName('admin')->givePermissionTo('utilizadores.anonimizar');
}

public function down(): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findByName('utilizadores.anonimizar')->delete();
}
```

---

## Contratos de teste

### Unit — `tests/Unit/Features/Utilizador/RestaurarUtilizadorActionTest.php`

| Cenário | Asserção |
|---|---|
| Admin, User soft-deleted passado directamente → restaura | `assertNotSoftDeleted('users', ...)` |
| Admin, User int PK passado → resolve e restaura | `assertNotSoftDeleted('users', ...)` |
| Admin, User não estava soft-deleted → DomainException | `toThrow(DomainException::class)` |
| Admin, User anonimizado → DomainException | `toThrow(DomainException::class)` |
| Rollback quando exception dentro da transação | registo permanece soft-deleted |
| Sem permissão → AuthorizationException | `toThrow(AuthorizationException::class)` |
| Guest → AuthorizationException | `toThrow(AuthorizationException::class)` |

### Unit — `tests/Unit/Features/Utilizador/AnonimizarUtilizadorActionTest.php`

| Cenário | Asserção |
|---|---|
| Admin, utilizador diferente → dados substituídos + soft-deleted + tokens revogados | `assertSoftDeleted`, campos `name`/`email` alterados, tokens eliminados |
| Admin, auto-anonimização → DomainException | `toThrow(DomainException::class)` |
| Admin, já anonimizado → DomainException | `toThrow(DomainException::class)` |
| Rollback quando exception dentro da transação | dados originais preservados, não soft-deleted |
| Sem permissão → AuthorizationException | `toThrow(AuthorizationException::class)` |
| Guest → AuthorizationException | `toThrow(AuthorizationException::class)` |

### Feature — `tests/Feature/Features/Utilizador/RestaurarUtilizadorTest.php`

| Cenário | Status | Asserção adicional |
|---|---|---|
| Admin, PATCH `.../restaurar` de utilizador soft-deleted | 200 | `deleted_at: null` no JSON |
| Admin, utilizador não estava inactivo | 422 | — |
| Admin, utilizador anonimizado | 422 | — |
| Admin, UUID inexistente | 404 | — |
| Sem permissão | 403 | permanece soft-deleted |
| Guest | 401 | — |
| Utilizador restaurado volta a aparecer em GET /utilizadores (default) | 200 | inclui na lista |

### Feature — `tests/Feature/Features/Utilizador/AnonimizarUtilizadorTest.php`

| Cenário | Status | Asserção adicional |
|---|---|---|
| Admin, POST `.../anonimizar` de utilizador activo | 204 | campos anonimizados em BD, soft-deleted, tokens revogados |
| Admin, auto-anonimização | 422 | — |
| Admin, já anonimizado | 422 | — |
| Admin, ID inexistente | 404 | — |
| Sem permissão | 403 | — |
| Guest | 401 | — |
| Token do utilizador anonimizado fica inválido | 401 | GET qualquer endpoint com token revogado |

---

## Critérios de aceitação rastreados

### Parte A — Restaurar

- [x] CA-A1: rota `PATCH /utilizadores/{utilizador}/restaurar` com `->withTrashed()`
- [x] CA-A2: `Gate::authorize('restore', $utilizador)` fora da transação
- [x] CA-A3: `$utilizador->restore()` dentro de `DB::transaction()` + invalida cache
- [x] CA-A4: invariante "não estava inactivo" → `DomainException` (→ 422)
- [x] CA-A5: invariante "anonimizado" → `DomainException` (→ 422)
- [x] CA-A6: `UtilizadorPolicy::restore()` usa `hasPermissionTo('utilizadores.eliminar')`
- [x] CA-A7: `RestaurarUtilizadorRequest::authorize()` em dupla camada
- [x] CA-A8: retorna 200 + `UtilizadorResource` com `deleted_at: null`
- [x] CA-A9: matriz 3 estados (admin 200 / sem permissão 403 / guest 401)
- [x] CA-A10: utilizador restaurado volta a aparecer em `GET /utilizadores`

### Parte B — Anonimização

- [x] CA-B1: substitui `name`, `email`, `password`, `remember_token`, `email_verified_at`
- [x] CA-B2: revoga tokens (`tokens()->delete()`) antes do soft delete
- [x] CA-B3: soft delete após anonimização
- [x] CA-B4: invariante auto-anonimização → `DomainException` (422)
- [x] CA-B5: invariante já-anonimizado → `DomainException` (422)
- [x] CA-B6: `Gate::authorize('anonimizar', ...)` na Action (dupla camada)
- [x] CA-B7: `UtilizadorPolicy::anonimizar()` com `hasPermissionTo('utilizadores.anonimizar')`
- [x] CA-B8: migration de seed cria `utilizadores.anonimizar` (role admin)
- [x] CA-B9: audit log `rgpd.anonimizacao` sem `name`/`email`
- [x] CA-B10: retorna 204
- [x] CA-B11: token do utilizador anonimizado fica inválido
- [x] CA-B12: matriz 3 estados + invariantes testados

---

## Desvios e decisões de implementação

| Decisão | Justificação |
|---|---|
| `User\|int` em vez de `User\|string` no Restaurar | PK de `User` é `int` (excepção documentada à regra UUID dos modelos de domínio) |
| `forceFill` em vez de `fill` no Anonimizar | `remember_token` e `email_verified_at` não estão em `$fillable` do `User` |
| Invariantes do Anonimizar fora da transação | Pré-verificações em memória (igual ao padrão de EliminarUtilizadorAction) — não há motivo para segurar uma transação durante validação |
| Sem `->withTrashed()` na rota Anonimizar | O fluxo opera sobre utilizadores activos; o soft delete é a saída da operação |
| `RegistaActividade` no `User` com exclusão de `password`/`remember_token` | Audita CRUD normal (name/email); credenciais nunca expostas no audit trail |
| `saveQuietly()` na anonimização + `activity()` manual | `save()` registaria `old.name`/`old.email` (PII) via auto-audit; `saveQuietly()` suprime esse evento; evento `rgpd.anonimizacao` é registado manualmente sem campos |
