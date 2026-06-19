# Convenção — Padrão Dual de Testes

**Estabelecido em:** Issue #40 (Entidade feature slice)
**Âmbito:** todas as features com Actions em `app/Features/`

---

## Princípio

Cada Action deve ter testes em **dois locais distintos** com responsabilidades separadas.
Não são redundantes — cobrem dois contextos de invocação distintos.

```
tests/Unit/Features/<Feature>/<Nome>ActionTest.php   ← programático/interno
tests/Feature/Features/<Feature>/<Operação>Test.php  ← HTTP/externo
```

---

## `tests/Unit/Features/<Feature>/` — programático (sem HTTP)

**O que testa:** a Action directamente, fora do contexto HTTP.

**Porquê:** Actions são invocadas em Jobs, Events, Artisan e testes de integração — não só
via HTTP. Os testes unitários garantem que o comportamento da Action é correcto em qualquer
contexto de invocação.

**Como instanciar:**
```php
// Action sem dependências
$accao = new VerEntidadeAction();
$resultado = $accao->handle($entidade);

// Action com dependências — usar app() para injecção automática
$accao = app(CriarEntidadeAction::class);
$resultado = $accao->handle($dto);
```

**Cobertura obrigatória:**
- Happy path — devolve o valor esperado
- Ambos os overloads quando a assinatura é `Model|string` (objecto E UUID string)
- Rollback: usar model event (`created`, `saved`, `deleting`) para lançar excepção dentro da
  transação e verificar que o estado não foi alterado na BD
- Regras de negócio: ex. unicidade, invariantes de domínio
- 404: `findOrFail` com UUID inexistente → `ModelNotFoundException`

**Exemplo de teste de rollback:**
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

**O que testa:** o endpoint de fora, como um cliente da API.

**Porquê:** valida a integração completa — rota, FormRequest (validação + autorização),
Controller, Action e Resource. Prepara para quando a autenticação for implementada.

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

- **Enums** — PHP não aceita `final enum`
- **FormRequests não-final** — mockáveis em testes unitários de DTO (`fromRequest()`)
- **Traits** — PHP não aceita `final trait`
- **Actions internas mockáveis** — ex. `RemoverMarcacaoEmpresaMaeAction` (mockada em testes
  de `RegraUnicidadeEmpresaMae`)

---

## Referência cruzada

- Regras resumidas também em `CLAUDE.md` → secção "CONVENÇÕES DE TESTES"
- Exemplo de implementação: `tests/Unit/Features/Entidade/` e `tests/Feature/Features/Entidade/` (Issue #40)
