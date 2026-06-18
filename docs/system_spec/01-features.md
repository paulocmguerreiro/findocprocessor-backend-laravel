# System Spec — 01: Features

> Actualizado automaticamente após cada Issue pela Fase 3 (documenta-issue).

## Features implementadas

### `CategoriaDocumento` — `App\Features\CategoriaDocumento\`

CRUD completo de categorias de documento. Slice auto-contida: Actions, DTOs, Controller, FormRequests e Resource co-localizados.

**Fluxo de dados:**
```
HTTP Request → FormRequest (autoriza + valida) → Controller (constrói DTO) → Action (autoriza + acede Model) → Controller (formata com Resource) → ApiResponse
```

**Decisão arquitectural:** Actions aceitam `CategoriaDocumento|string` — compatíveis com Route Model Binding (HTTP) e testes unitários (UUID directo). Sem Repository — Eloquent abstrai suficientemente a persistência para este CRUD simples (desvio explícito CLAUDE.md, aprovado na Issue #5). A listagem usa cursor pagination (keyset) em vez de OFFSET — padrão do sistema para todas as listagens futuras (Issue #9).

**Autorização:** dupla verificação intencional — FormRequest (`Gate::authorize()`) na camada HTTP + Action (`Gate::authorize()`) na camada de lógica. Garante que a Policy se aplica mesmo em invocações fora do contexto HTTP (Jobs, Artisan). Policy auto-descoberta por convenção de nome (`CategoriaDocumentoPolicy` ↔ `CategoriaDocumento`). Nesta fase, todos os métodos retornam `true` (acesso aberto, incluindo guests).

#### Actions

| Classe | Namespace | Assinatura `handle()` | Descrição |
|---|---|---|---|
| `ListarCategoriasAction` | `App\Features\CategoriaDocumento\Listar` | `handle(int $perPage, CampoOrdenacaoCategorias $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator<int, CategoriaDocumento>` | Devolve página via cursor pagination (`cursorPaginate`), ordenada pelo campo e direcção indicados |
| `CriarCategoriaAction` | `App\Features\CategoriaDocumento\Criar` | `handle(CriarCategoriaDto): CategoriaDocumento` | Cria e devolve nova categoria |
| `VerCategoriaAction` | `App\Features\CategoriaDocumento\Ver` | `handle(CategoriaDocumento\|string): CategoriaDocumento` | Devolve categoria; resolve UUID com `findOrFail` se string |
| `ActualizarCategoriaAction` | `App\Features\CategoriaDocumento\Actualizar` | `handle(CategoriaDocumento\|string, ActualizarCategoriaDto): CategoriaDocumento` | Update completo (PUT semântico) — `fill()` directo com os 3 campos; devolve `refresh()` (actualiza instância existente) |
| `EliminarCategoriaAction` | `App\Features\CategoriaDocumento\Eliminar` | `handle(CategoriaDocumento\|string): void` | Hard delete |

#### DTOs

| Classe | Namespace | Propriedades |
|---|---|---|
| `CriarCategoriaDto` | `App\Features\CategoriaDocumento\Criar` | `string $nome`, `string $slug`, `TipoMovimento $tipo_movimento` |
| `ActualizarCategoriaDto` | `App\Features\CategoriaDocumento\Actualizar` | `string $nome`, `string $slug`, `TipoMovimento $tipoMovimento` |

Ambos `final readonly`. `fromRequest()` usa `validated()` + guards `is_string()` + `UnexpectedValueException` (Larastan nível 9).

#### Enums de listagem

| Classe | Namespace | Cases | Descrição |
|---|---|---|---|
| `CampoOrdenacaoCategorias` | `App\Features\CategoriaDocumento\Listar` | `Nome = 'nome'` | Campo de ordenação da listagem de categorias; extensível com `Slug`, `TipoMovimento`, etc. |

#### Policy

`CategoriaDocumentoPolicy` (`App\Policies`) — `final class`, `strict_types=1`. Auto-descoberta por convenção de nome. Todos os métodos aceitam `?User $utilizador` (nullable — permite guests). Nesta fase, todos retornam `true`.

| Método | Assinatura |
|---|---|
| `viewAny` | `viewAny(?User $utilizador): bool` |
| `view` | `view(?User $utilizador, CategoriaDocumento $categoriaDocumento): bool` |
| `create` | `create(?User $utilizador): bool` |
| `update` | `update(?User $utilizador, CategoriaDocumento $categoriaDocumento): bool` |
| `delete` | `delete(?User $utilizador, CategoriaDocumento $categoriaDocumento): bool` |

**Nota `rector.php`:** `RemoveUnusedPublicMethodParameterRector` excluído para `app/Policies/` — parâmetros `?User` e `CategoriaDocumento` são contrato do framework (o Laravel usa reflexão para decidir se guests passam), não dead code.

#### FormRequests

| Classe | Namespace | `authorize()` chama | `rules()` |
|---|---|---|---|
| `ListarCategoriasRequest` | `Listar` | `Gate::authorize('viewAny', CategoriaDocumento::class)` | `per_page`, `sort`, `direction`, `cursor` |
| `CriarCategoriaRequest` | `Criar` | `Gate::authorize('create', CategoriaDocumento::class)` | `nome`, `slug`, `tipo_movimento` (required) |
| `VerCategoriaRequest` | `Ver` | `Gate::authorize('view', $this->route('categorias_documento'))` | `[]` |
| `ActualizarCategoriaRequest` | `Actualizar` | `Gate::authorize('update', $this->route('categorias_documento'))` | `nome`, `slug`, `tipo_movimento` (required) |
| `EliminarCategoriaRequest` | `Eliminar` | `Gate::authorize('delete', $this->route('categorias_documento'))` | `[]` |

`VerCategoriaRequest` e `EliminarCategoriaRequest` são FormRequests mínimos — sem `rules()`, apenas autorização. `CriarCategoriaRequest` e `ActualizarCategoriaRequest` não são `final` (são mockadas em testes unitários de DTO). `ActualizarCategoriaRequest` usa `required` em todos os campos — semântica PUT (update completo); sem `sometimes`.

#### Controller

`CategoriaDocumentoController` (`App\Features\CategoriaDocumento`) — `final`, sem lógica. Usa Route Model Binding (`CategoriaDocumento $categorias_documento`) + injecção de Actions via parâmetros de método.

| Método | FormRequest | Action invocada |
|---|---|---|
| `index` | `ListarCategoriasRequest` | `ListarCategoriasAction::handle()` — extrai `per_page` (cast `int`), `sort` e `direction`; devolve via `ApiResponse::devolverPaginado()` |
| `store` | `CriarCategoriaRequest` | `CriarCategoriaAction::handle()` |
| `show` | `VerCategoriaRequest` | `VerCategoriaAction::handle()` |
| `update` | `ActualizarCategoriaRequest` | `ActualizarCategoriaAction::handle()` |
| `destroy` | `EliminarCategoriaRequest` | `EliminarCategoriaAction::handle()` |

---

## Features planeadas

| Feature              | Actions planeadas                                     | Estado   |
| -------------------- | ----------------------------------------------------- | -------- |
| Documents/List       | ListDocumentsAction                                   | pendente |
| Documents/Correct    | CorrectDocumentAction                                 | pendente |
| Documents/Delete     | DeleteDoneAction, DeleteErrorAction                   | pendente |
| Documents/Reprocess  | ReprocessDocumentAction                               | pendente |
| Upload               | HandleUploadAction                                    | pendente |
| Batch                | ForceBatchCycleAction                                 | pendente |
| Files                | ListDirectoryAction, OpenFileAction                   | pendente |
| Sse                  | SseStreamAction                                       | pendente |
