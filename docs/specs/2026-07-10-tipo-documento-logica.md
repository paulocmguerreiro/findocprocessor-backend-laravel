# Spec: TipoDocumento — feature slice (Actions + Controller + FormRequests + testes)

**Issue:** #85
**Brief:** docs/briefs/2026-07-10-tipo-documento-logica.md
**Data:** 2026-07-10

## Requisitos funcionais

- RF-01: `CriarTipoDocumentoAction` cria um `TipoDocumento` a partir de `CriarTipoDocumentoDto`, dentro de `DB::transaction()`, após `Gate::authorize('create', TipoDocumento::class)`.
- RF-02: `ListarTiposDocumentoAction` devolve `CursorPaginator<int, TipoDocumento>` ordenado por `CampoOrdenacaoTiposDocumento` (só `Nome`), com `categoria` eager-loaded; aceita filtro opcional `id_categoria`.
- RF-03: `VerTipoDocumentoAction` devolve um `TipoDocumento` (aceita `TipoDocumento|string`), com `categoria` eager-loaded.
- RF-04: `ActualizarTipoDocumentoAction` faz update completo (PUT semântico) via `fill()` + `save()` + `refresh()`, com `categoria` eager-loaded no resultado devolvido.
- RF-05: `EliminarTipoDocumentoAction` faz hard delete simples (`delete()`) dentro de `DB::transaction()` — sem Padrão B (sem `SoftDeletes` em `TipoDocumento`).
- RF-06: `TipoDocumentoController` faz apenas dispatch — zero lógica de negócio, zero acesso a Model fora das Actions.
- RF-07: `CriarTipoDocumentoRequest`/`ActualizarTipoDocumentoRequest` usam `withValidator()` para validar RN-02 (pelo menos um `espera_*` `true`), com erro 422 associado ao campo `espera_data_documento`.
- RF-08: `fromRequest()` implementado em `CriarTipoDocumentoDto` e `ActualizarTipoDocumentoDto`.
- RF-09: Rotas REST expostas via `Route::apiResource('tipos-documento', TipoDocumentoController::class)->only(['index', 'store', 'show', 'update', 'destroy'])`.

## Requisitos não funcionais

- RNF-01: Autorização dupla camada (`FormRequest::authorize()` + `Action::handle()`) em todas as 5 operações — nunca `return true` hardcoded.
- RNF-02: `strict_types=1` em todos os ficheiros novos.
- RNF-03: 100% code coverage e 100% type coverage (`composer test`).
- RNF-04: Larastan nível 9 sem erros — sem `mixed`; `@var` array shape em todo `validated()`; `@throws` em todo método com `throw`.
- RNF-05: Cursor pagination (`cursorPaginate()`) na listagem — nunca `paginate()` com OFFSET.

## Contratos de API

| Método | Path | Request | Response |
| ------ | ---- | ------- | -------- |
| GET | `/api/tipos-documento` | query: `per_page`, `sort`, `direction`, `cursor`, `id_categoria` (todos opcionais) | 200 paginado (`TipoDocumentoResource::collection`) |
| POST | `/api/tipos-documento` | body: `nome`, `descricao`, `id_categoria`, `posicao_empresa_mae`, `espera_data_documento`, `espera_fornecedor`, `espera_cliente`, `espera_valor` (todos `required`) | 201 (`TipoDocumentoResource`) |
| GET | `/api/tipos-documento/{id}` | — | 200 (`TipoDocumentoResource`) / 404 |
| PUT | `/api/tipos-documento/{id}` | body: idêntico ao `POST` (update completo) | 200 (`TipoDocumentoResource`) / 404 / 422 |
| DELETE | `/api/tipos-documento/{id}` | — | 204 / 404 |

## Modelo de dados

Sem alteração — `tipos_documento` já criada em #84. Ver `docs/system_spec/03-models/tipo-documento.md`.

## Regras de negócio

- RN-01 *(#84)*: `id_categoria` obrigatório.
- RN-02 *(#84, validada aqui em dupla camada)*: pelo menos um dos 4 `espera_*` `true`. Construtor do DTO lança `\InvalidArgumentException` (contexto não-HTTP); `withValidator()` no FormRequest devolve 422 amigável (contexto HTTP) — ver "Questões resolvidas".
- RN-03 *(#84)*: `tipo_movimento` no Resource é sempre derivado de `categoria.tipo_movimento` — as Actions que devolvem `TipoDocumento` têm de eager-load `categoria`, senão o campo fica omitido/nulo no payload.
- RN-05 *(nova, desta issue)*: filtro `id_categoria` na listagem — se fornecido, tem de corresponder a uma `CategoriaDocumento` existente (`Rule::exists('categorias_documento', 'id')`); se omitido, sem filtro aplicado (comportamento de `->when()`).

## Dependências

- Issues bloqueantes: nenhuma — #84 (camada de modelo) já está mergeada (`main`, commit `28e70e2`).

## Questões resolvidas

| Questão (do Brief) | Decisão |
| ------------------ | ------- |
| Validação do parâmetro `id_categoria` em `ListarTiposDocumentoRequest` | `['sometimes', 'string', 'uuid', Rule::exists('categorias_documento', 'id')]` — omitido: sem filtro, sem impacto; fornecido: tem de existir, senão 422. Decisão do utilizador no Checkpoint A (2026-07-10): manter `sometimes` + `Rule::exists` em simultâneo, ao contrário da proposta inicial (uuid sem exists) — a assimetria entre "campo opcional" e "valor tem de ser válido quando presente" é a mesma já usada em `estado` (`ListarCategoriasRequest`) para `Rule::in`, agora estendida a `Rule::exists`. |

## Critérios de aceitação

> Herdados da issue #85 — não removidos nem reformulados.

- [ ] CA-01: Cada operação tem a sua Action com método `handle()` único *(issue)*
- [ ] CA-02: Controller não contém lógica de negócio — apenas dispatch *(issue)*
- [ ] CA-03: Actions acedem directamente ao Eloquent (CRUD simples, sem Repository — mesmo desvio aceite em `CategoriaDocumento`) *(issue)*
- [ ] CA-04: `FormRequest::authorize()` **e** `Action::handle()` chamam `Gate::authorize()`/`TipoDocumentoPolicy` (dupla camada) — nunca `return true` *(issue)*
- [ ] CA-05: `fromRequest()` implementado em `CriarTipoDocumentoDto` e `ActualizarTipoDocumentoDto` *(issue)*
- [ ] CA-06: `CriarTipoDocumentoRequest`/`ActualizarTipoDocumentoRequest` usam `withValidator()` para validar que pelo menos um `espera_*` é `true`, devolvendo 422 com mensagem clara *(issue)*
- [ ] CA-07: `id_categoria` validado com `Rule::exists('categorias_documento', 'id')`; `posicao_empresa_mae` validado com `Rule::in(...)` *(issue)*
- [ ] CA-08: `ListarTiposDocumentoAction` usa `cursorPaginate()` (nunca `paginate()` com OFFSET) e aceita filtro opcional `id_categoria` *(issue)*
- [ ] CA-09: `EliminarTipoDocumentoAction` faz hard delete simples (sem fallback soft delete — não há SoftDelete em `TipoDocumento`) *(issue)*
- [ ] CA-10: Testes cobrem a matriz de 3 estados por endpoint (guest → 401 / com-permissão → 2xx / sem-permissão → 403) + 404 — nas duas camadas (HTTP e Action) *(issue)*
- [ ] CA-11: Testes cobrem o 422 da validação cross-field (todos os `espera_*` a `false`) *(issue)*
- [ ] CA-12: Testes cobrem o filtro `id_categoria` na listagem *(issue)*
- [ ] CA-13: Teste de integração confirma que, ao eliminar uma `CategoriaDocumento` com um `TipoDocumento` associado, a categoria cai em soft delete (Padrão B em `EliminarCategoriaAction` — a FK nova de `tipos_documento.id_categoria` activa o mesmo fallback) *(issue)*
- [ ] CA-14: 100% code coverage e 100% type coverage (`composer test`) *(issue)*
- [ ] CA-15: `id_categoria` no filtro de listagem devolve 422 quando fornecido mas inexistente (`Rule::exists`), e não filtra quando omitido *(spec — decorre da questão resolvida)*
- [ ] CA-16: Actions `Criar`/`Ver`/`Actualizar`/`Listar` eager-load a relação `categoria` — teste de feature confirma que `categoria`/`tipo_movimento` aparecem no payload JSON de cada endpoint *(spec)*

## SYSTEM_SPEC a actualizar

- `docs/system_spec/01-features/tipo-documento.md` — ficheiro novo (Actions, DTOs `fromRequest()`, FormRequests incl. `withValidator()`, Controller, rotas)
- `docs/system_spec/05-routes/tipos-documento.md` — ficheiro novo (endpoints, query params)
- `docs/system_spec/00-index.md` — linha em "Features implementadas" e em "Rotas e Configuração"
- `./openapi.yaml` (raiz) — adicionar `/tipos-documento` (GET, POST, GET/{id}, PUT/{id}, DELETE/{id}); escrita efectiva na Fase 3a (`/documenta-implementacao`), aqui só se declara o delta

## Verificação RGPD/NIS2

- Dados pessoais: não — `TipoDocumento` não contém dados pessoais.
- Superfície de ataque: aumentada — 5 novos endpoints expostos, mitigados pela dupla camada de autorização (RNF-01) e pela `Rule::exists` em `id_categoria` (evita enumeração silenciosa de categorias via filtro — um UUID inválido dá 422, não 200 com lista vazia, o que é a decisão tomada mas não constitui vazamento de dados, apenas confirma que o filtro foi bem formado).
