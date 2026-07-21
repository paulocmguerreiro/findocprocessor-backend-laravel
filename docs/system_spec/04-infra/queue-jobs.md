# System Spec — Infra: Jobs / Queue

> `app/Jobs/`, `app/Console/Commands/Extracao/`

---

## Jobs implementados

| Job                       | Tipo      | Schedule/Queue                                                                                                                        |
| ------------------------- | --------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| `ReconciliarFicheirosJob` | Scheduled | `Schedule::job(new ReconciliarFicheirosJob)->everyFiveMinutes()->onOneServer()->name('reconciliar-ficheiros')` (`routes/console.php`) |

---

## Commands `extracao:*` + Schedule (pipeline automático de extracção)

> `app/Console/Commands/Extracao/`, agendados em `routes/console.php`.

5 Commands, um por etapa activa do pipeline (os 3 estados terminais — `Processado`/`Erro`/`Perigoso`
— não têm Command). **Não são Jobs de fila** — correm sincronamente a partir do `Schedule`, como um
Controller despacha para uma Action; a fila (`QUEUE_CONNECTION=redis`) fica reservada a
`ReconciliarFicheirosJob`. Todos finos: sem lógica de negócio, só ligam o comando à Action
orquestradora (`01-features/documento-pipeline.md`).

| Command | Signature | Action | Lote/ciclo | Schedule |
|---|---|---|---|---|
| `ExecutarScanExtracaoCommand` | `extracao:run-scan` | `ReivindicarDocumentoPendenteAction` (reutilizada) | 25 (`LOTE_PADRAO`) | `everyMinute()` |
| `ExecutarParserExtracaoCommand` | `extracao:run-parser` | `ProcessarAnaliseTextoDocumentoAction` | 25 | `everyMinute()` |
| `ExecutarTesseractExtracaoCommand` | `extracao:run-tesseract` | `ProcessarAnaliseOcrDocumentoAction` | **1** (Tesseract pesado, M1 8GB) | `everyMinute()` |
| `ExecutarIaLocalExtracaoCommand` | `extracao:run-ia-local` | `ProcessarAnaliseIaLocalDocumentoAction` | **1** (modelo local pesado) | `everyMinute()` |
| `ExecutarIaCloudExtracaoCommand` | `extracao:run-ia-cloud` | `ProcessarAnaliseCloudDocumentoAction` | 25 | `everyFiveMinutes()` |

`EtapaExtracaoCommand` (base abstracta, sufixo `Command` obrigatório mesmo na base — ArchTest de
nomenclatura de Commands aplica-se a toda a hierarquia) — `handle()` chama repetidamente
`processarProximo(): ?Documento` até devolver `null` (sem candidato) ou atingir `loteMaximo()`; as
subclasses só implementam essas duas primitivas. Todos os 5 Commands têm `->withoutOverlapping()` no
`Schedule` (o mesmo Command nunca se sobrepõe a si próprio entre execuções); a exclusão **por
documento** entre workers/execuções não é responsabilidade do Command — vem do lease
(`ReivindicarDocumentoEmEtapaAction`) ou da mudança de estado imediata (`scan`, via
`ReivindicarDocumentoPendenteAction`). Sem `WithoutOverlapping($idDocumento)` por Job — o lease já dá
essa garantia por documento, não é preciso replicá-la a nível de fila.

**`extracao:run-scan` ignora documentos manuais** (CA-09) por construção: só selecciona `Documento`s
em `Pendente`, e `RegistarDocumentoManualAction` nunca deixa um documento em `Pendente` (vai directo a
`Processado`/`Perigoso`/`Erro`).

`ReconciliarFicheirosJob` — reconciliação ficheiro↔BD: varre `Documento`s presos num estado
transitório (os 5 passos de análise: `AnaliseMalware`/`AnaliseTexto`/`AnaliseOcr`/`AnaliseIaLocal`/`AnaliseCloud`) há mais tempo que
`config('pipeline.reconciliacao_limiar_minutos')` (scope `Documento::documentosPresos()`), verifica
a coerência `disco_storage`/`nome_ficheiro_storage` via `RegraReconciliarLocalizacaoFicheiro` e repõe
automaticamente quando o ficheiro é localizado noutro disco conhecido, ou regista `Log::error`
estruturado quando não é encontrado em nenhum. `$tries = 1`, `$timeout = 120`. Implementa
`ShouldQueue` **e** `ShouldQueueAfterCommit` (ver secção seguinte). Detalhe do contrato de
atomicidade: `01-features/documento-reconciliacao.md`.

---

## Events de domínio

Events dispatched pelas Actions de transição do `Documento`. Todos implementam
`ShouldDispatchAfterCommit` — só são emitidos após o commit da transação.

| Event                           | Ficheiro                                       | Emitido por                                                              | Payload                                             |
| -------------------------------- | ----------------------------------------------- | ------------------------------------------------------------------------ | --------------------------------------------------- |
| `DocumentoProcessadoEvent`      | `app/Events/DocumentoProcessadoEvent.php`      | `RegistarDocumentoManualAction` (limpo/não configurado), `TransicionarProcessadoDocumentoAction` | `Documento $documento`                              |
| `DocumentoMarcadoErroEvent`     | `app/Events/DocumentoMarcadoErroEvent.php`     | `MarcarErroDocumentoAction`, `RegistarDocumentoManualAction` (falha do scan)                | `Documento $documento`, `string $mensagemErro`      |
| `DocumentoMarcadoPerigosoEvent` | `app/Events/DocumentoMarcadoPerigosoEvent.php` | `MarcarPerigosoDocumentoAction`, `RegistarDocumentoManualAction` (infectado)                 | `Documento $documento`, `string $motivo`            |
| `DocumentoReprocessadoEvent`    | `app/Events/DocumentoReprocessadoEvent.php`    | `ReprocessarDocumentoAction`                                             | `Documento $documento`, `ModoReprocessamento $modo` |

Sem Listeners registados.

### Invocação programática das Actions de pipeline

As Actions de transição de pipeline (`MarcarAnaliseMalware`, `MarcarAnaliseTexto`, `MarcarAnaliseOcr`,
`MarcarAnaliseIaLocal`, `MarcarAnaliseCloud`, `TransicionarProcessado`, `MarcarErro`, `MarcarPerigoso`)
não têm endpoint HTTP — são invocadas pelos 4 orquestradores de etapa (`ProcessarAnalise*`,
`01-features/documento-pipeline.md`), chamados pelos Commands `extracao:*` acima.
`TransicionarProcessadoDocumentoAction` corre em nome do utilizador que fez o upload
(`Documento.id_responsavel`, autor da primeira `EtapaDocumento`) — impersonação temporária feita por
`ConcluirExtracaoDocumentoAction`, único ponto do pipeline que precisa de um utilizador autenticado
(`Gate::authorize('update')`). Ver `03-models/etapa-documento.md` para detalhe de `id_utilizador`.

`ProcessarAnaliseMalwareDocumentoAction` corre o scan de malware e decide a transição, invocada por
`ReivindicarDocumentoPendenteAction` (mesma transacção/lock) — por sua vez chamada pelo
`ExecutarScanExtracaoCommand` (`extracao:run-scan`).

`RegistarEtapaExtracaoAction` (`app/Features/Documento/Processamento/`) é
invocada por cada um dos 4 orquestradores de etapa e por `RegistarFalhaTecnicaExtracaoAction` — regista
cada passo de IA/parser/OCR sobre um `Documento` (upsert em `extracoes_documento` + `EtapaDocumento`
com `resultado`; o passo é o `estado` actual). Ver `01-features/documento-reconciliacao.md`
("Dimensão de extracção").

---

## Nota transações

Jobs disparados dentro de transações de BD devem usar `after_commit: true` na config da queue ou
implementar `Illuminate\Contracts\Queue\ShouldQueueAfterCommit` (**não** `ShouldDispatchAfterCommit`
— essa interface é exclusiva de Events/Broadcasting, usada pelos 4 Events acima). ArchTest
(`tests/ArchTest.php` — `jobs implementam ShouldQueueAfterCommit`) garante que qualquer `Job` em
`app/Jobs/` que implemente `ShouldQueue` também implementa `ShouldQueueAfterCommit`, sem revisão
manual por issue (RN-01/CA-02). Ver `04-infra/transactions.md`.
