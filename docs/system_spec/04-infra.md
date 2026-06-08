# System Spec — 04: Infrastructure

> Actualizado automaticamente após cada Issue pela Fase 3 (documenta-issue).

## Repositories (app/Infrastructure/Repositories/)

_Vazio até à primeira issue implementada._

## AI Provider (app/Infrastructure/AI/)

_Vazio até à primeira issue implementada._

## File System (app/Infrastructure/FileSystem/)

_Vazio até à primeira issue implementada._

## Cache — Redis (app/Infrastructure/Cache/)

Chaves planeadas:
| Chave                         | TTL   | Conteúdo                         |
| ----------------------------- | ----- | -------------------------------- |
| `documents:all`               | 30s   | Lista completa de documentos     |
| `config:extraction_templates` | 5min  | Templates de extracção activos   |
| `batch:cycle_state`           | dinâm | Estado actual do ciclo batch     |

_Implementações pendentes._

## Jobs (app/Jobs/)

| Job               | Tipo      | Schedule/Queue     |
| ----------------- | --------- | ------------------ |
| `WatchInboxJob`   | Scheduled | cada 30s           |
| `ProcessBatchJob` | Queued    | dispatch por Watch |

_Implementações pendentes._
