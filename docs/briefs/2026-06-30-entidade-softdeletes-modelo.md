# Brief — Issue #69: Entidade SoftDeletes (Model Layer)

**Data:** 2026-06-30
**Branch:** `feat/entidade-softdeletes-modelo`
**Issue:** [#69](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/69)
**Tipo:** feat · scope:domain · prio:p2

---

## Problema

`EliminarEntidadeAction` executa um hard delete em `Entidade`. As FKs `id_fornecedor` e `id_cliente` em `documentos` estão configuradas com `nullOnDelete`, o que anula a referência quando a entidade é removida. Uma factura perde o fornecedor/cliente apenas porque o parceiro saiu da carteira activa — viola integridade referencial histórica.

## Solução

Adicionar `SoftDeletes` ao model `Entidade`:
- A eliminação passa a ser um soft delete automático (`deleted_at` preenchido)
- As FKs em `documentos` mudam de `nullOnDelete` para `restrictOnDelete` — rede de segurança BD para impedir hard delete acidental
- As relações `fornecedor()` e `cliente()` em `Documento` usam `withTrashed()` para que documentos históricos continuem a carregar a entidade inactiva

A lógica completa do Padrão B (`forceDelete` + `QueryException` catch) e os endpoints de toggle e restauro ficam para issues de lógica separadas.

---

## Âmbito

**Dentro do âmbito (model layer):**
- Migration `add_softdeletes_to_entidades_table`
- Migration `update_fk_constraints_entidades_in_documentos`
- Model `Entidade` — trait `SoftDeletes` + `@property-read ?Carbon $deleted_at`
- Model `Documento` — `fornecedor()` e `cliente()` com `->withTrashed()`
- `EntidadeFactory` — state `inativa()`
- `EntidadeResource` — campo `deleted_at`
- Testes actualizados para todas as alterações acima

**Fora do âmbito:**
- `EliminarEntidadeAction` — sem alteração de código; `delete()` já faz soft delete automaticamente com o trait
- `RestaurarAction` / endpoint `PATCH /restaurar` (issue de lógica)
- Toggle ativar/inativar endpoint (issue de lógica)
- `ListarEntidadesAction` com `FiltroEstadoRegisto` (issue de lógica)
- Padrão B completo no `EliminarEntidadeAction` (issue de lógica)

---

## Contexto técnico

### Estado actual da BD (MySQL prod / Docker)

**Tabela `entidades`:** sem coluna `deleted_at`.

**Tabela `documentos`:** FKs actuais (confirmado via `database-schema`):
- `documentos_id_fornecedor_foreign` → `entidades.id` com `on_delete: set null`
- `documentos_id_cliente_foreign` → `entidades.id` com `on_delete: set null`

### Comportamento após alteração

| Acção | Antes | Depois |
|---|---|---|
| `$entidade->delete()` | Hard delete permanente | Soft delete (`deleted_at` preenchido) |
| `$entidade->forceDelete()` | Hard delete | Hard delete — bloqueado por `restrictOnDelete` se houver documentos |
| `Documento::find($id)->fornecedor` (entidade inactiva) | `null` (sem `withTrashed`) | Entidade carregada (com `withTrashed`) |
| `Entidade::all()` | Todas as entidades | Só activas (automático com SoftDeletes) |

### Impacto em testes existentes

Testes que falham após a alteração (requerem actualização):
1. `EliminarEntidadeActionTest` — `assertDatabaseMissing` → `assertSoftDeleted`
2. `EliminarEntidadeTest` (Feature) — `assertDatabaseMissing` → `assertSoftDeleted`
3. `DocumentoTest` — testes `nullOnDelete` para fornecedor/cliente → comportamento muda (soft delete não nulifica FK)
4. `EntidadeResourceTest` — validar que `deleted_at` está presente no resource

---

## Riscos identificados

**R1 — Migration FK em SQLite:** SQLite não suporta `ALTER TABLE ... DROP CONSTRAINT`. A migration de alteração de FKs usa `$table->dropForeign()` + re-add com `restrictOnDelete()`. No SQLite (testes), o `dropForeign` é no-op e o `restrictOnDelete` não é enforced em runtime — aceitável por design (ver spec `02-shared/soft-delete.md`). Os testes cobrem os dois ramos com factories.

**R2 — Factory `inativa()` e eventos do audit trail:** A spec menciona usar `Entidade::withoutEvents()` se o trait `RegistaActividade` emitir eventos em `deleted_at`. A verificar durante implementação — se o `inativa()` state usar `->state(['deleted_at' => now()])` (apenas configura atributo, sem chamar `delete()`), não dispara eventos e `withoutEvents` não é necessário.

**R3 — `assertDatabaseMissing` vs `assertSoftDeleted`:** O helper `assertSoftDeleted` verifica que `deleted_at IS NOT NULL`. Os testes de eliminação precisam de passar de `assertDatabaseMissing` para `assertSoftDeleted` + verificar que o registo ainda existe na BD com `assertDatabaseHas`.

**R4 — `trashed()` built-in vs state `inativa`:** Laravel 13 tem `Factory::trashed()` built-in. Optamos por `inativa()` como state nomeado (nome de domínio PT) que internamente usa `->state(['deleted_at' => now()])` — mais explícito e consistente com as convenções de nomenclatura do projecto.

---

## Questões em aberto

Nenhuma — âmbito completamente definido na issue. A issue confirma explicitamente que `EliminarEntidadeAction` não precisa de alteração de código nesta fase.

---

## Aprendizagens esperadas

- Como `SoftDeletes` altera o comportamento de `delete()`, `find()`, `all()` e relações
- O mecanismo de `withTrashed()` em `belongsTo` para preservar integridade histórica
- Alteração de FK constraints em MySQL via `dropForeign` + re-add
- Como testes com `RefreshDatabase` se comportam com soft-deleted records
