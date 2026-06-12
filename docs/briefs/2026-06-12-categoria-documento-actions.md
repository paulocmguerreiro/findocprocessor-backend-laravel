# Brief — Issue #5: CategoriaDocumento — Actions + Controller

**Data:** 2026-06-12
**Branch:** `feat/categoria-documento-actions`
**Issue:** #5

---

## Contexto

A Issue #1 criou o Model `CategoriaDocumento` e a Issue #3 criou os FormRequests e o Resource. Falta agora a camada de lógica: as Actions que encapsulam cada operação CRUD e o Controller que as orquestra sem lógica própria.

## O que se vai fazer

Cinco Actions (uma por operação CRUD) + um Controller de dispatch + rotas `apiResource` + DTOs simples para transferir dados validados do FormRequest para as Actions.

## Decisão arquitectural documentada

Actions acedem directamente ao Eloquent Model `CategoriaDocumento` — **sem Repository**. Decisão deliberada para este CRUD simples; o Eloquent abstrai suficientemente a persistência. Desvio explicitamente aceite face a `CLAUDE.md` (ver Issue #5).

## Componentes a criar

| Ficheiro | Descrição |
|---|---|
| `app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php` | Devolve `Collection<CategoriaDocumento>` |
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php` | DTO: `nome`, `slug`, `tipo_movimento` |
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php` | Cria e devolve `CategoriaDocumento` |
| `app/Features/CategoriaDocumento/Ver/VerCategoriaAction.php` | Devolve `CategoriaDocumento` ou lança `ModelNotFoundException` |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php` | DTO: `nome?`, `slug?`, `tipo_movimento?` |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php` | Actualiza e devolve `CategoriaDocumento` |
| `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php` | Hard delete; lança `ModelNotFoundException` se não existir |
| `app/Features/CategoriaDocumento/CategoriaDocumentoController.php` | Dispatch para Actions; usa `ApiResponse` |

## Componentes reutilizados

- `CriarCategoriaRequest` (Issue #3)
- `ActualizarCategoriaRequest` (Issue #3)
- `CategoriaDocumentoResource` (Issue #3)
- `ApiResponse` (Issue #6)
- `CategoriaDocumento` Model (Issue #1)

## Rotas

```php
Route::apiResource('categorias-documento', CategoriaDocumentoController::class);
```

Gera: `GET /api/categorias-documento`, `POST /api/categorias-documento`, `GET /api/categorias-documento/{categorias_documento}`, `PUT /api/categorias-documento/{categorias_documento}`, `DELETE /api/categorias-documento/{categorias_documento}`.

> Nota: o parâmetro de rota gerado pelo `apiResource` será `{categorias_documento}`. A `ActualizarCategoriaRequest` usa `$this->route('categorias_documento')` — verificar e corrigir se necessário.

## Testes

Feature tests em `tests/Feature/Features/CategoriaDocumento/` cobrindo:
- Listar: 200 + estrutura da colecção
- Criar: 201 + estrutura do recurso + validação 422
- Ver: 200 + 404
- Actualizar: 200 + 404 + validação 422
- Eliminar: 204 + 404

## Critérios de qualidade

- `composer test` verde (100% coverage + types + arch + lint)
- Larastan nível 9 — zero erros
- `strict_types=1` em todos os ficheiros novos
