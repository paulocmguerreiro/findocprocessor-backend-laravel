# System Spec — Feature: Documento (superfície HTTP)

> `app/Features/Documento/` — Actions expostas por endpoint HTTP, DTOs, Events, Controller,
> FormRequests, Resources e autorização. Pipeline de background (transições sem HTTP, triagem,
> reivindicação, recorder de extracção, mapa de transições) em `01-features/documento-pipeline.md`.

---

## Visão geral

Feature com 17 Actions no total: 8 expostas via endpoint HTTP (documentadas aqui) + 9 sem HTTP
(`01-features/documento-pipeline.md`). 9 DTOs, 4 Events e camada HTTP completa. A máquina de
estados é a peça central do pipeline — cada transição passa obrigatoriamente pelo mapa central em
`RegraTransicaoEstado` (`documento-pipeline.md`) — nunca `if ($doc->estado == ...)`.

---

## Actions

### Actions de criação

| Action | Ability | Estado destino | Exposta HTTP |
|---|---|---|---|
| `RegistarDocumentoManualAction` | `create` | `Processado` (directo) | ✅ `POST /documentos` |
| `ReceberUploadDocumentoAction` | `create` | `Pendente` | ✅ `POST /documentos/upload` |

**`RegistarDocumentoManualAction`** — Cria um `Documento` já correndo o scan de malware
(`AnalisadorMalware`) sobre o ficheiro antes de gravar. Calcula `hash_sha256`, gera o nome
canónico via `RegraNomearProcessado`, escreve no disco decidido pelo scan: limpo/não configurado →
`Processado`/disco `processado` (comportamento original, emite `DocumentoProcessado`); infectado →
`Perigoso`/disco `perigoso` (motivo = assinatura, emite `DocumentoMarcadoPerigoso`); falha do scan →
`Erro`/disco `erro` (motivo = razão da falha, emite `DocumentoMarcadoErro`). O `Documento` **é
sempre criado** — nunca rejeitado sem registo. Não usa `RegraTransicaoEstado` (criação, não
transição).

**`ReceberUploadDocumentoAction`** — Recebe `UploadedFile`, calcula `hash_sha256`, escreve no disco
`entrada`, cria em `Pendente`. Grava 1 `EtapaDocumento (Pendente, "upload recebido", id)`. Trata
colisão de hash com `DocumentoDuplicadoException` (→ 422).

---

### Actions HTTP retidas (transições accionadas pelo utilizador)

| Action | Ability | De | Para | Exposta HTTP |
|---|---|---|---|---|
| `ReprocessarDocumentoAction` | `update` | `Erro` | `AguardaEnvio` | ✅ `POST /documentos/{documento}/reprocessar` |
| `CorrigirDocumentoAction` | `update` | `Processado` | `Processado` (loop) | ✅ `PATCH /documentos/{documento}` |
| `EliminarDocumentoAction` | `delete` | qualquer | — (eliminado) | ✅ `DELETE /documentos/{documento}` |

`ReprocessarDocumentoAction` — DTO `ReprocessarDocumentoDto` (enum `ModoReprocessamento`); move
`erro → entrada`; grava `EtapaDocumento (AguardaEnvio, modo->value, id)`; emite `DocumentoReprocessado`.
Abre a sua própria `DB::transaction()` (a transição via `ExecutorTransicaoDocumento` corre como
transacção aninhada/`SAVEPOINT` dentro dela) e reseta a linha `extracoes_documento` desse documento
**se existir** (`etapa_extracao = Pendente`, `extracao_reclamada_em = null`, `extracao_tentativas =
0`, `texto_extraido = null`, `dados_json = null`) via `update()` — nunca `create()`/`upsert()`, para
não criar dimensão de extracção a documentos que nunca lá entraram (ex.: erro de scan de malware em
`Pendente`). Ver `01-features/documento-pipeline.md` ("Modelo de 2 dimensões") para o racional.

`CorrigirDocumentoAction` — DTO `CorrigirDocumentoDto` (só campos de domínio, sem campos de storage);
renomeia via `RegraNomearProcessado` se o slug mudar; grava `EtapaDocumento (Processado, "correcção", id)`.

`EliminarDocumentoAction` — apaga o `Documento` + ficheiro do disco actual; `EtapaDocumento` removido
por `cascadeOnDelete`.

---

### Actions de leitura

| Action | Ability | Exposta HTTP |
|---|---|---|
| `ListarDocumentosAction` | `viewAny` | ✅ `GET /documentos` |
| `VerDocumentoAction` | `view` | ✅ `GET /documentos/{documento}` |
| `DescarregarDocumentoAction` | `view` | ✅ `GET /documentos/{documento}/ficheiro` |

`ListarDocumentosAction` — `cursorPaginate` com `CampoOrdenacaoDocumentos` + `DirecaoOrdenacao`;
filtro opcional por estado via `whereEstado(EstadoDocumento)`; cache `TagCache::Documentos` TTL
`Curta`. Directo no Eloquent — sem Repository.

`VerDocumentoAction` — carrega relação `historico`; exposta com `?include=historico` no query param
(o Controller carrega se pedido).

`DescarregarDocumentoAction` — devolve `FicheiroDocumentoDto` (VO com `disco` e `nome`); o Controller
faz `streamDownload`. Lança `NotFoundHttpException` se o ficheiro não existir no disco.

---

## Enums da feature

| Classe | Namespace | Cases | Descrição |
|---|---|---|---|
| `CampoOrdenacaoDocumentos` | `App\Features\Documento\Listar` | `DataDocumento = 'data_documento'`, `CriadoEm = 'created_at'` | Campo de ordenação da listagem de documentos |
| `ModoReprocessamento` | `App\Features\Documento\Reprocessar` | `Modelo = 'MODELO'`, `Ferramenta = 'FERRAMENTA'` | Modo de reprocessamento de um documento em `Erro`; registado como `motivo` na `EtapaDocumento`. Semântica de fallback entre modelos/ferramentas diferida para a issue de extracção |

---

## DTOs

| DTO | Ficheiro | Campos principais |
|---|---|---|
| `RegistarDocumentoManualDto` | `Criar/` | campos de domínio + `ficheiro: UploadedFile` |
| `ReceberUploadDocumentoDto` | `RecepcaoUpload/` | `ficheiro: UploadedFile` |
| `TransicionarProcessadoDocumentoDto` | `TransicionarProcessado/` | `idFornecedor`, `idCliente`, `idCategoria`, `valor:float`, `dataDocumento:DateTimeInterface` |
| `MarcarErroDocumentoDto` | `MarcarErro/` | `mensagemErro: string` (não-vazio) |
| `MarcarPerigosoDocumentoDto` | `MarcarPerigoso/` | `motivo: string` (não-vazio) |
| `ReprocessarDocumentoDto` | `Reprocessar/` | `modo: ModoReprocessamento` |
| `CorrigirDocumentoDto` | `Corrigir/` | campos de domínio (sem campos de storage) |
| `FicheiroDocumentoDto` | `Descarregar/` | `disco: string`, `nome: string` (VO de vista, sem `fromRequest`) |
| `ResultadoReconciliacaoFicheiro` | `Transicao/` | `coerente: bool`, `encontrado: bool`, `disco: ?string`, `nome: ?string` (VO interno, sem `fromRequest`) |
| `RegistarEtapaExtracaoDto` | `RegistarEtapaExtracao/` | `etapaExtracao: EtapaExtracao`, `resultado: ResultadoEtapa`, `motivo: ?string`, `textoExtraido: ?string`, `dadosJson: ?array`, `reclamar: bool`, `incrementarTentativas: bool` (VO interno, sem `fromRequest`) |

Todos `final readonly`. Todos com `fromRequest()` excepto `FicheiroDocumentoDto`,
`ResultadoReconciliacaoFicheiro` e `RegistarEtapaExtracaoDto` (VOs internos/de vista, nunca
originados de HTTP).
Campos de storage (`hash_sha256`, `disco_storage`, `nome_ficheiro_storage`) nunca vêm do cliente —
são derivados pela Action.

---

## Events de domínio

Todos `final`, `implements ShouldDispatchAfterCommit`, `use Dispatchable, SerializesModels`. Sem Listeners.

| Event | Emitido por | Payload extra |
|---|---|---|
| `DocumentoProcessado` | `RegistarDocumentoManualAction` (limpo/não configurado), `TransicionarProcessadoDocumentoAction` | — |
| `DocumentoMarcadoErro` | `MarcarErroDocumentoAction`, `RegistarDocumentoManualAction` (falha do scan) | `mensagemErro: string` |
| `DocumentoMarcadoPerigoso` | `MarcarPerigosoDocumentoAction`, `RegistarDocumentoManualAction` (infectado) | `motivo: string` |
| `DocumentoReprocessado` | `ReprocessarDocumentoAction` | `modo: ModoReprocessamento` |

Transições intermédias (`AguardaEnvio`, `Enviado`, `AguardaResposta`) não emitem Event.

---

## HTTP

### Controller

`DocumentoController` — zero lógica; dispatch para Actions; `ApiResponse` para todas as respostas.

### FormRequests

| FormRequest | Método | Autorização |
|---|---|---|
| `ListarDocumentosRequest` | `viewAny` | `Gate::authorize('viewAny', Documento::class)` |
| `CriarDocumentoManualRequest` | `create` | `Gate::authorize('create', Documento::class)` |
| `ReceberUploadDocumentoRequest` | `create` | idem; valida `multipart/form-data`, tipo e dimensão |
| `VerDocumentoRequest` | `view` | `Gate::authorize('view', $documento)` |
| `CorrigirDocumentoRequest` | `update` | `Gate::authorize('update', $documento)` |
| `ReprocessarDocumentoRequest` | `update` | idem |
| `EliminarDocumentoRequest` | `delete` | `Gate::authorize('delete', $documento)` |
| `DescarregarDocumentoRequest` | `view` | `Gate::authorize('view', $documento)` |

Todos com `messages()` em PT.

### Resources

`DocumentoResource` — serializa campos do `Documento`; histórico via `whenLoaded('historico')` →
`EtapaDocumentoResource`; `etapa_extracao` (string ou `null`) via `whenLoaded('extracao')` —
**nunca** expõe `texto_extraido`/`dados_json` (PII).

`EtapaDocumentoResource` — campos: `estado`, `passo`, `resultado` (`null` numa linha de negócio), `motivo`, `id_utilizador`, `criado_em`.

---

## Autorização — mapeamento abilities

As Actions **com login** mapeiam abilities do `DocumentoPolicy` para permissões granulares (`hasPermissionTo`) — `documentos.ver` (`viewAny`/`view`), `documentos.criar` (`create`), `documentos.actualizar` (`update`), `documentos.eliminar` (`delete`). Matriz role→permission em `04-infra/autorizacao.md` (admin todas; utilizador só `documentos.ver`).

| Ability | Permissão | Actions (com `Gate::authorize`) |
|---|---|---|
| `viewAny` | `documentos.ver` | `ListarDocumentosAction` |
| `view` | `documentos.ver` | `VerDocumentoAction`, `DescarregarDocumentoAction` |
| `create` | `documentos.criar` | `RegistarDocumentoManualAction`, `ReceberUploadDocumentoAction` |
| `update` | `documentos.actualizar` | `CorrigirDocumentoAction`, `ReprocessarDocumentoAction`, `TransicionarProcessadoDocumentoAction` |
| `delete` | `documentos.eliminar` | `EliminarDocumentoAction` |

As transições de pipeline sem Gate (`Marcar*`, `Reivindicar*`, `Triar*`, `RegistarEtapaExtracaoAction`)
estão documentadas em `01-features/documento-pipeline.md` ("Transições de sistema (sem Gate)").

---

## Limitações conhecidas

- **Colisão de nome canónico**: `RegraNomearProcessado` gera `yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}`. Dois documentos com o mesmo fornecedor/categoria/data terão o mesmo nome — `Storage::put()` sobrepõe silenciosamente. Diferido.
- **Atomicidade filesystem↔BD**: o ficheiro é movido antes da transação; em dupla falha (mover OK + compensação falha), a BD reverte mas o ficheiro fica no disco destino. Reconciliação manual necessária. Detalhe em `documento-pipeline.md`.
- **Sem ownership na autorização**: o `id_responsavel` regista o autor da entrada, mas o `DocumentoPolicy` ainda **não** o usa — qualquer utilizador com `documentos.actualizar`/`documentos.eliminar` pode alterar qualquer documento, não só os seus. Ownership por responsável fica para futuro.
- **Jobs reais de pipeline** (`WatchInboxJob`, `ProcessBatchJob`) diferidos: as Actions são invocáveis programaticamente e os Events são `after_commit`, mas o orquestrador real ainda não existe.
