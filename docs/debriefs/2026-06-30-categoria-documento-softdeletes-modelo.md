# Debrief — Issue #70
## CategoriaDocumento — model layer (SoftDeletes + migration deleted_at + FK restrictOnDelete + relação withTrashed)

**Data:** 2026-06-30
**Branch:** feat/categoria-documento-softdeletes-modelo
**Issue:** #70

---

## Resumo

Aplicação do padrão SoftDeletes ao Model `CategoriaDocumento`, replicando exactamente o que foi feito na Issue #69 para `Entidade`. Alterações:

- 2 migrations (coluna `deleted_at` + alteração FK `id_categoria` de `nullOnDelete` para `restrictOnDelete`)
- Model `CategoriaDocumento` — trait `SoftDeletes` + `@property-read ?Carbon $deleted_at`
- Model `Documento` — `categoria()` passa a usar `->withTrashed()`
- `CategoriaDocumentoFactory` — state `inativa()`
- `CategoriaDocumentoResource` — campo `deleted_at` (5.º campo; ISO 8601 ou `null`)
- 9 ficheiros de testes actualizados/criados

**Resultado:** 609 testes (↑6 vs Issue #69), 100% coverage, 100% type-coverage, Larastan 9 zero erros.

---

## Decisões tomadas

### SoftDeletes em vez de hard delete
Categorias financeiras são referenciadas historicamente por documentos. Hard delete quebraria o registo de classificação — o mesmo argumento da `Entidade`. Sem alternativas a avaliar.

### FK `restrictOnDelete` como rede de segurança
Com SoftDeletes activo, `forceDelete()` de uma categoria referenciada em documentos activos seria um erro silencioso sem constraint. `restrictOnDelete` bloqueia isso em MySQL/prod. SQLite recebe guard para não falhar nos testes.

### `withTrashed()` na relação `Documento::categoria()`
Sem `withTrashed()`, a relação devolvia `null` após soft delete da categoria, destruindo o histórico de classificação. A solução é idêntica à de `fornecedor()`/`cliente()` na Issue #69.

### Factory state `inativa()` vs `trashed()` built-in
O Laravel tem `trashed()` como state built-in. Optámos por `inativa()` para coerência com `EntidadeFactory` e com o vocabulário de domínio em PT.

### Testes colaterais descobertos em T11
Ao correr `composer test`, descobriu-se que 4 testes existentes falhavam por causa do campo `deleted_at` novo no Resource (asserções estritas `AssertableJson` sem `->etc()`). Todos corrigidos adicionando `->where('deleted_at', null)`. Também o `EliminarCategoriaActionTest` usava `assertDatabaseMissing` em 2 lugares — ambos trocados para `assertSoftDeleted`.

---

## O que correu bem

- Padrão estabelecido na #69 foi aplicado mecanicamente sem ambiguidades
- Guard SQLite nas migrations de FK funcionou correctamente
- Pipeline verde na primeira execução de `composer test` após as correções dos testes colaterais

---

## O que foi inesperado

Os 4 testes de feature/unit que falharam em T11 não eram óbvios antes de correr a pipeline. São uma consequência directa do campo novo no Resource e de asserções estritas — o padrão certo é sempre adicionar `->where('deleted_at', null)` quando se adiciona um campo ao Resource e existem testes com `AssertableJson` sem `->etc()`.

---

## Aprendizagens

**Vertical Slice e propagação de mudanças no Resource:** Quando se adiciona um campo a um Resource, é necessário actualizar *todos* os testes que verificam a estrutura JSON da resposta — não só os testes do Resource em si, mas também os testes de feature que chamam o endpoint. O `AssertableJson` em modo estrito (sem `->etc()`) falha silenciosamente se houver campos extra não declarados. A solução sistemática é: ao adicionar um campo ao Resource, procurar todos os usos de `->has('data', ...)` nas feature tests e verificar se precisam de incluir o campo novo.

**SoftDeletes e `assertDatabaseMissing`:** Qualquer Action de eliminação que use SoftDeletes tem os seus testes com `assertDatabaseMissing` a falhar após a activação — o registo continua na BD. O padrão correcto é `assertSoftDeleted`. Este é um erro previsível e deve ser verificado sistematicamente em todos os testes de eliminação ao activar SoftDeletes num Model.

---

## Ficheiros alterados

| Ficheiro | Tipo | Alteração |
|---|---|---|
| `database/migrations/2026_06_30_143925_add_softdeletes_to_categorias_documento_table.php` | Nova migration | `softDeletes()` em `categorias_documento` |
| `database/migrations/2026_06_30_144621_update_fk_constraint_categoria_in_documentos.php` | Nova migration | FK `nullOnDelete` → `restrictOnDelete` (guard SQLite) |
| `app/Models/CategoriaDocumento.php` | Model | `SoftDeletes` trait + `@property-read ?Carbon $deleted_at` |
| `app/Models/Documento.php` | Model | `categoria()->withTrashed()` |
| `database/factories/CategoriaDocumentoFactory.php` | Factory | State `inativa()` |
| `app/Features/CategoriaDocumento/CategoriaDocumentoResource.php` | Resource | Campo `deleted_at` |
| `tests/Unit/Models/CategoriaDocumentoTest.php` | Teste | `describe('SoftDeletes')` + state `inativa` |
| `tests/Unit/Features/CategoriaDocumento/CategoriaDocumentoResourceTest.php` | Teste | Testes para `deleted_at` |
| `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php` | Teste | `assertSoftDeleted` |
| `tests/Unit/Models/DocumentoTest.php` | Teste | Relação `categoria` com `withTrashed` |
| `tests/Unit/Features/CategoriaDocumento/EliminarCategoriaActionTest.php` | Teste | `assertSoftDeleted` (2 lugares) |
| `tests/Feature/Features/CategoriaDocumento/CriarCategoriaTest.php` | Teste | `->where('deleted_at', null)` |
| `tests/Feature/Features/CategoriaDocumento/VerCategoriaTest.php` | Teste | `->where('deleted_at', null)` |
| `tests/Feature/Features/CategoriaDocumento/ActualizarCategoriaTest.php` | Teste | `->where('deleted_at', null)` |

---

## Critérios de aceitação

| ID | Estado |
|---|---|
| CA-01 | ✅ |
| CA-02 | ✅ |
| CA-03 | ✅ |
| CA-04 | ✅ |
| CA-05 | ✅ |
| CA-06 | ✅ |
| CA-07 | ✅ |
| CA-08 | ✅ |
| CA-09 | ✅ |
| CA-10 | ✅ |
| CA-11 | ✅ |
| CA-12 | ✅ 609/609 · 100% coverage · 100% type-coverage · Larastan 0 erros |
