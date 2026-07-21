# System Spec — Shared: Contratos por Camada

> Checklist arquitectural por camada de uma feature slice. Cada item é uma invariante verificável (tipicamente por teste ou Larastan).

As features são construídas em três camadas implementadas em issues separadas: **modelo**, **persistência** e **lógica**. Cada camada tem um contrato próprio.

---

## Camada de Modelo

`migration + model + factory + policy + DTOs + resource + testes`

1. `HasUuids` como PK — nunca ID incremental.
2. Casts correctos para enums e tipos especiais (via método `casts()`).
3. `#[Table]`, `#[Fillable]` (e `#[Hidden]` quando há campos sensíveis) — atributos, não arrays.
4. `@property-read` para todas as colunas (tipagem completa para Larastan e IA).
5. Factory produz instâncias válidas para cada state definido.
6. DTOs: `final readonly class`; construtor valida invariantes e lança `\InvalidArgumentException`; Resource `final` em `app/Features/<Entidade>/` com `@mixin Model` e array shape em `toArray()`.

> Detalhe de Models em `03-models/00-convencoes-models.md`; de DTOs em `02-shared/padroes-dtos.md`.

---

## Camada de Persistência

`interface + repository + services + service provider + testes`

1. Interface do repositório declara todos os métodos com tipos completos (sem `mixed`, sem `array` não tipado).
2. Implementação (`<Nome>Interface` / `<Nome>`, sem prefixo `Eloquent`) é `final class` (não `readonly`) e satisfaz a interface (Larastan nível 9).
3. Implementação injecta o Model via construtor — nunca Facade nem `new Model()`.
4. Paginação usa `cursorPaginate()` — nunca `paginate()` com OFFSET.
5. Binding `interface → implementação` registado em `AppServiceProvider`.
6. Actions injectam a **interface**, nunca a implementação concreta.
7. Services com interface têm binding; services concretos têm justificação documentada.

> Critérios de quando usar Repository em `04-infra/repositories.md`.

---

## Camada de Lógica

`actions + controller + formrequests + jobs + events + observers + testes`

1. Cada operação tem a sua Action com método `handle()` único.
2. Controller não contém lógica de negócio — apenas dispatch.
3. Actions injectam interface do repositório (se existe) ou Eloquent directo apenas em CRUD simples (≤ 1 query, sem lógica partilhada).
4. `FormRequest::authorize()` chama a Policy — nunca `return true` hardcoded sem justificação.
5. Autorização dupla camada: `Gate::authorize()` no FormRequest **e** na Action (ver `02-shared/padroes-acoes.md`).
6. `fromRequest()` implementado nos DTOs correspondentes (quando existem FormRequests).
7. Events disparados **dentro** das Actions — nunca no Controller nem no Model.
8. Actions de escrita usam `DB::transaction()` com `@throws \Throwable` no `handle()` (ver `04-infra/transactions.md`).
9. Jobs assíncronos: `final class implements ShouldQueue`; `$tries` e `$timeout` declarados; queue definida; `ShouldQueueAfterCommit` se disparados dentro de transações (ArchTest garante isto para todo `Job` em `app/Jobs/`, ver `04-infra/queue-jobs.md`).

---

## Transversal a todas as camadas

- `declare(strict_types=1)` em todos os ficheiros.
- Nomenclatura PT (ver `02-shared/convencoes-nomenclatura.md`).
- Tipagem sem `mixed` (ver `02-shared/padroes-tipagem.md`).
- 100% code coverage e 100% type coverage (`composer test`).
- Padrão dual de testes — Unit (programático) + Feature (HTTP) (ver `07-testing.md`).
