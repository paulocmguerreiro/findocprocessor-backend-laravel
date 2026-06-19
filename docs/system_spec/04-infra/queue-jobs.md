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

## Nota transações

Jobs disparados dentro de transações de BD devem usar `after_commit: true` na config da queue ou implementar `ShouldDispatchAfterCommit`. Ver `04-infra/transactions.md`.
