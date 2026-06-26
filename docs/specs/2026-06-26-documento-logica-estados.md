# Spec: Documento — lógica (máquina de estados: Actions de transição + listagem + Regra* + Events)

**Issue:** #57
**Brief:** docs/briefs/2026-06-26-documento-logica-estados.md
**Data:** 2026-06-26

## Requisitos funcionais

### Actions de transição (uma slice por operação)

- **RF-01:** `RegistarDocumentoManualAction` cria um `Documento` directamente em `Processado` com
  todos os campos preenchidos, escreve **uma** `EtapaDocumento` `(Processado, "registo manual", id_utilizador)`
  e emite `DocumentoProcessado`. DTO: `CriarDocumentoManualDto` (#45, com `fromRequest()` adicionado).
- **RF-02:** `ReceberUploadDocumentoAction` recebe um ficheiro (`UploadedFile`), calcula o
  `hash_sha256`, escreve-o no disco `entrada`, cria o `Documento` em `Pendente`, escreve `EtapaDocumento`
  `(Pendente, "upload recebido", id_utilizador)`. DTO `ReceberUploadDocumentoDto`. Exposição HTTP
  (`multipart/form-data`).
- **RF-03:** `MarcarAguardaEnvioDocumentoAction` transiciona `Pendente → AguardaEnvio` (ficheiro fica em
  `entrada`); escreve `EtapaDocumento`. Sem DTO. Invocação pipeline (programática).
- **RF-04:** `MarcarEnviadoDocumentoAction` transiciona `AguardaEnvio → Enviado` (move ficheiro
  `entrada → enviado`); escreve `EtapaDocumento`. Pipeline.
- **RF-05:** `MarcarAguardaRespostaDocumentoAction` transiciona `Enviado → AguardaResposta` (ficheiro
  fica em `enviado`); escreve `EtapaDocumento`. Pipeline.
- **RF-06:** `TransicionarProcessadoDocumentoAction` transiciona `AguardaResposta → Processado`,
  preenche fornecedor/cliente/categoria/valor/data (DTO `TransicionarProcessadoDocumentoDto`), move
  ficheiro `enviado → processado` **e renomeia** via `RegraNomearProcessado`; escreve `EtapaDocumento`;
  emite `DocumentoProcessado`. Pipeline.
- **RF-07:** `MarcarErroDocumentoAction` transiciona `AguardaResposta → Erro` com `mensagem_erro` (DTO
  `MarcarErroDocumentoDto`), move ficheiro `enviado → erro`; escreve `EtapaDocumento` `(Erro, mensagem_erro, …)`;
  emite `DocumentoMarcadoErro`. Pipeline.
- **RF-08:** `MarcarPerigosoDocumentoAction` transiciona `Pendente → Perigoso` (pré-scan) **ou**
  `AguardaResposta → Perigoso` (guardrail) com `motivo` (DTO `MarcarPerigosoDocumentoDto`), move ficheiro
  para `perigoso`; escreve `EtapaDocumento`; emite `DocumentoMarcadoPerigoso`. Pipeline.
- **RF-09:** `ReprocessarDocumentoAction` transiciona `Erro → AguardaEnvio` com `modo` (enum
  `ModoReprocessamento`; DTO `ReprocessarDocumentoDto`), move ficheiro `erro → entrada`; escreve
  `EtapaDocumento` `(AguardaEnvio, modo->value, id_utilizador)`; emite `DocumentoReprocessado`.
  Exposição HTTP.
- **RF-10:** `CorrigirDocumentoAction` transiciona `Processado → Processado` (loop) com campos
  corrigidos (DTO `ActualizarDocumentoDto`, #45, com `fromRequest()`); se o slug do nome mudar, renomeia
  via `RegraNomearProcessado`; escreve `EtapaDocumento` `(Processado, "correcção", id_utilizador)`.
  Exposição HTTP.
- **RF-11:** `EliminarDocumentoAction` elimina o `Documento` (qualquer estado) e apaga o ficheiro do
  disco actual. Exposição HTTP. O histórico (`EtapaDocumento`) é removido por `cascadeOnDelete()` (#56).

### Listagem e leitura

- **RF-12:** `ListarDocumentosAction` devolve `cursorPaginate` ordenado por `CampoOrdenacaoDocumentos`
  (`DataDocumento`/`CriadoEm`) + `DirecaoOrdenacao`, com filtro opcional por estado mapeado a
  `whereEstado(EstadoDocumento)`. Cache via `CacheServico` (`TagCache::Documentos`, TTL curta). Direto no
  Eloquent, **sem Repository**.
- **RF-13:** `DocumentoResource` expõe o histórico via `whenLoaded('historico')`, serializado por um
  `EtapaDocumentoResource` novo (campos: `estado`, `motivo`, `id_utilizador`, `criado_em`). Sem endpoint
  dedicado de histórico nesta issue.

### Classes `Regra*` (invariantes injectados)

- **RF-14:** `RegraTransicaoEstado` valida que a transição `De → Para` consta de um **mapa central**;
  rejeita transições inválidas com excepção tipada `TransicaoInvalidaException` (convertida para `422`
  pelo exception handler). Sem `Gate::authorize()` própria.
- **RF-15:** `RegraMoverFicheiro` move o ficheiro entre discos (`put(get())` no destino + `delete()` na
  origem, porque os discos são distintos), verifica o valor de retorno de cada operação (discos têm
  `'throw' => false`) e lança em falha; deixa `disco_storage`/`nome_ficheiro_storage` consistentes com o
  novo `status`. Compensação best-effort: em excepção, tenta mover de volta.
- **RF-16:** `RegraNomearProcessado` gera o nome `yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}`
  com `Str::slug()`, preservando a extensão do `nome_ficheiro_original`.

### HTTP

- **RF-17:** `DocumentoController` (zero lógica) com endpoints só para as Actions HTTP: listar, registar
  manual, receber upload, reprocessar, corrigir, eliminar, ver. FormRequests com `authorize()` via
  `Gate` e `messages()` em PT.

## Requisitos não funcionais

- **RNF-01:** Todos os ficheiros com `declare(strict_types=1)`; Actions e Regra* `final readonly`; DTOs
  `final readonly`; Events `final`.
- **RNF-02:** Autorização dupla camada — `Gate::authorize()` no FormRequest **e** na Action; fora da
  `DB::transaction()`. **Mapeamento Action → ability** (reusa os 5 abilities CRUD do `DocumentoPolicy`,
  que continua stub — sem abilities granulares novas):

  | Ability | Actions |
  | ------- | ------- |
  | `viewAny` | `ListarDocumentosAction` |
  | `view` | `VerDocumentoAction` |
  | `create` | `RegistarDocumentoManualAction`, `ReceberUploadDocumentoAction` |
  | `update` | `CorrigirDocumentoAction`, `ReprocessarDocumentoAction`, `TransicionarProcessadoDocumentoAction`, `MarcarAguardaEnvioDocumentoAction`, `MarcarEnviadoDocumentoAction`, `MarcarAguardaRespostaDocumentoAction`, `MarcarErroDocumentoAction`, `MarcarPerigosoDocumentoAction` |
  | `delete` | `EliminarDocumentoAction` |

- **RNF-02b:** Contexto de invocação das Actions de pipeline — todas autorizam contra um **utilizador
  autenticado** (não há actor de sistema anónimo). Os testes Unit usam `actingAs(User::factory())`.
  **Limitação conhecida (diferida para a issue de Jobs/extracção):** os Jobs que invocarem as transições
  de pipeline correrão em nome do **utilizador que primeiro interagiu com o documento** — o autor do
  upload/registo, identificado pelo `id_utilizador` da primeira `EtapaDocumento`. Nesta issue não há Job
  real; só se garante que as Actions são autorizáveis com um utilizador no contexto.
- **RNF-03:** `DB::transaction()` em todas as Actions de escrita; `@throws \Throwable`; persistência +
  movimento de ficheiro + `EtapaDocumento` na mesma transacção (movimento dentro, com compensação).
- **RNF-04:** Events implementam `ShouldDispatchAfterCommit`; dispatch só após commit.
- **RNF-05:** `composer test` — 100% coverage + 100% type coverage; zero erros Larastan nível 9; Pint +
  Rector limpos.
- **RNF-06:** Campos sensíveis (`hash_sha256`, `disco_storage`, `nome_ficheiro_storage`, caminhos,
  `motivo`/`mensagem_erro`) não logados em claro; `Log::info` regista só ids e contexto não-sensível.
- **RNF-07:** Testes duais por slice — Unit (invocação programática, ex.: por Job) + Feature (HTTP, só
  Actions expostas).

## Contratos de API

| Método | Path | Request | Response |
| ------ | ---- | ------- | -------- |
| GET | `/api/documentos` | `?per_page&sort&direction&estado` | `200` paginado `DocumentoResource[]` |
| POST | `/api/documentos` | `CriarDocumentoManualRequest` (JSON) | `201` `DocumentoResource` |
| POST | `/api/documentos/upload` | `ReceberUploadDocumentoRequest` (`multipart/form-data`) | `201` `DocumentoResource` |
| GET | `/api/documentos/{documento}` | — (`?include=historico`) | `200` `DocumentoResource` |
| PATCH | `/api/documentos/{documento}` | `CorrigirDocumentoRequest` (JSON) | `200` `DocumentoResource` |
| POST | `/api/documentos/{documento}/reprocessar` | `ReprocessarDocumentoRequest` (`modo`) | `200` `DocumentoResource` |
| DELETE | `/api/documentos/{documento}` | — | `204` |

> As transições de pipeline (RF-03/04/05/06/07/08) **não** têm endpoint — são invocadas
> programaticamente (Job da extracção, issue futura). Têm só testes Unit.

## Modelo de dados

Sem alterações de schema. Consome `documentos` (#45) e `etapas_documento` (#56). Escreve linhas em
`etapas_documento` por transição. Novos artefactos de código (não-BD):

| Artefacto | Tipo | Notas |
| --------- | ---- | ----- |
| `ModoReprocessamento` | enum string | `Modelo='MODELO'`, `Ferramenta='FERRAMENTA'` |
| `CampoOrdenacaoDocumentos` | enum string | `DataDocumento='data_documento'`, `CriadoEm='created_at'` |
| `TagCache::Documentos` | caso de enum | `'documentos'` |
| `TransicaoInvalidaException` | exception | mapeada a `422` |

## Regras de negócio

- **RN-01:** Toda a mudança de estado passa por `RegraTransicaoEstado` (mapa central) — nunca
  `if ($doc->status == …)`. Transições válidas = exactamente as do grafo da issue.
- **RN-02:** `status ↔ disco_storage ↔ nome_ficheiro_storage` ficam sempre consistentes após uma
  transição (garantido por `RegraMoverFicheiro`).
- **RN-03:** Cada Action de criação/transição grava **exactamente uma** `EtapaDocumento` na mesma
  transacção; rollback não deixa histórico órfão.
- **RN-04:** `Erro` é estado retido — só sai por `ReprocessarDocumentoAction`, deliberada e
  parametrizada por `modo`.
- **RN-05:** `MarcarPerigosoDocumentoAction` é alcançável de `Pendente` (pré-scan) e de
  `AguardaResposta` (guardrail).
- **RN-06:** `RegistarDocumentoManualAction` gera uma única linha de histórico, logo em `Processado`.

## Dependências

- Issues bloqueantes: nenhuma (#45 e #56 já mergeadas — Model, state objects, DTOs, `EtapaDocumento`).
- Diferida (consumidora futura): mecanismo de extracção (IA/OCR) — invocará as Actions de pipeline.

## Questões resolvidas

| Questão (do Brief) | Decisão |
| ------------------ | ------- |
| Q1 — Atomicidade ficheiro↔BD | Mover ficheiro **dentro** da transacção; `RegraMoverFicheiro` verifica o retorno (discos `throw=>false`) e lança; compensação best-effort (tenta mover de volta) em excepção. |
| Q2 — Conjunto de Events | `DocumentoProcessado` (registo manual + transição), `DocumentoMarcadoErro`, `DocumentoMarcadoPerigoso`, `DocumentoReprocessado`. Transições intermédias (`AguardaEnvio`/`Enviado`/`AguardaResposta`) **sem** Event. Sem `DocumentoRecebido`/`DocumentoRegistado` nesta issue. |
| Q3 — Listeners | **Só Events** (sem Listeners). Testes assertam dispatch via `Event::fake()`. |
| Q4 — Leitura do histórico | `DocumentoResource` via `whenLoaded('historico')` + `EtapaDocumentoResource`. **Sem** endpoint próprio. |
| Q5 — Naming das Actions | Forma **longa com `Documento`** (`CorrigirDocumentoAction`, etc.) — alinha com `00-index.md` e `CriarEntidadeAction`. |
| Q6 — Mapeamento DTOs↔Actions | `CriarDocumentoManualDto`→`RegistarDocumentoManualAction`; `ActualizarDocumentoDto`→`CorrigirDocumentoAction` (ambos ganham `fromRequest()`). DTOs novos: `ReceberUploadDocumentoDto`, `TransicionarProcessadoDocumentoDto`, `MarcarErroDocumentoDto`, `MarcarPerigosoDocumentoDto`, `ReprocessarDocumentoDto`. Transições sem dados (RF-03/04/05) sem DTO. |
| Q7 — Fronteira Upload | **Full aqui**: `ReceberUploadDocumentoAction` calcula `hash_sha256`, escreve no disco `entrada` e cria em `Pendente`. (O pré-scan real continua fora de âmbito.) |
| Q8 — `modo` de reprocessamento | Enum novo `ModoReprocessamento` (`Modelo`, `Ferramenta`); a semântica de fallback fica para a issue de extracção. |
| Q9 — Ordenação/filtro da listagem | `CampoOrdenacaoDocumentos` (`DataDocumento`, `CriadoEm`); filtro opcional por estado via query param mapeado a `whereEstado`. |
| Autorização — abilities | Reusar os 5 abilities CRUD do `DocumentoPolicy` (stub); **sem** abilities granulares novas. Mapeamento em RNF-02. |
| Autorização — contexto pipeline | Actions de pipeline autorizam contra utilizador autenticado; Jobs futuros correrão como o autor do upload (1ª `EtapaDocumento`). Ver RNF-02b. |

## Critérios de aceitação

> Herdados da issue — não reformulados.

- [ ] CA-01: Cada Action de transição faz `Gate::authorize()` (fora) + `DB::transaction()` (persistência + movimento de ficheiro + escrita de `EtapaDocumento`) *(issue)*
- [ ] CA-02: `RegraTransicaoEstado` rejeita transições inválidas (ex.: `Processado → Enviado`) com excepção tipada *(issue)*
- [ ] CA-03: `RegraMoverFicheiro` move entre discos e deixa `disco_storage`/`nome_ficheiro_storage` consistentes com o novo `status` *(issue)*
- [ ] CA-04: `TransicionarProcessadoDocumentoAction` renomeia para `yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}` *(issue)*
- [ ] CA-05: `ReprocessarDocumentoAction` aceita `modo`, volta a `AguardaEnvio` e regista o `modo` em `EtapaDocumento.motivo` *(issue)*
- [ ] CA-06: `MarcarPerigosoDocumentoAction` é alcançável de `Pendente` (pré-scan) e de `AguardaResposta` (guardrail) *(issue)*
- [ ] CA-07: Cada Action de criação/transição grava exactamente uma linha de `EtapaDocumento` na mesma transacção (rollback não deixa histórico órfão); `RegistarDocumentoManualAction` grava `(Processado, "registo manual", id_utilizador)` *(issue)*
- [ ] CA-08: Cada transição emite o Event de domínio correspondente (after_commit) *(issue)*
- [ ] CA-09: `ListarDocumentosAction` usa `cursorPaginate` + scopes; filtros por estado funcionam; sem Repository *(issue)*
- [ ] CA-10: Actions HTTP têm Controller + rota + FormRequest com `authorize()` + `messages()` PT *(issue)*
- [ ] CA-11: DTOs de transição são `final readonly`, validam invariantes no construtor, com `fromRequest()` *(issue)*
- [ ] CA-12: Testes duais por slice — Unit (programático, ex.: por Job) + Feature (HTTP) *(issue)*
- [ ] CA-13: `composer test` — 100% coverage e 100% type coverage; zero erros Larastan *(issue)*
- [ ] CA-14: `RegraMoverFicheiro` verifica o retorno das operações de disco (`throw=>false`) e faz compensação best-effort em falha *(spec)*
- [ ] CA-15: `TransicaoInvalidaException` é convertida para `422` pelo exception handler *(spec)*

## SYSTEM_SPEC a actualizar

- `docs/system_spec/01-features/documento.md` — criar (Actions de transição + listagem + leitura)
- `docs/system_spec/02-shared/regras-negocio.md` — `RegraTransicaoEstado`, `RegraMoverFicheiro`, `RegraNomearProcessado`
- `docs/system_spec/02-shared/estados.md` — transições permitidas (mapa central)
- `docs/system_spec/02-shared/enums.md` — `ModoReprocessamento`, `CampoOrdenacaoDocumentos`
- `docs/system_spec/02-shared/http.md` — `TransicaoInvalidaException` → 422
- `docs/system_spec/04-infra/queue-jobs.md` — Events de domínio; invocação por Job na extracção
- `docs/system_spec/04-infra/cache.md` — `TagCache::Documentos`
- `docs/system_spec/05-routes/documento.md` — criar (rotas HTTP)
- `docs/system_spec/00-index.md` — novos ficheiros/Actions/Events/enums
- `./openapi.yaml` — endpoints HTTP (Fase 3a)

## Verificação RGPD/NIS2

- **Dados pessoais:** documentos podem conter dados de fornecedor/cliente (entidades). `motivo` e
  `mensagem_erro` podem conter detalhe sensível — não logados em claro (RNF-06); `EtapaDocumentoResource`
  só é exposto a quem já pode ver o documento (Policy `view`).
- **Superfície de ataque:** upload (`multipart/form-data`) é o novo ponto de entrada de ficheiros — a
  validação de tipo/dimensão fica no `ReceberUploadDocumentoRequest`; o **pré-scan de malware real**
  continua fora de âmbito (só o estado `Perigoso` e a Action existem). `hash_sha256` único previne
  duplicados; colisão de hash no upload tem de devolver erro controlado, não 500.
