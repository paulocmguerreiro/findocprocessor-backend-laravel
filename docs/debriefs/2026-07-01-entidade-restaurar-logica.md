# Debrief: Entidade — lógica layer (restaurar soft-deleted + ListarEntidades com inativas)

**Issue:** #71
**Branch:** feat/entidade-restaurar-logica
**Data:** 2026-07-01
**Commits:** 10 commits (implementação) + brief/spec/plan

## O que foi implementado

Camada de lógica de SoftDelete para a feature `Entidade`: endpoint de restauro, filtro de estado na listagem e Padrão B de eliminação — mais duas correcções de fundo descobertas durante os testes.

- **`RestaurarEntidadeAction`** (nova) — `handle(Entidade|string): Entidade`; resolve com `withTrashed()`, `Gate::authorize('restore')` fora da transação, `restore()` + invalidação de cache dentro.
- **`RestaurarEntidadeRequest`** (novo) + **`EntidadePolicy::restore()`** (reutiliza `entidades.eliminar`).
- **`EntidadeController::restaurar()`** + rota `PATCH /entidades/{entidade}/restaurar` com `->withTrashed()` (RMB inclui soft-deleted).
- **Filtro `?estado=`** na listagem — trait `FiltravelPorEstadoRegisto` no model `Entidade`; `ListarEntidadesAction` ganha 4.º parâmetro `FiltroEstadoRegisto` (scope + chave de cache).
- **Padrão B** em `EliminarEntidadeAction` (`forceDelete` com fallback soft delete).
- **Correcção transversal** do Padrão B (`EliminarEntidadeAction` + `EliminarUtilizadorAction`): o fallback usa `fresh()?->delete()`.
- **Migration** a forçar `restrictOnDelete` nos FKs `documentos→entidades` também em SQLite (paridade).
- **Testes:** Restaurar (Unit + Feature, novos), Eliminar (dois ramos), Listar (3 estados + 422), Policy `restore()`. Suite global: **696 testes**, 100% coverage e type-coverage, verde em SQLite **e** MySQL.

## Ficheiros alterados

| Ficheiro | Tipo | Notas |
| -------- | ---- | ----- |
| `app/Features/Entidade/Restaurar/RestaurarEntidadeAction.php` | criado | `Entidade\|string`; `withTrashed()->findOrFail`; restore + cache |
| `app/Features/Entidade/Restaurar/RestaurarEntidadeRequest.php` | criado | `Gate::authorize('restore', $this->route('entidade'))` (RMB) |
| `app/Policies/EntidadePolicy.php` | alterado | método `restore()` (reutiliza `entidades.eliminar`) |
| `app/Features/Entidade/EntidadeController.php` | alterado | `restaurar()` + extracção de `estado` no `index()` |
| `app/Features/Entidade/Listar/ListarEntidadesAction.php` | alterado | 4.º param `FiltroEstadoRegisto`; scope + `estado` na cache; `$perPage`→`$porPagina` |
| `app/Features/Entidade/Listar/ListarEntidadesRequest.php` | alterado | campo `estado` (`Rule::in(FiltroEstadoRegisto)`) + mensagens PT |
| `app/Models/Entidade.php` | alterado | trait `FiltravelPorEstadoRegisto` |
| `app/Features/Entidade/Eliminar/EliminarEntidadeAction.php` | alterado | Padrão B try/catch + fallback `fresh()?->delete()` |
| `app/Features/Utilizador/Eliminar/EliminarUtilizadorAction.php` | alterado | mesma correcção `fresh()?->delete()` |
| `routes/api.php` | alterado | `apiResource(...)->withTrashed([...])` + rota `restaurar` com `->withTrashed()` |
| `database/migrations/..._enforce_restrict_entidades_fk_in_documentos.php` | criado | `id_fornecedor`/`id_cliente` → `restrictOnDelete` em todos os drivers |
| `docs/system_spec/02-shared/soft-delete.md` | alterado | RMB por omissão; armadilha `forceDeleting`; fallback `fresh()` |
| `tests/Unit/Features/Entidade/RestaurarEntidadeActionTest.php` | criado | admin/404/403/401/rollback/dual-signature |
| `tests/Feature/Features/Entidade/RestaurarEntidadeTest.php` | criado | 200+Resource/idempotente/404/403/401 |
| `tests/Unit/Features/Entidade/EliminarEntidadeActionTest.php` | alterado | ramos hard delete (sem docs) + soft delete (com docs) |
| `tests/Feature/Features/Entidade/EliminarEntidadeTest.php` | alterado | idem (HTTP) + audit `deleted` em ambos |
| `tests/Unit/Features/Entidade/ListarEntidadesActionTest.php` | alterado | 4.º arg + casos de estado + cache key |
| `tests/Feature/Features/Entidade/ListarEntidadesTest.php` | alterado | `?estado=` (todos/inativos) + 422 |
| `tests/Unit/Policies/EntidadePolicyTest.php` | alterado | casos `restore()` |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| **RMB por omissão** no controller/FormRequest; só a Action aceita `Entidade\|string` | Passar `string` e resolver `withTrashed()->findOrFail()` à mão na Action **e** no Request (como o spec previa) | Elimina a dupla resolução (2 queries), alinha com os restantes métodos do controller e com `EliminarEntidadeRequest`. A rota `/restaurar` usa `->withTrashed()` para o binding incluir soft-deleted. Decisão do utilizador |
| Fallback do Padrão B usa **`fresh()?->delete()`** | `$entidade->delete()` sobre a mesma instância (o padrão textual, herdado de `EliminarUtilizadorAction`) | `forceDelete()` **não repõe** `forceDeleting` ao lançar; um `delete()` na mesma instância voltaria a fazer hard delete e relançava (500 em prod). Instância fresca garante soft delete real |
| **Migration** a aplicar `RESTRICT` em SQLite (via `dropForeign(['coluna'])` — forma por colunas) | Manter o skip de SQLite de #70 e testar o ramo soft-delete forçando `QueryException` | A forma por colunas reconstrói a tabela em SQLite (provado empiricamente). Testa o **FK real**, não a implementação; dá paridade com prod. Opção escolhida pelo utilizador |
| Assinatura da Action de restauro `Entidade\|string` (padrão dual) | `string` apenas | Consistência com `EliminarEntidadeAction`; suporta invocação programática (Jobs) e HTTP |
| `RestaurarEntidadeAction` **sem `Log::`** | Adicionar logging como em `EliminarEntidadeAction` | Seguir o spec; logging de restauro não pedido (pode entrar depois de forma consistente) |
| Renomear `$perPage`→`$porPagina` em `ListarEntidadesAction` | Deixar como estava | Convenção PT (variáveis NOUN+Intent) e alinhamento com `ListarUtilizadoresAction`; chamada posicional não quebra |

## Desvios ao Plano

- **RMB em vez de `string`** (o Brief dizia explicitamente "sem RMB"): revertido a pedido do utilizador — RMB é preferível; só Actions recebem `Entidade|string`. Brief/spec/system_spec actualizados.
- **Bug do Padrão B (`forceDeleting`)** — não previsto no plano. `forceDelete()` deixa a flag `forceDeleting=true` ao lançar, pelo que o `catch { delete() }` re-fazia hard delete. Corrigido em `EliminarEntidadeAction` **e** `EliminarUtilizadorAction` (#68) com `fresh()?->delete()`.
- **Migration de FK extra** — não prevista. Os FKs `documentos.id_fornecedor/id_cliente` eram `SET NULL` em SQLite (migration de #70 saltava SQLite), tornando o ramo soft-delete intestável. Nova migration força `RESTRICT` em todos os drivers.
- **Nota de audit trail corrigida** — o Brief assumia `forceDelete` → `Activity::count()===0`; verificado empiricamente que dispara `deleted` (count 1). Spec corrigida.
- **Follow-up criado (#77)** — migrar testes para MySQL-only + preflight Docker/Redis/MySQL + collation; a migration de FK-em-SQLite fica redundante quando o #77 remover o SQLite.

## Aprendizagens

- **O Padrão B nunca funcionou — e o #68 diagnosticou mal a causa.** O debrief de #68 concluiu que o `try/catch forceDelete` "escapa ao catch porque a violação de FK difere para o commit no SQLite", e contornou com pré-verificação. A investigação empírica de #71 mostra que a causa real é outra: `SoftDeletes::forceDelete()` faz `$this->forceDeleting = true` e só repõe a flag num callback `tap()` **após** o `delete()` interno; quando esse `delete()` lança (FK), o callback nunca corre e a flag **fica presa a true**. O `delete()` do `catch`, na mesma instância, volta então a fazer hard delete e relança. Não era "diferido para o commit" — era estado de instância corrompido. A lição: quando um workaround "resolve" um bug, confirmar o **mecanismo**; um diagnóstico errado propaga-se (o try/catch foi reintroduzido em `EliminarUtilizadorAction` a acreditar que funcionava).
- **Testes rápidos escondem divergências de ambiente.** O ramo soft-delete era invisível porque (a) o FK estava `SET NULL` em SQLite e (b) nenhum teste o tocava. Um "verde" com 100% coverage não garante paridade com prod se o motor de BD diverge. Medir e forçar paridade (Opção A da migration, e o #77) é o que fecha o buraco.
- **RMB vs resolução manual é uma decisão de fronteira.** O `withTrashed()` pertence à **rota** (binding) e à **Action** (invocação programática), não ao controller nem ao FormRequest. Tê-lo em dois sítios (Request + Action) era duplicação; empurrá-lo para a rota deixa o controller consistente e a Action com o padrão dual `Modelo|string`.
- **Verificar suposições sobre eventos com código, não com intuição.** As três suposições do Brief sobre o audit trail (`forceDelete` não regista; `restore` regista) só ficaram fiáveis depois de as medir num probe descartável. `restore` regista `restored` (spatie detecta SoftDeletes); `forceDelete` regista `deleted` (fira `deleting`/`deleted` na mesma). Uma delas estava errada no Brief.
- **`fresh()` como reset de estado de modelo.** Quando uma operação Eloquent falha a meio e deixa flags internas inconsistentes (`forceDeleting`, `exists`), reidratar com `fresh()` é mais seguro e legível do que tentar repor estado protegido — e o `?->` satisfaz o Larastan sem ramo morto observável.

## SYSTEM_SPEC a actualizar

- `docs/system_spec/00-index.md` — nova slice `Restaurar` na feature Entidade; nova migration; actualizar contagem de Actions/rotas.
- `docs/system_spec/01-features/entidade.md` — `RestaurarEntidadeAction`, filtro `?estado=`, Padrão B com `fresh()`.
- `docs/system_spec/05-routes/entidades.md` — rota `PATCH /entidades/{entidade}/restaurar` + `withTrashed` no apiResource.
- `docs/system_spec/02-shared/soft-delete.md` — **já actualizado** (RMB por omissão; armadilha `forceDeleting`/`fresh()`).
- `openapi.yaml` — endpoint `restaurar` + parâmetro `estado` no GET `/entidades`.

## Verificação final

- [x] Linter a verde (Pint + Rector)
- [x] Testes a verde (696 testes, 100% coverage + type-coverage, Larastan 9, arch) — SQLite **e** MySQL, Redis real
- [x] Nenhum dado sensível em logs (`nif` excluído do audit; logs só com `id`)
- [x] Nenhum segredo em código
- [x] Autorização dupla camada (`Gate::authorize` no Request **e** na Action)
