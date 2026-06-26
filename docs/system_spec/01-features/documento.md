# System Spec — Feature: Documento (lógica — máquina de estados)

> `app/Features/Documento/`
> Issues: #45 (modelo), #56 (EtapaDocumento), #57 (lógica — esta spec)

---

## Visão geral

Feature com 13 Actions, 3 classes `Regra*`, 1 executor partilhado interno, 7 DTOs, 4 Events e camada
HTTP completa. A máquina de estados é a peça central: cada transição passa obrigatoriamente pelo mapa
central em `RegraTransicaoEstado` — nunca `if ($doc->status == ...)`.

---

## Actions

### Actions de criação

| Action | Ability | Estado destino | Exposta HTTP |
|---|---|---|---|
| `RegistarDocumentoManualAction` | `create` | `Processado` (directo) | ✅ `POST /documentos` |
| `ReceberUploadDocumentoAction` | `create` | `Pendente` | ✅ `POST /documentos/upload` |

**`RegistarDocumentoManualAction`** — Cria um `Documento` directamente em `Processado` com todos os
campos de domínio preenchidos. Calcula `hash_sha256`, gera o nome canónico via `RegraNomearProcessado`,
escreve o ficheiro no disco `processado`. Grava 1 `EtapaDocumento (Processado, "registo manual", id)`.
Emite `DocumentoProcessado`. Não usa `RegraTransicaoEstado` (criação, não transição).

**`ReceberUploadDocumentoAction`** — Recebe `UploadedFile`, calcula `hash_sha256`, escreve no disco
`entrada`, cria em `Pendente`. Grava 1 `EtapaDocumento (Pendente, "upload recebido", id)`. Trata
colisão de hash com `DocumentoDuplicadoException` (→ 422).

---

### Actions de transição de pipeline (sem endpoint HTTP)

| Action | Ability | De | Para | Move ficheiro |
|---|---|---|---|---|
| `MarcarAguardaEnvioDocumentoAction` | `update` | `Pendente` | `AguardaEnvio` | Não (fica em `entrada`) |
| `MarcarEnviadoDocumentoAction` | `update` | `AguardaEnvio` | `Enviado` | `entrada → enviado` |
| `MarcarAguardaRespostaDocumentoAction` | `update` | `Enviado` | `AguardaResposta` | Não (fica em `enviado`) |
| `TransicionarProcessadoDocumentoAction` | `update` | `AguardaResposta` | `Processado` | `enviado → processado` + rename |
| `MarcarErroDocumentoAction` | `update` | `AguardaResposta` | `Erro` | `enviado → erro` |
| `MarcarPerigosoDocumentoAction` | `update` | `Pendente` ou `AguardaResposta` | `Perigoso` | origem → `perigoso` |

Todas delegam em `ExecutorTransicaoDocumento::executar()`.

`TransicionarProcessadoDocumentoAction` — preenche campos de domínio (fornecedor/cliente/categoria/
valor/data); usa `RegraNomearProcessado` para gerar o nome canónico. DTO:
`TransicionarProcessadoDocumentoDto`. Emite `DocumentoProcessado`.

`MarcarErroDocumentoAction` — DTO `MarcarErroDocumentoDto` (campo `mensagemErro`). Emite
`DocumentoMarcadoErro`.

`MarcarPerigosoDocumentoAction` — DTO `MarcarPerigosoDocumentoDto` (campo `motivo`). Alcançável de
dois estados (`Pendente` pré-scan e `AguardaResposta` guardrail). Emite `DocumentoMarcadoPerigoso`.

---

### Actions HTTP retidas

| Action | Ability | De | Para | Exposta HTTP |
|---|---|---|---|---|
| `ReprocessarDocumentoAction` | `update` | `Erro` | `AguardaEnvio` | ✅ `POST /documentos/{documento}/reprocessar` |
| `CorrigirDocumentoAction` | `update` | `Processado` | `Processado` (loop) | ✅ `PATCH /documentos/{documento}` |
| `EliminarDocumentoAction` | `delete` | qualquer | — (eliminado) | ✅ `DELETE /documentos/{documento}` |

`ReprocessarDocumentoAction` — DTO `ReprocessarDocumentoDto` (enum `ModoReprocessamento`); move
`erro → entrada`; grava `EtapaDocumento (AguardaEnvio, modo->value, id)`; emite `DocumentoReprocessado`.

`CorrigirDocumentoAction` — DTO `CorrigirDocumentoDto` (só campos de domínio, sem campos de storage);
renomeia via `RegraNomearProcessado` se o slug mudar; grava `EtapaDocumento (Processado, "correcção", id)`.

`EliminarDocumentoAction` — apaga o `Documento` + ficheiro do disco actual; `EtapaDocumento` removido
por `cascadeOnDelete` (#56).

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

## Executor partilhado interno

### `ExecutorTransicaoDocumento`

**Ficheiro:** `app/Features/Documento/Transicao/ExecutorTransicaoDocumento.php`

Orquestrador partilhado pelas 8 Actions de transição simples. Encapsula a mecânica comum:

```
regraTransicao->handle($de, $para)   ← valida De→Para
regraMover->handle(...)              ← move ficheiro (fora da transação)
DB::transaction()
  documento->update([status, disco, nome, ...campos domínio])
  historico()->create([estado, motivo, id_utilizador])
  cache->invalidarCache(Documentos)
  Event::dispatch($evento($documento))  ← se evento fornecido
catch (\Throwable)
  regraMover->handle(...)            ← compensação: repor na origem
  throw $erro
```

**Assinatura:**
```php
executar(
    Documento $documento,
    EstadoDocumento $novoEstado,
    string $motivo,
    array $camposDominio = [],
    ?string $nomeDestino = null,
    ?Closure $evento = null,       // factory: fn(Documento): Event
): Documento
```

Não é uma Action — não tem `Gate::authorize()` própria. A autorização é sempre feita pela Action
chamante antes de invocar `executar()`.

---

## DTOs

| DTO | Ficheiro | Campos principais |
|---|---|---|
| `RegistarDocumentoManualDto` | `Criar/` | campos de domínio + `ficheiro: UploadedFile` |
| `ReceberUploadDocumentoDto` | `RecepcaoUpload/` | `ficheiro: UploadedFile` |
| `TransicionarProcessadoDocumentoDto` | `TransicionarProcessado/` | `idFornecedor`, `idCliente`, `idCategoria`, `valor`, `dataDocumento`, `nomeFicheiroProcessado` |
| `MarcarErroDocumentoDto` | `MarcarErro/` | `mensagemErro: string` (não-vazio) |
| `MarcarPerigosoDocumentoDto` | `MarcarPerigoso/` | `motivo: string` (não-vazio) |
| `ReprocessarDocumentoDto` | `Reprocessar/` | `modo: ModoReprocessamento` |
| `CorrigirDocumentoDto` | `Corrigir/` | campos de domínio (sem campos de storage) |
| `FicheiroDocumentoDto` | `Descarregar/` | `disco: string`, `nome: string` (VO de vista, sem `fromRequest`) |

Todos `final readonly`. Todos com `fromRequest()` excepto `FicheiroDocumentoDto` (VO de vista).
Campos de storage (`hash_sha256`, `disco_storage`, `nome_ficheiro_storage`) nunca vêm do cliente —
são derivados pela Action.

---

## Events de domínio

Todos `final`, `implements ShouldDispatchAfterCommit`, `use Dispatchable, SerializesModels`. Sem Listeners nesta issue.

| Event | Emitido por | Payload extra |
|---|---|---|
| `DocumentoProcessado` | `RegistarDocumentoManualAction`, `TransicionarProcessadoDocumentoAction` | — |
| `DocumentoMarcadoErro` | `MarcarErroDocumentoAction` | `mensagemErro: string` |
| `DocumentoMarcadoPerigoso` | `MarcarPerigosoDocumentoAction` | `motivo: string` |
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
`EtapaDocumentoResource`.

`EtapaDocumentoResource` — campos: `estado`, `motivo`, `id_utilizador`, `criado_em`.

---

## Autorização — mapeamento abilities

Reutiliza os 5 abilities CRUD do `DocumentoPolicy` (stub `true`). Sem abilities granulares novas.

| Ability | Actions |
|---|---|
| `viewAny` | `ListarDocumentosAction` |
| `view` | `VerDocumentoAction`, `DescarregarDocumentoAction` |
| `create` | `RegistarDocumentoManualAction`, `ReceberUploadDocumentoAction` |
| `update` | `CorrigirDocumentoAction`, `ReprocessarDocumentoAction`, `TransicionarProcessadoDocumentoAction`, `MarcarAguardaEnvioDocumentoAction`, `MarcarEnviadoDocumentoAction`, `MarcarAguardaRespostaDocumentoAction`, `MarcarErroDocumentoAction`, `MarcarPerigosoDocumentoAction` |
| `delete` | `EliminarDocumentoAction` |

---

## Limitações conhecidas

- **Colisão de nome canónico**: `RegraNomearProcessado` gera `yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}`. Dois documentos com o mesmo fornecedor/categoria/data terão o mesmo nome — `Storage::put()` sobrepõe silenciosamente. Diferido.
- **Atomicidade filesystem↔BD**: o ficheiro é movido antes da transação; em dupla falha (mover OK + compensação falha), a BD reverte mas o ficheiro fica no disco destino. Reconciliação manual necessária. Mitigação real exigiria outbox/saga.
- **`DocumentoPolicy` é stub** (`return true`): autorização real diferida para issue de autorização granular.
- **Jobs reais de pipeline** (`WatchInboxJob`, `ProcessBatchJob`) diferidos: a issue garante que as Actions são invocáveis programaticamente e que os Events são `after_commit`.
