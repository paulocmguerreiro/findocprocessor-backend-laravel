# Plano: Entidade — agrupar/fundir duplicados (repontar FKs + hard-delete)

**Issue:** #99
**Spec:** docs/specs/2026-07-21-entidade-agrupar-duplicados.md
**Data:** 2026-07-21

## Tarefas

### Tarefa 1 — Permissão `entidades.agrupar` + Policy `agrupar()`
- Ficheiros a criar/alterar:
  - `database/migrations/2026_07_21_XXXXXX_seed_entidades_agrupar_permission.php` (nova data-migration,
    padrão de `seed_utilizadores_anonimizar_permission`: cria a permission e sincroniza ao role
    `admin`; `down()` remove-a — `forgetCachedPermissions()` no início de `up()`/`down()`)
  - `app/Policies/EntidadePolicy.php` — novo método `agrupar(User $utilizador): bool` →
    `hasPermissionTo('entidades.agrupar')`
- O que implementar: nova permissão granular só para `admin`; ability dedicada na Policy (não
  reutiliza `eliminar`). Autorização dupla camada usará esta ability.
- Testes associados:
  - `tests/Unit/Policies/EntidadePolicyTest.php` — `agrupar`: `admin` → `true`, `utilizador` → `false`
    (seguir o padrão dos métodos já testados na mesma classe de teste)
  - Teste que confirma a permissão semeada após `migrate` (seguir padrão existente de verificação de
    permissões, se houver; caso contrário coberto pelo teste HTTP 403/200 na Tarefa 4)
- Commit: `feat(entidade): permissão entidades.agrupar + EntidadePolicy::agrupar`

### Tarefa 2 — Guarda de futuro: `InventarioReferenciasEntidade` + `AgrupamentoInvalidoException`
- Ficheiros a criar:
  - `app/Features/Entidade/Agrupar/InventarioReferenciasEntidade.php` — serviço concreto (sem
    interface: introspecção pura, sem substituição prevista). Método
    `detectarColunasQueReferenciamEntidades(): list<string>` que percorre `Schema::getTables()` +
    `Schema::getForeignKeys($tabela)` e devolve as colunas cujo `foreign_table === 'entidades'` no
    formato `"tabela.coluna"` (ex.: `documentos.id_fornecedor`).
  - `app/Features/Entidade/Agrupar/AgrupamentoInvalidoException.php` — `final class` que estende
    `DomainException` (→ `422` via handler existente). Factories estáticas:
    `paraEntidadesIguais(): self`, `paraEmpresaAplicacao(): self`,
    `paraReferenciasNaoTratadas(list<string> $colunas): self`.
- O que implementar: a peça de introspecção do esquema (à prova de futuro) e a excepção de domínio das
  três guardas. Nenhuma lógica de fusão ainda — só os blocos reutilizáveis.
- Testes associados:
  - `tests/Unit/Features/Entidade/Agrupar/InventarioReferenciasEntidadeTest.php` — assere que devolve
    **exactamente** `documentos.id_fornecedor` e `documentos.id_cliente` contra o esquema real (MySQL).
  - `tests/Unit/Features/Entidade/Agrupar/AgrupamentoInvalidoExceptionTest.php` — cada factory produz a
    mensagem esperada e é instância de `DomainException`.
- Commit: `feat(entidade): inventário de referências a entidades + excepção de fusão`

### Tarefa 3 — `AgruparEntidadeAction` (repontar UUID + unir papéis + hard-delete)
- Ficheiros a criar:
  - `app/Features/Entidade/Agrupar/AgruparEntidadeAction.php` — `final readonly`, injecta
    `InventarioReferenciasEntidade` e `CacheServico`. Constante
    `COLUNAS_TRATADAS = ['documentos.id_fornecedor', 'documentos.id_cliente']`.
- O que implementar (assinatura `handle(Entidade|string $principal, Entidade|string $secundaria): Entidade`):
  1. Resolver ambos (`findOrFail` se string) — **sem** `withTrashed` (registos activos apenas).
  2. `Gate::authorize('agrupar', Entidade::class)` — **fora** da transação.
  3. Guardas de negócio (fora ou no topo da transação, antes de mutar):
     `principal->id === secundaria->id` → `AgrupamentoInvalidoException::paraEntidadesIguais()`;
     `secundaria->e_empresa_aplicacao` → `::paraEmpresaAplicacao()`.
  4. `DB::transaction()`:
     - **Guarda de futuro:** `diff` entre `InventarioReferenciasEntidade::detectarColunasQueReferenciamEntidades()`
       e `COLUNAS_TRATADAS`; se não-vazio → `::paraReferenciasNaoTratadas($diff)`.
     - **Repontar:** por cada `"tabela.coluna"` em `COLUNAS_TRATADAS`,
       `DB::table($tabela)->where($coluna, $secundaria->id)->update([$coluna => $principal->id])`
       (nomes vêm da constante de código — nunca do cliente).
     - **Unir papéis:** `principal->update(['e_cliente' => $principal->e_cliente || $secundaria->e_cliente,
       'e_fornecedor' => $principal->e_fornecedor || $secundaria->e_fornecedor])` — `e_empresa_aplicacao`
       intacto.
     - **Hard-delete:** `$secundaria->forceDelete()` — sem fallback; se falhar, excepção propaga →
       rollback (RN-06).
     - `cache->invalidarCache(TagCache::Entidades)`.
     - `return $principal->refresh()`.
  - `@throws ModelNotFoundException<Entidade>`, `AuthorizationException`,
    `AgrupamentoInvalidoException`, `\Throwable`. `Log::info` início/fim (padrão das outras Actions).
- Testes associados — `tests/Unit/Features/Entidade/Agrupar/AgruparEntidadeActionTest.php`:
  - documentos com `id_fornecedor`/`id_cliente` = secundária passam a apontar para a principal (CA-01)
  - secundária deixa de existir mesmo com `Entidade::withTrashed()` (hard-delete, CA-02)
  - união de papéis por OR; `e_empresa_aplicacao` da principal inalterado (CA-07)
  - `principal == secundaria` → excepção, sem mutação (CA-03)
  - `secundaria` = empresa aplicação → excepção, sem mutação (CA-03)
  - FK nova não tratada (criar tabela temporária com FK→`entidades` no teste) → excepção + rollback,
    secundária **não** removida (CA-08)
  - sem permissão `entidades.agrupar` → `AuthorizationException` (autorização camada lógica)
- Commit: `feat(entidade): AgruparEntidadeAction — repontar referências + unir papéis + hard-delete`

### Tarefa 4 — Endpoint HTTP: Request + Controller + rota (padrão dual)
- Ficheiros a criar/alterar:
  - `app/Features/Entidade/Agrupar/AgruparEntidadeRequest.php` — `authorize()` via
    `Gate::allows('agrupar', Entidade::class)`; `rules(): []` (corpo vazio); `messages()` PT se aplicável.
  - `app/Features/Entidade/EntidadeController.php` — novo método
    `agruparCom(AgruparEntidadeRequest $pedido, Entidade $principal, Entidade $secundaria, AgruparEntidadeAction $accao): JsonResponse`
    → `ApiResponse::devolverSucesso(new EntidadeResource($accao->handle($principal, $secundaria)))`.
    RMB implícito resolve `{principal}`/`{secundaria}` por nome de parâmetro (sem `withTrashed`).
  - `routes/api.php` — `Route::post('entidades/{principal}/agrupar-com/{secundaria}', [EntidadeController::class, 'agruparCom'])`.
- O que implementar: camada HTTP fina, sem lógica; autorização camada HTTP (a par da camada lógica da
  Tarefa 3).
- Testes associados — `tests/Feature/Features/Entidade/Agrupar/AgruparEntidadeTest.php`:
  - `admin` → `200` + `EntidadeResource` da principal com papéis unidos; documentos repontados na BD
  - `principal == secundaria` → `422`; secundária = empresa aplicação → `422`
  - principal/secundária inexistente **ou** soft-deleted → `404`
  - `utilizador` sem permissão → `403`; não autenticado → `401`
- Commit: `feat(entidade): endpoint POST /entidades/{principal}/agrupar-com/{secundaria}`

## Ordem de implementação

1. **Tarefa 1** (permissão + Policy) — fundação de autorização; a ability é pré-requisito do
   `Gate::authorize` da Action (T3) e do Request (T4).
2. **Tarefa 2** (inventário + excepção) — blocos de domínio reutilizáveis; sem dependência de T3/T4.
3. **Tarefa 3** (Action) — depende de T2 (inventário + excepção) e de T1 (ability).
4. **Tarefa 4** (HTTP) — depende de T3 (Action) e T1 (ability). Fecha o padrão dual (Feature tests).

> Ordem por camada: authz (fundação) → domínio (T2) → aplicação (T3) → api (T4).

## Testes a escrever

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| Policy `agrupar` admin/utilizador | unit | `tests/Unit/Policies/EntidadePolicyTest.php` | autorização (CA-09) |
| Inventário devolve as 2 colunas conhecidas | unit | `tests/Unit/Features/Entidade/Agrupar/InventarioReferenciasEntidadeTest.php` | introspecção (RF-05) |
| Factories da excepção | unit | `tests/Unit/Features/Entidade/Agrupar/AgrupamentoInvalidoExceptionTest.php` | mensagens 422 |
| Repontagem de documentos | unit | `AgruparEntidadeActionTest.php` | CA-01 |
| Hard-delete da secundária (`withTrashed` vazio) | unit | `AgruparEntidadeActionTest.php` | CA-02 |
| União de papéis OR; empresa_aplicacao intacto | unit | `AgruparEntidadeActionTest.php` | CA-07 |
| Guardas iguais / empresa aplicação | unit | `AgruparEntidadeActionTest.php` | CA-03 |
| FK nova não tratada → falha + rollback | unit | `AgruparEntidadeActionTest.php` | CA-08 |
| Autorização camada lógica | unit | `AgruparEntidadeActionTest.php` | CA-09 |
| HTTP 200 sucesso + repontagem | feature | `tests/Feature/Features/Entidade/Agrupar/AgruparEntidadeTest.php` | CA-01/04/07 |
| HTTP 422 (iguais, empresa aplicação) | feature | `AgruparEntidadeTest.php` | CA-03 |
| HTTP 404 (inexistente/soft-deleted) | feature | `AgruparEntidadeTest.php` | CA-03 |
| HTTP 403/401 (sem permissão / anónimo) | feature | `AgruparEntidadeTest.php` | CA-09 |

## Dependências
- Issues bloqueantes: **nenhuma**.
- Deve ser implementada após: **#98** (motivação — gera os duplicados), mas não é bloqueio técnico.

## Riscos de implementação
> Consolidados do Brief (`## Riscos identificados`) e da Spec.
- **FK não tratada → órfãos:** mitigado por dupla salvaguarda — guarda por introspecção do esquema
  (erro `422` explícito) + hard-delete sobre `restrictOnDelete` (rollback automático via
  `QueryException`). A guarda cobre também `nullOnDelete`/`cascadeOnDelete`.
- **Mass update não dispara eventos Eloquent** — documentos repontados não geram audit trail
  individual; a auditoria fica ao nível da `Entidade` (decisão consciente).
- **`forceDelete()` + audit trail** — confirmar em T3 que o spatie/activitylog regista a remoção da
  secundária como esperado (comportamento do `forceDelete` a validar; sem bloqueio de âmbito).
- **Concorrência** — duas fusões simultâneas sobre a mesma entidade. Mitigado pela transação; avaliar
  `lockForUpdate` só se um teste o justificar (evitar sobre-engenharia — operação administrativa rara).
- **NIF divergente entre principal e secundária** — não se valida (fusão é decisão humana); `nif`
  fica excluído do audit trail.

## O que NÃO fazer nesta issue
- Não detectar automaticamente duplicados (sugestão de candidatos) — futura.
- Não fundir dados campo-a-campo (nome, nif, etc.) — só repontar referências e remover a secundária.
- Não alterar o esquema da BD (sem migration de tabela/coluna) nem tocar em Actions existentes de
  Entidade ou no pipeline de extração.
- Não permitir a `secundaria` = empresa aplicação; a `principal` pode sê-lo.
- Não usar soft-delete nem o Padrão B (force-com-fallback) para a secundária — é hard-delete sem
  fallback.
- Não actualizar `docs/system_spec/` nesta fase (é responsabilidade da Fase 3a).
