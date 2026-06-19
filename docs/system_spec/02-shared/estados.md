# System Spec — Shared: Estados e Contratos

> `app/Shared/`

---

## States (`app/Shared/States/`)

Ciclo de estados do documento — fluxo feliz na horizontal, ramos de falha/risco em baixo:

```
PENDING → AGUARDA_ENVIO → ENVIADO → AGUARDA_RESPOSTA → DONE
                                                      ↘ ERROR
                                                      ↘ PERIGOSO
```

### Estados

| Estado | Significado |
|---|---|
| `PENDING` | Documento recebido, ainda não enfileirado para processamento |
| `AGUARDA_ENVIO` | Pronto a ser enviado para o serviço de extracção |
| `ENVIADO` | Enviado para o serviço de extracção (IA) |
| `AGUARDA_RESPOSTA` | À espera da resposta do serviço de extracção |
| `DONE` | Processamento concluído com sucesso |
| `ERROR` | Falha no processamento — recuperável (permite reprocessar) |
| `PERIGOSO` | Documento marcado como potencialmente malicioso/suspeito |

### Transições

A mudança de estado é feita sempre através do objecto de estado — `$doc->state()->correct($data)` — **nunca** com `if ($doc->status == ...)` nas Actions. O estado actual é a fonte de verdade para as transições permitidas.

`ERROR` e `PERIGOSO` são estados terminais do ponto de vista do fluxo automático; saídas destes estados são desencadeadas por acções explícitas (reprocessar, eliminar).

_Implementações pendentes — os objectos de estado e a coluna `status` são definidos com a feature Document. O enum correspondente está documentado em `02-shared/enums.md` (`DocumentStatus`)._

---

## Contracts (`app/Shared/Contracts/`)

_Vazio até à primeira issue implementada._

---

## DTOs partilhados (`app/Shared/DTOs/`)

_Vazio até à primeira issue implementada._

DTOs de feature vivem dentro da sua slice (`app/Features/<Feature>/`). Ver `01-features/<slug>.md` para DTOs específicos de cada feature.
