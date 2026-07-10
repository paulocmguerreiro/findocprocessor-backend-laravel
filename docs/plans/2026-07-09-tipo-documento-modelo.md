# Plano — Issue #84: TipoDocumento — Camada de Modelo

**Data:** 2026-07-09
**Issue:** #84
**Branch:** `feat/tipo-documento-modelo`
**Spec:** `docs/specs/2026-07-09-tipo-documento-modelo.md`

---

## Ordem de implementação

**T1** Enum → **T2** Migration tabela → **T3** Migration permissions → **T4** Policy → **T5** Model → **T6** Factory → **T7** DTOs → **T8** Resource → **T9** Testes → **T10** Verificação final.

> Após **cada** tarefa: `composer lint` + `composer refactor` antes do commit da tarefa. Checkpoint por tarefa.

---

### Tarefa 1 — Enum `PosicaoEmpresaMae`

**Ficheiro:** `app/Shared/Enums/PosicaoEmpresaMae.php`

- Criar manualmente (enum simples, sem `make:` equivalente): `Fornecedor = 'fornecedor'`, `Cliente = 'cliente'` — PHP 8.5 backed enum (string), cases TitleCase PT.

**Verificação:** `composer test:types`.

---

### Tarefa 2 — Migration `create_tipos_documento_table`

```
php artisan make:migration create_tipos_documento_table --no-interaction
```

- Tabela `tipos_documento` conforme Spec (modelo de dados): `id` uuid PK; `nome` string(255) único; `descricao` text; `id_categoria` uuid FK → `categorias_documento.id` com `restrictOnDelete()`; `posicao_empresa_mae` string(50); 4 booleans `espera_*` default `true`; `timestamps()`. Sem `deleted_at`.
- Comentários de coluna em PT (padrão de `create_categorias_documento_table`).

**Verificação:** `php artisan migrate --no-interaction` sem erros.

---

### Tarefa 3 — Migration `seed_tipos_documento_permissions`

```
php artisan make:migration seed_tipos_documento_permissions --no-interaction
```

- Mesmo padrão de `2026_06_26_120000_seed_documentos_permissions.php`: `forgetCachedPermissions()`, criar `tipos-documento.{ver,criar,actualizar,eliminar}`, `admin` recebe todas via `givePermissionTo($novasPermissions)`, `utilizador` recebe só `tipos-documento.ver`. `down()` remove as 4 permissions.

**Verificação:** `php artisan migrate --no-interaction` sem erros; `Role::findByName('admin')->hasPermissionTo('tipos-documento.criar')` verdadeiro.

---

### Tarefa 4 — Policy `TipoDocumentoPolicy`

```
php artisan make:policy TipoDocumentoPolicy --model=TipoDocumento --no-interaction
```

- `final class` com `viewAny`, `view`, `create`, `update`, `delete` — cada um `hasPermissionTo('tipos-documento.<accao>')` (nunca `return true`). Sem `restore`.
- Referencia `App\Models\TipoDocumento` antes de T5 existir — aceitável (autoload resolve; Larastan completo fecha após T5, como no precedente #45).

**Verificação:** `php -l`; Larastan completo adiado para depois de T5.

---

### Tarefa 5 — Model `TipoDocumento`

```
php artisan make:model TipoDocumento --no-interaction
```

- `@property-read` completo (todas as colunas + `?CategoriaDocumento $categoria`); `#[Table('tipos_documento')]`; `#[Fillable([...])]` com as 8 colunas de dados; `#[UsePolicy(TipoDocumentoPolicy::class)]`.
- Traits: `HasFactory`, `HasUuids`, `RegistaActividade` (audit trail, sem campos excluídos — nada sensível).
- `casts()`: `posicao_empresa_mae` → `PosicaoEmpresaMae::class`; 4 `espera_*` → `'boolean'`.
- `categoria(): BelongsTo` → `belongsTo(CategoriaDocumento::class, 'id_categoria')->withTrashed()`.
- Sem relação inversa em `CategoriaDocumento`.

**Verificação:** `composer test:types` **agora verde** (fecha o ciclo Policy ↔ Model).

---

### Tarefa 6 — Factory `TipoDocumentoFactory`

```
php artisan make:factory TipoDocumentoFactory --model=TipoDocumento --no-interaction
```

- `definition()`: `id_categoria => CategoriaDocumento::factory()`, `nome`/`descricao` via faker, `posicao_empresa_mae => $this->faker->randomElement(PosicaoEmpresaMae::cases())`, os 4 `espera_*` a `true`. Sem states adicionais (conforme Spec/Brief).

**Verificação:** `TipoDocumento::factory()->make()` não lança; `TipoDocumento::factory()->create()` persiste com `id_categoria` válido.

---

### Tarefa 7 — DTOs `CriarTipoDocumentoDto` + `ActualizarTipoDocumentoDto`

**Ficheiros:** `app/Features/TipoDocumento/Criar/CriarTipoDocumentoDto.php`, `app/Features/TipoDocumento/Actualizar/ActualizarTipoDocumentoDto.php`

- `final readonly class`; propriedades: `nome`, `descricao`, `idCategoria` (string), `posicaoEmpresaMae` (`PosicaoEmpresaMae`), `esperaDataDocumento`/`esperaFornecedor`/`esperaCliente`/`esperaValor` (bool).
- Construtor valida: `nome`/`descricao`/`idCategoria` não-vazios (trim) → `\InvalidArgumentException`; e **pelo menos um** dos 4 `espera_*` `true` — se todos `false`, `\InvalidArgumentException`. `@throws` declarado.
- `ActualizarTipoDocumentoDto` estrutura idêntica (update completo/PUT). **Sem** `fromRequest()`.

**Verificação:** `composer test:types`.

---

### Tarefa 8 — Resource `TipoDocumentoResource`

**Ficheiro:** `app/Features/TipoDocumento/TipoDocumentoResource.php`

- `final class extends JsonResource`, `@mixin TipoDocumento`, array shape no PHPDoc de `toArray()`.
- Campos: `id`, `nome`, `descricao`, `categoria` (`CategoriaDocumentoResource::make($this->whenLoaded('categoria'))`), `tipo_movimento` (`$this->categoria?->tipo_movimento?->value` — derivado, `string|null`), `posicao_empresa_mae` (`->value`), 4 `espera_*` (bool directo), `criado_em`/`actualizado_em` (`toIso8601String()`).

**Verificação:** `composer test:types`.

---

### Tarefa 9 — Testes unitários

Criar via `php artisan make:test --pest <Nome> --no-interaction` e mover para os caminhos abaixo (padrão dual não se aplica — camada de modelo sem HTTP, conforme `07-testing.md` e precedente #45):

- `tests/Unit/Models/TipoDocumentoTest.php` — UUID PK; `#[Fillable]`; casts (`posicao_empresa_mae` → enum, 4 booleans); relação `categoria()` (carrega, `withTrashed()` traz categoria soft-deleted); FK `restrictOnDelete()` (apagar `CategoriaDocumento` referenciada por um `TipoDocumento` lança `QueryException`); factory produz instância válida.
- `tests/Unit/Policies/TipoDocumentoPolicyTest.php` — matriz `admin` (todas as abilities permitidas) vs `utilizador` (só `viewAny`/`view` permitidas, `create`/`update`/`delete` negadas) — padrão de `CategoriaDocumentoPolicyTest.php`.
- `tests/Unit/Features/TipoDocumento/CriarTipoDocumentoDtoTest.php` — happy path; excepção `nome` vazio; excepção `descricao` vazia; excepção `idCategoria` vazio; excepção com os 4 `espera_*` a `false`; aceita com apenas 1 `espera_*` `true`.
- `tests/Unit/Features/TipoDocumento/ActualizarTipoDocumentoDtoTest.php` — idem.
- `tests/Unit/Features/TipoDocumento/TipoDocumentoResourceTest.php` — todos os campos presentes com tipos correctos; `categoria` ausente (sem `whenLoaded`) vs presente; `tipo_movimento` derivado correctamente da categoria carregada; `tipo_movimento` `null` quando `categoria` não carregada.

**Verificação:** `composer test` — 100% coverage + 100% type coverage.

---

### Tarefa 10 — Verificação final + pipeline

```bash
composer lint
composer refactor
composer test
```

- Zero erros Larastan 9; 100% coverage; 100% type coverage; Rector sem sugestões pendentes; Pint limpo.
- ArchTest verde (`PosicaoEmpresaMae` excluído da regra "actions are final" — é enum; DTOs `final readonly`).

---

## Testes a escrever

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| UUID PK, fillable, casts, relação, FK restrict | unit | `tests/Unit/Models/TipoDocumentoTest.php` | CA-02, CA-12 |
| Matriz autorização admin/utilizador | unit | `tests/Unit/Policies/TipoDocumentoPolicyTest.php` | CA-04, CA-05 |
| Invariantes construtor (happy + 5 excepções) | unit | `tests/Unit/Features/TipoDocumento/CriarTipoDocumentoDtoTest.php` | CA-06, CA-07, CA-09 |
| Invariantes construtor (happy + 5 excepções) | unit | `tests/Unit/Features/TipoDocumento/ActualizarTipoDocumentoDtoTest.php` | CA-06, CA-07, CA-09 |
| Serialização completa + categoria loaded/unloaded | unit | `tests/Unit/Features/TipoDocumento/TipoDocumentoResourceTest.php` | CA-08, CA-10 |

## Dependências

- Issues bloqueantes: nenhuma.
- Deve ser implementada após: nenhuma.

## Riscos de implementação

> Consolidados do Brief e da Spec.

- Invariante cross-field "pelo menos um `espera_*` true" só garantido no DTO nesta issue — sem `FormRequest` ainda; revisitar na issue de lógica (#57-like) se deve replicar no `FormRequest` para 422 amigável.
- FK `id_categoria` obrigatória com `restrictOnDelete()` — Factory tem de criar sempre `CategoriaDocumento::factory()` associada; teste dedicado confirma que `restrictOnDelete()` bloqueia a eliminação.
- Cast do enum `PosicaoEmpresaMae` via `casts()` — nunca comparação directa a string.
- Sem SoftDelete nesta issue (tabela ainda não é referenciada por FK de nenhuma tabela filha) — coerente com `00-convencoes-models.md`.

## O que NÃO fazer nesta issue

- Sem Actions, Controller, FormRequests, Events, Jobs, rotas API.
- Sem Repository/interface (CRUD simples).
- Sem `fromRequest()` nos DTOs.
- Sem alterações a `Documento`, `CategoriaDocumento` ou ao `openapi.yaml`.
- Sem relação inversa `hasMany` em `CategoriaDocumento`.
