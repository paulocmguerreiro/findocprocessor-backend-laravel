# System Spec — 01: Features

> Actualizado automaticamente após cada Issue pela Fase 3 (documenta-issue).

## Features implementadas

### `CategoriaDocumento` — `App\Features\CategoriaDocumento\`

CRUD completo de categorias de documento. Slice auto-contida: Actions, DTOs, Controller, FormRequests e Resource co-localizados.

**Fluxo de dados:**
```
HTTP Request → FormRequest (valida) → Controller (constrói DTO) → Action (acede Model) → Controller (formata com Resource) → ApiResponse
```

**Decisão arquitectural:** Actions aceitam `CategoriaDocumento|string` — compatíveis com Route Model Binding (HTTP) e testes unitários (UUID directo). Sem Repository — Eloquent abstrai suficientemente a persistência para este CRUD simples (desvio explícito CLAUDE.md, aprovado na Issue #5). A listagem usa cursor pagination (keyset) em vez de OFFSET — padrão do sistema para todas as listagens futuras (Issue #9).

#### Actions

| Classe | Namespace | Assinatura `handle()` | Descrição |
|---|---|---|---|
| `ListarCategoriasAction` | `App\Features\CategoriaDocumento\Listar` | `handle(int $perPage, CampoOrdenacaoCategorias $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator<int, CategoriaDocumento>` | Devolve página via cursor pagination (`cursorPaginate`), ordenada pelo campo e direcção indicados |
| `CriarCategoriaAction` | `App\Features\CategoriaDocumento\Criar` | `handle(CriarCategoriaDto): CategoriaDocumento` | Cria e devolve nova categoria |
| `VerCategoriaAction` | `App\Features\CategoriaDocumento\Ver` | `handle(CategoriaDocumento\|string): CategoriaDocumento` | Devolve categoria; resolve UUID com `findOrFail` se string |
| `ActualizarCategoriaAction` | `App\Features\CategoriaDocumento\Actualizar` | `handle(CategoriaDocumento\|string, ActualizarCategoriaDto): CategoriaDocumento` | Actualização parcial via `array_filter !== null`; devolve `refresh()` (actualiza instância existente) |
| `EliminarCategoriaAction` | `App\Features\CategoriaDocumento\Eliminar` | `handle(CategoriaDocumento\|string): void` | Hard delete |

#### DTOs

| Classe | Namespace | Propriedades |
|---|---|---|
| `CriarCategoriaDto` | `App\Features\CategoriaDocumento\Criar` | `string $nome`, `string $slug`, `TipoMovimento $tipo_movimento` |
| `ActualizarCategoriaDto` | `App\Features\CategoriaDocumento\Actualizar` | `?string $nome`, `?string $slug`, `?TipoMovimento $tipo_movimento` |

Ambos `final readonly`. `fromRequest()` usa `validated()` + guards `is_string()` + `UnexpectedValueException` (Larastan nível 9).

#### Enums de listagem

| Classe | Namespace | Cases | Descrição |
|---|---|---|---|
| `CampoOrdenacaoCategorias` | `App\Features\CategoriaDocumento\Listar` | `Nome = 'nome'` | Campo de ordenação da listagem de categorias; extensível com `Slug`, `TipoMovimento`, etc. |

#### Controller

`CategoriaDocumentoController` (`App\Features\CategoriaDocumento`) — `final`, sem lógica. Usa Route Model Binding (`CategoriaDocumento $categorias_documento`) + injecção de Actions via parâmetros de método.

| Método | Action invocada |
|---|---|
| `index` | `ListarCategoriasAction::handle()` — extrai `per_page` (cast `int`), `sort` e `direction` do `ListarCategoriasRequest`; devolve via `ApiResponse::devolverPaginado()` |
| `store` | `CriarCategoriaAction::handle()` |
| `show` | `VerCategoriaAction::handle()` |
| `update` | `ActualizarCategoriaAction::handle()` |
| `destroy` | `EliminarCategoriaAction::handle()` |

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
