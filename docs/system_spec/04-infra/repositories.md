# System Spec — Infra: Repositories

> `app/Infrastructure/Repositories/`

O Repository fica **entre a Action e o Eloquent Model**. Não é obrigatório em todos os casos — a sua presença depende da complexidade da query e da partilha de lógica entre Actions.

---

## Quando usar — critérios

| Situação | Repository | Justificação |
|---|---|---|
| Joins, aggregates, raw SQL | **Obrigatório** | Lógica de query complexa não deve estar na Action |
| Query partilhada entre ≥ 2 Actions | **Obrigatório** | Evita duplicação de lógica de leitura/escrita |
| Filtros dinâmicos / paginação keyset complexa | **Obrigatório** | Encapsula a construção do query builder |
| CRUD simples (≤ 1 query Eloquent por `handle()`, sem lógica partilhada) | **Dispensável** | Eloquent directo na Action é suficiente e idiomático |
| Filtro simples (`WHERE`, `lockForUpdate()`, sem joins/aggregates) com um único consumidor **actual** | **Dispensável** | Expor como scope no Model é suficiente; `lockForUpdate()` não é, por si só, "lógica de query complexa" |

**Regra:** quando uma Action de CRUD simples acede ao Eloquent directamente (sem Repository), o desvio é **sempre documentado no Brief da feature**.

**Cuidado com "reutilização futura":** justificar um Repository por "vai ser reutilizado por ≥ 2
consumidores" quando só existe 1 consumidor **hoje** é especulação, não o critério real da tabela
acima (que exige reutilização **actual**, não projectada). Nesta arquitectura Vertical Slice +
Actions, o scope no Model já cobre a maioria dos casos de filtro reutilizável — ver `Estado actual`.

---

## Padrão de implementação

- A Action injecta sempre a **interface** do repositório, nunca a implementação concreta.
- Nomenclatura: interface `Contrato<Nome>`; implementação `<Nome>` (sem prefixo `Eloquent` — não há outra
  implementação prevista, o prefixo não acrescenta informação).
- A implementação é `final class` — **não** `readonly` (o Eloquent não é imutável).
- A implementação injecta o Model via construtor — nunca acede ao Facade nem instancia com `new Model()`.
- Interface tipada com tipos nativos PHP 8.5 — sem `mixed`, sem `array` não tipado.
- Binding `interface → implementação` registado em `AppServiceProvider`.
- Paginação: `cursorPaginate()` obrigatório — nunca `paginate()` com OFFSET (ver `02-shared/http.md`).

---

## Estado actual

_Pendente — primeiro repository a implementar quando surgir lógica de query complexa ou partilhada. As Actions de CRUD actuais (`CategoriaDocumento`, `Entidade`) acedem ao Eloquent directamente por serem CRUD simples._

**Precedente (#90):** a fundação de concorrência do pipeline (`ReivindicarDocumentoPendenteAction`
com `lockForUpdate()`; `ReconciliarFicheirosJob` com filtro por `status`/`updated_at`) foi desenhada
inicialmente com Repository (critério "Query partilhada entre ≥ 2 Actions" — mas com apenas 1
consumidor real cada, a justificação real era "reutilização futura"). Revertido para scopes no
`Documento` (`wherePendente()`, `documentosPresos()`) chamados directamente pela Action/Job — sem
Repository. Mantém-se "Pendente" acima.
