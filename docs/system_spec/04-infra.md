# System Spec — 04: Infrastructure

> Actualizado automaticamente após cada Issue pela Fase 3 (documenta-issue).

## Transações de BD — Padrão obrigatório (Issue #34)

Todas as Actions de escrita envolvem a persistência em `DB::transaction()`. Autorização (`Gate::authorize()`) fica fora da transação.

**Padrão canónico:**
```php
Gate::authorize('create', Xxx::class);                        // fora — autorização

return DB::transaction(fn (): Xxx => Xxx::create([...]));     // dentro — persistência
```

Para Actions com múltiplas operações:
```php
Gate::authorize('update', $xxx);

return DB::transaction(function () use ($xxx, $dados): Xxx {
    $xxx->fill([...])->save();
    $xxx->refresh();
    return $xxx;
});
```

**Actions que implementam este padrão:**

| Action | Feature |
|---|---|
| `CriarCategoriaAction` | `CategoriaDocumento/Criar` |
| `ActualizarCategoriaAction` | `CategoriaDocumento/Actualizar` |
| `EliminarCategoriaAction` | `CategoriaDocumento/Eliminar` |

Todas as Actions de escrita futuras seguem este padrão obrigatoriamente (ver `CLAUDE.md` — Padrões obrigatórios).

**Nota Jobs:** Jobs disparados dentro de transações devem usar `after_commit: true` na config da queue ou implementar `ShouldDispatchAfterCommit` para evitar processamento antes do commit.

---

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
