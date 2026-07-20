# System Spec — Feature: Documento (superfície HTTP)

> `app/Features/Documento/` — Actions expostas por endpoint HTTP, DTOs, Events, Controller,
> FormRequests, Resources e autorização. Pipeline de background (transições sem HTTP, triagem,
> reivindicação, recorder de extracção, mapa de transições) em `01-features/documento-pipeline.md`.

---

## Visão geral

Feature com 26 Actions no total: 8 expostas via endpoint HTTP (documentadas aqui) + 18 sem HTTP
(`01-features/documento-pipeline.md` — inclui os 4 orquestradores de etapa + reivindicação por lease
do pipeline automático de extracção). 9 DTOs, 4 Events e camada HTTP completa. A máquina de
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
`Processado`/disco `processado` (comportamento original, emite `DocumentoProcessadoEvent`); infectado →
`Perigoso`/disco `perigoso` (motivo = assinatura, emite `DocumentoMarcadoPerigosoEvent`); falha do scan →
`Erro`/disco `erro` (motivo = razão da falha, emite `DocumentoMarcadoErroEvent`). O `Documento` **é
sempre criado** — nunca rejeitado sem registo. Não usa `RegraTransicaoEstado` (criação, não
transição).

**`ReceberUploadDocumentoAction`** — Recebe `UploadedFile`, calcula `hash_sha256`, escreve no disco
`entrada`, cria em `Pendente`. Grava 1 `EtapaDocumento (Pendente, "upload recebido", id)`. Trata
colisão de hash com `DocumentoDuplicadoException` (→ 422).

---

### Actions HTTP retidas (transições accionadas pelo utilizador)

| Action | Ability | De | Para | Exposta HTTP |
|---|---|---|---|---|
| `ReprocessarDocumentoAction` | `update` | `Erro` | `Pendente` | ✅ `POST /documentos/{documento}/reprocessar` |
| `CorrigirDocumentoAction` | `update` | `Processado` | `Processado` (loop) | ✅ `PATCH /documentos/{documento}` |
| `EliminarDocumentoAction` | `delete` | qualquer | — (eliminado) | ✅ `DELETE /documentos/{documento}` |

`ReprocessarDocumentoAction` — DTO `ReprocessarDocumentoDto` (enum `ModoReprocessamento`); reabre o
pipeline (`Erro → Pendente`); move `erro → entrada`; grava `EtapaDocumento (Pendente, modo->value, id)`;
emite `DocumentoReprocessadoEvent`. Delega a atomicidade no `ExecutorTransicaoDocumento` (sem transacção
própria). A linha `extracoes_documento` já foi eliminada ao entrar em `Erro`
(`RegraEliminarExtracaoTerminal`); a Action mantém apenas um `delete()` defensivo idempotente como rede
de segurança (RF-10) — nunca herda scratch space residual. Ver `01-features/documento-pipeline.md`
("Dimensão de extracção") para o racional.

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
| `CampoOrdenacaoDocumentos` | `App\Features\Documento\Pesquisa\Listar` | `DataDocumento = 'data_documento'`, `CriadoEm = 'created_at'` | Campo de ordenação da listagem de documentos |
| `ModoReprocessamento` | `App\Features\Documento\Operacoes\Reprocessar` | `Modelo = 'MODELO'`, `Ferramenta = 'FERRAMENTA'` | Modo de reprocessamento de um documento em `Erro`; registado como `motivo` na `EtapaDocumento`. Semântica de fallback entre modelos/ferramentas diferida para a issue de extracção |

---

## DTOs

| DTO | Ficheiro | Campos principais |
|---|---|---|
| `RegistarDocumentoManualDto` | `Criar/` | campos de domínio + `ficheiro: UploadedFile` |
| `ReceberUploadDocumentoDto` | `RecepcaoUpload/` | `ficheiro: UploadedFile` |
| `TransicionarProcessadoDocumentoDto` | `TransicoesEstado/` | `idFornecedor`, `idCliente`, `idCategoria`, `valor:float`, `dataDocumento:DateTimeInterface` |
| `MarcarErroDocumentoDto` | `TransicoesEstado/` | `mensagemErro: string` (não-vazio) |
| `MarcarPerigosoDocumentoDto` | `TransicoesEstado/` | `motivo: string` (não-vazio) |
| `ReprocessarDocumentoDto` | `Reprocessar/` | `modo: ModoReprocessamento` |
| `CorrigirDocumentoDto` | `Corrigir/` | campos de domínio (sem campos de storage) |
| `FicheiroDocumentoDto` | `Descarregar/` | `disco: string`, `nome: string` (VO de vista, sem `fromRequest`) |
| `ResultadoReconciliacaoFicheiro` | `Transicao/` | `coerente: bool`, `encontrado: bool`, `disco: ?string`, `nome: ?string` (VO interno, sem `fromRequest`) |
| `RegistarEtapaExtracaoDto` | `Processamento/` | `resultado: ResultadoEtapa`, `motivo: ?string`, `textoExtraido: ?string`, `dadosJson: ?array`, `reclamar: bool`, `incrementarTentativas: bool` (VO interno, sem `fromRequest`; o passo é o `estado` actual do `Documento`, não redundado no DTO) |

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
| `DocumentoProcessadoEvent` | `RegistarDocumentoManualAction` (limpo/não configurado), `TransicionarProcessadoDocumentoAction` | — |
| `DocumentoMarcadoErroEvent` | `MarcarErroDocumentoAction`, `RegistarDocumentoManualAction` (falha do scan) | `mensagemErro: string` |
| `DocumentoMarcadoPerigosoEvent` | `MarcarPerigosoDocumentoAction`, `RegistarDocumentoManualAction` (infectado) | `motivo: string` |
| `DocumentoReprocessadoEvent` | `ReprocessarDocumentoAction` | `modo: ModoReprocessamento` |

Transições intermédias (`AnaliseMalware`, `AnaliseTexto`, `AnaliseOcr`, `AnaliseIaLocal`, `AnaliseCloud`) não emitem Event.

---

## HTTP

### Controller

`DocumentoController` — zero lógica; dispatch para Actions; `ApiResponse` para todas as respostas.

### FormRequests

| FormRequest | Método | Autorização |
|---|---|---|
| `ListarDocumentosRequest` | `viewAny` | `Gate::authorize('viewAny', Documento::class)` |
| `CriarDocumentoManualRequest` | `create` | `Gate::authorize('create', Documento::class)` |
| `ReceberUploadDocumentoRequest` | `create` | idem; valida `multipart/form-data`, tipo (`pdf`/`jpeg`/`png`/`tiff`/`bmp`/`webp`) e dimensão (≤ 50 MB) |
| `VerDocumentoRequest` | `view` | `Gate::authorize('view', $documento)` |
| `CorrigirDocumentoRequest` | `update` | `Gate::authorize('update', $documento)` |
| `ReprocessarDocumentoRequest` | `update` | idem |
| `EliminarDocumentoRequest` | `delete` | `Gate::authorize('delete', $documento)` |
| `DescarregarDocumentoRequest` | `view` | `Gate::authorize('view', $documento)` |

Todos com `messages()` em PT.

### Resources

`DocumentoResource` — serializa campos do `Documento`; histórico via `whenLoaded('historico')` →
`EtapaDocumentoResource`. Sem `etapa_extracao` (coluna removida, #110); o progresso de extracção lê-se
de `estado`. **Nunca** expõe `texto_extraido`/`dados_json` (PII).

`EtapaDocumentoResource` — campos: `estado`, `resultado` (`null` numa linha de transição de negócio), `motivo`, `id_utilizador`, `criado_em`. Sem `passo` (coluna removida, #110).

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

As transições de pipeline sem Gate (`Marcar*`, `Reivindicar*`, `Processar*`, `RegistarEtapaExtracaoAction`)
estão documentadas em `01-features/documento-pipeline.md` ("Transições de sistema (sem Gate)").

---

## Limitações conhecidas

- **Colisão de nome canónico**: `RegraNomearProcessado` gera `yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}`. Dois documentos com o mesmo fornecedor/categoria/data terão o mesmo nome — `Storage::put()` sobrepõe silenciosamente. Diferido.
- **Atomicidade filesystem↔BD**: o ficheiro é movido antes da transação; em dupla falha (mover OK + compensação falha), a BD reverte mas o ficheiro fica no disco destino. Reconciliação manual necessária. Detalhe em `documento-pipeline.md`.
- **Sem ownership na autorização**: o `id_responsavel` regista o autor da entrada, mas o `DocumentoPolicy` ainda **não** o usa — qualquer utilizador com `documentos.actualizar`/`documentos.eliminar` pode alterar qualquer documento, não só os seus. Ownership por responsável fica para futuro.
