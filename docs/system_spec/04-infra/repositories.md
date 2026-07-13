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

**Regra:** quando uma Action de CRUD simples acede ao Eloquent directamente (sem Repository), o desvio é **sempre documentado no Brief da feature**.

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
