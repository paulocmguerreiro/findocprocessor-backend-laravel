# System Spec — Shared: Estados e Contratos

> `app/Shared/`

---

## States (`app/Shared/States/`)

Ciclo de estados do documento:

```
PENDING → AGUARDA_ENVIO → ENVIADO → AGUARDA_RESPOSTA → DONE
                                                      ↘ ERROR
                                                      ↘ PERIGOSO
```

_Implementações pendentes — definidas com a feature Document._

---

## Contracts (`app/Shared/Contracts/`)

_Vazio até à primeira issue implementada._

---

## DTOs partilhados (`app/Shared/DTOs/`)

_Vazio até à primeira issue implementada._

DTOs de feature vivem dentro da sua slice (`app/Features/<Feature>/`). Ver `01-features/<slug>.md` para DTOs específicos de cada feature.
