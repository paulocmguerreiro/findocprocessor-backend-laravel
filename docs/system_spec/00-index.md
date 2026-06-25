# System Spec — Índice

> Porta de entrada. Ler antes de qualquer actualização de spec.
> Para detalhe: abrir apenas o ficheiro indicado — nunca ler todos.

## Features implementadas

| Feature | Ficheiro | Actions | Rotas |
|---|---|---|---|
| Auth | `01-features/auth.md` | 3 (Login, Logout, CriarToken) | 3 REST |
| CategoriaDocumento | `01-features/categoria-documento.md` | 5 CRUD | 5 REST |
| Entidade | `01-features/entidade.md` | 7 (5 CRUD + ConverterEmEmpresaMae + Remover) | 5 REST + 1 especial |
| Role | `01-features/role.md` | 5 CRUD | 5 REST |
| Utilizador | `01-features/utilizador.md` | 1 (AtribuirRole) | 1 especial |

## Features planeadas

| Feature | Actions planeadas |
|---|---|
| Documento/Listar | `ListarDocumentosAction` |
| Documento/Corrigir | `CorrigirDocumentoAction` |
| Documento/Eliminar | `EliminarDocumentoAction` |
| Documento/Reprocessar | `ReprocessarDocumentoAction` |
| Upload | `HandleUploadAction` |
| Batch | `ForceBatchCycleAction` |
| Files | `ListDirectoryAction`, `OpenFileAction` |
| Sse | `SseStreamAction` |

## Shared

| Componente | Ficheiro |
|---|---|
| Enums partilhados (`TipoMovimento`, `DirecaoOrdenacao`, `EstadoDocumento`) | `02-shared/enums.md` |
| HTTP (`ApiResponse`, Exception Handler, cursor pagination) | `02-shared/http.md` |
| Estados de documento + Interface `ContratoEstadoDocumento` + 7 state objects | `02-shared/estados.md` |

## Padrões e convenções (Shared)

| Tema | Ficheiro |
|---|---|
| Padrões de Actions (autorização dupla camada) | `02-shared/padroes-acoes.md` |
| Padrões de DTOs (Value Object) | `02-shared/padroes-dtos.md` |
| Padrões de tipagem (array shape, `@throws`) | `02-shared/padroes-tipagem.md` |
| Convenções de nomenclatura (PT/EN) | `02-shared/convencoes-nomenclatura.md` |
| Contratos por camada (checklist arquitectural) | `02-shared/contratos-por-camada.md` |
| Regras de negócio (`Regra*`) — catálogo e padrão | `02-shared/regras-negocio.md` |

## Modelos Eloquent

| Model | Ficheiro |
|---|---|
| Convenções canónicas de Models | `03-models/00-convencoes-models.md` |
| `User` | `03-models/user.md` |
| `CategoriaDocumento` | `03-models/categoria-documento.md` |
| `Entidade` | `03-models/entidade.md` |
| `Role` (Spatie — audit via Observer) | `03-models/role.md` |
| `Documento` (migration, Model, Factory, Policy, DTOs, Resource) | `03-models/documento.md` |

## Infra

| Subsistema | Ficheiro | Estado |
|---|---|---|
| Transações de BD | `04-infra/transactions.md` | implementado |
| Autorização (Roles/Permissions) | `04-infra/autorizacao.md` | implementado |
| Repositories | `04-infra/repositories.md` | pendente |
| Cache / Redis | `04-infra/cache.md` | implementado |
| Logging estruturado | `04-infra/logging.md` | implementado |
| Audit trail (spatie/laravel-activitylog) | `04-infra/audit-trail.md` | implementado |
| Jobs / Queue | `04-infra/queue-jobs.md` | pendente |
| APIs externas (IA) | `04-infra/external-apis.md` | pendente |
| Ambiente Docker + paridade de testes (MySQL) | `04-infra/ambiente-docker.md` | implementado |

## Rotas e Configuração

| Área | Ficheiro |
|---|---|
| Rotas Auth | `05-routes/auth.md` |
| Rotas CategoriaDocumento | `05-routes/categorias-documento.md` |
| Rotas Entidade | `05-routes/entidades.md` |
| Rotas Role + Utilizador | `05-routes/role.md` |
| Rotas planeadas | `05-routes/planeadas.md` |
| Configuração e .env | `06-config.md` |

## Testes

| Área | Ficheiro |
|---|---|
| Padrão dual de testes (Unit + Feature) + ArchTest | `07-testing.md` |
