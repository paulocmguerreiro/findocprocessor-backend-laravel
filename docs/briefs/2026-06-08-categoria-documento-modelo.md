# Brief — Issue #1: CategoriaDocumento — Camada de Modelo

**Data:** 2026-06-08
**Issue:** #1
**Slug:** `categoria-documento-modelo`
**Branch:** `feat/categoria-documento-modelo`
**Tipo:** feat
**Labels:** prio:p2, scope:domain, stack:laravel, type:feat

---

## Contexto

`CategoriaDocumento` é a primeira entidade de domínio do sistema FinDocProcessor.
Classifica documentos financeiros (faturas, recibos, avisos, extractos) e determina
o tipo de movimento contabilístico que produzem. Sem categorias, não é possível
classificar documentos — é uma entidade de referência fundamental.

Esta issue cobre apenas a **camada de modelo**: migration, Model Eloquent, Enum e Factory.
Repositório e Actions são abordados em issues subsequentes.

---

## O que vai ser construído

| Componente        | Localização                                      | Descrição                                     |
|-------------------|--------------------------------------------------|-----------------------------------------------|
| Migration         | `database/migrations/..._create_categorias_documento_table.php` | Tabela com UUID, campos, índice único no slug |
| Enum              | `app/Shared/Enums/TipoMovimento.php`             | BackedEnum string: Debito, Credito, Neutro    |
| Model             | `app/Models/CategoriaDocumento.php`              | HasUuids, casts, fillable, @property-read     |
| Factory           | `database/factories/CategoriaDocumentoFactory.php` | 3 states por TipoMovimento                  |
| Testes unitários  | `tests/Unit/Models/CategoriaDocumentoTest.php`   | Model + Factory                               |

---

## Decisões técnicas relevantes

1. **UUID como PK** — `HasUuids` obrigatório per CLAUDE.md. Nunca ID incremental.
2. **SQLite em dev/testes** — não suporta `CHECK` constraints nativas; a validação do enum é feita pelo PHP via cast Eloquent, não por constraint na migration.
3. **Enum em `app/Shared/Enums/`** — `TipoMovimento` é partilhado entre features; não pertence a uma Feature slice específica.
4. **Model em `app/Models/`** — Eloquent Model na pasta convencional Laravel (não numa Feature slice, pois é usado por múltiplas features futuras).
5. **Cases em TitleCase PT** — `Debito`, `Credito`, `Neutro` (não `DEBITO`, não `debit`).
6. **`@property-read` completo** — PHPStan/Larastan nível 9 exige tipagem de todas as colunas no docblock do Model.

---

## Critérios de aceitação (da Issue)

- CA-01: Migration cria `categorias_documento` com todos os campos, `id` UUID, índice único em `slug`
- CA-02: `CategoriaDocumento` tem `HasUuids`, `#[Fillable]`, `@property-read` em todas as colunas
- CA-03: Cast de `tipo_movimento` para `TipoMovimento` enum funciona correctamente
- CA-04: `TipoMovimento` é `BackedEnum` string com cases `Debito`, `Credito`, `Neutro`
- CA-05: Factory base produz instância válida; cada state define `tipo_movimento` correctamente
- CA-06: `declare(strict_types=1)` em todos os ficheiros gerados
- CA-07: `composer test` passa sem erros (100% coverage, type coverage, Larastan 9)

---

## Fora de âmbito

- Repositório e interface de persistência (issue futura)
- Actions, Controller, Events (issue futura)
- Endpoints de API
- Seeder com categorias iniciais

---

## Riscos e invariantes

- Não usar `$table->unsignedBigInteger('id')->autoIncrement()` — usar `$table->uuid('id')->primary()`
- Índice único em `slug` é crítico para integridade referencial futura
- Factory states devem cobrir os 3 casos do enum para testes de cobertura
