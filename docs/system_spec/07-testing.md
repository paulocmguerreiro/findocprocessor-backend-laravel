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
- Autorização (Actions com `Gate::authorize`): utilizador sem a permissão **e** guest → `AuthorizationException` (ver "Matriz de autorização obrigatória").

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

| Endpoint | Cenários mínimos | Autorização (ver matriz) |
|---|---|---|
| `GET /api/...` (listar) | lista vazia, estrutura correcta, `per_page`, cursor sem duplicados, 422 `per_page>100`, 422 `sort` inválido | utilizador COM leitura → 200; utilizador SEM leitura → 403; guest → 401 |
| `POST /api/...` (criar) | 201 com recurso, 422 campos obrigatórios em falta | utilizador sem permissão → 403; guest → 401 |
| `GET /api/.../{id}` (ver) | 200 com recurso, 404 UUID inexistente | utilizador COM leitura → 200; utilizador SEM leitura → 403; guest → 401 |
| `PUT/PATCH /api/.../{id}` (actualizar) | 200 actualizado, 404, 422 campos obrigatórios em falta | utilizador sem permissão → 403; guest → 401 |
| `DELETE /api/.../{id}` (eliminar) | 204, 404 | utilizador sem permissão → 403; guest → 401 |
| Endpoints especiais | happy path + 404 | conforme a ability exigida |

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

## Helpers globais de autenticação

Definidos em `tests/Pest.php`. Disponíveis em todos os ficheiros de teste (Unit e Feature).

| Helper | O que faz | Quando usar |
|---|---|---|
| `criarAdmin(): User` | Cria utilizador com role `admin` | Unit tests — `$this->actingAs(criarAdmin())` |
| `criarUtilizador(): User` | Cria utilizador com role `utilizador` | Unit tests — `$this->actingAs(criarUtilizador())` |
| `criarEAutenticarAdmin(): User` | Cria admin + `Sanctum::actingAs($u, ['api'])` | Feature tests — `beforeEach(fn(): User => criarEAutenticarAdmin())` |
| `criarEAutenticarUtilizador(): User` | Cria utilizador + `Sanctum::actingAs($u, ['api'])` | Feature tests — testes 403 fora do `describe` |
| `criarEAutenticarSemRole(): User` | Cria utilizador **sem role** + `Sanctum::actingAs($u, ['api'])` | Feature tests — estado "SEM permissão → 403" nas **leituras** (`utilizador` tem `*.ver`, logo o 403 de leitura exige um actor sem role) |

O `beforeEach` global em `Pest.php` invoca `forgetCachedPermissions()` antes de cada teste — não é necessário repeti-lo nos ficheiros individuais.

### Padrão `describe()` por role — Unit tests

```php
describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('happy path...', function (): void { ... });
    it('rollback...', function (): void { ... });
});

describe('sem permissão de escrita', function (): void {   // ou 'leitura' para Ver/Listar
    beforeEach(fn () => $this->actingAs(criarUtilizador())); // ou User::factory()->create() se sem role
    it('lança AuthorizationException...', function (): void { ... });
});
```

Ordem obrigatória: `'como admin'` primeiro, `'sem permissão'` depois.

Para actions sem Gate (ex: `RemoverMarcacaoEmpresaMaeAction`) — não usar `describe()`, sem setup de autenticação.

### Padrão `describe()` por role — Feature tests

```php
describe('autenticado', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());
    // testes happy path e validações — sem alteração
});

it('utilizador sem permissão recebe 403', function (): void {
    criarEAutenticarUtilizador();
    $this->postJson(...)->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $this->postJson(...)->assertUnauthorized();
});
```

Para endpoints com acesso de leitura ao role `utilizador`, usar `criarEAutenticarUtilizador()` no teste 200.

---

## Matriz de autorização obrigatória (3 estados)

A autorização não tem "papéis" como actores. `admin` e `utilizador` são apenas **configurações de permissões** (conjuntos de abilities) e só existem como actor quando há login efectuado. O que distingue o resultado de uma operação são **três estados**, definidos pela relação entre o utilizador e a **permissão daquela operação**:

| Estado | Como reproduzir | HTTP | Action (`Gate::authorize`) |
|---|---|---|---|
| **Sem autenticação** (guest) | sem token (HTTP) / `auth()->logout()` (Action) | **401** | `AuthorizationException` |
| **Autenticado COM a permissão** | utilizador cuja config de permissões inclui a ability da operação | **2xx** (happy path) | executa |
| **Autenticado SEM a permissão** | utilizador cuja config **não** inclui a ability da operação | **403** | `AuthorizationException` |

Pontos a reter:

- **`admin` e `utilizador` não são actores distintos** — são configs de permissões. O *mesmo* `utilizador` está "COM a permissão" numa leitura (`documentos.ver`) → 200, e "SEM a permissão" numa escrita → 403. O estado depende da **operação**, não da identidade. Por isso o teste escolhe a config que produz cada estado: hoje `admin` materializa "COM" em tudo; `utilizador` materializa "COM" nas leituras e "SEM" nas escritas; um utilizador sem role nenhuma materializa "SEM" até nas leituras.
- **Guest não acede a nada da API exceto `login`.** `POST /api/auth/login` é a única rota pública; todas as outras exigem token e devolvem **401** sem ele. O teste de guest confirma exactamente isso.
- **As duas camadas (HTTP e Action)** cobrem-se independentemente — a dupla camada de autorização exige testar ambas. Na camada Action não existe "401" (não há HTTP): tanto o guest como o autenticado-sem-permissão resultam em `AuthorizationException`.
- **Actions de sistema (background, sem `Gate`)** ficam **fora** desta matriz — não há autorização a testar. Correm sem utilizador autenticado; o teste verifica que executam sem login e que a `EtapaDocumento` fica como passo de sistema (`id_utilizador = null`). Ex.: as transições `Marcar*` do Documento (ver `02-shared/padroes-acoes.md`).

> **Falha sempre que falte autorização real.** Se a Policy devolver `true` incondicionalmente, os estados "sem permissão" e "guest" passam por engano e a lacuna fica mascarada. A Policy tem de usar `hasPermissionTo(...)` — ver checklist em `04-infra/autorizacao.md`.

**Distinção 403 vs 401:** `403` é utilizador autenticado **sem** a permissão (a Policy nega); `401` é ausência de autenticação (middleware Sanctum bloqueia antes da Policy). Na camada Action o guest dá `AuthorizationException` (não 401) — o primeiro parâmetro do método de Policy ser `User` (não `?User`) faz o Laravel negar guests automaticamente.

**Listagens com cache:** flush da tag no `beforeEach` — `CACHE_STORE=redis` nos testes **não isola entre testes**, e um paginador serializado de um teste anterior rebenta noutro (`unserialize` de objecto incompleto → 500):

```php
beforeEach(fn () => Cache::tags(['documentos'])->flush());
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

100% code coverage e 100% type coverage exigidos. Executar `composer test` (corre preflight + lint + arch + types + type-coverage + coverage em MySQL) antes de finalizar qualquer alteração. A suite corre exclusivamente em MySQL (`findocprocessor_testing`) — requer Docker a correr (`docker compose up -d`). Convenções de teste resumidas também em `CLAUDE.md` e em `docs/conventions/tests-dual-pattern.md`.
