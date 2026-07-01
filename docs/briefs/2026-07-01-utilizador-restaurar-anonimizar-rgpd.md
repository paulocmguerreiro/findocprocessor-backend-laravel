# Brief — Issue #73: Utilizador Restaurar + RGPD Anonimização

**Data:** 2026-07-01
**Slug:** `utilizador-restaurar-anonimizar-rgpd`
**Branch:** `feat/utilizador-restaurar-anonimizar-rgpd`
**Issue:** [#73](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/73)
**Dependência:** #68 (concluída — SoftDeletes + FKs restrictOnDelete já entregues)

---

## Contexto

A Issue #68 entregou `deleted_at` em `users`, alterou as FKs
`documentos.id_responsavel` e `etapas_documento.id_utilizador` para
`restrictOnDelete`, e implementou a listagem filtrada por `FiltroEstadoRegisto`.
A Issue #73 cobre as duas operações de ciclo de vida que faltam para os
utilizadores soft-deleted:

- **Parte A — Restaurar:** reactivar um utilizador soft-deleted
  (estrutura idêntica a #71 Entidade e #72 CategoriaDocumento, adaptada ao slice `Utilizador`)
- **Parte B — RGPD Anonimizar:** substituir dados pessoais (`name`, `email`,
  `password`) por valores não-identificativos, fazer soft delete e revogar
  tokens — satisfaz Art. 17.º RGPD sem quebrar FKs `restrictOnDelete`

A `ListarUtilizadoresAction` **não é alterada** (filtro por estado já existe desde #68).

---

## Parte A — Restaurar

### Componentes novos

| Componente | Tipo | Localização |
|---|---|---|
| `RestaurarUtilizadorAction` | Action final | `app/Features/Utilizador/Restaurar/` |
| `RestaurarUtilizadorRequest` | FormRequest final | `app/Features/Utilizador/Restaurar/` |
| `UtilizadorController::restaurar()` | método novo | `app/Features/Utilizador/UtilizadorController.php` |
| `UtilizadorPolicy::restore()` | método novo | `app/Policies/UtilizadorPolicy.php` |
| Rota `PATCH /utilizadores/{utilizador}/restaurar` | `routes/api.php` | novo |
| `RestaurarUtilizadorActionTest` | Unit test | `tests/Unit/Features/Utilizador/` |
| `RestaurarUtilizadorTest` | Feature test | `tests/Feature/Features/Utilizador/` |

### Assinatura da Action

```php
public function handle(User|int $utilizador): User
```

> User usa PK `int` (excepção documentada — não UUID), por isso o ramo
> programático é `int` em vez de `string`. Resolve via
> `User::withTrashed()->findOrFail($id)`.

### Invariantes

1. `! $utilizador->trashed()` → `DomainException('Utilizador não está inactivo.')`
2. `str_starts_with($utilizador->email, 'anonimizado+')` → `DomainException('Utilizador anonimizado não pode ser restaurado.')`

### Fluxo

```
Gate::authorize('restore', $utilizador)    # fora da transação
DB::transaction {
    $utilizador->restore()
    invalidarCache(TagCache::Utilizadores)
}
return $utilizador->load('roles')
```

### Autorização

```php
// UtilizadorPolicy
public function restore(User $autenticado, User $alvo): bool
{
    return $autenticado->hasPermissionTo('utilizadores.eliminar');
}
```

Reutiliza `utilizadores.eliminar` — sem nova migration de seed.

### Rota

```php
Route::patch('utilizadores/{utilizador}/restaurar', [UtilizadorController::class, 'restaurar'])
    ->withTrashed();
```

### Resposta

| Caso | Status | Body |
|---|---|---|
| Sucesso | 200 | `{ data: UtilizadorResource }` |
| Não estava inactivo | 422 | DomainException |
| Anonimizado | 422 | DomainException |
| Sem permissão | 403 | — |
| Guest | 401 | — |

---

## Parte B — RGPD Anonimização

### Componentes novos

| Componente | Tipo | Localização |
|---|---|---|
| `AnonimizarUtilizadorAction` | Action final | `app/Features/Utilizador/Anonimizar/` |
| `AnonimizarUtilizadorRequest` | FormRequest final | `app/Features/Utilizador/Anonimizar/` |
| `UtilizadorController::anonimizar()` | método novo | `app/Features/Utilizador/UtilizadorController.php` |
| `UtilizadorPolicy::anonimizar()` | método novo | `app/Policies/UtilizadorPolicy.php` |
| Migration `seed_utilizadores_anonimizar_permission` | data migration | `database/migrations/` |
| `AnonimizarUtilizadorActionTest` | Unit test | `tests/Unit/Features/Utilizador/` |
| `AnonimizarUtilizadorTest` | Feature test | `tests/Feature/Features/Utilizador/` |

### Assinatura da Action

```php
public function handle(User $utilizador): void
```

> A anonimização é irreversível; sem necessidade de devolver modelo.

### Invariantes

1. `Auth::id() === $utilizador->id` → `DomainException('Não é possível anonimizar o próprio utilizador.')`
2. `str_starts_with($utilizador->email, 'anonimizado+')` → `DomainException('Utilizador já está anonimizado.')`

### Dados substituídos

| Campo | Valor anonimizado |
|---|---|
| `name` | `'Utilizador #' . $utilizador->id` |
| `email` | `'anonimizado+' . $utilizador->id . '@removido.invalid'` |
| `password` | `Hash::make(Str::random(64))` |
| `remember_token` | `null` |
| `email_verified_at` | `null` |

### Fluxo

```
Gate::authorize('anonimizar', $utilizador)    # fora da transação
DB::transaction {
    invariante auto-anonimização              # dentro da transação
    invariante já-anonimizado                # dentro da transação
    $utilizador->tokens()->delete()
    $utilizador->forceFill([...])->save()
    $utilizador->delete()                    # soft delete
    Log::info('rgpd.anonimizacao', ['id_alvo' => $utilizador->id])  # sem PII
    invalidarCache(TagCache::Utilizadores)
}
```

> **Nota:** os invariantes de anonimização ficam **dentro** da transação
> (ao contrário do `Gate::authorize` que fica fora) porque dependem do
> estado persistido, mas não são verificações de autorização.

### Autorização

```php
// UtilizadorPolicy
public function anonimizar(User $autenticado, User $alvo): bool
{
    return $autenticado->hasPermissionTo('utilizadores.anonimizar');
}
```

Nova permissão — requer migration de seed atribuída ao role `admin`.

### Migration de permissão

```
2026_07_01_<ts>_seed_utilizadores_anonimizar_permission.php
```

Cria `utilizadores.anonimizar`, atribui a `admin`. Segue o padrão
`seed_utilizadores_permissions`.

### Rota

```php
Route::post('utilizadores/{utilizador}/anonimizar', [UtilizadorController::class, 'anonimizar']);
```

> POST (operação irreversível, não-idempotente). Sem `->withTrashed()` —
> não faz sentido anonimizar um utilizador já soft-deleted neste fluxo
> (o endpoint faz o soft delete como parte da anonimização).

### Resposta

| Caso | Status | Body |
|---|---|---|
| Sucesso | 204 | — |
| Auto-anonimização | 422 | DomainException |
| Já anonimizado | 422 | DomainException |
| Sem permissão | 403 | — |
| Guest | 401 | — |

---

## Impacto técnico

- **Ficheiros alterados:** `UtilizadorController`, `UtilizadorPolicy`, `routes/api.php`
- **Ficheiros novos:** 2 Actions, 2 Requests, 1 Migration, 4 testes (2 Unit + 2 Feature)
- **System_spec a actualizar:** `01-features/utilizador.md`, `05-routes/role.md`, `04-infra/autorizacao.md`
- **OpenAPI:** `PATCH /utilizadores/{id}/restaurar` (200), `POST /utilizadores/{id}/anonimizar` (204)

---

## Riscos identificados

1. **`->withTrashed()` na rota custom** — rota `restaurar` é declarada fora do `apiResource`,
   por isso o `->withTrashed()` tem de ser explícito na própria `Route::patch()`.
   Confirmado pela doc (Routing › Soft Deleted Models): `->withTrashed()` encadeia directamente
   na definição da rota. Sem ele, o RMB devolve 404 para utilizadores soft-deleted.
2. **Invariante anonimizado no Restaurar** — a verificação `str_starts_with($email, 'anonimizado+')`
   é uma heurística baseada na convenção de email anonimizado (Parte B). É suficiente para este
   âmbito; não requer coluna `anonimizado_em` separada.
3. **User PK é `int` (não UUID)** — o ramo programático da Action usa `User|int` em vez de
   `User|string` (padrão dos outros modelos). Comportamento idêntico; diferença apenas no tipo do
   argumento de lookup.
4. **`forceFill` + `save` vs `update`** — `forceFill` ignora `$fillable`; necessário porque
   `remember_token` e `email_verified_at` não estão nos campos fillable do model `User`.
5. **Audit trail da Anonimização** — o `User` não usa o trait `RegistaActividade` (é modelo
   de autenticação, não de domínio). O `forceFill()->save()` não gera log automático no
   `activity_log`. O audit da anonimização usa `Log::info('rgpd.anonimizacao')` (logging
   estruturado) sem PII. Aceitável para este âmbito.

## Questões em aberto

- Nenhuma — todos os critérios de aceitação e contratos estão definidos na issue.

---

## Aprendizagem esperada

Esta issue consolida o padrão completo de ciclo de vida soft-delete em Vertical Slice:
Restaurar é o inverso do Eliminar (Padrão B); Anonimizar é uma operação de domínio
irreversível que combina mutação de dados pessoais + soft delete + revogação de tokens,
tudo dentro de uma única transação com autorização dupla camada.
