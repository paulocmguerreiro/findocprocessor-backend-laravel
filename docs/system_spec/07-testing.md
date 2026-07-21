# System Spec — Testing

> Padrão dual obrigatório por feature slice. Stack: Pest 4 + Mockery.

Cada Action tem testes em **dois locais distintos** com responsabilidades separadas. Não são redundantes — cobrem dois contextos de invocação diferentes.

```
tests/Unit/Features/<Feature>/<Nome>ActionTest.php   ← programático/interno
tests/Feature/Features/<Feature>/<Operação>Test.php  ← HTTP/externo
```

---

## BD partilhada — proibido reconstruir a meio de um teste

Todos os workers Pest em paralelo partilham a **mesma** BD MySQL (`findocprocessor_testing`); o
isolamento entre testes é o wrapper transaccional do `RefreshDatabase` (rollback no fim de cada teste),
**não** uma BD por worker.

**Nunca** usar o trait `DatabaseMigrations`, `RefreshDatabase` com `migrate:fresh`, nem chamar
`Artisan::call('migrate:fresh'|'migrate:refresh')` dentro de um teste ou do seu setup: largar/recriar
tabelas na BD partilhada parte os testes concorrentes de outros workers (flake não-determinístico).
Estado de esquema/dados incompatível com o wrapper transaccional isola-se por **interface + fake**
(substituir a dependência), nunca reconstruindo a BD.

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

**Campos populados via `whenLoaded()`:** todo o campo de um Resource que usa
`whenLoaded('relacao')` tem de ter, no teste do(s) endpoint(s) onde é suposto
aparecer, uma asserção do **conteúdo** do campo (não basta 200/estrutura genérica)
— só isso prova que o Controller/Action fez o eager-load (`->with()`/`->load()`)
necessário. `whenLoaded()` não lança excepção nem faz query quando a relação não
está carregada: devolve `MissingValue` e a chave desaparece do JSON em silêncio,
mesmo que o PHPDoc do Resource a declare como sempre presente.

Nos endpoints onde o campo é **deliberadamente omitido** (ex.: listagens leves
que não fazem eager-load de relações pesadas), o teste tem de assertar
explicitamente a **ausência** da chave — para distinguir "omissão deliberada,
coberta por teste" de "esquecimento não detectado".

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

**Listagens com cache:** flush da tag no `beforeEach` — dentro do **mesmo** processo paralelo, um
paginador serializado de um teste anterior rebenta noutro (`unserialize` de objecto incompleto → 500):

```php
beforeEach(fn () => Cache::tags(['documentos'])->flush());
```

**Isolamento entre processos paralelos:** `AppServiceProvider::boot()` regista
`ParallelTesting::setUpTestCase([self::class, 'isolarCacheParalelo'])`, guardado por
`runningUnitTests()`. `AppServiceProvider::isolarCacheParalelo(int $token)` salga
`config('cache.prefix')` com o token do processo (`AppServiceProvider::prefixoCacheParalelo()`) e chama
`Cache::purge('redis')` logo a seguir. Cada processo Pest passa a escrever/ler chaves Redis sob um
prefixo próprio — sem isto, `Cache::tags([...])->flush()` de um worker apaga chaves escritas por
outro antes da asserção correr (condição de corrida intermitente em CI). O `flush()` do `beforeEach`
acima continua necessário — isola entre testes do *mesmo* processo, papel distinto do prefixo por
processo.

**Nota sobre o hook usado:** o gancho é `ParallelTesting::setUpTestCase()`, não `setUpProcess()`
(o exemplo oficial da documentação Laravel). O Pest `--parallel` corre com o seu próprio
`WrapperRunner`, que nunca invoca `callSetUpProcessCallbacks()` — um callback registado via
`setUpProcess()` fica registado mas nunca executa nesta stack. `setUpTestCase()` é invocado
directamente por `InteractsWithTestCaseLifecycle` em cada teste, independente do runner usado.

**`APP_ENV=testing` tem de ser forçado na invocação do Pest, não só no `phpunit.xml`:**
o container `app` do `compose.yaml` define `APP_ENV: local` como variável de ambiente real do
processo (conveniência para `docker compose up` local). Essa variável popula `$_SERVER['APP_ENV']`
no arranque do PHP-CLI, e a leitura de ambiente do Laravel (`Illuminate\Support\Env`, via
`checkForSpecificEnvironmentFile()`/`detectEnvironment()`) lê `$_SERVER` — que o `<env>` do
`phpunit.xml` **não** consegue sobrepor, mesmo com `force="true"` (`force` só afecta `getenv()`/`$_ENV`,
nunca `$_SERVER`, que já está populado a partir do ambiente real do container antes do PHP arrancar).
Sem este prefixo, `config('app.env')` fica `'local'` durante toda a suite, `runningUnitTests()` fica
sempre `false`, e o `if` que regista `isolarCacheParalelo()` nunca entra — a isolação de cache entre
workers nunca dispara. Por isso os scripts `test:arch`, `test:type-coverage` e `test:coverage` do
`composer.json` prefixam `APP_ENV=testing` directamente na linha de comando (variável de shell real,
que sobrepõe a do container) — não basta o `<env>` do `phpunit.xml`. Qualquer novo script que corra
Pest fora destes três deve seguir o mesmo padrão, ou correr através de `composer test`/`composer
test:coverage`.

**Cobertura de closures registadas em hooks de framework:** código só alcançável através da
invocação indirecta de um hook (`ParallelTesting::setUpTestCase`, `Schedule::call`, etc.) não é
registado de forma fiável pelo `pcov` como coberto, mesmo quando executa de facto (confirmado por
instrumentação directa). Por isso `isolarCacheParalelo()` foi extraído para um método nomeado
`public static` — chamado directamente por um teste (`AppServiceProviderCacheIsolamentoTest.php`) —
em vez de viver dentro do closure inline. O registo em `setUpTestCase()` usa ainda um callable-array
(`[self::class, 'isolarCacheParalelo']`), não uma closure, porque o `pcov` também falha a atribuir
cobertura à *linha* de definição de uma closure passada como argumento quando invocada indirectamente
via `Container::call()` (reflexão) — o callable-array evita esse problema por não ser um objecto
`Closure` com bytecode próprio a rastrear.

---

## ArchTest — classes não-final a excluir

Em `tests/ArchTest.php`, adicionar ao `ignoring` do `arch('actions are final')`:

- **Enums** — PHP não aceita `final enum`.
- **FormRequests não-final** — mockáveis em testes unitários de DTO (`fromRequest()`).
- **Traits** — PHP não aceita `final trait`.
- **Actions internas mockáveis** — ex. `RemoverMarcacaoEmpresaMaeAction` (mockada em testes de regra de unicidade).

---

## Testes de concorrência real (duas conexões MySQL)

Primeiro padrão do género no projecto (`tests/Feature/Features/Documento/ReivindicarDocumentoPendenteConcorrenciaTest.php`),
usado para provar exclusão mútua de `lockForUpdate()` entre dois "workers" reais.

**Problema:** a conexão de teste por omissão (`mysql`) é embrulhada numa transacção pelo
`RefreshDatabase` (`connectionsToTransact()` só inclui `database.default`) que **nunca é commitada
de facto** — é sempre revertida no fim do teste. Se se usar essa conexão como um dos dois "workers",
`beginTransaction()`/`commit()` explícitos dentro do teste tornam-se `SAVEPOINT`s aninhados: o
`commit()` não liberta o lock ao nível do motor, e o teste nunca observa a segunda conexão a
desbloquear.

**Padrão:** clonar a config da conexão `mysql` (já com o sufixo `_test_N` do paralelo aplicado) para
duas conexões novas em runtime, **nenhuma delas `mysql`**:

```php
config([
    'database.connections.mysql_teste_concorrente_a' => config('database.connections.mysql'),
    'database.connections.mysql_teste_concorrente_b' => config('database.connections.mysql'),
]);
DB::purge('mysql_teste_concorrente_a');
DB::purge('mysql_teste_concorrente_b');

$conexaoA = DB::connection('mysql_teste_concorrente_a');
$conexaoB = DB::connection('mysql_teste_concorrente_b');
```

Como nenhuma das duas está em `connectionsToTransact()`, `beginTransaction()`/`commit()` são reais.

**Dados de teste:** o registo tem de ser criado numa das conexões não-embrulhadas (ex.:
`$model->setConnection('mysql_teste_concorrente_a')->save()`), nunca via factory na conexão por
omissão — um registo criado dentro da transacção nunca commitada de `mysql` é invisível a outras
conexões (isolamento transaccional). Cuidado com FKs: relações resolvidas via `Model::factory()`
(ex.: `id_responsavel: User::factory()`) resolvem sempre na conexão por omissão — se ficarem por
commitar, bloqueiam o `FK check` da tabela dependente (`lock wait timeout`) na conexão isolada.
Definir esses campos a `null` (se nullable) evita a dependência.

**Limpeza:** dados criados fora do wrapper do `RefreshDatabase` não são revertidos automaticamente —
apagar manualmente no fim do teste (`try`/`finally`).

**Confirmar exclusão mútua:** `SET SESSION innodb_lock_wait_timeout = 1` na conexão B antes de
tentar `lockForUpdate()` sobre a mesma linha que A já bloqueou (sem commit) — B lança
`QueryException` (lock wait timeout). Depois do `commit()` de A, B consegue obter o lock.

---

## Pipeline de qualidade

100% code coverage e 100% type coverage exigidos. Executar `composer test` (corre preflight + lint + arch + types + type-coverage + coverage em MySQL) antes de finalizar qualquer alteração. A suite corre exclusivamente em MySQL (`findocprocessor_testing`) — requer Docker a correr (`docker compose up -d`). Convenções de teste resumidas também em `CLAUDE.md`.
