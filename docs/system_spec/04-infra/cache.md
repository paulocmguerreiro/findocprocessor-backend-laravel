# System Spec — Infra: Cache / Redis

> `app/Shared/Cache/`

## Visão geral

Cache Redis com tags para listagens e registos individuais. Invalidação explícita nas Actions de escrita. Driver: `predis/predis` (sem extensão PHP).

---

## Infra partilhada — `App\Shared\Cache\`

### `TagCache` (enum string)

Identifica o domínio cacheado. Uma tag por feature.

| Caso | Valor | Usado em |
|---|---|---|
| `Entidades` | `'entidades'` | Feature Entidade |
| `CategoriasDocumento` | `'categorias_documento'` | Feature CategoriaDocumento |
| `Documentos` | `'documentos'` | Feature Documento |
| `Roles` | `'roles'` | Feature Role (futuro) |

### `TagOperacao` (enum string)

Tipo de operação de leitura.

| Caso | Valor |
|---|---|
| `Ver` | `'ver'` |
| `Listar` | `'listar'` |

### `TtlCache` (enum int — segundos)

| Caso | Valor | Uso típico |
|---|---|---|
| `Curta` | 30 | Listagens e paginação |
| `Media` | 300 | Registos individuais |
| `Longa` | 3600 | Dados transversais |
| `Alargada` | 86400 | Relatórios (invalidação manual) |

### `CacheServico` (final class)

Serviço injectável que encapsula `Cache::tags()`. Três métodos públicos:

```php
criarChave(TagCache, TagOperacao, array $params = [], ?User $utilizador = null): string
lembrar(TagCache, string $chave, TtlCache, callable $callback, ?User $utilizador = null): mixed
invalidarCache(TagCache, ?User $utilizador = null): void
```

**Formato das chaves:**
- Sem utilizador: `{tag}:{operacao}:{sha256(params)}`
- Com utilizador: `{tag}:utilizador:{id}:{operacao}:{sha256(params)}`

Os `$params` são ordenados por `ksort()` antes do hash — a chave é determinística independentemente da ordem de inserção.

---

## Padrão nas Actions de leitura

```php
final readonly class ListarEntidadesAction
{
    public function __construct(private CacheServico $cache) {}

    public function handle(...): CursorPaginator
    {
        Gate::authorize(...);  // Sempre fora do cache

        $cursor = request()->string('cursor')->value() ?: null;

        $chave = $this->cache->criarChave(
            TagCache::Entidades,
            TagOperacao::Listar,
            ['campo' => ..., 'cursor' => $cursor, 'direcao' => ..., 'por_pagina' => ...],
        );

        return $this->cache->lembrar(
            TagCache::Entidades,
            $chave,
            TtlCache::Curta,
            fn (): CursorPaginator => Entidade::orderBy(...)->cursorPaginate(...),
        );
    }
}
```

---

## Padrão nas Actions de escrita

`invalidarCache()` dentro de `DB::transaction()`, antes do `return`:

```php
return DB::transaction(function () use (...): Entidade {
    // ... persistência ...
    $this->cache->invalidarCache(TagCache::Entidades);
    return $entidade;
});
```

---

## Serialização

`config/cache.php` → `serializable_classes`:

```php
Entidade::class,
CategoriaDocumento::class,
CursorPaginator::class,
Collection::class,                  // Illuminate\Support\Collection
EloquentCollection::class,          // Illuminate\Database\Eloquent\Collection
```

A `EloquentCollection` é necessária porque o `CursorPaginator` contém uma coleção Eloquent que precisa de ser desserializada.

---

## Configuração

| Variável | Valor dev | Notas |
|---|---|---|
| `CACHE_STORE` | `redis` | Driver principal |
| `REDIS_CLIENT` | `predis` | Sem extensão phpredis necessária |
| `REDIS_CACHE_DB` | `1` | DB separado do default (0) |
| `REDIS_HOST` | `127.0.0.1` | Docker expõe na porta 6379 |
| `REDIS_PORT` | `6379` | — |

Em testes (`phpunit.xml`): `CACHE_STORE=redis` — Redis real necessário (container Docker). Em
paralelo (`--parallel`), cada processo isola-se via prefixo de chave salgado com o token do teste
(`AppServiceProvider::prefixoCacheParalelo()`, aplicado por `AppServiceProvider::isolarCacheParalelo()`,
registado em `ParallelTesting::setUpTestCase()`) — requer `APP_ENV=testing` forçado na linha de
comando do Pest (`composer test:coverage`/`test:arch`/`test:type-coverage`), não só no `phpunit.xml`
— ver detalhe em `07-testing.md`.

---

## Features com cache activo

| Feature | Tags | TTL listagem | TTL registo |
|---|---|---|---|
| Entidade | `entidades` | `Curta` (30s) | `Media` (300s) |
| CategoriaDocumento | `categorias_documento` | `Curta` (30s) | `Media` (300s) |
| Documento | `documentos` | `Curta` (30s) | — (invalidação por escrita) |
