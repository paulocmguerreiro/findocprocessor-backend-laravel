# System Spec — Feature: Entidade

> `App\Features\Entidade\`

CRUD completo de entidades (clientes, fornecedores, Empresa Mãe/Aplicação). Inclui a regra de negócio de unicidade da Empresa Mãe e um endpoint dedicado para converter uma entidade em Empresa Mãe.

**Fluxo de dados:**
```
HTTP Request → FormRequest (autoriza + valida) → Controller (constrói DTO ou passa Entidade via RMB) → Action (autoriza + acede Model) → Controller (formata com EntidadeResource) → ApiResponse
```

**Decisão arquitectural:** Sem Repository — Eloquent directo nas Actions (CRUD simples, ≤ 1 query por `handle()`). A sub-action `RemoverMarcacaoEmpresaMaeAction` é invocada via `RegraUnicidadeEmpresaMae` (classe de domínio que encapsula o `if (eEmpresaAplicacao)`), sempre **dentro** da transação do caller — nunca abre transação própria. A invariante "Empresa Mãe implica cliente + fornecedor" está encapsulada no trait `ComFlagsEfectivosEmpresaMae` (usado nos DTOs).

**Autorização:** dupla verificação — FormRequest + Action. `RemoverMarcacaoEmpresaMaeAction` (action interna) não tem `Gate::authorize()` próprio — é chamada dentro de uma Action já autorizada.

---

## Actions

| Classe | Namespace | Assinatura `handle()` | Descrição |
|---|---|---|---|
| `ListarEntidadesAction` | `App\Features\Entidade\Listar` | `handle(int $porPagina, CampoOrdenacaoEntidades $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao, FiltroEstadoRegisto $filtroEstado): CursorPaginator<int, Entidade>` | Devolve página via cursor pagination, ordenada e filtrada por estado SoftDelete via scope `filtrarPorEstadoRegisto()`; `estado` na chave de cache |
| `CriarEntidadeAction` | `App\Features\Entidade\Criar` | `handle(CriarEntidadeDto): Entidade` | Cria entidade; invoca `RegraUnicidadeEmpresaMae` se `eEmpresaAplicacao = true` |
| `VerEntidadeAction` | `App\Features\Entidade\Ver` | `handle(Entidade\|string): Entidade` | Devolve entidade; resolve UUID com `findOrFail` se string |
| `ActualizarEntidadeAction` | `App\Features\Entidade\Actualizar` | `handle(Entidade\|string, ActualizarEntidadeDto): Entidade` | Update completo (PUT semântico); invoca `RegraUnicidadeEmpresaMae`; devolve `refresh()` |
| `EliminarEntidadeAction` | `App\Features\Entidade\Eliminar` | `handle(Entidade\|string): void` | **Padrão B**: `forceDelete()` (hard delete) com fallback `fresh()?->delete()` (soft delete) quando FK `restrictOnDelete` bloqueia; ver `02-shared/soft-delete.md` |
| `RestaurarEntidadeAction` | `App\Features\Entidade\Restaurar` | `handle(Entidade\|string): Entidade` | Reactiva entidade soft-deleted; resolve com `withTrashed()->findOrFail`; `restore()` + invalida cache dentro da transação; `Gate::authorize('restore')` fora |
| `ConverterEmEmpresaMaeAction` | `App\Features\Entidade\EmpresaMae` | `handle(Entidade\|string): Entidade` | Remove marcação anterior + força os 3 flags (`e_empresa_aplicacao`, `e_cliente`, `e_fornecedor`) |
| `RemoverMarcacaoEmpresaMaeAction` | `App\Features\Entidade\EmpresaMae` | `handle(): void` | Action interna — `UPDATE entidades SET e_empresa_aplicacao = false WHERE e_empresa_aplicacao = true`; sem autorização própria; sempre chamada dentro da transação do caller |
| `AgruparEntidadeAction` | `App\Features\Entidade\Agrupar` | `handle(Entidade\|string $principal, Entidade\|string $secundaria): Entidade` | Funde `secundaria` em `principal`: reponta FKs conhecidas (`documentos.id_fornecedor`, `documentos.id_cliente`), une `e_cliente`/`e_fornecedor` por OR (`e_empresa_aplicacao` intocado), hard-delete (`forceDelete()`) da secundária sem fallback. Tudo em `DB::transaction()`; guarda de futuro por introspecção do esquema (ver `InventarioReferenciasEntidadeInterface`) antes do delete. `final readonly`, injecta `InventarioReferenciasEntidadeInterface` + `CacheServico` |

---

## Classe de domínio

| Classe | Namespace | Descrição |
|---|---|---|
| `RegraUnicidadeEmpresaMae` | `App\Features\Entidade\EmpresaMae` | Encapsula a regra: se `eEmpresaAplicacao = true`, invoca `RemoverMarcacaoEmpresaMaeAction`. Injectada por construtor nas 3 Actions de escrita. |

---

## Fusão de entidades (Agrupar)

`AgruparEntidadeAction` funde duas entidades duplicadas: assume-se a `principal` como correcta, reponta-se o UUID da `secundaria` para o da `principal` em todas as FKs conhecidas, e a `secundaria` é removida permanentemente. Não há fusão campo-a-campo — correcções à `principal` fazem-se pelo `update` normal.

| Classe | Namespace | Descrição |
|---|---|---|
| `InventarioReferenciasEntidadeInterface` | `App\Features\Entidade\Agrupar` | Contrato: `detectarColunasQueReferenciamEntidades(): list<string>`. Existe como interface (não classe concreta directa) para permitir substituição em testes — simula uma FK nova não tratada sem manipular o esquema real, incompatível com testes em paralelo sobre BD partilhada. Bind em `AppServiceProvider` → `InventarioReferenciasEntidade`. Excepção explícita na regra ArchTest "actions are final" (`tests/ArchTest.php`) por não ser `final`. |
| `InventarioReferenciasEntidade` | `App\Features\Entidade\Agrupar` | Implementação real: percorre `Schema::getTables()` + `Schema::getForeignKeys($tabela)`, devolve as colunas (`"tabela.coluna"`) cuja `foreign_table === 'entidades'`, ordenadas e sem duplicados. Guarda de futuro — se aparecer uma FK fora da allow-list `AgruparEntidadeAction::COLUNAS_TRATADAS`, a fusão falha em vez de deixar órfãos (cobre também `nullOnDelete`/`cascadeOnDelete`, que o `restrictOnDelete` da BD não bloquearia). |
| `AgrupamentoInvalidoException` | `App\Features\Entidade\Agrupar` | `final class extends DomainException` → `422` via handler existente. Factories: `paraEntidadesIguais()`, `paraEmpresaAplicacao()`, `paraReferenciasNaoTratadas(list<string> $colunas)`. |

**Allow-list de FKs tratadas** (`AgruparEntidadeAction::COLUNAS_TRATADAS`): `documentos.id_fornecedor`, `documentos.id_cliente`. Repontagem via `DB::table($tabela)->where($coluna, $secundaria->id)->update([$coluna => $principal->id])` (mass update — não dispara eventos Eloquent nem audit trail por documento; decisão consciente, auditoria fica ao nível da `Entidade`).

---

## DTOs

Todos `final readonly` com `fromRequest()` (array shape `@var`, Larastan nível 9). Usam o trait `ComFlagsEfectivosEmpresaMae`.

| DTO | Namespace | Campos (tipo) | Invariantes (construtor) |
|---|---|---|---|
| `CriarEntidadeDto` | `Entidade\Criar` | `nome:string`, `nif:string`, `eCliente:bool`, `eFornecedor:bool`, `eEmpresaAplicacao:bool` | `nome`/`nif` não-vazios (`trim`); booleans sem validação (não têm estado "vazio") |
| `ActualizarEntidadeDto` | `Entidade\Actualizar` | idem (update completo — sem campos opcionais) | idem (valida incondicionalmente, PUT) |

> A invariante `eEmpresaAplicacao → eCliente/eFornecedor` **não** está no DTO — é regra de negócio na Action (via `ComFlagsEfectivosEmpresaMae` + `RegraUnicidadeEmpresaMae`).

---

## Trait

| Classe | Namespace | Descrição |
|---|---|---|
| `ComFlagsEfectivosEmpresaMae` | `App\Features\Entidade` | `eClienteEfectivo(): bool` = `eEmpresaAplicacao || eCliente`; `eFornecedorEfectivo(): bool` = `eEmpresaAplicacao || eFornecedor`. Garante a invariante em criar e actualizar sem duplicação. |

---

## Enums de listagem

| Classe | Namespace | Cases | Descrição |
|---|---|---|---|
| `CampoOrdenacaoEntidades` | `App\Features\Entidade\Listar` | `Nome = 'nome'` | Campo de ordenação da listagem de entidades; extensível com `Nif`, `CreatedAt` |
| `FiltroEstadoRegisto` (partilhado) | `App\Shared\Enums` | `Todos`, `SomenteAtivos`, `SomenteInativos` | Filtro de estado SoftDelete na listagem; traduzido para scope via trait `FiltravelPorEstadoRegisto` no model. Ver `02-shared/soft-delete.md` |

---

## Policy

`EntidadePolicy` (`App\Policies`) — ligada por `#[UsePolicy(EntidadePolicy::class)]` no Model. Cada método exige `User` e verifica `hasPermissionTo('entidades.<accao>')` (guests são negados pelo Laravel por o 1.º parâmetro não ser `?User`). Matriz role→permission em `04-infra/autorizacao.md`.

| Método | Permissão |
|---|---|
| `viewAny` / `view` | `entidades.ver` |
| `create` | `entidades.criar` |
| `update` | `entidades.actualizar` |
| `delete` | `entidades.eliminar` |
| `restore` | `entidades.eliminar` (reutiliza — quem inactiva reactiva) |
| `agrupar` | `entidades.agrupar` (ability dedicada, não reutiliza `eliminar`/`actualizar`) |

---

## FormRequests

| Classe | Namespace | `authorize()` chama | `rules()` |
|---|---|---|---|
| `ListarEntidadesRequest` | `Listar` | `Gate::authorize('viewAny', Entidade::class)` | `per_page`, `sort`, `direction`, `estado`, `cursor` |
| `CriarEntidadeRequest` | `Criar` | `Gate::authorize('create', Entidade::class)` | `nome`, `nif`, `e_cliente`, `e_fornecedor`, `e_empresa_aplicacao` (required) |
| `VerEntidadeRequest` | `Ver` | `Gate::authorize('view', $this->route('entidade'))` | `[]` |
| `ActualizarEntidadeRequest` | `Actualizar` | `Gate::authorize('update', $this->route('entidade'))` | `nome`, `nif`, `e_cliente`, `e_fornecedor`, `e_empresa_aplicacao` (required) |
| `EliminarEntidadeRequest` | `Eliminar` | `Gate::authorize('delete', $this->route('entidade'))` | `[]` |
| `RestaurarEntidadeRequest` | `Restaurar` | `Gate::authorize('restore', $this->route('entidade'))` (modelo já ligado via RMB `withTrashed`) | `[]` |
| `ConverterEmEmpresaMaeRequest` | `EmpresaMae` | `Gate::authorize('update', $this->route('entidade'))` | `[]` |
| `AgruparEntidadeRequest` | `Agrupar` | `Gate::authorize('agrupar', Entidade::class)` | `[]` (corpo vazio — `principal`/`secundaria` vêm da rota) |

`CriarEntidadeRequest` e `ActualizarEntidadeRequest` não são `final` (mockáveis em testes unitários de DTO).

---

## Controller

`EntidadeController` (`App\Features\Entidade`) — `final`, sem lógica. Usa Route Model Binding (`Entidade $entidade`) + injecção de Actions via parâmetros de método.

| Método | FormRequest | Action invocada |
|---|---|---|
| `index` | `ListarEntidadesRequest` | `ListarEntidadesAction::handle()` — extrai `per_page` (cast `int`), `sort`, `direction`, `estado` (`FiltroEstadoRegisto`, default `SomenteAtivos`); devolve via `ApiResponse::devolverPaginado()` |
| `store` | `CriarEntidadeRequest` | `CriarEntidadeAction::handle(CriarEntidadeDto::fromRequest($pedido))` |
| `show` | `VerEntidadeRequest` | `VerEntidadeAction::handle($entidade)` |
| `update` | `ActualizarEntidadeRequest` | `ActualizarEntidadeAction::handle($entidade, ActualizarEntidadeDto::fromRequest($pedido))` |
| `destroy` | `EliminarEntidadeRequest` | `EliminarEntidadeAction::handle($entidade)` |
| `restaurar` | `RestaurarEntidadeRequest` | `RestaurarEntidadeAction::handle($entidade)` — `Entidade` via RMB `withTrashed` |
| `converterEmEmpresaMae` | `ConverterEmEmpresaMaeRequest` | `ConverterEmEmpresaMaeAction::handle($entidade)` |
| `agruparCom` | `AgruparEntidadeRequest` | `AgruparEntidadeAction::handle($principal, $secundaria)` — `Entidade $principal`, `Entidade $secundaria` via RMB nomeado (sem `withTrashed`) |

---

## Resource

`EntidadeResource` — `App\Features\Entidade\EntidadeResource`

Formata a resposta JSON de todos os endpoints que retornem uma `Entidade`.

```json
{
  "id": "019741b2-...",
  "nome": "Empresa Teste",
  "nif": "123456789",
  "e_cliente": true,
  "e_fornecedor": true,
  "e_empresa_aplicacao": false,
  "deleted_at": null
}
```

- Booleans devolvidos como `bool` (cast Eloquent `'boolean'` garante o tipo)
- `deleted_at`: `null` para entidades activas, ISO 8601 para soft-deleted — usado pelo endpoint `restaurar` para confirmar reactivação
- Restantes timestamps (`created_at`/`updated_at`) omitidos intencionalmente
- `@mixin Entidade` necessário para Larastan inferir as propriedades do model
