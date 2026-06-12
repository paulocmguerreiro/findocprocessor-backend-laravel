# System Spec — 01: Features

> Actualizado automaticamente após cada Issue pela Fase 3 (documenta-issue).

## Features implementadas

### `CategoriaDocumento` — `App\Features\CategoriaDocumento\`

CRUD completo de categorias de documento. Slice auto-contida: Actions, DTOs, Controller, FormRequests e Resource co-localizados.

**Fluxo de dados:**
```
HTTP Request → FormRequest (valida) → Controller (constrói DTO) → Action (acede Model) → Controller (formata com Resource) → ApiResponse
```

**Decisão arquitectural:** Actions aceitam `CategoriaDocumento|string` — compatíveis com Route Model Binding (HTTP) e testes unitários (UUID directo). Sem Repository — Eloquent abstrai suficientemente a persistência para este CRUD simples (desvio explícito CLAUDE.md, aprovado na Issue #5).

#### Actions

| Classe | Namespace | Assinatura `handle()` | Descrição |
|---|---|---|---|
| `ListarCategoriasAction` | `App\Features\CategoriaDocumento\Listar` | `handle(): Collection<int, CategoriaDocumento>` | Devolve todas as categorias (`CategoriaDocumento::all()`) |
| `CriarCategoriaAction` | `App\Features\CategoriaDocumento\Criar` | `handle(CriarCategoriaDto): CategoriaDocumento` | Cria e devolve nova categoria |
| `VerCategoriaAction` | `App\Features\CategoriaDocumento\Ver` | `handle(CategoriaDocumento\|string): CategoriaDocumento` | Devolve categoria; resolve UUID com `findOrFail` se string |
| `ActualizarCategoriaAction` | `App\Features\CategoriaDocumento\Actualizar` | `handle(CategoriaDocumento\|string, ActualizarCategoriaDto): CategoriaDocumento` | Actualização parcial via `array_filter !== null`; devolve `fresh()` |
| `EliminarCategoriaAction` | `App\Features\CategoriaDocumento\Eliminar` | `handle(CategoriaDocumento\|string): void` | Hard delete |

#### DTOs

| Classe | Namespace | Propriedades |
|---|---|---|
| `CriarCategoriaDto` | `App\Features\CategoriaDocumento\Criar` | `string $nome`, `string $slug`, `TipoMovimento $tipo_movimento` |
| `ActualizarCategoriaDto` | `App\Features\CategoriaDocumento\Actualizar` | `?string $nome`, `?string $slug`, `?TipoMovimento $tipo_movimento` |

Ambos `final readonly`. `fromRequest()` usa `validated()` + guards `is_string()` + `UnexpectedValueException` (Larastan nível 9).

#### Controller

`CategoriaDocumentoController` (`App\Features\CategoriaDocumento`) — `final`, sem lógica. Usa Route Model Binding (`CategoriaDocumento $categorias_documento`) + injecção de Actions via parâmetros de método.

| Método | Action invocada |
|---|---|
| `index` | `ListarCategoriasAction::handle()` |
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
