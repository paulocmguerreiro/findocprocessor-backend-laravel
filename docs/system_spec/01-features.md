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

### `Entidade` — `App\Features\Entidade\`

CRUD completo de entidades (clientes, fornecedores, Empresa Mãe/Aplicação). Inclui a regra de negócio de unicidade da Empresa Mãe e um endpoint dedicado para converter uma entidade em Empresa Mãe.

**Fluxo de dados:**
```
HTTP Request → FormRequest (autoriza + valida) → Controller (constrói DTO ou passa Entidade via RMB) → Action (autoriza + acede Model) → Controller (formata com EntidadeResource) → ApiResponse
```

**Decisão arquitectural:** Sem Repository — Eloquent directo nas Actions (CRUD simples, ≤ 1 query por `handle()`). A sub-action `RemoverMarcacaoEmpresaMaeAction` é invocada via `RegraUnicidadeEmpresaMae` (classe de domínio que encapsula o `if (eEmpresaAplicacao)`), sempre **dentro** da transação do caller — nunca abre transação própria. A invariante "Empresa Mãe implica cliente + fornecedor" está encapsulada no trait `ComFlagsEfectivosEmpresaMae` (usado nos DTOs).

**Autorização:** dupla verificação — FormRequest + Action. `RemoverMarcacaoEmpresaMaeAction` (action interna) não tem `Gate::authorize()` próprio — é chamada dentro de uma Action já autorizada.

#### Actions

| Classe | Namespace | Assinatura `handle()` | Descrição |
|---|---|---|---|
| `ListarEntidadesAction` | `App\Features\Entidade\Listar` | `handle(int $perPage, CampoOrdenacaoEntidades $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator<int, Entidade>` | Devolve página via cursor pagination, ordenada pelo campo e direcção indicados |
| `CriarEntidadeAction` | `App\Features\Entidade\Criar` | `handle(CriarEntidadeDto): Entidade` | Cria entidade; invoca `RegraUnicidadeEmpresaMae` se `eEmpresaAplicacao = true` |
| `VerEntidadeAction` | `App\Features\Entidade\Ver` | `handle(Entidade\|string): Entidade` | Devolve entidade; resolve UUID com `findOrFail` se string |
| `ActualizarEntidadeAction` | `App\Features\Entidade\Actualizar` | `handle(Entidade\|string, ActualizarEntidadeDto): Entidade` | Update completo (PUT semântico); invoca `RegraUnicidadeEmpresaMae`; devolve `refresh()` |
| `EliminarEntidadeAction` | `App\Features\Entidade\Eliminar` | `handle(Entidade\|string): void` | Hard delete |
| `ConverterEmEmpresaMaeAction` | `App\Features\Entidade\EmpresaMae` | `handle(Entidade\|string): Entidade` | Remove marcação anterior + força os 3 flags (`e_empresa_aplicacao`, `e_cliente`, `e_fornecedor`) |
| `RemoverMarcacaoEmpresaMaeAction` | `App\Features\Entidade\EmpresaMae` | `handle(): void` | Action interna — `UPDATE entidades SET e_empresa_aplicacao = false WHERE e_empresa_aplicacao = true`; sem autorização própria; sempre chamada dentro da transação do caller |

#### Classe de domínio

| Classe | Namespace | Descrição |
|---|---|---|
| `RegraUnicidadeEmpresaMae` | `App\Features\Entidade\EmpresaMae` | Encapsula a regra: se `eEmpresaAplicacao = true`, invoca `RemoverMarcacaoEmpresaMaeAction`. Injectada por construtor nas 3 Actions de escrita. |

#### DTOs

| Classe | Namespace | Propriedades |
|---|---|---|
| `CriarEntidadeDto` | `App\Features\Entidade\Criar` | `string $nome`, `string $nif`, `bool $eCliente`, `bool $eFornecedor`, `bool $eEmpresaAplicacao` |
| `ActualizarEntidadeDto` | `App\Features\Entidade\Actualizar` | `string $nome`, `string $nif`, `bool $eCliente`, `bool $eFornecedor`, `bool $eEmpresaAplicacao` |

Ambos `final readonly`. Usam o trait `ComFlagsEfectivosEmpresaMae` (`eClienteEfectivo()`, `eFornecedorEfectivo()`). `fromRequest()` com array shape `@var` (Larastan nível 9). Construtor valida `nome`/`nif` não-vazios.

#### Trait

| Classe | Namespace | Descrição |
|---|---|---|
| `ComFlagsEfectivosEmpresaMae` | `App\Features\Entidade` | `eClienteEfectivo(): bool` = `eEmpresaAplicacao || eCliente`; `eFornecedorEfectivo(): bool` = `eEmpresaAplicacao || eFornecedor`. Garante a invariante em criar e actualizar sem duplicação. |

#### Enums de listagem

| Classe | Namespace | Cases | Descrição |
|---|---|---|---|
| `CampoOrdenacaoEntidades` | `App\Features\Entidade\Listar` | `Nome = 'nome'` | Campo de ordenação da listagem de entidades; extensível com `Nif`, `CreatedAt` |

#### Policy

`EntidadePolicy` (`App\Policies`) — auto-descoberta por convenção de nome. Todos os métodos aceitam `?User $utilizador` (nullable — guests permitidos). Nesta fase, todos retornam `true`.

| Método | Assinatura |
|---|---|
| `viewAny` | `viewAny(?User $utilizador): bool` |
| `view` | `view(?User $utilizador, Entidade $entidade): bool` |
| `create` | `create(?User $utilizador): bool` |
| `update` | `update(?User $utilizador, Entidade $entidade): bool` |
| `delete` | `delete(?User $utilizador, Entidade $entidade): bool` |

#### FormRequests

| Classe | Namespace | `authorize()` chama | `rules()` |
|---|---|---|---|
| `ListarEntidadesRequest` | `Listar` | `Gate::authorize('viewAny', Entidade::class)` | `per_page`, `sort`, `direction`, `cursor` |
| `CriarEntidadeRequest` | `Criar` | `Gate::authorize('create', Entidade::class)` | `nome`, `nif`, `e_cliente`, `e_fornecedor`, `e_empresa_aplicacao` (required) |
| `VerEntidadeRequest` | `Ver` | `Gate::authorize('view', $this->route('entidade'))` | `[]` |
| `ActualizarEntidadeRequest` | `Actualizar` | `Gate::authorize('update', $this->route('entidade'))` | `nome`, `nif`, `e_cliente`, `e_fornecedor`, `e_empresa_aplicacao` (required) |
| `EliminarEntidadeRequest` | `Eliminar` | `Gate::authorize('delete', $this->route('entidade'))` | `[]` |
| `ConverterEmEmpresaMaeRequest` | `EmpresaMae` | `Gate::authorize('update', $this->route('entidade'))` | `[]` |

`CriarEntidadeRequest` e `ActualizarEntidadeRequest` não são `final` (mockáveis em testes unitários de DTO).

#### Controller

`EntidadeController` (`App\Features\Entidade`) — `final`, sem lógica. Usa Route Model Binding (`Entidade $entidade`) + injecção de Actions via parâmetros de método.

| Método | FormRequest | Action invocada |
|---|---|---|
| `index` | `ListarEntidadesRequest` | `ListarEntidadesAction::handle()` — extrai `per_page` (cast `int`), `sort`, `direction`; devolve via `ApiResponse::devolverPaginado()` |
| `store` | `CriarEntidadeRequest` | `CriarEntidadeAction::handle(CriarEntidadeDto::fromRequest($pedido))` |
| `show` | `VerEntidadeRequest` | `VerEntidadeAction::handle($entidade)` |
| `update` | `ActualizarEntidadeRequest` | `ActualizarEntidadeAction::handle($entidade, ActualizarEntidadeDto::fromRequest($pedido))` |
| `destroy` | `EliminarEntidadeRequest` | `EliminarEntidadeAction::handle($entidade)` |
| `converterEmEmpresaMae` | `ConverterEmEmpresaMaeRequest` | `ConverterEmEmpresaMaeAction::handle($entidade)` |

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
