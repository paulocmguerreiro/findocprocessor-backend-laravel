# System Spec — Índice

> Porta de entrada. Ler antes de qualquer actualização de spec.
> Para detalhe: abrir apenas o ficheiro indicado — nunca ler todos.

## Features implementadas

| Feature | Ficheiro | Actions | Rotas |
|---|---|---|---|
| CategoriaDocumento | `01-features/categoria-documento.md` | 5 CRUD | 5 REST |
| Entidade | `01-features/entidade.md` | 7 (5 CRUD + ConverterEmEmpresaMae + Remover) | 5 REST + 1 especial |

## Features planeadas

| Feature | Actions planeadas |
|---|---|
| Documents/List | `ListDocumentsAction` |
| Documents/Correct | `CorrectDocumentAction` |
| Documents/Delete | `DeleteDoneAction`, `DeleteErrorAction` |
| Documents/Reprocess | `ReprocessDocumentAction` |
| Upload | `HandleUploadAction` |
| Batch | `ForceBatchCycleAction` |
| Files | `ListDirectoryAction`, `OpenFileAction` |
| Sse | `SseStreamAction` |

## Shared

| Componente | Ficheiro |
|---|---|
| Enums partilhados (`TipoMovimento`, `DirecaoOrdenacao`) | `02-shared/enums.md` |
| HTTP (`ApiResponse`, Exception Handler) | `02-shared/http.md` |
| Estados de documento + Contratos | `02-shared/estados.md` |

## Modelos Eloquent

| Model | Ficheiro |
|---|---|
| `CategoriaDocumento` | `03-models/categoria-documento.md` |
| `Entidade` | `03-models/entidade.md` |
| `Document` _(pendente)_ | `03-models/documento.md` |

## Infra

| Subsistema | Ficheiro | Estado |
|---|---|---|
| Transações de BD | `04-infra/transactions.md` | implementado |
| Repositories | `04-infra/repositories.md` | pendente |
| Cache / Redis | `04-infra/cache.md` | pendente |
| Jobs / Queue | `04-infra/queue-jobs.md` | pendente |
| APIs externas (IA) | `04-infra/external-apis.md` | pendente |

## Rotas e Configuração

| Área | Ficheiro |
|---|---|
| Rotas CategoriaDocumento | `05-routes/categorias-documento.md` |
| Rotas Entidade | `05-routes/entidades.md` |
| Rotas planeadas | `05-routes/planeadas.md` |
| Configuração e .env | `06-config.md` |
