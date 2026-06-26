# Plano — Issue #56: EtapaDocumento — Camada de Modelo

**Data:** 2026-06-25 · **Issue:** #56 · **Branch:** `feat/etapa-documento-modelo`
**Modo:** SDD Activo — checkpoint por tarefa + `composer test` no fim de cada tarefa.

> Referências: Brief `docs/briefs/2026-06-25-etapa-documento-modelo.md` ·
> Spec `docs/specs/2026-06-25-etapa-documento-modelo.md`.

---

## Tarefas

### T1 — Migration `etapas_documento`
- `php artisan make:migration create_etapas_documento_table --no-interaction`.
- Implementar conforme Spec §1: `uuid id` PK; `foreignUuid id_documento`→`documentos` `cascadeOnDelete`;
  `string('estado',50)->index()`; `text motivo nullable`; `foreignId id_utilizador`→`users`
  `nullable nullOnDelete`; **só** `timestamp('created_at')->nullable()` (sem `timestamps()`).
- `down()`: `dropIfExists`.
- `php artisan migrate` (verde) → cobre **CA-01, CA-02 (parte BD), CA-05**.
- **Lint+refactor+test** → checkpoint.

### T2 — Model `EtapaDocumento`
- `php artisan make:model EtapaDocumento --no-interaction` (sem `-m`, migration já existe).
- Implementar conforme Spec §2: atributos `#[Table]`/`#[Fillable]`, `HasUuids`, `HasFactory`,
  `const UPDATED_AT = null`, `casts()` (`estado`), `documento()`/`utilizador()` `belongsTo`,
  PHPDoc `@property-read` completo (`id_utilizador` `?int`, `utilizador` `?User`).
- **Não** adicionar `RegistaActividade`.
- Cobre **CA-02 (Model), CA-03**.
- **Lint+refactor+test** → checkpoint.

### T3 — Relação `Documento->historico`
- Em `app/Models/Documento.php`: adicionar `historico(): HasMany` (`->orderBy('created_at')`),
  `use ...HasMany;`, e `@property-read Collection<int, EtapaDocumento> $historico` no PHPDoc.
- Cobre **CA-04**.
- **Lint+refactor+test** → checkpoint.

### T4 — Factory `EtapaDocumentoFactory`
- `php artisan make:factory EtapaDocumentoFactory --model=EtapaDocumento --no-interaction`.
- Implementar conforme Spec §4: base `Pendente`; states `processado/erro/perigoso/manual`;
  PHPDoc array shape no `definition()`.
- Cobre **CA-06**.
- **Lint+refactor+test** → checkpoint.

### T5 — Testes do Model + Factory
- `tests/Unit/Models/EtapaDocumentoTest.php` (novo) conforme Spec §5: blocos `Model`, `Append-only`,
  `Casts`, `Relações` (com `cascadeOnDelete` e `nullOnDelete`), `Factory — states` (dataset).
- Adição ao `tests/Unit/Models/DocumentoTest.php`: bloco `historico` ordenado.
- `uses(RefreshDatabase::class)` nos blocos de persistência.
- Cobre **CA-04 (verificação), CA-06 (verificação)**.
- **Lint+refactor+test** → checkpoint.

### T6 — Fecho de qualidade
- `composer test` completo: 100% coverage + 100% type coverage + Larastan 9 zero erros +
  Pint/Rector limpos + ArchTests verdes.
- Cobre **CA-07**.
- Confirmar que nenhuma SYSTEM_SPEC foi tocada nesta fase (fica para Fase 3a).
- Checkpoint final ②.

---

## Ordem e dependências
T1 → T2 → T3 → T4 → T5 → T6 (sequencial; T3 depende do Model T2; testes T5 dependem de T2-T4).

## Riscos a vigiar durante a implementação
- **R1** (resolvido): FK `id_utilizador` = `foreignId`→`users`. Confirmar que `make:model`/migration
  não reintroduzem `uuid`.
- **R4**: `const UPDATED_AT = null` — se Larastan reclamar, garantir tipo `?string` e `#[\Override]`
  não aplicável a constantes (é constante, não método).
- Verificar em T5 que `create()` numa `EtapaDocumento` não tenta escrever `updated_at`
  (erro de coluna inexistente em MySQL) — valida CA-02 na prática.

## Definição de pronto
Todos os CA-01..CA-07 verdes; Brief/Spec/Plano commitados; `composer test` verde;
nenhuma SYSTEM_SPEC alterada (Fase 3a). Próximo: `/documenta-implementacao #56`.
