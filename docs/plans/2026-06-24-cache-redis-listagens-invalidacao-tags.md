# Plano — Issue #38: Cache Redis — Listagens e Queries Frequentes

**Data:** 2026-06-24  
**Branch:** feat/cache-redis-listagens-invalidacao-tags

---

## T1 — Infra: 3 enums + CacheServico

Criar 4 ficheiros novos em `app/Shared/Cache/`:

**`TagCache.php`**
```php
<?php

declare(strict_types=1);

namespace App\Shared\Cache;

enum TagCache: string
{
    case Entidades           = 'entidades';
    case CategoriasDocumento = 'categorias_documento';
    case Roles               = 'roles';
}
```

**`TagOperacao.php`**
```php
<?php

declare(strict_types=1);

namespace App\Shared\Cache;

enum TagOperacao: string
{
    case Ver      = 'ver';
    case Listar   = 'listar';
    case Exportar = 'exportar';
}
```

**`TtlCache.php`**
```php
<?php

declare(strict_types=1);

namespace App\Shared\Cache;

enum TtlCache: int
{
    case Listagem = 30;
    case Registo  = 300;
    case Longo    = 3600;
}
```

**`CacheServico.php`**
```php
<?php

declare(strict_types=1);

namespace App\Shared\Cache;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class CacheServico
{
    /**
     * @param array<string, int|string|null> $params
     */
    public function criarChave(
        TagCache $tag,
        TagOperacao $operacao,
        array $params = [],
        ?User $utilizador = null,
    ): string {
        ksort($params);
        $hash = md5(http_build_query($params));

        if ($utilizador !== null) {
            return "{$tag->value}:utilizador:{$utilizador->id}:{$operacao->value}:{$hash}";
        }

        return "{$tag->value}:{$operacao->value}:{$hash}";
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function lembrar(
        TagCache $tag,
        string $chave,
        TtlCache $ttl,
        callable $callback,
        ?User $utilizador = null,
    ): mixed {
        $tags = [$tag->value];

        if ($utilizador !== null) {
            $tags[] = "{$tag->value}:utilizador:{$utilizador->id}";
        }

        return Cache::tags($tags)->remember($chave, $ttl->value, $callback);
    }

    public function invalidarCache(TagCache $tag, ?User $utilizador = null): void
    {
        if ($utilizador !== null) {
            Cache::tags(["{$tag->value}:utilizador:{$utilizador->id}"])->flush();

            return;
        }

        Cache::tags([$tag->value])->flush();
    }
}
```

✔ `composer lint && composer refactor`

---

## T2 — Config: `config/cache.php` e `.env`

**`config/cache.php`** — duas alterações:

1. Default `database` → `redis`:
```php
'default' => env('CACHE_STORE', 'redis'),
```

2. Adicionar `serializable_classes` (após a chave `prefix`):
```php
'serializable_classes' => [
    App\Models\Entidade::class,
    App\Models\CategoriaDocumento::class,
    Illuminate\Pagination\CursorPaginator::class,
    Illuminate\Support\Collection::class,
],
```

**`.env`** — verificar/adicionar:
```dotenv
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_CACHE_DB=1
```

**`.env.example`** — adicionar as mesmas variáveis com nota sobre `REDIS_PASSWORD` em produção.

✔ `composer lint`

---

## T3 — Feature Entidade: Actions de leitura

**`ListarEntidadesAction`** — mudar de `final class` para `final readonly class`, adicionar construtor:

```php
final readonly class ListarEntidadesAction
{
    public function __construct(private CacheServico $cache) {}

    public function handle(int $porPagina, CampoOrdenacaoEntidades $campo, DirecaoOrdenacao $direcao): CursorPaginator
    {
        Gate::authorize('viewAny', Entidade::class);

        $cursor = request()->string('cursor')->value() ?: null;

        $chave = $this->cache->criarChave(
            TagCache::Entidades,
            TagOperacao::Listar,
            ['campo' => $campo->value, 'cursor' => $cursor, 'direcao' => $direcao->value, 'por_pagina' => $porPagina],
        );

        /** @var CursorPaginator<int, Entidade> $resultado */
        $resultado = $this->cache->lembrar(
            TagCache::Entidades,
            $chave,
            TtlCache::Listagem,
            fn (): CursorPaginator => Entidade::orderBy($campo->value, $direcao->value)->cursorPaginate($porPagina),
        );

        return $resultado;
    }
}
```

**`VerEntidadeAction`** — já é `final class`, adicionar construtor:

```php
final readonly class VerEntidadeAction
{
    public function __construct(private CacheServico $cache) {}

    public function handle(Entidade|string $idEntidade): Entidade
    {
        /** @var Entidade $entidade */
        $entidade = is_string($idEntidade) ? Entidade::findOrFail($idEntidade) : $idEntidade;

        Gate::authorize('view', $entidade);

        $chave = $this->cache->criarChave(TagCache::Entidades, TagOperacao::Ver, ['id' => $entidade->id]);

        /** @var Entidade $resultado */
        $resultado = $this->cache->lembrar(
            TagCache::Entidades,
            $chave,
            TtlCache::Registo,
            fn (): Entidade => $entidade,
        );

        return $resultado;
    }
}
```

✔ `composer lint && composer refactor`

---

## T4 — Feature Entidade: Actions de escrita

Adicionar `CacheServico $cache` ao construtor de cada Action e chamar `invalidarCache()` **dentro de `DB::transaction()`**, antes do `return`.

**`CriarEntidadeAction`** — já tem construtor (`RegraUnicidadeEmpresaMae`), acrescentar:
```php
public function __construct(
    private RegraUnicidadeEmpresaMae $regraUnicidade,
    private CacheServico $cache,
) {}

// Dentro de DB::transaction(), antes do return:
$this->cache->invalidarCache(TagCache::Entidades);
```

**`ActualizarEntidadeAction`** — mesmo padrão.

**`EliminarEntidadeAction`** — sem construtor ainda, adicionar:
```php
public function __construct(private CacheServico $cache) {}

// Dentro de DB::transaction():
DB::transaction(function () use ($entidade): void {
    $entidade->delete();
    $this->cache->invalidarCache(TagCache::Entidades);
});
```

**`ConverterEmEmpresaMaeAction`** — já tem construtor (`RegraUnicidadeEmpresaMae`), acrescentar `CacheServico`. Invalidar após `$entidade->refresh()`.

✔ `composer lint && composer refactor`

---

## T5 — Feature CategoriaDocumento: Actions de leitura

**`ListarCategoriasAction`** — adicionar construtor e encapsular query:
```php
final readonly class ListarCategoriasAction
{
    public function __construct(private CacheServico $cache) {}

    public function handle(int $perPage, CampoOrdenacaoCategorias $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator
    {
        Gate::authorize('viewAny', CategoriaDocumento::class);

        $cursor = request()->string('cursor')->value() ?: null;

        $chave = $this->cache->criarChave(
            TagCache::CategoriasDocumento,
            TagOperacao::Listar,
            ['campo' => $campoOrdenacao->value, 'cursor' => $cursor, 'direcao' => $direcaoOrdenacao->value, 'por_pagina' => $perPage],
        );

        /** @var CursorPaginator<int, CategoriaDocumento> $resultado */
        $resultado = $this->cache->lembrar(
            TagCache::CategoriasDocumento,
            $chave,
            TtlCache::Listagem,
            fn (): CursorPaginator => CategoriaDocumento::orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)->cursorPaginate($perPage),
        );

        return $resultado;
    }
}
```

**`VerCategoriaAction`** — mesmo padrão com `TagCache::CategoriasDocumento` e `TtlCache::Registo`.

✔ `composer lint && composer refactor`

---

## T6 — Feature CategoriaDocumento: Actions de escrita

**`CriarCategoriaAction`**, **`ActualizarCategoriaAction`**, **`EliminarCategoriaAction`** — adicionar construtor e `invalidarCache(TagCache::CategoriasDocumento)` dentro da transação.

✔ `composer lint && composer refactor`

---

## T7 — ArchTest: verificar regras

Abrir `tests/ArchTest.php` e verificar:
- Se existe regra `are final` a apontar para `app/Shared/` — verificar se enums precisam de ser excluídos
- `CacheServico` é `final` → passa automaticamente
- Se existir regra de namespace ou herança que afecte `app/Shared/Cache/`, ajustar `ignoring()`

✔ `composer test:arch`

---

## T8 — Testes Unit: `CacheServico` isolado

Criar `tests/Unit/Shared/Cache/CacheServicoTest.php`:

```php
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Cache\TtlCache;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('gera a mesma chave independentemente da ordem dos params', function (): void {
    $servico = new CacheServico();

    $chave1 = $servico->criarChave(TagCache::Entidades, TagOperacao::Listar, ['b' => '2', 'a' => '1']);
    $chave2 = $servico->criarChave(TagCache::Entidades, TagOperacao::Listar, ['a' => '1', 'b' => '2']);

    expect($chave1)->toBe($chave2);
});

it('inclui o id do utilizador na chave quando fornecido', function (): void {
    $utilizador = User::factory()->create();
    $servico = new CacheServico();

    $chave = $servico->criarChave(TagCache::Entidades, TagOperacao::Ver, ['id' => 'x'], $utilizador);

    expect($chave)->toContain("utilizador:{$utilizador->id}");
});

it('executa callback no miss e devolve valor cacheado no hit', function (): void {
    $servico = new CacheServico();
    $chave = $servico->criarChave(TagCache::Entidades, TagOperacao::Ver, ['id' => 'abc']);
    $contador = 0;

    $servico->lembrar(TagCache::Entidades, $chave, TtlCache::Registo, function () use (&$contador): string {
        $contador++;
        return 'valor';
    });

    $resultado = $servico->lembrar(TagCache::Entidades, $chave, TtlCache::Registo, function () use (&$contador): string {
        $contador++;
        return 'valor';
    });

    expect($contador)->toBe(1)->and($resultado)->toBe('valor');
});

it('invalida cache — callback volta a ser executado após flush', function (): void {
    $servico = new CacheServico();
    $chave = $servico->criarChave(TagCache::Entidades, TagOperacao::Listar, []);
    $contador = 0;
    $cb = function () use (&$contador): int { return ++$contador; };

    $servico->lembrar(TagCache::Entidades, $chave, TtlCache::Listagem, $cb);
    $servico->invalidarCache(TagCache::Entidades);
    $servico->lembrar(TagCache::Entidades, $chave, TtlCache::Listagem, $cb);

    expect($contador)->toBe(2);
});
```

---

## T9 — Testes Unit: Actions modificadas (Entidade)

Nos ficheiros Unit existentes de cada Action de Entidade, adicionar cenários de cache:

**`ListarEntidadesActionTest`** — mock de `CacheServico`:
```php
it('delega a listagem ao CacheServico', function (): void {
    $cacheMock = Mockery::mock(CacheServico::class);
    $cacheMock->shouldReceive('criarChave')->once()->andReturn('chave-teste');
    $cacheMock->shouldReceive('lembrar')
        ->once()
        ->with(TagCache::Entidades, 'chave-teste', TtlCache::Listagem, Mockery::type('callable'), null)
        ->andReturn(new CursorPaginator([], 15, null));

    $accao = new ListarEntidadesAction($cacheMock);
    $accao->handle(15, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc);
});
```

**Actions de escrita** — verificar que `invalidarCache()` é chamado:
```php
it('invalida cache após criar entidade', function (): void {
    $cacheMock = Mockery::mock(CacheServico::class);
    $cacheMock->shouldReceive('invalidarCache')
        ->once()
        ->with(TagCache::Entidades, null);

    app()->instance(CacheServico::class, $cacheMock);
    app(CriarEntidadeAction::class)->handle($dto);
});
```

---

## T10 — Testes Unit: Actions modificadas (CategoriaDocumento)

Mesmo padrão do T9 para `ListarCategoriasAction`, `VerCategoriaAction` e as 3 Actions de escrita.

---

## T11 — Testes Feature: invalidação end-to-end

Nos ficheiros Feature existentes de Entidade e CategoriaDocumento, adicionar:

```php
it('invalida cache após criar entidade', function (): void {
    $this->getJson('/api/entidades')->assertOk();  // popula cache

    $this->postJson('/api/entidades', [
        'nome' => 'Nova', 'nif' => '123456789',
        'e_cliente' => true, 'e_fornecedor' => false, 'e_empresa_aplicacao' => false,
    ])->assertCreated();

    $resposta = $this->getJson('/api/entidades')->assertOk();
    expect($resposta->json('data'))->toHaveCount(1);  // cache invalida, nova entidade visível
});
```

---

## T12 — system_spec + pipeline

1. Actualizar `docs/system_spec/04-infra/cache.md` — substituir placeholder pelo design implementado (3 enums + CacheServico, padrões de chave, TTLs, invalidação)
2. Actualizar `docs/system_spec/06-config.md` — adicionar `CACHE_STORE`, `REDIS_CLIENT`, `REDIS_CACHE_DB`
3. Actualizar `docs/system_spec/00-index.md` — marcar `Cache / Redis` como `implementado`

✔ `composer test` — pipeline completa (lint + arch + types + type-coverage + coverage)

---

## Ordem de execução

```
T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8 → T9 → T10 → T11 → T12
```

T7 (ArchTest) pode ser verificado após T1 sem esperar pelas Actions.  
T8–T11 (testes) após cada tarefa de código correspondente.
