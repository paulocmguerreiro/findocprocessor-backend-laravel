# Brief — Issue #34: Transações de BD nas Actions de escrita

**Data:** 2026-06-18
**Issue:** #34
**Slug:** `transacoes-bd-actions`
**Branch:** `chore/transacoes-bd-actions`

---

## Contexto

As Actions de escrita (`Criar`, `Actualizar`, `Eliminar`) nas features `CategoriaDocumento` e `Entidade` não estão protegidas por `DB::transaction()`. Em caso de falha a meio de uma operação composta (ex: insert + disparo de evento + segunda query), a BD pode ficar em estado inconsistente.

A feature `Entidade` ainda não tem Actions implementadas — serão criadas na issue seguinte (camada lógica). Esta issue:
1. Aplica o padrão às 3 Actions existentes de `CategoriaDocumento`
2. Documenta o padrão no `CLAUDE.md` para que Actions futuras (incluindo `Entidade`) o sigam desde o início

---

## Estado actual das Actions

| Action | Operações de BD | Risco actual |
|---|---|---|
| `CriarCategoriaAction` | `CategoriaDocumento::create()` | Baixo — operação única |
| `ActualizarCategoriaAction` | `findOrFail()` + `fill()->save()` + `refresh()` | Médio — `save` e `refresh` são sequenciais |
| `EliminarCategoriaAction` | `findOrFail()` + `delete()` | Baixo — operação única |

Mesmo em operações simples, a transação protege contra falhas inesperadas no código posterior ao `save()` (ex: eventos, observers futuros).

---

## Decisão arquitectural: Gate::authorize() dentro ou fora da transação?

**Fora** — autorização não é uma operação de BD no contexto actual (Policies usam `return true`). Abrir uma transação para depois falhar na autorização é desnecessário.

**Padrão adoptado:**
```php
public function handle(CriarCategoriaDto $dados): CategoriaDocumento
{
    Gate::authorize('create', CategoriaDocumento::class);  // fora da transação

    return DB::transaction(fn() => CategoriaDocumento::create([
        'nome' => $dados->nome,
        'slug' => $dados->slug,
        'tipo_movimento' => $dados->tipoMovimento,
    ]));
}
```

**@throws a adicionar:** `DB::transaction()` re-lança excepções automaticamente — adicionar `@throws \Throwable` ao PHPDoc dos `handle()`.

---

## Padrão de teste para rollback

Como as Actions actuais são maioritariamente single-operation, a estratégia de teste é:
- Usar `Model::created()` hook (que dispara após o INSERT mas antes do commit) para lançar excepção
- Verificar que o registo não foi persistido (`assertDatabaseCount`)

```php
it('faz rollback quando ocorre excepção após insert', function (): void {
    CategoriaDocumento::created(function (): void {
        throw new \RuntimeException('falha simulada');
    });

    expect(fn() => (new CriarCategoriaAction)->handle($dto))
        ->toThrow(\RuntimeException::class);

    $this->assertDatabaseCount('categorias_documento', 0);
});
```

Para `ActualizarCategoriaAction`, o teste mais realista é lançar excepção no `saved` event e verificar que os dados originais são preservados.

---

## Riscos identificados

- **`@throws \Throwable`** vs `@throws \Exception`: `DB::transaction()` pode lançar `\Throwable` (inclui `Error`). Usar `\Throwable` no PHPDoc.
- **Jobs dentro de transações:** não há Jobs neste scope. Quando existirem, usar `after_commit: true` na configuração da queue.
- **Observers futuros:** se um Observer for adicionado a `CategoriaDocumento` e implementar `ShouldHandleEventsAfterCommit`, o comportamento é automático com transações activas.
- **SQLite em testes:** `DB::transaction()` funciona correctamente com SQLite in-memory — validado pela documentação Laravel 13.

---

## Questões em aberto

- Nenhuma — padrão simples e bem documentado no Laravel 13.

---

## Fora de âmbito

- Actions de `Entidade` — serão criadas na issue seguinte já com o padrão aplicado
- Configuração `after_commit` na queue
- Saga pattern para Jobs assíncronos
