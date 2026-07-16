# System Spec — Infra: Jobs / Queue

> `app/Jobs/`

---

## Jobs implementados

| Job                       | Tipo      | Schedule/Queue                                                                                                                        |
| ------------------------- | --------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| `ReconciliarFicheirosJob` | Scheduled | `Schedule::job(new ReconciliarFicheirosJob)->everyFiveMinutes()->onOneServer()->name('reconciliar-ficheiros')` (`routes/console.php`) |

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

Sem Listeners nesta issue. Os Listeners serão adicionados quando a issue de extracção (IA/OCR) for implementada.

### Invocação programática das Actions de pipeline

As Actions de transição de pipeline (`MarcarAnaliseMalware`, `MarcarAnaliseTexto`, `MarcarAnaliseOcr`,
`MarcarAnaliseIaLocal`, `MarcarAnaliseCloud`, `TransicionarProcessado`, `MarcarErro`, `MarcarPerigoso`)
não têm endpoint HTTP — são invocadas
pelos Jobs da extracção. Os Jobs futuros correrão em nome do utilizador que fez o upload (autor da
primeira `EtapaDocumento` do documento). Ver `03-models/etapa-documento.md` para detalhe de
`id_utilizador`.

`TriarDocumentoPendenteAction` é outro ponto de invocação programática sem Job concreto
nesta issue — corre o scan de malware e decide a transição, invocada por
`ReivindicarDocumentoPendenteAction` (mesma transacção/lock). Fica pendente integrar
`TriarDocumentoPendenteAction`/o Job que a envolver no pipeline de extracção, invocando-o **antes** de
iniciar o processamento — mesmo padrão de dependência a informar já usado por `Reivindicar`/
`MarcarAnaliseMalware`.

`RegistarEtapaExtracaoAction` (`app/Features/Documento/RegistarEtapaExtracao/`) é o ponto de
invocação programática que o futuro orquestrador de pipeline vai chamar para registar cada
passo de IA (OCR/cloud) sobre um `Documento` — upsert em `extracoes_documento` + `EtapaDocumento`
(`resultado`; o passo é o `estado` actual). Sem Job concreto nesta issue: só o modelo de dados e o
recorder existem; o Job/Schedule que varre `extracoes_documento` por `extracao_reclamada_em` e invoca
esta Action fica para o orquestrador de pipeline. Ver `01-features/documento-reconciliacao.md`
("Dimensão de extracção").

---

## Nota transações

Jobs disparados dentro de transações de BD devem usar `after_commit: true` na config da queue ou
implementar `Illuminate\Contracts\Queue\ShouldQueueAfterCommit` (**não** `ShouldDispatchAfterCommit`
— essa interface é exclusiva de Events/Broadcasting, usada pelos 4 Events acima). ArchTest
(`tests/ArchTest.php` — `jobs implementam ShouldQueueAfterCommit`) garante que qualquer `Job` em
`app/Jobs/` que implemente `ShouldQueue` também implementa `ShouldQueueAfterCommit`, sem revisão
manual por issue (RN-01/CA-02). Ver `04-infra/transactions.md`.
