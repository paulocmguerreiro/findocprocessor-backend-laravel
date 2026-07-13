# System Spec — Feature: Documento (lógica — máquina de estados)

> `app/Features/Documento/`
> Issues: #45 (modelo), #56 (EtapaDocumento), #57 (lógica), #90 (concorrência do pipeline), #91 (scan de malware ClamAV — esta spec)

---

## Visão geral

Feature com 16 Actions (8 expostas via endpoint HTTP + 8 sem HTTP, invocadas apenas
programaticamente: 6 de transição de pipeline — `Marcar*` e `TransicionarProcessadoDocumentoAction`
— + `ReivindicarDocumentoPendenteAction` (#90) + `TriarDocumentoPendenteAction` (#91)), 4 classes
`Regra*`, 1 executor partilhado interno, 8 DTOs, 4 Events e camada HTTP completa. A máquina de
estados é a peça central: cada transição passa obrigatoriamente pelo mapa central em
`RegraTransicaoEstado` — nunca `if ($doc->status == ...)`.

---

## Actions

### Actions de criação

| Action | Ability | Estado destino | Exposta HTTP |
|---|---|---|---|
| `RegistarDocumentoManualAction` | `create` | `Processado` (directo) | ✅ `POST /documentos` |
| `ReceberUploadDocumentoAction` | `create` | `Pendente` | ✅ `POST /documentos/upload` |

**`RegistarDocumentoManualAction`** — Cria um `Documento` já correndo o scan de malware
(`AnalisadorMalware`, #91) sobre o ficheiro antes de gravar. Calcula `hash_sha256`, gera o nome
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

### Actions de transição de pipeline (sem endpoint HTTP)

| Action | Ability | De | Para | Move ficheiro |
|---|---|---|---|---|
| `MarcarAguardaEnvioDocumentoAction` | `update` | `Pendente` | `AguardaEnvio` | Não (fica em `entrada`) |
| `MarcarEnviadoDocumentoAction` | `update` | `AguardaEnvio` | `Enviado` | `entrada → enviado` |
| `MarcarAguardaRespostaDocumentoAction` | `update` | `Enviado` | `AguardaResposta` | Não (fica em `enviado`) |
| `TransicionarProcessadoDocumentoAction` | `update` | `AguardaResposta` | `Processado` | `enviado → processado` + rename |
| `MarcarErroDocumentoAction` | `update` | `AguardaResposta` ou `Pendente` (#91) | `Erro` | origem → `erro` |
| `MarcarPerigosoDocumentoAction` | `update` | `Pendente` ou `AguardaResposta` | `Perigoso` | origem → `perigoso` |

Todas delegam em `ExecutorTransicaoDocumento::executar()`.

`TransicionarProcessadoDocumentoAction` — preenche campos de domínio (fornecedor/cliente/categoria/
valor/data); usa `RegraNomearProcessado` para gerar o nome canónico. DTO:
`TransicionarProcessadoDocumentoDto`. Emite `DocumentoProcessado`.

`MarcarErroDocumentoAction` — DTO `MarcarErroDocumentoDto` (campo `mensagemErro`). Emite
`DocumentoMarcadoErro`. Alcançável de `AguardaResposta` (falha de envio/resposta) e de `Pendente`
(falha do scan de malware, #91) — genérica, sem alteração de código entre os dois casos.

`MarcarPerigosoDocumentoAction` — DTO `MarcarPerigosoDocumentoDto` (campo `motivo`). Alcançável de
dois estados (`Pendente` pré-scan e `AguardaResposta` guardrail). Emite `DocumentoMarcadoPerigoso`.

---

### Actions de triagem e reivindicação de pipeline (sem endpoint HTTP, #90/#91)

| Action | Ability | De | Para | Move ficheiro |
|---|---|---|---|---|
| `ReivindicarDocumentoPendenteAction` | — (sem Gate, sistema) | `Pendente` | `AguardaEnvio`/`Perigoso`/`Erro` (via `TriarDocumentoPendenteAction`) | Não/origem → `perigoso`/`erro` |
| `TriarDocumentoPendenteAction` | — (sem Gate, sistema) | `Pendente` | `AguardaEnvio`/`Perigoso`/`Erro` | conforme a Action delegada |

`ReivindicarDocumentoPendenteAction` (`app/Features/Documento/Reivindicar/`) — componente reutilizável
de reivindicação para o futuro orquestrador de IA: abre `DB::transaction()` (ponto de entrada, sem
Action chamante), bloqueia (`lockForUpdate()`) o próximo `Documento` `Pendente` (scope
`wherePendente()`) e delega em `TriarDocumentoPendenteAction` (transação aninhada via `SAVEPOINT`,
desde #91 — antes delegava directo em `MarcarAguardaEnvioDocumentoAction`). Evita que dois workers
concorrentes reivindiquem o mesmo documento — ver `04-infra/transactions.md` para o padrão completo
e `07-testing.md` para o teste de concorrência real (duas conexões MySQL).

`TriarDocumentoPendenteAction` (`app/Features/Documento/Triar/`, #91) — corre o `AnalisadorMalware`
sobre o ficheiro do `Documento` `Pendente`, **dentro da mesma transacção/lock** que o reivindica (não
abre transacção própria), e ramifica: infectado → `MarcarPerigosoDocumentoAction` (motivo =
assinatura); limpo → `MarcarAguardaEnvioDocumentoAction`; não configurado (camada `clamd`
inactiva) → `MarcarAguardaEnvioDocumentoAction` com motivo "scan de malware desligado"; falha do
scan (`FalhaAnaliseMalwareException`) → `MarcarErroDocumentoAction` com o motivo = razão da falha.
Ver `04-infra/external-apis.md` para o contrato `AnalisadorMalware`.

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
| `TransicionarProcessadoDocumentoDto` | `TransicionarProcessado/` | `idFornecedor`, `idCliente`, `idCategoria`, `valor:float`, `dataDocumento:DateTimeInterface` |
| `MarcarErroDocumentoDto` | `MarcarErro/` | `mensagemErro: string` (não-vazio) |
| `MarcarPerigosoDocumentoDto` | `MarcarPerigoso/` | `motivo: string` (não-vazio) |
| `ReprocessarDocumentoDto` | `Reprocessar/` | `modo: ModoReprocessamento` |
| `CorrigirDocumentoDto` | `Corrigir/` | campos de domínio (sem campos de storage) |
| `FicheiroDocumentoDto` | `Descarregar/` | `disco: string`, `nome: string` (VO de vista, sem `fromRequest`) |
| `ResultadoReconciliacaoFicheiro` | `Transicao/` | `coerente: bool`, `encontrado: bool`, `disco: ?string`, `nome: ?string` (VO interno, sem `fromRequest`, #90) |

Todos `final readonly`. Todos com `fromRequest()` excepto `FicheiroDocumentoDto` e
`ResultadoReconciliacaoFicheiro` (VOs internos/de vista, nunca originados de HTTP).
Campos de storage (`hash_sha256`, `disco_storage`, `nome_ficheiro_storage`) nunca vêm do cliente —
são derivados pela Action.

---

## Events de domínio

Todos `final`, `implements ShouldDispatchAfterCommit`, `use Dispatchable, SerializesModels`. Sem Listeners nesta issue.

| Event | Emitido por | Payload extra |
|---|---|---|
| `DocumentoProcessado` | `RegistarDocumentoManualAction` (limpo/não configurado), `TransicionarProcessadoDocumentoAction` | — |
| `DocumentoMarcadoErro` | `MarcarErroDocumentoAction`, `RegistarDocumentoManualAction` (falha do scan, #91) | `mensagemErro: string` |
| `DocumentoMarcadoPerigoso` | `MarcarPerigosoDocumentoAction`, `RegistarDocumentoManualAction` (infectado, #91) | `motivo: string` |
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

As Actions **com login** mapeiam abilities do `DocumentoPolicy` para permissões granulares (`hasPermissionTo`) — `documentos.ver` (`viewAny`/`view`), `documentos.criar` (`create`), `documentos.actualizar` (`update`), `documentos.eliminar` (`delete`). Matriz role→permission em `04-infra/autorizacao.md` (admin todas; utilizador só `documentos.ver`).

| Ability | Permissão | Actions (com `Gate::authorize`) |
|---|---|---|
| `viewAny` | `documentos.ver` | `ListarDocumentosAction` |
| `view` | `documentos.ver` | `VerDocumentoAction`, `DescarregarDocumentoAction` |
| `create` | `documentos.criar` | `RegistarDocumentoManualAction`, `ReceberUploadDocumentoAction` |
| `update` | `documentos.actualizar` | `CorrigirDocumentoAction`, `ReprocessarDocumentoAction`, `TransicionarProcessadoDocumentoAction` |
| `delete` | `documentos.eliminar` | `EliminarDocumentoAction` |

### Transições de sistema (sem Gate)

As 5 transições intermédias de pipeline **não têm `Gate::authorize`** — correm sempre em background (Jobs de extracção), sem utilizador autenticado: `MarcarAguardaEnvioDocumentoAction`, `MarcarEnviadoDocumentoAction`, `MarcarAguardaRespostaDocumentoAction`, `MarcarErroDocumentoAction`, `MarcarPerigosoDocumentoAction`. A `EtapaDocumento` que gravam fica como **passo de sistema** (`id_utilizador = null`). `ReivindicarDocumentoPendenteAction` (#90) e `TriarDocumentoPendenteAction` (#91) seguem o mesmo padrão — sem Gate.

O `TransicionarProcessadoDocumentoAction` é a excepção: apesar de não ter endpoint, **mantém `Gate::authorize('update')`** porque escreve os dados de negócio extraídos (fornecedor, valor, categoria, nome canónico) — é um write significativo, não uma mera flag de estado. Ver padrão em `02-shared/padroes-acoes.md`.

---

## Limitações conhecidas

- **Colisão de nome canónico**: `RegraNomearProcessado` gera `yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}`. Dois documentos com o mesmo fornecedor/categoria/data terão o mesmo nome — `Storage::put()` sobrepõe silenciosamente. Diferido.
- **Atomicidade filesystem↔BD**: o ficheiro é movido antes da transação; em dupla falha (mover OK + compensação falha), a BD reverte mas o ficheiro fica no disco destino. Reconciliação manual necessária. Mitigação real exigiria outbox/saga.
- **Sem ownership na autorização**: o `id_responsavel` regista o autor da entrada, mas o `DocumentoPolicy` ainda **não** o usa — qualquer utilizador com `documentos.actualizar`/`documentos.eliminar` pode alterar qualquer documento, não só os seus. Ownership por responsável fica para futuro.
- **Jobs reais de pipeline** (`WatchInboxJob`, `ProcessBatchJob`) diferidos: a issue garante que as Actions são invocáveis programaticamente e que os Events são `after_commit`.
