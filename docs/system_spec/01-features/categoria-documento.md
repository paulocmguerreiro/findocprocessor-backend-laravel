# System Spec — Feature: CategoriaDocumento

> `App\Features\CategoriaDocumento\`

CRUD completo de categorias de documento com suporte a SoftDelete (restaurar, listar por estado). Slice auto-contida: Actions, DTOs, Controller, FormRequests e Resource co-localizados.

**Fluxo de dados:**
```
HTTP Request → FormRequest (autoriza + valida) → Controller (constrói DTO) → Action (autoriza + acede Model) → Controller (formata com Resource) → ApiResponse
```

**Decisão arquitectural:** Actions aceitam `CategoriaDocumento|string` — compatíveis com Route Model Binding (HTTP) e testes unitários (UUID directo). Sem Repository — Eloquent abstrai suficientemente a persistência para este CRUD simples (desvio explícito CLAUDE.md, aprovado na Issue #5). A listagem usa cursor pagination (keyset) em vez de OFFSET — padrão do sistema para todas as listagens futuras (Issue #9).

**Autorização:** dupla verificação intencional — FormRequest (`Gate::authorize()`) na camada HTTP + Action (`Gate::authorize()`) na camada de lógica. Garante que a Policy se aplica mesmo em invocações fora do contexto HTTP (Jobs, Artisan). Policy ligada por `#[UsePolicy(CategoriaDocumentoPolicy::class)]`; cada método verifica `hasPermissionTo('categorias-documento.<accao>')` (admin todas; utilizador só `.ver`; guests negados). Ver `04-infra/autorizacao.md`.

---

## Actions

| Classe | Namespace | Assinatura `handle()` | Descrição |
|---|---|---|---|
| `ListarCategoriasAction` | `App\Features\CategoriaDocumento\Listar` | `handle(int $perPage, CampoOrdenacaoCategorias $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao, FiltroEstadoRegisto $filtroEstado): CursorPaginator<int, CategoriaDocumento>` | Devolve página via cursor pagination, filtrada por estado (`somente_ativos` por omissão); `estado` incluído na chave de cache |
| `CriarCategoriaAction` | `App\Features\CategoriaDocumento\Criar` | `handle(CriarCategoriaDto): CategoriaDocumento` | Cria e devolve nova categoria |
| `VerCategoriaAction` | `App\Features\CategoriaDocumento\Ver` | `handle(CategoriaDocumento\|string): CategoriaDocumento` | Devolve categoria; resolve UUID com `findOrFail` se string |
| `ActualizarCategoriaAction` | `App\Features\CategoriaDocumento\Actualizar` | `handle(CategoriaDocumento\|string, ActualizarCategoriaDto): CategoriaDocumento` | Update completo (PUT semântico) — `fill()` directo com os 3 campos; devolve `refresh()` (actualiza instância existente) |
| `EliminarCategoriaAction` | `App\Features\CategoriaDocumento\Eliminar` | `handle(CategoriaDocumento\|string): void` | **Padrão B**: `forceDelete()` (hard delete quando sem referências) com fallback `fresh()?->delete()` (soft delete quando há FK constraint em `documentos.id_categoria`) |
| `RestaurarCategoriaAction` | `App\Features\CategoriaDocumento\Restaurar` | `handle(CategoriaDocumento\|string): CategoriaDocumento` | Restaura categoria soft-deleted; resolve com `withTrashed()->findOrFail()` (ramo string); `Gate::authorize('restore')` fora da transação; `restore()` + invalidação de cache dentro |

---

## DTOs

Todos `final readonly` com `fromRequest()` (array shape `@var`; `tipo_movimento` via `TipoMovimento::from()`). Padrão Value Object — construtor valida invariantes estruturais; `fromRequest()` só mapeia.

| DTO | Namespace | Campos (tipo) | Invariantes (construtor) |
|---|---|---|---|
| `CriarCategoriaDto` | `CategoriaDocumento\Criar` | `nome:string`, `slug:string`, `tipoMovimento:TipoMovimento` | `nome`/`slug` não-vazios (`trim`) |
| `ActualizarCategoriaDto` | `CategoriaDocumento\Actualizar` | idem (update completo — PUT) | idem (valida incondicionalmente) |

> Array shape sem `?` — todos os campos são `required` no FormRequest. Estrutura idêntica entre os dois (Issue #30).

---

## Enums de listagem

| Classe | Namespace | Cases | Descrição |
|---|---|---|---|
| `CampoOrdenacaoCategorias` | `App\Features\CategoriaDocumento\Listar` | `Nome = 'nome'` | Campo de ordenação da listagem de categorias; extensível com `Slug`, `TipoMovimento`, etc. |

---

## Policy

`CategoriaDocumentoPolicy` (`App\Policies`) — `final class`, `strict_types=1`. Ligada por `#[UsePolicy(CategoriaDocumentoPolicy::class)]` no Model. Cada método exige `User` e verifica `hasPermissionTo('categorias-documento.<accao>')`; guests são negados pelo Laravel (1.º parâmetro `User`, não `?User`). Matriz role→permission em `04-infra/autorizacao.md`.

| Método | Permissão |
|---|---|
| `viewAny` / `view` | `categorias-documento.ver` |
| `create` | `categorias-documento.criar` |
| `update` | `categorias-documento.actualizar` |
| `delete` | `categorias-documento.eliminar` |
| `restore` | `categorias-documento.eliminar` (reutiliza — quem pode inactivar pode reactivar) |

---

## FormRequests

| Classe | Namespace | `authorize()` chama | `rules()` |
|---|---|---|---|
| `ListarCategoriasRequest` | `Listar` | `Gate::authorize('viewAny', CategoriaDocumento::class)` | `per_page`, `sort`, `direction`, `cursor`, `estado` (`Rule::in(FiltroEstadoRegisto)`) |
| `CriarCategoriaRequest` | `Criar` | `Gate::authorize('create', CategoriaDocumento::class)` | `nome`, `slug`, `tipo_movimento` (required) |
| `VerCategoriaRequest` | `Ver` | `Gate::authorize('view', $this->route('categorias_documento'))` | `[]` |
| `ActualizarCategoriaRequest` | `Actualizar` | `Gate::authorize('update', $this->route('categorias_documento'))` | `nome`, `slug`, `tipo_movimento` (required) |
| `EliminarCategoriaRequest` | `Eliminar` | `Gate::authorize('delete', $this->route('categorias_documento'))` | `[]` |
| `RestaurarCategoriaRequest` | `Restaurar` | `Gate::authorize('restore', $this->route('categorias_documento'))` | `[]` |

`VerCategoriaRequest` e `EliminarCategoriaRequest` são FormRequests mínimos — sem `rules()`, apenas autorização. `CriarCategoriaRequest` e `ActualizarCategoriaRequest` não são `final` (são mockadas em testes unitários de DTO). `ActualizarCategoriaRequest` usa `required` em todos os campos — semântica PUT (update completo); sem `sometimes`.

**Regras de validação de `CriarCategoriaRequest`:**

| Campo | Regras |
|---|---|
| `nome` | `required`, `string`, `max:255` |
| `slug` | `required`, `string`, `max:255`, `Rule::unique('categorias_documento', 'slug')` |
| `tipo_movimento` | `required`, `string`, `Rule::in(TipoMovimento::cases())` |

**Regras de validação de `ActualizarCategoriaRequest`:**

| Campo | Regras |
|---|---|
| `nome` | `required`, `string`, `max:255` |
| `slug` | `required`, `string`, `max:255`, `Rule::unique(...)->ignore($uuid)` |
| `tipo_movimento` | `required`, `string`, `Rule::in(TipoMovimento::cases())` |

- `$uuid` via `$this->route('categorias_documento')` — exclui o registo actual da validação de unicidade
- Mensagens em português de Portugal via `messages()`; inclui entradas `*.required` para os 3 campos (Issue #30 — semântica PUT)

---

## Controller

`CategoriaDocumentoController` (`App\Features\CategoriaDocumento`) — `final`, sem lógica. Usa Route Model Binding (`CategoriaDocumento $categorias_documento`) + injecção de Actions via parâmetros de método.

| Método | FormRequest | Action invocada |
|---|---|---|
| `index` | `ListarCategoriasRequest` | `ListarCategoriasAction::handle()` — extrai `per_page` (cast `int`), `sort`, `direction` e `estado` (→ `FiltroEstadoRegisto`; default `SomenteAtivos`); devolve via `ApiResponse::devolverPaginado()` |
| `store` | `CriarCategoriaRequest` | `CriarCategoriaAction::handle()` |
| `show` | `VerCategoriaRequest` | `VerCategoriaAction::handle()` |
| `update` | `ActualizarCategoriaRequest` | `ActualizarCategoriaAction::handle()` |
| `destroy` | `EliminarCategoriaRequest` | `EliminarCategoriaAction::handle()` |
| `restaurar` | `RestaurarCategoriaRequest` | `RestaurarCategoriaAction::handle()` — devolve via `ApiResponse::devolverSucesso()` |

---

## Resource

`CategoriaDocumentoResource` — `App\Features\CategoriaDocumento\CategoriaDocumentoResource`

Formata a resposta JSON de todos os endpoints que retornem uma `CategoriaDocumento`.

```json
{
  "id": "019741b2-...",
  "nome": "Fatura de Fornecedor",
  "slug": "fatura-de-fornecedor",
  "tipo_movimento": "debito"
}
```

- `tipo_movimento` exposto como string via `->value` (nunca o enum em bruto)
- Timestamps omitidos intencionalmente
- PHPDoc `array{id: string, nome: string, slug: string, tipo_movimento: string}` em `toArray()`
