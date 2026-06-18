# Debrief — Issue #34: Transações de BD nas Actions de escrita

**Data:** 2026-06-18
**Issue:** #34
**Branch:** `chore/transacoes-bd-actions`
**Duração:** 1 sessão

---

## O que foi feito

Aplicado `DB::transaction()` às 3 Actions de escrita da feature `CategoriaDocumento`. Documentado o padrão no `CLAUDE.md` e no `system_spec/04-infra.md`. Criados/actualizados testes de rollback para as 3 Actions.

### Ficheiros alterados

| Ficheiro | Tipo de alteração |
|---|---|
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php` | `DB::transaction()` + `@throws \Throwable` |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php` | `DB::transaction()` + `@throws \Throwable` |
| `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php` | `DB::transaction()` + `@throws \Throwable` |
| `tests/Unit/Features/CategoriaDocumento/CriarCategoriaActionTest.php` | Novo — happy path + rollback |
| `tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaActionTest.php` | Rollback adicionado |
| `tests/Unit/Features/CategoriaDocumento/EliminarCategoriaActionTest.php` | Rollback adicionado |
| `CLAUDE.md` | Padrão `DB::transaction()` em "Padrões obrigatórios" |
| `docs/system_spec/04-infra.md` | Secção "Transações de BD" |

---

## Decisões tomadas

### Gate::authorize() fora da transação

A autorização fica antes do `DB::transaction()`. Razão: autorização não é uma operação de BD no contexto actual — abrir uma transação para depois falhar em `Gate::authorize()` seria desnecessário. Esta decisão é consistente com a arquitectura dupla camada já existente (FormRequest + Action).

### Padrão com arrow function vs closure

Para Actions simples (uma operação), usou-se arrow function:
```php
DB::transaction(fn (): CategoriaDocumento => CategoriaDocumento::create([...]))
```

Para Actions com múltiplas operações encadeadas, usou-se closure com `use`:
```php
DB::transaction(function () use ($categoria, $dados): CategoriaDocumento {
    $categoria->fill([...])->save();
    $categoria->refresh();
    return $categoria;
})
```

### @throws \Throwable vs @throws \Exception

`DB::transaction()` re-lança qualquer `\Throwable` (inclui `Error`), não apenas `\Exception`. O PHPDoc reflecte isso com `@throws \Throwable`.

### Estratégia de teste para rollback

Como as Actions actuais são maioritariamente single-operation, a forma de testar rollback é registar um model event hook (`created`, `saved`, `deleting`) que lança excepção após a operação de BD mas antes do commit da transação. O `assertDatabaseCount`/`assertDatabaseHas` confirma que o rollback ocorreu.

### EliminarCategoriaAction: DB::transaction retorna ?bool

`$categoria->delete()` retorna `bool|null`. A transação retorna esse valor mas a assinatura do `handle()` é `void` — o valor de retorno é simplesmente descartado. Pint ajustou `bool|null` → `?bool` na arrow function (`nullable_type_declaration` fixer).

---

## Problemas encontrados

### Rector — AddArrowFunctionReturnTypeRector nos testes

Após os primeiros commits dos testes, `composer test` falhou porque Rector queria adicionar tipos de retorno às arrow functions nos ficheiros de teste:

```php
// antes
expect(fn () => (new CriarCategoriaAction)->handle($dto))

// depois (Rector)
expect(fn (): \App\Models\CategoriaDocumento => (new CriarCategoriaAction)->handle($dto))
```

Resolvido com `composer refactor` + `composer lint` antes do commit final. Esta sequência reforça a regra do `CLAUDE.md`: Pint + Rector antes de cada commit.

### Pint — fully_qualified_strict_types

Depois de Rector adicionar `\App\Models\CategoriaDocumento`, Pint aplicou `fully_qualified_strict_types` que converteu para `use` statements no topo. Sequência correcta: `refactor` → `lint` → `test` → commit.

---

## Aprendizagens

### Vertical Slice + Transações: o padrão é local a cada Action

Em Vertical Slice não há um "service layer" central onde colocar a lógica de transação. Cada Action é responsável por envolver a sua própria persistência. Isto é uma vantagem: a transação está co-localizada com o código que protege, não escondida numa camada intermédia.

### DB::transaction() re-lança — não é preciso try/catch

Uma dúvida natural é "preciso de `try/catch` dentro da transação?". A resposta é não — `DB::transaction()` faz rollback e re-lança automaticamente qualquer `\Throwable`. O caller (Controller) continua a receber a excepção original. O `@throws \Throwable` no PHPDoc serve apenas para documentar este comportamento.

### Model events como ferramenta de teste de rollback

Usar `Model::created()` / `Model::saved()` / `Model::deleting()` para lançar excepção em testes é uma técnica limpa para verificar rollback sem precisar de mocks ou doubles — usa a mesma infra de eventos que o Eloquent usa em produção.

### Gate::authorize() antes da transação — princípio de separação de concerns

Autorização e persistência são concerns distintos. Misturá-los dentro da mesma transação acoplaria dois sistemas que têm razões diferentes para falhar. Ao separar, fica claro: se a transação falha, é um problema de BD; se a autorização falha, é um problema de permissões.
