# Brief — Issue #70
## CategoriaDocumento — model layer (SoftDeletes + migration deleted_at + FK restrictOnDelete + relação withTrashed)

**Data:** 2026-06-30
**Issue:** #70
**Branch:** feat/categoria-documento-softdeletes-modelo
**Tipo:** feat · scope:domain · stack:laravel · prio:p2

---

## Contexto

`CategoriaDocumento` classifica documentos financeiros (ex: Factura, Recibo). O comportamento actual de `nullOnDelete` na FK `id_categoria` deixa documentos sem classificação ao eliminar uma categoria — incoerente para auditoria e histórico.

Esta issue aplica o mesmo padrão da Issue #69 (Entidade SoftDeletes): em vez de hard delete, a categoria fica inactiva com `deleted_at` preenchido, a FK muda para `restrictOnDelete` como rede de segurança, e a relação `Documento::categoria()` passa a usar `withTrashed()` para que documentos com categorias inactivas continuem a carregar correctamente.

---

## Decisões de arquitectura

### SoftDeletes vs hard delete
**Decisão:** SoftDeletes via `Illuminate\Database\Eloquent\SoftDeletes`.  
**Porquê:** Idêntico à Issue #69 — mantém integridade histórica sem apagar registos. A categoria pode ter estado associada a centenas de documentos; a sua remoção física quebraria o histórico de classificação.

### FK `nullOnDelete` → `restrictOnDelete`
**Decisão:** Alterar constraint na migração, com guard SQLite (idêntico ao padrão #69).  
**Porquê:** Com SoftDeletes activo, uma categoria nunca é hard-deleted. A constraint `restrictOnDelete` serve de rede de segurança em MySQL/prod para bloquear `forceDelete()` acidental. Em SQLite (testes), o guard é necessário pois SQLite não suporta `dropForeign` por nome.

### `Documento::categoria()` → `withTrashed()`
**Decisão:** Adicionar `->withTrashed()` à relação `belongsTo`.  
**Porquê:** Sem `withTrashed()`, a relação devolve `null` para categorias inactivas, apagando a classificação histórica do documento.

### Factory state: `inativa` (não `trashed`)
**Decisão:** State explícito `inativa()` com `['deleted_at' => now()]`.  
**Porquê:** Coerência com o padrão estabelecido em `EntidadeFactory`. Laravel tem um state built-in `trashed()`, mas `inativa` é mais expressivo no domínio PT e consistente com o vocabulário já estabelecido.

---

## Componentes afectados

| Componente | Alteração |
|---|---|
| Migration `add_softdeletes_to_categorias_documento_table` | Nova — `$table->softDeletes()` |
| Migration `update_fk_constraint_categoria_in_documentos` | Nova — `nullOnDelete` → `restrictOnDelete` com guard SQLite |
| `app/Models/CategoriaDocumento.php` | Adicionar `SoftDeletes` + `@property-read ?Carbon $deleted_at` |
| `app/Models/Documento.php` | `categoria()` → `->withTrashed()` |
| `database/factories/CategoriaDocumentoFactory.php` | Adicionar state `inativa()` |
| `app/Features/CategoriaDocumento/CategoriaDocumentoResource.php` | Adicionar campo `deleted_at` |
| `tests/Unit/Models/CategoriaDocumentoTest.php` | Adicionar `describe('SoftDeletes')` + `describe('Factory — states')` `inativa` |
| `tests/Unit/Features/CategoriaDocumento/CategoriaDocumentoResourceTest.php` | Adicionar teste para `deleted_at` |
| `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php` | `assertDatabaseMissing` → `assertSoftDeleted` |
| `tests/Unit/Models/DocumentoTest.php` (se existir) | Verificar relação `categoria` com `inativa` |

---

## Riscos identificados

- **Migração FK em SQLite** — `dropForeign` não é suportado; o guard `if (DB::getDriverName() === 'sqlite') return;` é obrigatório (padrão #69).
- **`EliminarCategoriaTest`** — usa `assertDatabaseMissing` que falha após SoftDeletes activado; tem de mudar para `assertSoftDeleted`.
- **`CategoriaDocumentoResource`** — o campo `deleted_at` é `?Carbon`; deve ser serializado como `?->toIso8601String()` (null quando activa).

---

## Questões em aberto

Nenhuma — o padrão é totalmente definido pela Issue #69.

---

## Fora de âmbito

- Toggle ativar/inativar endpoint (issue separada — lógica layer)
- Listagem com/sem soft-deleted (issue de lógica)
- `openapi.yaml` — expor `deleted_at` no contrato (gerado na Fase 3a)

---

## Aprendizagens esperadas

Aplicação mecânica do padrão SoftDeletes — o foco é na consistência: guard SQLite nas migrations de FK, `withTrashed()` nas relações, `inativa()` como state de factory, e actualização de testes de eliminação de `assertDatabaseMissing` para `assertSoftDeleted`.
