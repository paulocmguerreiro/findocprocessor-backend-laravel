# Debrief — Issue #85: TipoDocumento — Camada de Lógica

**Data:** 2026-07-10
**Issue:** #85
**Slug:** `tipo-documento-logica`
**Branch:** `feat/tipo-documento-logica`

---

## Resumo

Completada a feature slice `TipoDocumento` com a camada de lógica: 5 Actions CRUD (`Criar`, `Listar`, `Ver`, `Actualizar`, `Eliminar`), respectivos FormRequests, `Controller` e rotas REST (`Route::apiResource`). Sem Repository (CRUD simples, mesmo desvio de `CategoriaDocumento`). Sem SoftDelete — hard delete simples, sem `RestaurarAction`. Introduz `withValidator()` — primeiro uso deste mecanismo no projecto — para validar em dupla camada a regra cross-field RN-02 (pelo menos um `espera_*` `true`), devolvendo 422 amigável em HTTP em vez de deixar propagar a `\InvalidArgumentException` do construtor do DTO.

---

## Critérios de aceitação — resultado

| CA    | Descrição                                                                                                              | Estado |
| ----- | ---------------------------------------------------------------------------------------------------------------------- | ------ |
| CA-01 | Cada operação tem a sua Action com `handle()` único                                                                    | ✅     |
| CA-02 | Controller sem lógica de negócio — apenas dispatch                                                                     | ✅     |
| CA-03 | Actions acedem directamente ao Eloquent (sem Repository)                                                               | ✅     |
| CA-04 | Autorização dupla camada (`FormRequest` + `Action`) em todas as 5 operações                                            | ✅     |
| CA-05 | `fromRequest()` em `CriarTipoDocumentoDto` e `ActualizarTipoDocumentoDto`                                              | ✅     |
| CA-06 | `withValidator()` valida RN-02 e devolve 422 com mensagem clara                                                        | ✅     |
| CA-07 | `id_categoria` com `Rule::exists`; `posicao_empresa_mae` com `Rule::in`                                                | ✅     |
| CA-08 | `ListarTiposDocumentoAction` usa `cursorPaginate()` + filtro opcional `id_categoria`                                   | ✅     |
| CA-09 | `EliminarTipoDocumentoAction` faz hard delete simples (sem Padrão B)                                                   | ✅     |
| CA-10 | Testes cobrem matriz de 3 estados + 404 (HTTP e Action)                                                                | ✅     |
| CA-11 | Testes cobrem 422 da validação cross-field                                                                             | ✅     |
| CA-12 | Testes cobrem filtro `id_categoria` na listagem                                                                        | ✅     |
| CA-13 | Teste de integração: eliminar `CategoriaDocumento` com `TipoDocumento` associado → soft delete (Padrão B já existente) | ✅     |
| CA-14 | 100% code coverage e 100% type coverage                                                                                | ✅     |
| CA-15 | `id_categoria` inexistente no filtro → 422; omitido → sem filtro                                                       | ✅     |
| CA-16 | Actions `Criar`/`Ver`/`Actualizar`/`Listar` eager-load `categoria`                                                     | ✅     |

---

## Tarefas executadas

| #   | Tarefa                                                                       | Commit    | Resultado |
| --- | ---------------------------------------------------------------------------- | --------- | --------- |
| T1  | `fromRequest()` nos DTOs existentes                                          | `8aebbe8` | verde     |
| T2  | Enum `CampoOrdenacaoTiposDocumento` + `ListarTiposDocumentoAction`/`Request` | `42e08f8` | verde     |
| T3  | `CriarTipoDocumentoAction`/`Request`                                         | `3af9b7c` | verde     |
| T4  | `VerTipoDocumentoAction`/`Request`                                           | `5e7bce6` | verde     |
| T5  | `ActualizarTipoDocumentoAction`/`Request`                                    | `3199f57` | verde     |
| T6  | `EliminarTipoDocumentoAction`/`Request`                                      | `629b6a8` | verde     |
| T7  | `TipoDocumentoController` + rotas + teste integração CA-13                   | `490f8df` | verde     |
| —   | Correcção `ArchTest` exclusions + asserts `AssertableJson`                   | `5127892` | verde     |

---

## Decisões tomadas

### D1 — Cache em `ListarTiposDocumentoAction` (checkpoint Tarefa 2)

**Decisão:** seguir o precedente de todas as listagens existentes (`ListarCategoriasAction`, `ListarDocumentosAction`) e usar `CacheServico` com `TagCache::TiposDocumento` dedicada, em vez de documentar uma excepção sem cache.

### D2 — `Rule::unique('tipos_documento', 'nome')` adicionado fora do enunciado da issue

**Decisão:** `CriarTipoDocumentoRequest` e `ActualizarTipoDocumentoRequest` (com `->ignore($uuid)`) validam unicidade de `nome`, apesar de a issue não mencionar esta regra explicitamente.

**Por quê:** `03-models/tipo-documento.md` confirma índice único em `nome` na BD. Sem a regra, um `nome` duplicado geraria `QueryException` (500) em vez de 422 — inconsistente com o padrão já usado em `CategoriaDocumento.slug`. Identificado ao escrever o Plano (Tarefa 5), não durante a implementação.

### D3 — Filtro `id_categoria`: `sometimes` + `Rule::exists` em simultâneo

**Decisão:** `['sometimes', 'string', 'uuid', Rule::exists('categorias_documento', 'id')]` — omitido não valida nada; fornecido tem de corresponder a um registo existente, senão 422.

**Por quê:** decisão do utilizador no Checkpoint A — a assimetria "opcional, mas válido quando presente" já existe para `estado` (`Rule::in`) em `ListarCategoriasRequest`; estende-se agora a `Rule::exists`, em vez de aceitar silenciosamente um `id_categoria` inexistente e devolver lista vazia.

---

## Desvios ao plano original

| Desvio                                                                                                                                                                                                                                                           | Impacto                                                           |
| ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| `ArchTest.php` precisou de exclusões adicionais (`CriarTipoDocumentoRequest`, `ActualizarTipoDocumentoRequest`, `CampoOrdenacaoTiposDocumento`) e 3 testes de Feature precisaram de ajustes de asserts `AssertableJson`, corrigidos num commit isolado após o T7 | Sem impacto de âmbito — ajuste de teste, não de lógica de negócio |

---

## Ficheiros criados/alterados

| Ficheiro                                                                                | Operação                           |
| --------------------------------------------------------------------------------------- | ---------------------------------- |
| `app/Features/TipoDocumento/Criar/CriarTipoDocumentoDto.php`                            | Alterado (`fromRequest()`)         |
| `app/Features/TipoDocumento/Actualizar/ActualizarTipoDocumentoDto.php`                  | Alterado (`fromRequest()`)         |
| `app/Features/TipoDocumento/Criar/CriarTipoDocumentoAction.php`                         | Criado                             |
| `app/Features/TipoDocumento/Criar/CriarTipoDocumentoRequest.php`                        | Criado                             |
| `app/Features/TipoDocumento/Listar/CampoOrdenacaoTiposDocumento.php`                    | Criado                             |
| `app/Features/TipoDocumento/Listar/ListarTiposDocumentoAction.php`                      | Criado                             |
| `app/Features/TipoDocumento/Listar/ListarTiposDocumentoRequest.php`                     | Criado                             |
| `app/Features/TipoDocumento/Ver/VerTipoDocumentoAction.php`                             | Criado                             |
| `app/Features/TipoDocumento/Ver/VerTipoDocumentoRequest.php`                            | Criado                             |
| `app/Features/TipoDocumento/Actualizar/ActualizarTipoDocumentoAction.php`               | Criado                             |
| `app/Features/TipoDocumento/Actualizar/ActualizarTipoDocumentoRequest.php`              | Criado                             |
| `app/Features/TipoDocumento/Eliminar/EliminarTipoDocumentoAction.php`                   | Criado                             |
| `app/Features/TipoDocumento/Eliminar/EliminarTipoDocumentoRequest.php`                  | Criado                             |
| `app/Features/TipoDocumento/TipoDocumentoController.php`                                | Criado                             |
| `app/Shared/Cache/TagCache.php`                                                         | Alterado (`TiposDocumento` case)   |
| `routes/api.php`                                                                        | Alterado (rotas `tipos-documento`) |
| `tests/ArchTest.php`                                                                    | Alterado (exclusões)               |
| `tests/Unit/Features/TipoDocumento/*ActionTest.php` (5) + `*DtoTest.php` (2, alterados) | Criados/Alterados                  |
| `tests/Feature/Features/TipoDocumento/*Test.php` (5)                                    | Criados                            |
| `tests/Feature/Features/TipoDocumento/EliminarCategoriaComTipoDocumentoTest.php`        | Criado (CA-13)                     |
| `docs/briefs/2026-07-10-tipo-documento-logica.md`                                       | Criado                             |
| `docs/specs/2026-07-10-tipo-documento-logica.md`                                        | Criado                             |
| `docs/plans/2026-07-10-tipo-documento-logica.md`                                        | Criado                             |

---

## Métricas finais

| Métrica           | Valor                                                                                                           |
| ----------------- | --------------------------------------------------------------------------------------------------------------- |
| Testes totais     | 853                                                                                                             |
| Testes aprovados  | 853                                                                                                             |
| Assertions        | 2020                                                                                                            |
| Type coverage     | 100%                                                                                                            |
| Code coverage     | 100%                                                                                                            |
| Larastan erros    | 0                                                                                                               |
| Rector alterações | 0                                                                                                               |
| Pint alterações   | 0                                                                                                               |
| Checkpoint scan   | ver `docs/process-warnings.md` (WRN-003 — NPM CVE Audit dev-only, ignorado por decisão já registada em WRN-001) |

---

## Aprendizagens

### 1. `withValidator()` cobre o que o construtor do DTO não pode cobrir sozinho

O DTO (`CriarTipoDocumentoDto`/`ActualizarTipoDocumentoDto`) já validava RN-02 no construtor, lançando `\InvalidArgumentException` — suficiente para proteger contextos não-HTTP (Jobs, Artisan, testes directos à Action). Mas em HTTP, deixar essa excepção propagar dava 500, não 422. `withValidator()` com `$validator->after()` resolve isto sem duplicar a lógica de negócio em si — apenas antecipa a mesma verificação para produzir um erro de validação amigável antes de o DTO sequer ser construído. É a primeira vez que este mecanismo aparece no projecto e fixa o padrão: sempre que um DTO tem uma invariante cross-field que pode falhar de forma previsível a partir de input do utilizador, vale a pena espelhar essa verificação em `withValidator()` — não porque o DTO seja insuficiente, mas porque o contexto HTTP tem uma expectativa de resposta diferente (422 estruturado vs. excepção não tratada).

### 2. Ler `$this->boolean(...)` em vez de `validated()` dentro de `withValidator()->after()`

A regra cross-field lê `$this->boolean('espera_data_documento')` (e os outros 3 campos) directamente do request, não de `$validated`. Isto foi deliberado (documentado no Plano): se `validated()` for usado e um campo individual já tiver falhado outra regra (`required`/`boolean`), essa chave pode não existir no array validado, e a regra `after()` correria o risco de nunca disparar ou de lançar um erro de acesso a chave inexistente. Ler directamente do request via `$this->boolean()` (que tem fallback `false` para chave ausente) desacopla a regra cross-field do resultado das regras por-campo, tornando-a robusta a combinações de erros simultâneos.

### 3. Duplicação intencional exige disciplina de sincronização manual

RN-02 vive em dois sítios — construtor do DTO e `withValidator()` do FormRequest — e nenhum dos dois deriva do outro. Isto não é acidental (documentado como risco no Brief), mas é um lembrete de que "dupla camada" em Vertical Slice nem sempre significa "uma fonte de verdade com dois pontos de entrada": por vezes significa duas implementações da mesma regra, cada uma no formato exigido pelo seu contexto (excepção vs. erro de validação), que têm de ser actualizadas em conjunto se a regra de negócio mudar. Vale a pena marcar este tipo de duplicação explicitamente no código ou na spec, para não ser confundida com lógica esquecida numa futura alteração.

---

## Próximo passo

Fase 3a em curso → `/publica-implementacao #85`
