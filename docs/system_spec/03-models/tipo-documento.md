# System Spec — Model: TipoDocumento

> `app/Models/TipoDocumento.php` · Tabela: `tipos_documento` · Issue #84

---

## Tabela `tipos_documento`

| Coluna | Tipo BD | Nullable | Índice | Notas |
|---|---|---|---|---|
| `id` | `uuid` PK | Não | PK | UUIDv7 via `HasUuids` |
| `nome` | `string(255)` | Não | único | Nome legível do tipo de documento |
| `descricao` | `text` | Não | — | Texto livre, guia a IA a categorizar correctamente |
| `id_categoria` | `uuid` FK | Não | FK | → `categorias_documento.id`; `restrictOnDelete()` + `cascadeOnUpdate()`; **obrigatória** (ao contrário de `documentos.id_categoria`, que é nullable) |
| `posicao_empresa_mae` | `string(50)` | Não | — | Cast → `PosicaoEmpresaMae` |
| `espera_data_documento` | `boolean` | Não (default `true`) | — | Indica se a IA deve extrair a data do documento |
| `espera_fornecedor` | `boolean` | Não (default `true`) | — | Indica se a IA deve extrair o fornecedor |
| `espera_cliente` | `boolean` | Não (default `true`) | — | Indica se a IA deve extrair o cliente |
| `espera_valor` | `boolean` | Não (default `true`) | — | Indica se a IA deve extrair o valor |
| `created_at` / `updated_at` | `timestamp` | Sim | — | `timestamps()`; **sem `deleted_at`** (sem SoftDelete nesta issue) |

**Notas:**
- `id_categoria` **obrigatório** (não nullable) — um `TipoDocumento` só existe associado a uma categoria.
- `restrictOnDelete()` — não é possível eliminar (hard delete) uma `CategoriaDocumento` referenciada por um `TipoDocumento`. Como `CategoriaDocumento` usa `SoftDeletes`, `delete()` normal (soft) nunca dispara esta constraint — só `forceDelete()`.
- Sem `deleted_at` — a tabela ainda não é referenciada por FK de nenhuma tabela filha (coerente com `00-convencoes-models.md`).
- `cascadeOnUpdate()` (migration `add_cascade_on_update_to_domain_fks`, 2026-07-14) — sem esta cascade, um `UPDATE` à PK da categoria falharia por violação de FK; prepara para uma futura reconciliação/agregação de bases de dados que precise de remapear UUIDs.
- **RN-02 (invariante cross-field):** pelo menos um dos 4 `espera_*` tem de ser `true` — validado no construtor dos DTOs (`CriarTipoDocumentoDto`/`ActualizarTipoDocumentoDto`), não na BD.

---

## Model `TipoDocumento`

**Ficheiro:** `app/Models/TipoDocumento.php`

```php
#[Table('tipos_documento')]
#[Fillable([
    'nome', 'descricao', 'id_categoria', 'posicao_empresa_mae',
    'espera_data_documento', 'espera_fornecedor', 'espera_cliente', 'espera_valor',
])]
#[UsePolicy(TipoDocumentoPolicy::class)]
class TipoDocumento extends Model
{
    use HasFactory, HasUuids, RegistaActividade;

    protected function casts(): array
    {
        return [
            'posicao_empresa_mae' => PosicaoEmpresaMae::class,
            'espera_data_documento' => 'boolean',
            'espera_fornecedor' => 'boolean',
            'espera_cliente' => 'boolean',
            'espera_valor' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaDocumento::class, 'id_categoria')->withTrashed();
    }
}
```

### PHPDoc `@property-read`

```php
/**
 * @property-read string             $id
 * @property-read string             $nome
 * @property-read string             $descricao
 * @property-read string             $id_categoria
 * @property-read PosicaoEmpresaMae  $posicao_empresa_mae
 * @property-read bool               $espera_data_documento
 * @property-read bool               $espera_fornecedor
 * @property-read bool               $espera_cliente
 * @property-read bool               $espera_valor
 * @property-read Carbon             $created_at
 * @property-read Carbon             $updated_at
 * @property-read ?CategoriaDocumento $categoria
 */
```

### Relações

```php
public function categoria(): BelongsTo // → CategoriaDocumento (id_categoria); withTrashed()
```

`withTrashed()` — mesmo padrão de `Documento::categoria()`: uma categoria soft-deletada continua acessível via o `TipoDocumento` que a referencia.

### Auditoria

Usa `RegistaActividade` **sem campos excluídos** — nada sensível em `TipoDocumento`.

---

## Factory `TipoDocumentoFactory`

**Ficheiro:** `database/factories/TipoDocumentoFactory.php`

Sem states adicionais. `definition()` associa sempre uma `CategoriaDocumento::factory()` e define os 4 `espera_*` a `true`.

---

## Policy `TipoDocumentoPolicy`

Ver `04-infra/autorizacao.md` — permissions `tipos-documento.{ver,criar,actualizar,eliminar}`. Sem `restore` (sem SoftDelete).

---

## DTOs

**Ficheiros:** `app/Features/TipoDocumento/Criar/CriarTipoDocumentoDto.php`, `app/Features/TipoDocumento/Actualizar/ActualizarTipoDocumentoDto.php`

`final readonly class`, estrutura idêntica (update completo/PUT), sem `fromRequest()` (adiado para a issue de lógica). Construtor valida:
- `nome`/`descricao`/`idCategoria` não-vazios (trim) → `\InvalidArgumentException`
- pelo menos um dos 4 `espera_*` `true` → `\InvalidArgumentException` se todos `false` (RN-02)

---

## Resource `TipoDocumentoResource`

**Ficheiro:** `app/Features/TipoDocumento/TipoDocumentoResource.php`

Serializa: `id`, `nome`, `descricao`, `categoria` (`CategoriaDocumentoResource::make($this->whenLoaded('categoria'))`), `tipo_movimento` (`$this->categoria?->tipo_movimento?->value` — **derivado, nunca coluna própria**, RN-03), `posicao_empresa_mae` (`->value`), os 4 `espera_*` (bool directo), `criado_em`/`actualizado_em` (`toIso8601String()`).

`categoria` usa `whenLoaded()` (omite o campo se a relação não foi eager-loaded, evita N+1 silencioso). `tipo_movimento` acede à relação directamente — é um escalar derivado que deve estar sempre presente quando a categoria existe, independentemente de eager-load explícito.

---

## Invariantes de domínio

- RN-01: `id_categoria` obrigatório — um `TipoDocumento` não existe sem categoria.
- RN-02: pelo menos um dos 4 `espera_*` `true` — validado nos DTOs, não na BD.
- RN-03: `tipo_movimento` nunca é campo próprio — sempre derivado de `$tipoDocumento->categoria->tipo_movimento`.
- RN-04: `posicao_empresa_mae` determina se a entidade com `e_empresa_aplicacao = true` aparece como `Fornecedor` ou `Cliente` — regra de leitura pela issue futura de extracção; sem lógica de validação nesta camada.

---

## Notas arquitecturais

- **Sem Repository** — CRUD simples (critério de `04-infra/repositories.md`).
- **Sem Actions/Controller/FormRequests/rotas** nesta issue — camada de modelo apenas; ficam para a issue de lógica (`/cria-issue-logica`).
- **Sem relação inversa `hasMany`** em `CategoriaDocumento` — fora de âmbito.
- **Hard-delete deliberado** — `TipoDocumento` não usa `SoftDeletes`; decisão documentada em `../02-shared/soft-delete.md`.
