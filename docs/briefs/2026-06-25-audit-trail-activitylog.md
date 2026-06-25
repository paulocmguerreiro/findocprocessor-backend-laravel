# Brief — Audit Trail com spatie/laravel-activitylog

**Issue:** #54
**Data:** 2026-06-25
**Branch:** `feat/audit-trail-activitylog`
**Tipo:** feat (infra)

---

## Problema

O sistema não tem rastreio persistente de alterações de dados de domínio. O logging estruturado (Issue #37) escreve para stdout/ficheiro mas não é consultável por sujeito ou utilizador. Necessário: quem alterou o quê, com que valores antes/depois, com atomicidade garantida.

---

## Solução adoptada

Instalar `spatie/laravel-activitylog ^4.x` e adicionar o trait `LogsActivity` aos três modelos de domínio auditados: `CategoriaDocumento`, `Entidade`, e `Role`.

Para `Role` (modelo de terceiro em `Spatie\Permission\Models\Role`): criar `App\Models\Role` que estenda o Spatie Role e adicione `LogsActivity`. Actualizar `config/permission.php` → `'models' => ['role' => App\Models\Role::class]`.

O pacote usa eventos Eloquent (`created`, `updated`, `deleted`) que disparam **dentro** da transação — sem jobs — garantindo atomicidade nativa com `DB::transaction()`.

---

## Desvios ao padrão

**Repositório:** não aplicável — esta issue não envolve queries de leitura; só adiciona behavior aos modelos via trait.

**Role model:** a única acção que acede directamente ao `Spatie\Permission\Models\Role` (`CriarRoleAction`, `ActualizarRoleAction`, `EliminarRoleAction`) continuará a funcionar porque `App\Models\Role` herda da classe Spatie. As Actions precisam de importar `App\Models\Role` em vez de `Spatie\Permission\Models\Role` — ou não necessitam (o binding via config/permission.php é automático).

---

## Componentes afectados

| Componente | Tipo de alteração |
|---|---|
| `composer.json` | nova dependência `spatie/laravel-activitylog ^4.x` |
| migration `create_activity_log_table` | nova (publicada do vendor) |
| `App\Models\CategoriaDocumento` | adicionar `LogsActivity` trait + `getActivitylogOptions()` |
| `App\Models\Entidade` | adicionar `LogsActivity` trait + `getActivitylogOptions()` com `logExcept(['nif'])` |
| `App\Models\Role` (novo) | criar `App\Models\Role` extends `Spatie\Permission\Models\Role` + `LogsActivity` |
| `config/permission.php` | `'models' => ['role' => App\Models\Role::class]` |
| `tests/Unit/Features/` | testes de audit log por model |
| `tests/Feature/Features/` | testes HTTP com verificação de registo de actividade |
| `docs/system_spec/04-infra/audit-trail.md` | novo |
| `docs/system_spec/00-index.md` | actualizar |
| `docs/system_spec/03-models/` | actualizar CategoriaDocumento, Entidade; criar Role |

---

## Riscos identificados

**R1 — Atomicidade com events vs. after-commit:** O pacote por omissão regista a actividade usando listeners de eventos Eloquent que correm **dentro** da transação. Se `DB_QUEUE_AFTER_COMMIT=true` ou o Model usar `ShouldDispatchAfterCommit`, os eventos podem disparar fora da transação. Verificar na implementação que o pacote **não** usa `ActivitylogServiceProvider` com fila e que não há `dispatchAfterCommit` configurado.

**R2 — Larastan nível 9:** `LogsActivity` usa `mixed` internamente. Pode gerar erros de PHPStan se as `@property-read` dos models colidirem com anotações do trait. Testar `composer test:types` após cada model.

**R3 — ArchTest `actions are final` / `preset()->laravel()`:** O trait `LogsActivity` não é uma Action nem um Controller — não deve conflituar. `App\Models\Role` herda de Spatie — verificar que o preset laravel não flag a herança.

**R4 — `logExcept` vs. `logOnly` para Entidade:** `logExcept(['nif'])` regista todos os fillable excepto `nif`. Alternativa: `logOnly(['nome', 'e_cliente', 'e_fornecedor', 'e_empresa_aplicacao'])`. A issue pede `logExcept` — mais seguro para campos novos futuros.

**R5 — Role como sujeito vs. causer:** A issue audita `Role` como **sujeito** (o que foi alterado), não como causer. `causer` é sempre o `User` autenticado (`Auth::user()`). `App\Models\Role` só precisa de `LogsActivity`.

---

## Questões em aberto

**Q1 — `logOnlyDirty`:** Activar `logOnlyDirty()` nos modelos? Evita registar actividade quando `save()` é chamado sem alterações reais. A issue não especifica — adoptar por omissão (melhor prática).

**Q2 — Política de retenção:** A issue menciona "12 meses mínimo (NIS2 Art. 21)" mas não pede implementação nesta issue. Registar como comentário no system_spec; implementação como issue separada.

**Q3 — Testes de rollback na camada Unit vs. Feature:** O rollback da actividade é um comportamento do pacote, não da Action. Testar ao nível Unit (model event que lança excepção dentro da transação) é suficiente — a Feature test pode verificar ausência de log após request que falha a meio.

---

## Critérios de conclusão (resumo)

- [ ] `activity_log` migration aplicada
- [ ] 3 modelos com `LogsActivity` configurado
- [ ] `nif` excluído dos logs de Entidade
- [ ] `causer` sempre ligado ao utilizador autenticado
- [ ] Testes padrão dual (Unit + Feature) a verde
- [ ] `composer test` sem erros
- [ ] `docs/system_spec/04-infra/audit-trail.md` criado
