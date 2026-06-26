# System Spec — Infra: Jobs / Queue

> `app/Jobs/`

_Pendente — implementado com as features de processamento de documentos._

---

## Jobs planeados

| Job | Tipo | Schedule/Queue |
|---|---|---|
| `WatchInboxJob` | Scheduled | cada 30s |
| `ProcessBatchJob` | Queued | dispatch por `WatchInboxJob` |

---

## Events de domínio (Issue #57)

Events dispatched pelas Actions de transição do `Documento`. Todos implementam
`ShouldDispatchAfterCommit` — só são emitidos após o commit da transação.

| Event | Ficheiro | Emitido por | Payload |
|---|---|---|---|
| `DocumentoProcessado` | `app/Events/DocumentoProcessado.php` | `RegistarDocumentoManualAction`, `TransicionarProcessadoDocumentoAction` | `Documento $documento` |
| `DocumentoMarcadoErro` | `app/Events/DocumentoMarcadoErro.php` | `MarcarErroDocumentoAction` | `Documento $documento`, `string $mensagemErro` |
| `DocumentoMarcadoPerigoso` | `app/Events/DocumentoMarcadoPerigoso.php` | `MarcarPerigosoDocumentoAction` | `Documento $documento`, `string $motivo` |
| `DocumentoReprocessado` | `app/Events/DocumentoReprocessado.php` | `ReprocessarDocumentoAction` | `Documento $documento`, `ModoReprocessamento $modo` |

Sem Listeners nesta issue. Os Listeners serão adicionados quando a issue de extracção (IA/OCR) for implementada.

### Invocação programática das Actions de pipeline

As Actions de transição de pipeline (`MarcarAguardaEnvio`, `MarcarEnviado`, `MarcarAguardaResposta`,
`TransicionarProcessado`, `MarcarErro`, `MarcarPerigoso`) não têm endpoint HTTP — são invocadas
pelos Jobs da extracção. Os Jobs futuros correrão em nome do utilizador que fez o upload (autor da
primeira `EtapaDocumento` do documento). Ver `03-models/etapa-documento.md` para detalhe de
`id_utilizador`.

---

## Nota transações

Jobs disparados dentro de transações de BD devem usar `after_commit: true` na config da queue ou implementar `ShouldDispatchAfterCommit`. Ver `04-infra/transactions.md`.
