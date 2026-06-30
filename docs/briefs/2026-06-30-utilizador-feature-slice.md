# Brief — Utilizador Feature Slice (CRUD completo)

**Issue:** #68
**Data:** 2026-06-30
**Slug:** utilizador-feature-slice
**Tipo:** feat

---

## Contexto

A feature `Utilizador` existe com apenas uma operação: `AtribuirRole` (Issue #50).
Esta issue completa o CRUD — listar, ver, criar, actualizar, eliminar — seguindo o mesmo padrão Vertical Slice das features `CategoriaDocumento`, `Entidade` e `Role`.

O modelo `User` é partilhado com a feature `Auth`. A PK é `int` autoincremental (excepção documentada — não UUID). A `UtilizadorPolicy` já existe e regista via `#[UsePolicy(UtilizadorPolicy::class)]`.

---

## Problema / Motivação

Não existe gestão de utilizadores — não é possível listar, criar, actualizar ou eliminar utilizadores pela API. `AtribuirRole` existe mas o CRUD que o suporta falta.

---

## Decisões de arquitectura

### Repository
CRUD simples sobre `User`. Sem query complexa que justifique Repository. Actions acedem directamente ao Eloquent Model (critério: `repositories.md` — dispensável em CRUD simples).

### SoftDelete — desvio intencional do Padrão B
O spec `02-shared/soft-delete.md` define "Padrão B" para `User` (forceDelete com fallback + anonimização + RestaurarAction + FiltroEstadoRegisto).

A issue #68 define um âmbito mais restrito:
- `EliminarUtilizadorAction` revoga tokens Sanctum e faz sempre `delete()` (soft delete directo, nunca `forceDelete`)
- `ListarUtilizadoresAction` exclui soft-deleted por defeito, sem parâmetro `FiltroEstadoRegisto`
- `RestaurarAction` e `AnonimizarAction` ficam **fora de âmbito**

**Motivo:** o User referencia documentos que não podem ficar órfãos; hard delete seria sempre rejeitado pela FK; logo, `forceDelete` seria sempre `QueryException` → soft delete de qualquer forma. O desvio simplifica sem perda funcional nesta fase.

**Fica registado como dívida técnica:** Padrão B completo (anonimização RGPD + restauro) será endereçado numa issue futura.

### `role` em CriarUtilizador
Campo opcional. Se omitido, utilizador fica sem role (comportamento padrão do Laravel/Spatie). Se fornecido, `$utilizador->assignRole($dados->role)` dentro da transação.

### Cache
`ListarCategoriasAction` e `ListarEntidadesAction` usam `CacheServico` — é o padrão estabelecido para todas as listagens. `ListarUtilizadoresAction` segue o mesmo padrão: cache com `TagCache::Utilizadores`, invalidada pelas Actions de escrita (Criar, Actualizar, Eliminar).

### Invariantes na Action
- `EliminarUtilizadorAction` impede auto-eliminação → `DomainException`
- `EliminarUtilizadorAction` impede eliminar o último utilizador com `utilizadores.eliminar` → `DomainException`
Ambas verificadas antes da transação (são verificações de negócio, não de persistência).

### `view` com auto-acesso
`UtilizadorPolicy::view()` permite: `hasPermissionTo('utilizadores.ver') || $utilizador->id === $alvo->id`. O utilizador vê sempre o próprio perfil.

### Autorização dupla camada
- `FormRequest::authorize()` — contexto HTTP
- `Action::handle()` via `Gate::authorize()` — cobre Jobs, Artisan, testes directos

---

## Componentes a criar

| Camada | Ficheiro |
|---|---|
| Migration | `add_softdeletes_to_users_table` |
| Migration | `seed_utilizadores_permissions` |
| Model (actualizar) | `app/Models/User.php` — SoftDeletes + `@property-read ?Carbon $deleted_at` |
| Policy (actualizar) | `app/Policies/UtilizadorPolicy.php` — 5 métodos |
| Enum | `app/Features/Utilizador/Listar/CampoOrdenacaoUtilizadores.php` |
| Resource | `app/Features/Utilizador/UtilizadorResource.php` |
| DTO | `app/Features/Utilizador/Criar/CriarUtilizadorDto.php` |
| DTO | `app/Features/Utilizador/Actualizar/ActualizarUtilizadorDto.php` |
| Action + Request | Listar, Ver, Criar, Actualizar, Eliminar (5×2 = 10 ficheiros) |
| Controller (actualizar) | `app/Features/Utilizador/UtilizadorController.php` — 5 métodos |
| Routes (actualizar) | `routes/api.php` — `Route::apiResource('utilizadores', ...)` |
| Testes Feature | `tests/Feature/Features/Utilizador/` — 5 ficheiros |
| Testes Unit | `tests/Unit/Features/Utilizador/` — 5 ficheiros |

---

## Riscos identificados

| Risco | Mitigação |
|---|---|
| `deleted_at` não está em `users` — migration necessária | Confirmado por `database-schema`; migration `add_softdeletes_to_users_table` é T1 |
| FK `documentos.id_responsavel` e `etapas_documento.id_utilizador` referem `users` | SoftDeletes preserva integridade (soft delete não remove a linha) |
| `password` em `ActualizarUtilizadorDto` — risco de log | `password` marcado como `#[Hidden]` no modelo; nunca logar `$dados->password` |
| Invariante "último com permissão eliminar" — query pode ser lenta | N+1 improvávelcom poucos utilizadores admin; aceitável nesta fase |
| `view` policy especial (auto-acesso) — testes devem cobrir utilizador SEM `utilizadores.ver` a ver o próprio | Coberto na matriz de 3 estados; CA-12 explícito na issue |

---

## Questões em aberto

Nenhuma — desvio do Padrão B está documentado acima e aceite para esta issue.

---

## Referências

- `docs/system_spec/01-features/utilizador.md`
- `docs/system_spec/03-models/user.md`
- `docs/system_spec/04-infra/autorizacao.md`
- `docs/system_spec/02-shared/soft-delete.md`
- `docs/system_spec/02-shared/padroes-acoes.md`
- `app/Features/CategoriaDocumento/` — padrão de referência para CRUD
