# Brief: TipoDocumento — feature slice (Actions + Controller + FormRequests + testes)

**Issue:** #85
**Data:** 2026-07-10
**Branch:** feat/tipo-documento-logica

## Contexto

A camada de modelo de `TipoDocumento` (`app/Models/TipoDocumento.php`, Policy, DTOs sem `fromRequest()`, Resource) foi criada na Issue #84. Esta issue completa a feature slice com a camada de lógica — Actions, Controller, FormRequests e rotas REST — para que o mecanismo de extracção (issue futura) e, entretanto, qualquer cliente da API, consiga gerir definições de tipo de documento via HTTP. Segue o mesmo padrão já estabelecido em `CategoriaDocumento` (#5), com uma particularidade nova: `TipoDocumento` não tem SoftDelete (sem `restaurar`, sem `withTrashed`), e introduz o primeiro uso de `withValidator()` no projecto para uma regra de negócio cross-field (RN-02).

## O que muda

- **Actions** (`app/Features/TipoDocumento/<Accao>/`): `CriarTipoDocumentoAction`, `ListarTiposDocumentoAction`, `VerTipoDocumentoAction`, `ActualizarTipoDocumentoAction`, `EliminarTipoDocumentoAction` — uma por operação, `handle()` único, autorização via `Gate::authorize()` (`TipoDocumentoPolicy`), escrita em `DB::transaction()`. Modelo directo (`CategoriaDocumentoAction`-equivalente): sem Repository (CRUD simples).
- **`EliminarTipoDocumentoAction`**: hard delete simples (`delete()` dentro de `DB::transaction()`) — **não** usa Padrão B (try/catch `forceDelete`/`fresh()->delete()`), porque `TipoDocumento` não tem `SoftDeletes` e nada referencia `tipos_documento` por FK.
- **`ListarTiposDocumentoAction`**: `cursorPaginate()`; novo enum `CampoOrdenacaoTiposDocumento` (`Nome = 'nome'`) em `app/Features/TipoDocumento/Listar/`; filtro opcional `id_categoria` via `->when()` (mesmo padrão de `ListarDocumentosAction::handle()` com `EstadoDocumento`), aplicado como `where('id_categoria', $idCategoria)` quando presente.
- **Controller** `TipoDocumentoController` — dispatch puro, Route Model Binding (`TipoDocumento $tipos_documento`).
- **FormRequests**: `CriarTipoDocumentoRequest`, `ListarTiposDocumentoRequest`, `VerTipoDocumentoRequest`, `ActualizarTipoDocumentoRequest`, `EliminarTipoDocumentoRequest` — `authorize()` via `Gate::authorize()`/`$this->route('tipos_documento')`; `rules()` completas incl. `Rule::exists('categorias_documento', 'id')` para `id_categoria` e `Rule::in(...)` para `posicao_empresa_mae`; `messages()` em PT.
- **`withValidator()`** em `CriarTipoDocumentoRequest`/`ActualizarTipoDocumentoRequest` — regra "after" que valida RN-02 (pelo menos um `espera_*` `true`) a nível HTTP, devolvendo 422 amigável em vez de deixar o `\InvalidArgumentException` do construtor do DTO propagar como 500.
- **`fromRequest()`** implementado em `CriarTipoDocumentoDto` e `ActualizarTipoDocumentoDto` (ficheiros já existentes de #84).
- **Rotas** (`routes/api.php`): `Route::apiResource('tipos-documento', TipoDocumentoController::class)->only(['index', 'store', 'show', 'update', 'destroy'])` — sem `withTrashed()`, sem `/restaurar` (sem SoftDelete).
- **Eager loading**: Actions que devolvem `TipoDocumento` (`Criar`, `Ver`, `Actualizar`, `Listar`) carregam a relação `categoria` (`->with('categoria')` ou `->load('categoria')`) — o `TipoDocumentoResource` usa `whenLoaded('categoria')` e omite o campo silenciosamente se não for eager-loaded.
- **SYSTEM_SPEC**: `01-features/tipo-documento.md` (novo), `05-routes/tipos-documento.md` (novo), `00-index.md` (linhas de Features implementadas + Rotas).

## O que NÃO muda

- Sem Repository/interface — CRUD simples, mesmo desvio aceite em `CategoriaDocumento`.
- Sem Jobs/Events/Listeners/Observers — sem processamento assíncrono nesta issue.
- Sem SoftDelete em `TipoDocumento` — sem `RestaurarAction`, sem `FiltroEstadoRegisto`, sem `/restaurar`.
- Sem alteração ao Model, Factory, Policy ou Resource de `TipoDocumento` (já fechados em #84) — excepto o eager-load de `categoria` feito nas Actions, não no Model.
- Sem alteração ao mecanismo de extracção / prompt de IA — issue futura diferida.
- Sem migration nova de permissões — `tipos-documento.{ver,criar,actualizar,eliminar}` já existe desde #84 (`seed_tipos_documento_permissions`), `admin` tem todas, `utilizador` só `.ver`.
- Sem relação inversa `hasMany` em `CategoriaDocumento`.

## Riscos identificados

- **`withValidator()` é o primeiro uso deste mecanismo no projecto** — sem precedente noutra feature. A regra "after" (`$validator->after(fn ($v) => ...)`, ver `search-docs` → Validation "Performing Additional Validation") tem de adicionar o erro a um campo concreto (`espera_data_documento`, conforme enunciado na issue) para o payload 422 ficar consistente com o padrão Problem Details (`02-shared/http.md`). Documentar bem em `01-features/tipo-documento.md` para servir de referência futura.
- **Duplicação intencional de RN-02** (DTO construtor `\InvalidArgumentException` **e** FormRequest `withValidator()` → 422): não é redundância acidental — o DTO protege contextos não-HTTP (Jobs, Artisan, testes directos à Action), o FormRequest dá 422 amigável em HTTP. As duas validações têm de ser mantidas em sincronia manualmente (sem fonte única) — risco de drift se a regra mudar (ex: passar a exigir 2 campos) e só um dos dois sítios for actualizado.
- **Eager loading da relação `categoria`**: se uma Action esquecer `->with('categoria')`/`->load('categoria')`, o `TipoDocumentoResource` não falha — omite silenciosamente `categoria` e `tipo_movimento` fica `null` (RN-03 depende de `categoria` carregada). Risco de bug silencioso não coberto por Larastan; só um teste de feature que inspecciona o payload JSON apanha a omissão.
- **CA-13 (integração com `EliminarCategoriaAction`)**: a nova FK `tipos_documento.id_categoria` (`restrictOnDelete()`, criada em #84) só é exercitada em runtime quando existe pelo menos um `TipoDocumento` associado. O teste tem de criar esse `TipoDocumento` antes de chamar `EliminarCategoriaAction::handle()` na `CategoriaDocumento` pai, e confirmar que o catch do Padrão B (`02-shared/soft-delete.md`) dispara — nenhuma alteração é feita a `EliminarCategoriaAction`, o teste apenas prova que o fallback já existente cobre esta FK nova.
- **Filtro opcional `id_categoria` na listagem**: não há `Rule::exists` nem `Rule::uuid` explicitados na issue para este parâmetro de query — decidir na Spec se o filtro valida formato UUID (`sometimes|uuid`) ou aceita qualquer string (e devolve lista vazia se não corresponder a nenhum registo). Ver `## Questões em aberto`.

## Questões em aberto

- **Validação do parâmetro `id_categoria` em `ListarTiposDocumentoRequest`**: a issue não especifica as regras. Proposta para a Spec: `['sometimes', 'string', 'uuid']` — sem `Rule::exists`, porque um `id_categoria` inexistente deve devolver lista vazia (200), não 422 (comportamento de filtro, não de referência obrigatória — consistente com `estado` em `ListarCategoriasRequest`, que também não valida existência).
