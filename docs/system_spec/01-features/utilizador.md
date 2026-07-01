# System Spec — Feature: Utilizador

> `App\Features\Utilizador\`
> Issue #50 (AtribuirRole) · Issue #68 (CRUD completo + filtro de estado) · Issue #73 (Restaurar + RGPD Anonimização)

Gestão de utilizadores em Vertical Slice sobre o modelo partilhado `User` (partilhado com a feature Auth). CRUD completo (Listar, Ver, Criar, Actualizar, Eliminar) mais a atribuição de role.

**Autorização:** Policy `UtilizadorPolicy` registada via `#[UsePolicy(UtilizadorPolicy::class)]` no modelo `User`. Autorização dupla camada: `FormRequest::authorize()` (HTTP) **e** `Gate::authorize()` na Action (Jobs/Artisan/testes).

---

## Actions

| Classe | Sub-namespace | Assinatura `handle()` | Descrição |
|---|---|---|---|
| `ListarUtilizadoresAction` | `Listar` | `handle(int $porPagina, CampoOrdenacaoUtilizadores $campo, DirecaoOrdenacao $direcao, FiltroEstadoRegisto $filtro): CursorPaginator` | Listagem cursor-paginada, eager-load de `roles`, filtro de estado e cache (`TagCache::Utilizadores`) |
| `VerUtilizadorAction` | `Ver` | `handle(User $utilizador): User` | Devolve o utilizador com `roles`; sem cache (leitura individual) |
| `CriarUtilizadorAction` | `Criar` | `handle(CriarUtilizadorDto $dados): User` | Cria utilizador (password auto-hash), atribui `role` se fornecido; invalida cache |
| `ActualizarUtilizadorAction` | `Actualizar` | `handle(User $utilizador, ActualizarUtilizadorDto $dados): User` | Actualiza nome/email; password só se fornecida; invalida cache |
| `EliminarUtilizadorAction` | `Eliminar` | `handle(User $utilizador): void` | Padrão B (hard/soft); revoga tokens Sanctum; invalida cache |
| `RestaurarUtilizadorAction` | `Restaurar` | `handle(User\|int $utilizador): User` | Reactiva utilizador soft-deleted; resolve `int` via `withTrashed()->findOrFail()`; invalida cache |
| `AnonimizarUtilizadorAction` | `Anonimizar` | `handle(User $utilizador): void` | RGPD Art. 17.º: substitui dados pessoais + soft delete + revoga tokens + audit; invalida cache |
| `AtribuirRoleAction` | `AtribuirRole` | `handle(User $utilizador, string $nomeRole): User` | Substitui role via `syncRoles()` |

DTOs: `CriarUtilizadorDto` (`nome`, `email`, `password`, `?role`), `ActualizarUtilizadorDto` (`nome`, `email`, `?password`) — Value Objects com invariantes no construtor e `fromRequest()`. Os FormRequests `CriarUtilizadorRequest`/`ActualizarUtilizadorRequest` são **não-final** (mockáveis nos testes de `fromRequest`); os restantes são `final`.

---

## Filtro de estado (SoftDelete)

`ListarUtilizadoresAction` aceita `FiltroEstadoRegisto` (default `SomenteAtivos`), aplicado pelo scope transversal `filtrarPorEstadoRegisto()` (trait `App\Models\Concerns\FiltravelPorEstadoRegisto`). Query string: `?estado=todos|somente_ativos|somente_inativos`. Ver `02-shared/soft-delete.md` e `02-shared/enums.md`.

---

## Eliminação — Padrão B por pré-verificação

`EliminarUtilizadorAction`:

1. `Gate::authorize('delete', $utilizador)` (fora da transação).
2. **Invariante de domínio:** não eliminar o próprio utilizador → `DomainException` (→ 422).
3. `DB::transaction`: revoga tokens (`tokens()->delete()`) → decide hard vs soft por **pré-verificação determinística** (`estaReferenciado()` consulta `documentos.id_responsavel` e `etapas_documento.id_utilizador`) → `forceDelete()` se sem referências, `delete()` (soft) se referenciado → invalida cache.

> A pré-verificação substitui o `try/catch forceDelete` textual do Padrão B: a violação de FK `RESTRICT` difere para o commit no SQLite (testes) e escapa ao `catch`. O `restrictOnDelete` das FKs filhas é a salvaguarda ao nível da BD. Anonimização RGPD do ramo soft delete: **Issue #73**.

A invariante "não eliminar o último utilizador com `utilizadores.eliminar`" foi **descartada** (recuperável via gestão de roles).

---

## Restauro — inverso do Eliminar (Issue #73)

`RestaurarUtilizadorAction`:

1. Resolve o argumento: `User\|int` → ramo `int` faz `User::withTrashed()->findOrFail($id)` (o `User` usa PK `int`, não UUID).
2. `Gate::authorize('restore', $utilizador)` (fora da transação).
3. **Invariante 1:** `! trashed()` → `DomainException('Utilizador não está inactivo.')` (→ 422).
4. **Invariante 2:** email começa por `anonimizado+` → `DomainException('Utilizador anonimizado não pode ser restaurado.')` (→ 422) — heurística sobre a convenção de email da anonimização, sem coluna dedicada.
5. `DB::transaction`: `restore()` → invalida cache. Devolve `$utilizador->load('roles')`.

Rota `PATCH /utilizadores/{utilizador}/restaurar` declarada com `->withTrashed()` explícito (fora do `apiResource`) para o RMB resolver o registo soft-deleted.

---

## Anonimização RGPD (Issue #73)

`AnonimizarUtilizadorAction` — operação **irreversível** (Art. 17.º RGPD) que substitui dados pessoais em vez de hard delete, preservando as FKs `restrictOnDelete`:

1. `Gate::authorize('anonimizar', $utilizador)` (fora da transação).
2. **Invariante 1:** `Auth::id() === $utilizador->id` → `DomainException` (não anonimizar o próprio) (→ 422).
3. **Invariante 2:** email já começa por `anonimizado+` → `DomainException` (já anonimizado) (→ 422).
4. `DB::transaction`: `tokens()->delete()` → `forceFill([...])->saveQuietly()` → `activity()->event('rgpd.anonimizacao')->log()` → `delete()` (soft) → invalida cache.

Dados substituídos: `name` = `Utilizador #{id}`, `email` = `anonimizado+{id}@removido.invalid`, `password` = `Hash::make(Str::random(64))`, `remember_token` = `null`, `email_verified_at` = `null`.

**Audit sem PII:** o `saveQuietly()` suprime o evento `updated` automático do trait `RegistaActividade` (que registaria `old.name`/`old.email`); o evento `rgpd.anonimizacao` é registado manualmente **sem propriedades**. Ver `04-infra/audit-trail.md`.

Rota `POST /utilizadores/{utilizador}/anonimizar` (não-idempotente, sem `->withTrashed()` — o soft delete é a saída da operação). Resposta 204.

---

## Invariante de domínio — auto-modificação de role

`AtribuirRoleAction` impede que um utilizador altere o próprio role (`auth()->id() === $utilizador->id` → `DomainException`). Aplicado na Action para cobrir todos os contextos de invocação. Handler converte `DomainException` → 422 (`bootstrap/app.php`).

---

## Policy

`UtilizadorPolicy` (`app/Policies/UtilizadorPolicy.php`):

| Método | Regra |
|---|---|
| `atribuirRole` | `hasPermissionTo('utilizadores.atribuir-role')` |
| `viewAny` | `hasPermissionTo('utilizadores.ver')` |
| `view` | `hasPermissionTo('utilizadores.ver') \|\| $utilizador->id === $alvo->id` (auto-acesso) |
| `create` | `hasPermissionTo('utilizadores.criar')` |
| `update` | `hasPermissionTo('utilizadores.actualizar')` |
| `delete` | `hasPermissionTo('utilizadores.eliminar')` |
| `restore` | `hasPermissionTo('utilizadores.eliminar')` (reutiliza a permissão de eliminação) |
| `anonimizar` | `hasPermissionTo('utilizadores.anonimizar')` |

Permissions criadas por `seed_utilizadores_permissions`; a permissão `utilizadores.anonimizar` por `seed_utilizadores_anonimizar_permission` (Issue #73). Todas atribuídas ao role `admin`. Detalhe: `04-infra/autorizacao.md`.

---

## Resource

`UtilizadorResource`: `id`, `name`, `email`, `roles` (nomes), `deleted_at` (ISO 8601 ou null), `created_at` (ISO 8601).
