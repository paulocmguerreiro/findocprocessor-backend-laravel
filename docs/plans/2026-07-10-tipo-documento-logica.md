# Plano: TipoDocumento — feature slice (Actions + Controller + FormRequests + testes)

**Issue:** #85
**Spec:** docs/specs/2026-07-10-tipo-documento-logica.md
**Data:** 2026-07-10

## Tarefas

### Tarefa 1 — `fromRequest()` nos DTOs existentes

- Ficheiros a criar/alterar:
  - `app/Features/TipoDocumento/Criar/CriarTipoDocumentoDto.php` (alterar — adicionar `fromRequest()`)
  - `app/Features/TipoDocumento/Actualizar/ActualizarTipoDocumentoDto.php` (alterar — adicionar `fromRequest()`)
- O que implementar: `fromRequest(CriarTipoDocumentoRequest $request): self` / `fromRequest(ActualizarTipoDocumentoRequest $request): self` — `@var array{nome: string, descricao: string, id_categoria: string, posicao_empresa_mae: string, espera_data_documento: bool, espera_fornecedor: bool, espera_cliente: bool, espera_valor: bool} $dadosValidados = $request->validated()`; mapeamento directo (`posicao_empresa_mae` via `PosicaoEmpresaMae::from()`); `@throws \InvalidArgumentException` herdado do construtor. Import circular: `FormRequest` fica em `Criar`/`Actualizar` — criar primeiro como referência de tipo (PHP resolve forward reference dentro do mesmo namespace sem problema, mas o FormRequest só existe na Tarefa 3/5; ordem de escrita não bloqueia porque `fromRequest()` referencia a classe por nome, não precisa dela compilada primeiro).
- Testes associados: `tests/Unit/Features/TipoDocumento/CriarTipoDocumentoDtoTest.php` e `ActualizarTipoDocumentoDtoTest.php` (já existem de #84 — adicionar casos `fromRequest()` com `Mockery::mock(CriarTipoDocumentoRequest::class)`/`ActualizarTipoDocumentoRequest::class)` — FormRequests não são `final`, mockáveis).
- Commit: `feat(tipo-documento): adicionar fromRequest() aos DTOs`

### Tarefa 2 — Enum de ordenação + Action e FormRequest de Listagem

- Ficheiros a criar:
  - `app/Features/TipoDocumento/Listar/CampoOrdenacaoTiposDocumento.php`
  - `app/Features/TipoDocumento/Listar/ListarTiposDocumentoAction.php`
  - `app/Features/TipoDocumento/Listar/ListarTiposDocumentoRequest.php`
- O que implementar:
  - Enum `CampoOrdenacaoTiposDocumento: string { case Nome = 'nome'; }`
  - `ListarTiposDocumentoAction::handle(int $perPage, CampoOrdenacaoTiposDocumento $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao, ?string $idCategoria = null): CursorPaginator` — `Gate::authorize('viewAny', TipoDocumento::class)`; `TipoDocumento::query()->with('categoria')->when($idCategoria, fn (Builder $c, string $id) => $c->where('id_categoria', $id))->orderBy(...)->cursorPaginate($perPage)`; **sem cache** (CacheServico não usado nesta Action — `TipoDocumento` ainda não tem `TagCache` dedicada; decisão: seguir o padrão simples sem cache nesta issue, documentar em `01-features/tipo-documento.md` como desvio deliberado face a `ListarCategoriasAction`/`ListarDocumentosAction`, que usam `CacheServico`). **Reconsiderar no Checkpoint da tarefa se o padrão exigir cache** — CLAUDE.md não obriga cache universal, mas todas as listagens existentes usam; decidir com o utilizador no checkpoint desta tarefa se se segue o precedente ou se documenta a excepção.
  - `ListarTiposDocumentoRequest::rules()` — `per_page`, `sort` (`Rule::in(CampoOrdenacaoTiposDocumento::cases())`), `direction` (`Rule::in(DirecaoOrdenacao::cases())`), `cursor`, `id_categoria` (`['sometimes', 'string', 'uuid', Rule::exists('categorias_documento', 'id')]`); `messages()` em PT.
- Testes associados:
  - `tests/Unit/Features/TipoDocumento/ListarTiposDocumentoActionTest.php` — happy path, filtro `id_categoria` (com e sem correspondência), ordenação, autorização (3 estados), eager-load de `categoria` no resultado.
  - `tests/Feature/Features/TipoDocumento/ListarTiposDocumentoTest.php` — lista vazia, estrutura, `per_page`, cursor sem duplicados, 422 `per_page>100`, 422 `sort` inválido, 422 `id_categoria` não-uuid / inexistente, filtro `id_categoria` válido, matriz de 3 estados (guest 401 / `utilizador` 200 / sem-role 403), payload inclui `categoria`/`tipo_movimento`.
- Commit: `feat(tipo-documento): adicionar ListarTiposDocumentoAction + Request`

### Tarefa 3 — Action e FormRequest de Criação

- Ficheiros a criar:
  - `app/Features/TipoDocumento/Criar/CriarTipoDocumentoAction.php`
  - `app/Features/TipoDocumento/Criar/CriarTipoDocumentoRequest.php`
- O que implementar:
  - `CriarTipoDocumentoAction::handle(CriarTipoDocumentoDto $dados): TipoDocumento` — `Gate::authorize('create', TipoDocumento::class)` fora da transação; `DB::transaction(fn () => TipoDocumento::create([...])->load('categoria'))`.
  - `CriarTipoDocumentoRequest::authorize()` via `Gate::authorize('create', TipoDocumento::class)`; `rules()` completas (`nome` required/string/max:255, `descricao` required/string, `id_categoria` required/uuid/`Rule::exists('categorias_documento', 'id')`, `posicao_empresa_mae` required/string/`Rule::in(PosicaoEmpresaMae::cases())`, os 4 `espera_*` required/boolean); `withValidator()` — `$validator->after()` adiciona erro a `espera_data_documento` se os 4 `espera_*` validados forem todos `false` (ler via `$this->boolean('espera_data_documento')` etc., não `validated()`, para funcionar mesmo se um campo individual já falhou noutra regra); `messages()` em PT incl. mensagem da regra cross-field.
- Testes associados:
  - `tests/Unit/Features/TipoDocumento/CriarTipoDocumentoActionTest.php` — happy path, rollback (model event `creating` lança excepção), autorização (3 estados).
  - `tests/Feature/Features/TipoDocumento/CriarTipoDocumentoTest.php` — 201 com recurso (incl. `categoria` no payload), 422 campos obrigatórios em falta, 422 `id_categoria` inexistente, 422 todos `espera_*` false, matriz de 3 estados.
- Commit: `feat(tipo-documento): adicionar CriarTipoDocumentoAction + Request`

### Tarefa 4 — Action e FormRequest de Visualização

- Ficheiros a criar:
  - `app/Features/TipoDocumento/Ver/VerTipoDocumentoAction.php`
  - `app/Features/TipoDocumento/Ver/VerTipoDocumentoRequest.php`
- O que implementar:
  - `VerTipoDocumentoAction::handle(TipoDocumento|string $idTipoDocumento): TipoDocumento` — resolve `findOrFail` se string; `Gate::authorize('view', $tipoDocumento)`; `$tipoDocumento->loadMissing('categoria')`.
  - `VerTipoDocumentoRequest::authorize()` via `Gate::authorize('view', $this->route('tipos_documento'))`; `rules(): []`.
- Testes associados:
  - `tests/Unit/Features/TipoDocumento/VerTipoDocumentoActionTest.php` — happy path (objecto e UUID string), 404, autorização (3 estados).
  - `tests/Feature/Features/TipoDocumento/VerTipoDocumentoTest.php` — 200 com recurso, 404, matriz de 3 estados.
- Commit: `feat(tipo-documento): adicionar VerTipoDocumentoAction + Request`

### Tarefa 5 — Action e FormRequest de Actualização

- Ficheiros a criar:
  - `app/Features/TipoDocumento/Actualizar/ActualizarTipoDocumentoAction.php`
  - `app/Features/TipoDocumento/Actualizar/ActualizarTipoDocumentoRequest.php`
- O que implementar:
  - `ActualizarTipoDocumentoAction::handle(TipoDocumento|string $idTipoDocumento, ActualizarTipoDocumentoDto $dados): TipoDocumento` — resolve `findOrFail` se string; `Gate::authorize('update', $tipoDocumento)`; dentro de `DB::transaction()`: `fill([...])->save()`, `refresh()`, `load('categoria')`.
  - `ActualizarTipoDocumentoRequest` — mesmas `rules()` de `CriarTipoDocumentoRequest` mas `id_categoria`/`nome` sem `Rule::unique` adicional (não há `unique` em `TipoDocumento.nome` na issue — confirmar contra `03-models/tipo-documento.md`: `nome` tem índice único na BD; adicionar `Rule::unique('tipos_documento', 'nome')` em `CriarTipoDocumentoRequest` e `->ignore($uuid)` em `ActualizarTipoDocumentoRequest`, mesmo padrão de `slug` em `CriarCategoriaRequest`/`ActualizarCategoriaRequest` — **nota: isto corrige uma omissão da Tarefa 3**, ver "Riscos de implementação"); mesma `withValidator()` de RN-02; `messages()` em PT.
- Testes associados:
  - `tests/Unit/Features/TipoDocumento/ActualizarTipoDocumentoActionTest.php` — happy path, rollback, 404, autorização (3 estados).
  - `tests/Feature/Features/TipoDocumento/ActualizarTipoDocumentoTest.php` — 200 actualizado, 404, 422 campos obrigatórios em falta, 422 `nome` duplicado (ignorando o próprio), 422 todos `espera_*` false, matriz de 3 estados.
- Commit: `feat(tipo-documento): adicionar ActualizarTipoDocumentoAction + Request`

### Tarefa 6 — Action e FormRequest de Eliminação

- Ficheiros a criar:
  - `app/Features/TipoDocumento/Eliminar/EliminarTipoDocumentoAction.php`
  - `app/Features/TipoDocumento/Eliminar/EliminarTipoDocumentoRequest.php`
- O que implementar:
  - `EliminarTipoDocumentoAction::handle(TipoDocumento|string $idTipoDocumento): void` — resolve `findOrFail` se string; `Gate::authorize('delete', $tipoDocumento)`; `DB::transaction(fn () => $tipoDocumento->delete())` — hard delete simples, **sem** try/catch Padrão B (sem `SoftDeletes`).
  - `EliminarTipoDocumentoRequest::authorize()` via `Gate::authorize('delete', $this->route('tipos_documento'))`; `rules(): []`.
- Testes associados:
  - `tests/Unit/Features/TipoDocumento/EliminarTipoDocumentoActionTest.php` — happy path (registo desaparece da BD — `assertModelMissing` ou `TipoDocumento::count()`), 404, autorização (3 estados).
  - `tests/Feature/Features/TipoDocumento/EliminarTipoDocumentoTest.php` — 204, 404, matriz de 3 estados.
- Commit: `feat(tipo-documento): adicionar EliminarTipoDocumentoAction + Request`

### Tarefa 7 — Controller, Resource (confirmação), rotas e integração CA-13

- Ficheiros a criar/alterar:
  - `app/Features/TipoDocumento/TipoDocumentoController.php` (novo)
  - `routes/api.php` (alterar — adicionar `Route::apiResource('tipos-documento', TipoDocumentoController::class)->only(['index', 'store', 'show', 'update', 'destroy'])` dentro do grupo `auth:sanctum`)
  - `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php` (alterar — adicionar caso CA-13, ou novo ficheiro `tests/Feature/Features/TipoDocumento/EliminarCategoriaComTipoDocumentoTest.php` se o existente não for o local certo — confirmar no checkpoint da tarefa)
- O que implementar:
  - `TipoDocumentoController` — 5 métodos (`index`, `store`, `show`, `update`, `destroy`), dispatch puro, `ApiResponse::devolverPaginado`/`devolverCriado`/`devolverSucesso`/`devolverVazio`, mesmo padrão de `CategoriaDocumentoController`.
  - Rota nova em `routes/api.php`.
  - Teste de integração CA-13: criar `CategoriaDocumento` + `TipoDocumento::factory()->for($categoria, 'categoria')`; chamar `EliminarCategoriaAction::handle($categoria)` (sem alterar a Action — já existe Padrão B); `assertSoftDeleted('categorias_documento', ['id' => $categoria->id])`.
- Testes associados: incluídos acima; validar manualmente com `php artisan route:list --path=tipos-documento`.
- Commit: `feat(tipo-documento): adicionar Controller + rotas; teste integração CA-13`

## Ordem de implementação

1. Tarefa 1 — `fromRequest()` nos DTOs, porque todas as Actions de escrita (3, 5) e o Controller dependem deles.
2. Tarefa 2 — Listar, porque não depende de nenhuma outra Action e valida o padrão do filtro `id_categoria` cedo.
3. Tarefa 3 — Criar, porque Ver/Actualizar/Eliminar/CA-13 precisam de registos existentes para testar.
4. Tarefa 4 — Ver, depende de Criar para os testes (happy path lê um registo criado).
5. Tarefa 5 — Actualizar, depende de Criar; corrige a omissão de `Rule::unique('tipos_documento', 'nome')` identificada ao especificar esta tarefa.
6. Tarefa 6 — Eliminar, depende de Criar.
7. Tarefa 7 — Controller + rotas, por último — integra todas as Actions e só faz sentido quando todas existem; teste CA-13 depende de Criar (Tarefa 3) e do Model `TipoDocumento` (#84, já em `main`).

## Testes a escrever

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| `fromRequest()` DTOs | unit | `tests/Unit/Features/TipoDocumento/{Criar,Actualizar}TipoDocumentoDtoTest.php` | mapeamento correcto a partir de FormRequest mockado |
| Listar — Action | unit | `tests/Unit/Features/TipoDocumento/ListarTiposDocumentoActionTest.php` | cursor pagination, filtro `id_categoria`, ordenação, autorização, eager-load |
| Listar — HTTP | feature | `tests/Feature/Features/TipoDocumento/ListarTiposDocumentoTest.php` | lista vazia, `per_page`, 422s, filtro, 3 estados, payload com `categoria` |
| Criar — Action | unit | `tests/Unit/Features/TipoDocumento/CriarTipoDocumentoActionTest.php` | happy path, rollback, autorização |
| Criar — HTTP | feature | `tests/Feature/Features/TipoDocumento/CriarTipoDocumentoTest.php` | 201, 422s (obrigatórios, `id_categoria` inexistente, cross-field), 3 estados |
| Ver — Action | unit | `tests/Unit/Features/TipoDocumento/VerTipoDocumentoActionTest.php` | happy path (objecto+UUID), 404, autorização |
| Ver — HTTP | feature | `tests/Feature/Features/TipoDocumento/VerTipoDocumentoTest.php` | 200, 404, 3 estados |
| Actualizar — Action | unit | `tests/Unit/Features/TipoDocumento/ActualizarTipoDocumentoActionTest.php` | happy path, rollback, 404, autorização |
| Actualizar — HTTP | feature | `tests/Feature/Features/TipoDocumento/ActualizarTipoDocumentoTest.php` | 200, 404, 422s (obrigatórios, nome duplicado, cross-field), 3 estados |
| Eliminar — Action | unit | `tests/Unit/Features/TipoDocumento/EliminarTipoDocumentoActionTest.php` | happy path (hard delete real), 404, autorização |
| Eliminar — HTTP | feature | `tests/Feature/Features/TipoDocumento/EliminarTipoDocumentoTest.php` | 204, 404, 3 estados |
| CA-13 integração | feature | ficheiro a decidir na Tarefa 7 | `EliminarCategoriaAction` com `TipoDocumento` associado → soft delete (Padrão B já existente) |

## Dependências

- Issues bloqueantes: nenhuma — #84 já mergeada em `main` (commit `28e70e2`).
- Deve ser implementada após: #84.

## Riscos de implementação

> Consolidados do Brief e da Spec — não apagados.

- `withValidator()` é o primeiro uso deste mecanismo no projecto — documentar bem em `01-features/tipo-documento.md` (Fase 3a) para servir de referência futura (Brief).
- Duplicação intencional de RN-02 entre DTO (`\InvalidArgumentException`) e FormRequest (`withValidator()` → 422) — risco de drift manual se a regra mudar (Brief).
- Eager loading da relação `categoria` esquecido numa Action → omissão silenciosa de `categoria`/`tipo_movimento` no payload, sem erro do Larastan (Brief) — mitigado por CA-16 (teste de payload).
- CA-13 depende da FK `restrictOnDelete()` de `tipos_documento.id_categoria` (#84) estar correcta em runtime — só validado por teste de integração real, não por leitura do código (Brief).
- **Novo risco identificado ao escrever este Plano (Tarefa 5):** a issue não menciona `Rule::unique('tipos_documento', 'nome')` explicitamente na tabela de regras de validação, mas `03-models/tipo-documento.md` confirma que `nome` tem índice único na BD (`| nome | string(255) | Não | único |`). Sem esta regra, uma tentativa de criar/actualizar com `nome` duplicado falharia com `QueryException` (500) em vez de 422 — inconsistente com o padrão de `CategoriaDocumento.slug`. Adicionar `Rule::unique` em `CriarTipoDocumentoRequest` (Tarefa 3) e `->ignore($uuid)` em `ActualizarTipoDocumentoRequest` (Tarefa 5), com teste 422 dedicado.
- **Decisão pendente de checkpoint (Tarefa 2):** usar `CacheServico` em `ListarTiposDocumentoAction` (precedente de todas as listagens existentes) ou documentar excepção sem cache. Resolver no checkpoint da Tarefa 2, não assumir agora.

## O que NÃO fazer nesta issue

- Não alterar `EliminarCategoriaAction` — CA-13 é um teste que prova comportamento já existente, não uma alteração de código.
- Não adicionar SoftDelete, `RestaurarAction` ou `FiltroEstadoRegisto` a `TipoDocumento`.
- Não adicionar Repository/interface.
- Não adicionar Jobs, Events, Listeners ou Observers.
- Não tocar no Model, Factory, Policy ou Resource de `TipoDocumento` além do necessário (nenhuma alteração prevista a estes ficheiros — só leitura/uso).
- Não implementar o mecanismo de extracção / prompt de IA.
- Não criar migration de permissões nova — `tipos-documento.*` já existe (#84).
