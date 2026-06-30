# System Spec — Feature: Utilizador

> `App\Features\Utilizador\`
> Issue #50 (AtribuirRole) · Issue #68 (CRUD completo + filtro de estado)

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

Permissions criadas por `seed_utilizadores_permissions` (atribuídas ao role `admin`). Detalhe: `04-infra/autorizacao.md`.

---

## Resource

`UtilizadorResource`: `id`, `name`, `email`, `roles` (nomes), `deleted_at` (ISO 8601 ou null), `created_at` (ISO 8601).
