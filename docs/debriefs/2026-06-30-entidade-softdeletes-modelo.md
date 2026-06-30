# Debrief — Issue #69: Entidade SoftDeletes (Model Layer)

**Data:** 2026-06-30
**Branch:** `feat/entidade-softdeletes-modelo`
**Issue:** [#69](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/69)
**Resultado:** ✅ 603 testes · 100% cobertura · Larastan 9 zero erros

---

## O que foi implementado

### Migrations
- **`add_softdeletes_to_entidades_table`** — adiciona coluna `deleted_at` (nullable timestamp) à tabela `entidades`.
- **`update_fk_constraints_entidades_in_documentos`** — altera `id_fornecedor` e `id_cliente` de `nullOnDelete` para `restrictOnDelete`. Guarded para SQLite (`DB::getDriverName() === 'sqlite'`) — ver decisão abaixo.

### Model `Entidade`
- Trait `SoftDeletes` adicionado; `delete()` passa a fazer soft delete automaticamente.
- `@property-read ?Carbon $deleted_at` adicionado ao PHPDoc.

### Model `Documento`
- `fornecedor()` e `cliente()` agora terminam em `->withTrashed()` — documentos históricos continuam a carregar a entidade mesmo após soft delete.

### Factory `EntidadeFactory`
- State `inativa()` — `->state(['deleted_at' => now()])`. Apenas define o atributo (não chama `delete()`), pelo que não dispara eventos do `RegistaActividade`.

### Resource `EntidadeResource`
- Campo `deleted_at` adicionado (7.º campo). Valor: `null` se activa, string ISO 8601 se inactiva.

### Testes
- `EntidadeTest` — nova secção `SoftDeletes` (3 testes: soft-deleta, exclui por defeito, state `inativa`).
- `EntidadeResourceTest` — actualizado para 7 campos; 2 novos testes (`deleted_at null` activa, ISO 8601 inactiva).
- `EliminarEntidadeActionTest` — `assertDatabaseMissing` → `assertSoftDeleted` nos 2 testes de eliminação.
- `EliminarEntidadeTest` (Feature) — `assertDatabaseMissing` → `assertSoftDeleted`.
- `DocumentoTest` — removidos os testes `nullOnDelete` para fornecedor/cliente; adicionados testes `withTrashed` (entidade inactiva continua a ser carregada).
- `CriarEntidadeTest` (Feature) — `->where('deleted_at', null)` adicionado ao `AssertableJson` (entidade recém-criada é activa).

---

## Decisões tomadas

### D1 — Migration FK guarded para SQLite
SQLite não suporta `dropForeign` por nome (`This database driver does not support dropping foreign keys by name`). A migration `update_fk_constraints_entidades_in_documentos` faz um early return quando `DB::getDriverName() === 'sqlite'`. A constraint `restrictOnDelete` só é relevante em MySQL/prod — impede hard delete acidental de uma entidade com documentos. Em SQLite (dev/testes), os testes de comportamento (`withTrashed`) cobrem o cenário de soft delete de forma adequada. Esta abordagem é o padrão Laravel para migrações de FK incompatíveis com SQLite.

### D2 — `inativa()` em vez de `trashed()` built-in
Laravel 13 disponibiliza `Factory::trashed()` built-in. Optámos por `inativa()` nomeado em PT-PT — consistente com as convenções de nomenclatura do projecto e torna o código de testes mais legível no contexto de domínio.

### D3 — `EliminarEntidadeAction` sem alteração de código
A Action chama `$entidade->delete()`. Com o trait `SoftDeletes` no model, este call passa a fazer soft delete automaticamente — sem qualquer alteração na Action. O mecanismo Eloquent absorve a mudança de comportamento transparentemente.

### D4 — FK `restrictOnDelete` como rede de segurança
Mesmo com SoftDeletes, um `forceDelete()` acidental numa entidade com documentos seria destrutivo. `restrictOnDelete` em MySQL impede-o a nível de BD. Em SQLite, a proteção não existe (no-op), mas os testes de integração com `withTrashed` cobrem o comportamento correcto.

---

## Desvios à spec

Nenhum. A spec foi seguida integralmente. A única adição não prevista explicitamente foi a necessidade de actualizar `CriarEntidadeTest` (Feature) para incluir `deleted_at: null` no `AssertableJson` — consequência directa de o resource passar a ter 7 campos.

---

## Aprendizagens

### SoftDeletes altera comportamento de toda a camada Eloquent
Adicionar `use SoftDeletes` ao model não só muda o `delete()` — altera silenciosamente o `all()`, `find()`, `where()`, e todas as queries por defeito (excluem registos com `deleted_at IS NOT NULL`). Isto é poderoso mas pode surpreender: um teste que cria uma entidade `inativa()` e depois conta `Entidade::count()` vê 0 — não 1.

### `withTrashed()` em `belongsTo` preserva integridade histórica
Sem `withTrashed()`, um `Documento->fornecedor` devolve `null` após o soft delete da entidade — como se o documento nunca tivesse tido fornecedor. Com `withTrashed()`, a relação continua a carregar a entidade inactiva. Esta é a forma correcta de preservar integridade histórica num modelo contabilístico.

### Factory states vs `delete()` — sem eventos não planeados
`->state(['deleted_at' => now()])` define o atributo directamente, sem passar pelo ciclo de vida do model. `$entidade->delete()` dispara eventos (`deleting`, `deleted`, `RegistaActividade`). Para testes que precisam de uma entidade já inactiva sem ruído de eventos de auditoria, o `->state()` é a abordagem correcta.

### Migration FK em SQLite — guard por driver é o padrão
Laravel SQLite não suporta `dropForeign` por nome. A solução idiomática é guardar a migration com `DB::getDriverName() === 'sqlite'`. Isto é diferente de uma limitação a corrigir — é uma característica do SQLite que se aceita por design em projectos que usam SQLite apenas em dev/testes.
