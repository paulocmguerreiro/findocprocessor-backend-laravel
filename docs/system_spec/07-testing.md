# System Spec — Testing

> Padrão dual obrigatório por feature slice. Stack: Pest 4 + Mockery. Estabelecido na Issue #40.

Cada Action tem testes em **dois locais distintos** com responsabilidades separadas. Não são redundantes — cobrem dois contextos de invocação diferentes.

```
tests/Unit/Features/<Feature>/<Nome>ActionTest.php   ← programático/interno
tests/Feature/Features/<Feature>/<Operação>Test.php  ← HTTP/externo
```

---

## `tests/Unit/Features/<Feature>/` — programático (sem HTTP)

**O que testa:** a Action directamente, fora do contexto HTTP — porque as Actions são invocadas em Jobs, Events, Artisan e testes de integração, não só via HTTP.

**Como instanciar:**
```php
// Action sem dependências
$resultado = (new VerEntidadeAction())->handle($entidade);

// Action com dependências — app() para injecção automática
$resultado = app(CriarEntidadeAction::class)->handle($dto);
```

**Cobertura obrigatória:**
- Happy path — devolve o valor esperado.
- Ambos os overloads quando a assinatura é `Model|string` (objecto **e** UUID string).
- Rollback: model event (`creating`, `saved`, `deleting`) lança excepção dentro da transação e o teste verifica que o estado não foi alterado na BD.
- Regras de negócio (ex: unicidade de Empresa Mãe, invariantes de domínio).
- 404: `findOrFail` com UUID inexistente → `ModelNotFoundException`.

**Exemplo de rollback:**
```php
it('faz rollback se falhar a meio', function (): void {
    Entidade::creating(fn () => throw new \RuntimeException('falha simulada'));

    expect(fn () => app(CriarEntidadeAction::class)->handle($dto))
        ->toThrow(\RuntimeException::class);

    expect(Entidade::count())->toBe(0);
});
```

---

## `tests/Feature/Features/<Feature>/` — HTTP (sem acesso directo à Action)

**O que testa:** o endpoint de fora, como um cliente da API. Valida a integração completa — rota, FormRequest (validação + autorização), Controller, Action e Resource.

**Regra:** **nunca** chamar Actions directamente nestes ficheiros.

**Cobertura obrigatória por endpoint:**

| Endpoint | Cenários mínimos |
|---|---|
| `GET /api/...` (listar) | lista vazia, estrutura correcta, `per_page`, cursor sem duplicados, 422 `per_page>100`, 422 `sort` inválido |
| `POST /api/...` (criar) | 201 com recurso, 422 campos obrigatórios em falta, guest pode criar (enquanto Policy retorna `true`) |
| `GET /api/.../{id}` (ver) | 200 com recurso, 404 UUID inexistente |
| `PUT /api/.../{id}` (actualizar) | 200 actualizado, 404, 422 campos obrigatórios em falta |
| `DELETE /api/.../{id}` (eliminar) | 204, 404 |
| Endpoints especiais | happy path + 404 |

---

## Estrutura de ficheiros por feature slice completa

```
tests/
  Unit/Features/<Feature>/
    CriarXxxActionTest.php
    VerXxxActionTest.php
    ActualizarXxxActionTest.php
    EliminarXxxActionTest.php
    ListarXxxActionTest.php
    [OutrasActionTest.php — uma por Action interna relevante]
  Feature/Features/<Feature>/
    CriarXxxTest.php         ← POST /api/...
    ListarXxxTest.php        ← GET /api/...
    VerXxxTest.php           ← GET /api/.../{id}
    ActualizarXxxTest.php    ← PUT/PATCH /api/.../{id}
    EliminarXxxTest.php      ← DELETE /api/.../{id}
    [EndpointEspecialTest.php — um por endpoint extra]
```

---

## ArchTest — classes não-final a excluir

Em `tests/ArchTest.php`, adicionar ao `ignoring` do `arch('actions are final')`:

- **Enums** — PHP não aceita `final enum`.
- **FormRequests não-final** — mockáveis em testes unitários de DTO (`fromRequest()`).
- **Traits** — PHP não aceita `final trait`.
- **Actions internas mockáveis** — ex. `RemoverMarcacaoEmpresaMaeAction` (mockada em testes de regra de unicidade).

---

## Pipeline de qualidade

100% code coverage e 100% type coverage exigidos. Executar `composer test` (corre lint, arch, types, type-coverage e coverage) antes de finalizar qualquer alteração. Convenções de teste resumidas também em `CLAUDE.md` e em `docs/conventions/tests-dual-pattern.md`.
