# Plano — Issue #1: CategoriaDocumento — Camada de Modelo

**Data:** 2026-06-08
**Issue:** #1
**Branch:** `feat/categoria-documento-modelo`
**Spec:** `docs/specs/2026-06-08-categoria-documento-modelo.md`

---

## Ordem de implementação

A sequência respeita dependências: Enum → Migration → Model → Factory → Testes.

---

### Tarefa 1 — Enum `TipoMovimento`

**Ficheiro:** `app/Shared/Enums/TipoMovimento.php`

```
php artisan make:class App/Shared/Enums/TipoMovimento --invokable=no
```

Ou criar manualmente (classe simples enum).

Conteúdo conforme Spec §2.

**Verificação:** `composer test:types` sem erros.

---

### Tarefa 2 — Migration `create_categorias_documento_table`

```
php artisan make:migration create_categorias_documento_table --no-interaction
```

Campos conforme Spec §1:
- `uuid('id')->primary()`
- `string('nome', 255)->index()`
- `string('slug', 255)->unique()`
- `string('tipo_movimento', 50)`
- `timestamps()`

**Verificação:** `php artisan migrate --no-interaction` sem erros.

---

### Tarefa 3 — Model `CategoriaDocumento`

```
php artisan make:model CategoriaDocumento --no-interaction
```

Substituir conteúdo gerado pela estrutura da Spec §3:
- Atributos `#[Table]`, `#[Fillable]`, `#[Casts]`
- Traits `HasFactory` + `HasUuids`
- Docblock `@property-read` completo

**Verificação:** `composer test:types` sem erros.

---

### Tarefa 4 — Factory `CategoriaDocumentoFactory`

```
php artisan make:factory CategoriaDocumentoFactory --model=CategoriaDocumento --no-interaction
```

Substituir conteúdo gerado pela estrutura da Spec §4:
- `definition()` com `faker->randomElement(TipoMovimento::cases())`
- States: `comMovimentoDebito()`, `comMovimentoCredito()`, `comMovimentoNeutro()`

**Verificação:** `CategoriaDocumento::factory()->make()` não lança excepção.

---

### Tarefa 5 — Testes unitários

```
php artisan make:test --pest CategoriaDocumentoTest --no-interaction
```

Mover para `tests/Unit/Models/` (artisan cria em `tests/Feature/` por omissão).

Grupos conforme Spec §5:

**Model (4 testes):**
- UUID como chave primária
- Fillable correcto
- Cast de `tipo_movimento` para `TipoMovimento`
- Tem timestamps

**Factory (4 testes):**
- Instância base válida
- State `comMovimentoDebito`
- State `comMovimentoCredito`
- State `comMovimentoNeutro`

---

### Tarefa 6 — Pipeline de qualidade

```bash
composer lint          # Pint — formatar
composer refactor      # Rector — modernizar
composer test:types    # Larastan nível 9
composer test          # pipeline completa
```

Corrigir todos os erros antes de avançar para commits.

---

### Tarefa 7 — Commits

Dois commits atómicos:

1. `feat: add TipoMovimento enum and CategoriaDocumento model layer`
   - Enum, Migration, Model, Factory

2. `test: add unit tests for CategoriaDocumento model and factory`
   - Ficheiro de testes

---

## Ficheiros a criar

| # | Ficheiro                                                            |
|---|---------------------------------------------------------------------|
| 1 | `app/Shared/Enums/TipoMovimento.php`                                |
| 2 | `database/migrations/..._create_categorias_documento_table.php`     |
| 3 | `app/Models/CategoriaDocumento.php`                                 |
| 4 | `database/factories/CategoriaDocumentoFactory.php`                  |
| 5 | `tests/Unit/Models/CategoriaDocumentoTest.php`                      |

## Ficheiros a NÃO modificar

- Nenhum ficheiro existente é alterado nesta issue.
- `docs/system_spec/02-shared.md` e `03-models.md` são actualizados na Fase 3 (`/documenta-implementacao`).
