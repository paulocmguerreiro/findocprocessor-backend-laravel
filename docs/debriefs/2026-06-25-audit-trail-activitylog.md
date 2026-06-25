# Debrief — Issue #54: Audit Trail com spatie/laravel-activitylog

**Data:** 2026-06-25
**Issue:** #54
**Branch:** `feat/audit-trail-activitylog`
**Tipo:** feat (infra)

---

## O que foi implementado

Rastreio persistente de alterações de dados de domínio (`CategoriaDocumento`, `Entidade`, `Role`) com atomicidade garantida pela transação da Action. Implementado com `spatie/laravel-activitylog ^4.0`.

### Componentes criados

| Componente | Descrição |
|---|---|
| `app/Models/Concerns/RegistaActividade` | Trait que encapsula a política canónica de audit trail (logFillable + logOnlyDirty + dontSubmitEmptyLogs) |
| `app/Observers/RoleObserver` | Observer para `Spatie\Permission\Models\Role` (modelo de terceiro — não usa o trait) |
| `tests/Unit/Features/AuditTrail/` | 3 ficheiros de testes Unit (CategoriaDocumento, Entidade, Role) |
| `docs/system_spec/04-infra/audit-trail.md` | Spec completo de audit trail |
| `docs/system_spec/03-models/role.md` | Spec do RoleObserver |

### Componentes alterados

| Componente | Alteração |
|---|---|
| `app/Models/CategoriaDocumento` | Adiciona `RegistaActividade` trait |
| `app/Models/Entidade` | Adiciona `RegistaActividade` trait; sobrepõe `atributosExcluidosDaActividade()` → `['nif']` |
| `app/Providers/AppServiceProvider` | Regista `Role::observe(RoleObserver::class)` em `boot()` |
| Migrations `create_activity_log_table` (×3) | Publicadas do vendor e modernizadas; `subject_id` trocado para `char(36)` |
| Testes HTTP existentes (CategoriaDocumento, Entidade, Role) | Assertions `Activity::count()` em criar/actualizar/eliminar/403 |

---

## Decisões tomadas

### D1 — Trait `RegistaActividade` em vez de `LogsActivity` directo

**O que:** em vez de cada model usar `LogsActivity` + `getActivitylogOptions()` duplicado, foi extraído o trait `App\Models\Concerns\RegistaActividade` que encapsula a política canónica do projecto.

**Porquê:** Spec previa `LogsActivity` directo. Durante T2, detectou-se que T3 iria duplicar exactamente o mesmo `getActivitylogOptions()`. Extrair o trait cumpre o princípio DRY, centraliza a política (uma alteração propaga a todos os modelos auditados) e mantém o hook `atributosExcluidosDaActividade()` para campos sensíveis por model.

**Trade-off aceite:** um nível de indirection adicional. Justificado pela centralização e pela facilidade de adicionar novos modelos no futuro.

### D2 — `nullableUuidMorphs('subject')` na migration de `activity_log`

**O que:** o stub publicado pelo pacote cria `subject_id` como `bigint` (`nullableMorphs`). Foi substituído por `nullableUuidMorphs('subject')` → `char(36)`.

**Porquê:** `CategoriaDocumento` e `Entidade` usam `HasUuids`; `Role` usa bigint. `char(36)` acomoda ambos. Usar `bigint` falharia ao persistir UUIDs. `causer` mantém `nullableMorphs` porque `User` usa bigint.

### D3 — Observer para `Role` sem subclasse

**O que:** a spec original (Brief) previa criar `App\Models\Role extends Spatie\Permission\Models\Role` com `LogsActivity`. A spec final adoptou Observer directo sobre o modelo Spatie.

**Porquê:** a subclasse exigiria alterar `config/permission.php` e poderia gerar conflitos com o binding automático do pacote Spatie. O Observer é mais limpo e não toca em configuração.

### D4 — Ordem de migração: activity_log antes dos seeds de roles

**O que:** os timestamps das 3 migrations de `activity_log` foram definidos como `2026_06_22_14500x` (antes de `2026_06_22_150715`, que cria os seeds de roles).

**Porquê:** os seeds de roles criam roles via Eloquent → o `RoleObserver` tenta escrever em `activity_log`. Se a tabela não existir, `migrate --fresh` falha. Este desvio foi necessário para garantir que a infra de auditoria existe antes de qualquer escrita auditada.

---

## O que não foi feito (fora de âmbito)

- **Retenção de logs (NIS2 Art. 21):** purga agendada de `activity_log` após 12 meses não implementada — candidato a issue separada.
- **Endpoint de consulta de audit trail:** nenhuma rota nova — o `openapi.yaml` não foi alterado.

---

## Números finais

- **315 testes** (todos a verde), **100% cobertura**, **PHPStan nível 9 sem erros**
- Commits: 10 (incluindo desvios de fix e refactor)

---

## Aprendizagens

### Vertical Slice + comportamento transversal

A maior lição desta issue foi perceber onde colocar comportamento **transversal** (cross-cutting) numa arquitectura Vertical Slice. O audit trail não pertence a nenhuma feature slice — pertence ao modelo. A solução foi colocar o trait em `app/Models/Concerns/`, que é o espaço correcto para comportamento partilhado entre modelos, sem violar o isolamento das slices.

### Observer vs. Trait para modelos de terceiros

Quando não se controla o código do modelo (caso do `Spatie\Permission\Models\Role`), o Observer é o mecanismo Laravel correcto para adicionar comportamento sem subclasse. A tentação de criar `App\Models\Role extends Role` é contraproducente: adiciona um nível de herança só para contornar uma limitação de ownership.

### Atomicidade de eventos Eloquent dentro de transação

Confirmado empiricamente (testes de rollback) que os eventos Eloquent (`created`, `updated`, `deleted`) disparam **dentro** da `DB::transaction()` das Actions. O rollback da transação reverte o registo de actividade. Este comportamento é garantido pelo Laravel e não requer configuração adicional no pacote.

### Ordem de migrations importa quando há seeds auditados

Se os seeds de roles criam Eloquent records, e esses models têm observers que escrevem noutras tabelas, essa tabela tem de existir antes dos seeds. A solução foi ajustar os timestamps das migrations para garantir a ordem correcta — não é óbvio à primeira vista mas é crítico para `migrate --fresh`.

### `char(36)` como tipo polimórfico para sujeitos mistos (UUID + bigint)

Quando uma tabela de audit guarda referências para modelos com PKs de tipos diferentes (UUID e bigint), `char(36)` é o tipo mínimo que acomoda ambos. `nullableUuidMorphs()` gera `char(36)` no Laravel — mais correcto do que `nullableMorphs()` (que gera `bigint`).
