# Debrief: CategoriaDocumento — lógica layer (restaurar + listar com inativas + Padrão B)

**Issue:** #72
**Branch:** feat/categoria-documento-restaurar-logica
**Data:** 2026-07-02
**Commits:** 15 commits (implementação) + brief/spec/plan

## O que foi implementado

Camada de lógica de SoftDelete para a feature `CategoriaDocumento`: endpoint de restauro, filtro de estado na listagem e Padrão B de eliminação — aplicando o padrão estabelecido na Issue #71 (Entidade).

- **`RestaurarCategoriaAction`** (nova) — `handle(CategoriaDocumento|string): CategoriaDocumento`; resolve com `withTrashed()->findOrFail()` (ramo string) ou RMB (ramo modelo), `Gate::authorize('restore')` fora da transação, `restore()` + invalidação de cache dentro.
- **`RestaurarCategoriaRequest`** (novo) + **`CategoriaDocumentoPolicy::restore()`** (reutiliza `categorias-documento.eliminar`).
- **`CategoriaDocumentoController::restaurar()`** + rota `PATCH /categorias-documento/{categorias_documento}/restaurar` com `->withTrashed()`.
- **`apiResource` com `->withTrashed(['show','update','destroy'])`** — RMB inclui soft-deleted nessas rotas.
- **Filtro `?estado=`** na listagem — trait `FiltravelPorEstadoRegisto` no model `CategoriaDocumento`; `ListarCategoriasAction` ganha 4.º parâmetro `FiltroEstadoRegisto` (scope + `estado` na chave de cache); `ListarCategoriasRequest` aceita campo `estado` com mensagem PT.
- **Padrão B** em `EliminarCategoriaAction`: `forceDelete()` com fallback `fresh()?->delete()` (não `$categoria->delete()`) quando há FK constraint — mesma correcção aplicada em #71.
- **Testes:** Restaurar (Unit + Feature, novos), Eliminar (dois ramos Padrão B), Listar (3 estados + 422). Suite global: **742 testes**, 100% coverage e type-coverage, Larastan 9 — verde em MySQL.

## Ficheiros alterados

| Ficheiro | Tipo | Notas |
| -------- | ---- | ----- |
| `app/Models/CategoriaDocumento.php` | alterado | trait `FiltravelPorEstadoRegisto` adicionado |
| `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php` | alterado | Padrão B: `forceDelete()` + catch `fresh()?->delete()` |
| `app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php` | alterado | 4.º param `FiltroEstadoRegisto`; scope `filtrarPorEstadoRegisto` + `estado` na chave de cache |
| `app/Features/CategoriaDocumento/Listar/ListarCategoriasRequest.php` | alterado | campo `estado` (`Rule::in(FiltroEstadoRegisto)`) + mensagem PT |
| `app/Features/CategoriaDocumento/Restaurar/RestaurarCategoriaAction.php` | criado | `CategoriaDocumento\|string`; `withTrashed()->findOrFail`; restore + cache |
| `app/Features/CategoriaDocumento/Restaurar/RestaurarCategoriaRequest.php` | criado | `Gate::authorize('restore', $this->route('categorias_documento'))` |
| `app/Features/CategoriaDocumento/CategoriaDocumentoController.php` | alterado | `restaurar()` novo + `index()` extrai `$filtroEstado` |
| `app/Policies/CategoriaDocumentoPolicy.php` | alterado | método `restore()` (reutiliza `categorias-documento.eliminar`) |
| `routes/api.php` | alterado | `apiResource->withTrashed(['show','update','destroy'])` + rota `/restaurar` com `->withTrashed()` |
| `tests/Unit/Features/CategoriaDocumento/RestaurarCategoriaActionTest.php` | criado | admin/UUID/RMB/rollback/403/401 |
| `tests/Feature/Features/CategoriaDocumento/RestaurarCategoriaTest.php` | criado | 200+Resource/404/403/401 |
| `tests/Unit/Features/CategoriaDocumento/EliminarCategoriaActionTest.php` | alterado | ramos hard delete (sem docs) + soft delete (com docs) |
| `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php` | alterado | idem (HTTP) |
| `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php` | alterado | 4.º arg `FiltroEstadoRegisto` + casos de estado |
| `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php` | alterado | `?estado=` (todos/inativos) + 422 |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| **`fresh()?->delete()`** no catch do Padrão B | `$categoria->delete()` sobre a mesma instância | `forceDelete()` não repõe `forceDeleting=true` ao lançar; um `delete()` na mesma instância faria hard delete novamente e relançava (500 em prod). `fresh()` hidrata uma instância limpa. Correcção estabelecida em #71. |
| **`FiltroEstadoRegisto`** em vez de `bool $incluirInativas` | Flag booleana mais simples | Consistência com Entidade e Utilizador; enum mais expressivo; chave de cache inclui `estado` — evita cache poisoning entre `?estado=todos` e `?estado=somente_ativos`. |
| **Reutiliza `categorias-documento.eliminar`** na `Policy::restore()` | Nova permissão `categorias-documento.restaurar` | Quem pode inactivar pode reactivar — sem nova migration de seed. Padrão de #71 e #73. |
| **`EliminarCategoriaAction` mantém `Log::info()`** | Remover o logging | Logging já existia no ficheiro antes de #72; não foi removido para não aumentar escopo. |
| **Padrão B sem migration adicional** | Nova migration de FK (como em #71) | A migration `update_fk_constraint_categoria_in_documentos` (#70) já impõe `restrictOnDelete` e a suite corre em MySQL (#77). Sem SQLite, não há divergência a corrigir. |

## Desvios ao Plano

- **Commit de refactor pós-testes** (`a654b19`): o primeiro commit do Padrão B usava `$categoria->delete()` no catch; após rever o debrief de #71, substituído por `fresh()?->delete()` para coerência com o padrão estabelecido. O plano já previa `fresh()` — o refactor foi uma correcção da implementação inicial.
- **Sem migration extra**: o plano mencionava verificar a FK `restrictOnDelete`; confirmado que a migration de #70 já a aplica e que a suite corre em MySQL. Não foi necessária nova migration.

## Aprendizagens

- **O padrão de SoftDelete ficou estável após a #71.** Aplicar `FiltravelPorEstadoRegisto` + Padrão B + `RestaurarAction` em `CategoriaDocumento` foi mecânico: o risco estava em divergir inadvertidamente de um dos detalhes (`fresh()`, `estado` na chave de cache, `withTrashed()` na rota). A checklist do spec impediu esses desvios. O valor do padrão documentado está em que a segunda aplicação é quase só "substituir `Entidade` por `CategoriaDocumento`".
- **Cache poisoning é silencioso.** Sem `estado` na chave de cache, um pedido `?estado=todos` guardaria no cache a lista completa sob a mesma chave que `?estado=somente_ativos` — e os testes passariam sem detectar o problema (o resultado chegaria antes do cache ser usado). Incluir **todos** os parâmetros que afectam o resultado na chave de cache é invariante de sistema, não opção.
- **`forceDeleting` como armadilha de estado de instância (confirmação).** Este é o segundo caso em que o Padrão B foi implementado com `$modelo->delete()` no catch e não funcionaria em produção. A lesson de #71 ficou registada em `soft-delete.md`, mas mesmo assim o primeiro commit desta issue repetiu o erro — só o refactor o corrigiu. Indica que a documentação deve incluir o **código correcto** de forma mais saliente (em vez de só descrever o problema).

## SYSTEM_SPEC a actualizar

- `docs/system_spec/01-features/categoria-documento.md` — `RestaurarCategoriaAction`, filtro `?estado=`, Padrão B, `ListarCategoriasAction` nova assinatura, Policy `restore()`, `RestaurarCategoriaRequest`.
- `docs/system_spec/05-routes/categorias-documento.md` — rota `PATCH /categorias-documento/{categorias_documento}/restaurar` + `withTrashed` no apiResource + query param `estado`.
- `docs/system_spec/00-index.md` — contagem de Actions (5→7) e Rotas (5→7) em `CategoriaDocumento`.
- `openapi.yaml` — endpoint `/categorias-documento/{id}/restaurar` + parâmetro `estado` no `GET /categorias-documento`.

## Verificação final

- [x] Linter a verde (Pint + Rector)
- [x] Testes a verde (742 testes, 100% coverage + type-coverage, Larastan 9, arch) — MySQL, Redis real
- [x] Nenhum dado sensível em logs (logs só com `id_utilizador`)
- [x] Nenhum segredo em código
- [x] Autorização dupla camada (`Gate::authorize` no Request **e** na Action)
