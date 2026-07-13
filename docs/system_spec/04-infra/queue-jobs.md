# System Spec — Infra: Jobs / Queue

> `app/Jobs/`

---

## Jobs implementados

| Job                       | Tipo      | Schedule/Queue                                                                                                                        | Issue |
| ------------------------- | --------- | ------------------------------------------------------------------------------------------------------------------------------------- | ----- |
| `ReconciliarFicheirosJob` | Scheduled | `Schedule::job(new ReconciliarFicheirosJob)->everyFiveMinutes()->onOneServer()->name('reconciliar-ficheiros')` (`routes/console.php`) | #90   |

`ReconciliarFicheirosJob` — reconciliação ficheiro↔BD: varre `Documento`s presos num estado
transitório (`AguardaEnvio`/`Enviado`/`AguardaResposta`) há mais tempo que
`config('pipeline.reconciliacao_limiar_minutos')` (scope `Documento::documentosPresos()`), verifica
a coerência `disco_storage`/`nome_ficheiro_storage` via `RegraReconciliarLocalizacaoFicheiro` e repõe
automaticamente quando o ficheiro é localizado noutro disco conhecido, ou regista `Log::error`
estruturado quando não é encontrado em nenhum. `$tries = 1`, `$timeout = 120`. Implementa
`ShouldQueue` **e** `ShouldQueueAfterCommit` (ver secção seguinte). Detalhe do contrato de
atomicidade: `02-shared/estados.md`.

---

## Events de domínio (Issue #57)

Events dispatched pelas Actions de transição do `Documento`. Todos implementam
`ShouldDispatchAfterCommit` — só são emitidos após o commit da transação.

| Event                      | Ficheiro                                  | Emitido por                                                              | Payload                                             |
| -------------------------- | ----------------------------------------- | ------------------------------------------------------------------------ | --------------------------------------------------- |
| `DocumentoProcessado`      | `app/Events/DocumentoProcessado.php`      | `RegistarDocumentoManualAction`, `TransicionarProcessadoDocumentoAction` | `Documento $documento`                              |
| `DocumentoMarcadoErro`     | `app/Events/DocumentoMarcadoErro.php`     | `MarcarErroDocumentoAction`                                              | `Documento $documento`, `string $mensagemErro`      |
| `DocumentoMarcadoPerigoso` | `app/Events/DocumentoMarcadoPerigoso.php` | `MarcarPerigosoDocumentoAction`                                          | `Documento $documento`, `string $motivo`            |
| `DocumentoReprocessado`    | `app/Events/DocumentoReprocessado.php`    | `ReprocessarDocumentoAction`                                             | `Documento $documento`, `ModoReprocessamento $modo` |

Sem Listeners nesta issue. Os Listeners serão adicionados quando a issue de extracção (IA/OCR) for implementada.

### Invocação programática das Actions de pipeline

As Actions de transição de pipeline (`MarcarAguardaEnvio`, `MarcarEnviado`, `MarcarAguardaResposta`,
`TransicionarProcessado`, `MarcarErro`, `MarcarPerigoso`) não têm endpoint HTTP — são invocadas
pelos Jobs da extracção. Os Jobs futuros correrão em nome do utilizador que fez o upload (autor da
primeira `EtapaDocumento` do documento). Ver `03-models/etapa-documento.md` para detalhe de
`id_utilizador`.

---

## Nota transações

Jobs disparados dentro de transações de BD devem usar `after_commit: true` na config da queue ou
implementar `Illuminate\Contracts\Queue\ShouldQueueAfterCommit` (**não** `ShouldDispatchAfterCommit`
— essa interface é exclusiva de Events/Broadcasting, usada pelos 4 Events acima). ArchTest
(`tests/ArchTest.php` — `jobs implementam ShouldQueueAfterCommit`) garante que qualquer `Job` em
`app/Jobs/` que implemente `ShouldQueue` também implementa `ShouldQueueAfterCommit`, sem revisão
manual por issue (RN-01/CA-02, #90). Ver `04-infra/transactions.md`.
