# System Spec — Infra: Transações de BD

> Padrão obrigatório (Issue #34). Ver também `CLAUDE.md` — Padrões obrigatórios.

Todas as Actions de escrita envolvem a persistência em `DB::transaction()`. Autorização (`Gate::authorize()`) fica fora da transação.

---

## Padrão canónico

```php
Gate::authorize('create', Xxx::class);                        // fora — autorização

return DB::transaction(fn (): Xxx => Xxx::create([...]));     // dentro — persistência
```

Para Actions com múltiplas operações:

```php
Gate::authorize('update', $xxx);

return DB::transaction(function () use ($xxx, $dados): Xxx {
    $xxx->fill([...])->save();
    $xxx->refresh();
    return $xxx;
});
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

## Nota Jobs

Jobs disparados dentro de transações devem usar `after_commit: true` na config da queue ou implementar `ShouldDispatchAfterCommit` para evitar processamento antes do commit.
