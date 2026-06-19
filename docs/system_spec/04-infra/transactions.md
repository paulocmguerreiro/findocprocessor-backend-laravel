# System Spec — Infra: Transações de BD

> Padrão obrigatório (Issue #34). Ver também `CLAUDE.md` — Padrões obrigatórios.

Todas as Actions de escrita (criar, actualizar, eliminar) envolvem a persistência em `DB::transaction()`. Autorização (`Gate::authorize()`) fica **fora** da transação — autorização não é operação de BD. A persistência fica **dentro**.

`DB::transaction()` faz rollback e **re-lança automaticamente** qualquer `\Throwable` levantado dentro do callback. Por isso:

- O `handle()` da Action declara `@throws \Throwable` no PHPDoc — **obrigatório** sempre que houver `DB::transaction()`. Os callers ficam informados estaticamente (IDE + Larastan) de que a operação pode propagar excepções.

---

## Padrão canónico

```php
/**
 * @throws \Throwable
 */
public function handle(CriarXxxDto $dados): Xxx
{
    Gate::authorize('create', Xxx::class);                        // fora — autorização

    return DB::transaction(fn (): Xxx => Xxx::create([...]));     // dentro — persistência
}
```

Para Actions com múltiplas operações:

```php
/**
 * @throws \Throwable
 */
public function handle(Xxx $xxx, ActualizarXxxDto $dados): Xxx
{
    Gate::authorize('update', $xxx);

    return DB::transaction(function () use ($xxx, $dados): Xxx {
        $xxx->fill([...])->save();
        $xxx->refresh();

        return $xxx;
    });
}
```

---

## Actions que implementam este padrão

| Action | Feature |
|---|---|
| `CriarCategoriaAction` | `CategoriaDocumento/Criar` |
| `ActualizarCategoriaAction` | `CategoriaDocumento/Actualizar` |
| `EliminarCategoriaAction` | `CategoriaDocumento/Eliminar` |
| `CriarEntidadeAction` | `Entidade/Criar` |
| `ActualizarEntidadeAction` | `Entidade/Actualizar` |
| `EliminarEntidadeAction` | `Entidade/Eliminar` |
| `ConverterEmEmpresaMaeAction` | `Entidade/EmpresaMae` |

Todas as Actions de escrita futuras seguem este padrão obrigatoriamente.

---

## Nota Jobs — `ShouldDispatchAfterCommit`

Jobs disparados **dentro** de uma transação não podem ser processados pelo worker antes de o commit concluir — caso contrário a queue processa o Job sobre dados ainda não persistidos (ou que sofrem rollback).

Duas formas de garantir o despacho só após commit:

| Mecanismo | Âmbito | Como |
|---|---|---|
| `after_commit: true` | Global por connection de queue | `config/queue.php` → `'connections' => ['<conn>' => ['after_commit' => true]]` |
| `ShouldDispatchAfterCommit` | Por Job individual | `final class XxxJob implements ShouldQueue, ShouldDispatchAfterCommit` |

A interface por Job tem precedência e é preferível quando só alguns Jobs precisam deste comportamento.
