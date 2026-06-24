# Spec — Issue #38: Cache Redis — Listagens e Queries Frequentes

**Data:** 2026-06-24  
**Slug:** cache-redis-listagens-invalidacao-tags  
**Branch:** feat/cache-redis-listagens-invalidacao-tags

---

## Infraestrutura de Cache — Novos ficheiros

### `app/Shared/Cache/TagCache.php` (enum)

Tag que identifica o domínio da cache. Usada em `criarChave`, `lembrar` e `invalidarCache`.

```php
enum TagCache: string
{
    case Entidades            = 'entidades';
    case CategoriasDocumento  = 'categorias_documento';
    case Roles                = 'roles';
    // Adicionar ao criar cache em nova feature
}
```

> O valor `'categorias_documento'` corresponde ao nome da tabela (`getTable()`).

### `app/Shared/Cache/TagOperacao.php` (enum)

Tipo de operação — componente semântico da chave.

```php
enum TagOperacao: string
{
    case Ver      = 'ver';
    case Listar   = 'listar';
    case Exportar = 'exportar'; // reservado
}
```

### `app/Shared/Cache/TtlCache.php` (enum)

Tempo de vida — terceiro eixo do design (simétrico com `TagCache` e `TagOperacao`).  
Impede magic numbers nas chamadas a `lembrar()` e documenta os TTLs num único sítio.

```php
enum TtlCache: int
{
    case Listagem = 30;    // 30 segundos — listagens paginadas
    case Registo  = 300;   // 5 minutos   — registos individuais
    case Longo    = 3600;  // 1 hora      — relatórios / dados estáticos
}
```

> Para obter o `int` quando necessário: `TtlCache::Listagem->value`.

### `app/Shared/Cache/CacheServico.php` (serviço injectável)

Serviço final, sem herança, sem trait. Laravel resolve por auto-wire (sem binding no ServiceProvider).

**Contrato completo:**

```php
final class CacheServico
{
    /**
     * Cria a chave de cache.
     * $params é array associativo — ksort garante o mesmo hash
     * independentemente da ordem com que os parâmetros são passados.
     *
     * @param array<string, int|string|null> $params
     */
    public function criarChave(
        TagCache $tag,
        TagOperacao $operacao,
        array $params = [],
        ?User $utilizador = null,
    ): string;

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
    ): mixed;

    public function invalidarCache(TagCache $tag, ?User $utilizador = null): void;
}
```

**Os 3 enums simétricos:**

| Enum | Tipo | Pergunta que responde |
|---|---|---|
| `TagCache` | `string` | *que domínio?* (`Entidades`, `CategoriasDocumento`, …) |
| `TagOperacao` | `string` | *que operação?* (`Listar`, `Ver`, …) |
| `TtlCache` | `int` | *durante quanto tempo?* (`Listagem`, `Registo`, `Longo`) |

**Lógica de chave:**

| Cenário | Formato |
|---|---|
| Cache global | `{tag}:{operacao}:{md5(ksort(params))}` |
| Cache por utilizador | `{tag}:utilizador:{user.id}:{operacao}:{md5(ksort(params))}` |

**Lógica de tags Redis:**

| Chamada | Tags usadas no `Cache::tags()` |
|---|---|
| `lembrar(tag, ..., utilizador: null)` | `['entidades']` |
| `lembrar(tag, ..., utilizador: $u)` | `['entidades', 'entidades:utilizador:{id}']` |
| `invalidarCache(tag)` | flush `['entidades']` — remove global + todos os utilizadores |
| `invalidarCache(tag, $u)` | flush `['entidades:utilizador:{id}']` — só este utilizador |

---

## Configuração

### `config/cache.php`

Alterar default de `database` para `redis`:

```php
'default' => env('CACHE_STORE', 'redis'),
```

Adicionar `serializable_classes` (required pelo Laravel 13 para serializar objectos PHP):

```php
'serializable_classes' => [
    App\Models\Entidade::class,
    App\Models\CategoriaDocumento::class,
    Illuminate\Pagination\CursorPaginator::class,
    Illuminate\Support\Collection::class,
],
```

### `.env` / `.env.example`

```dotenv
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
REDIS_CACHE_DB=1
```

> `REDIS_CLIENT=phpredis` — extensão nativa PHP. Não requer `predis/predis`.  
> `REDIS_CACHE_DB=1` — base Redis separada da base de dados geral (DB 0).

### `phpunit.xml`

Já tem `CACHE_STORE=array` — nenhuma alteração necessária. `Cache::fake()` nos testes Feature suporta tags.

---

## Feature Entidade — Alterações

### Actions de leitura

**`ListarEntidadesAction`** — injectar `CacheServico`, encapsular query em `lembrar()`:

```php
final readonly class ListarEntidadesAction
{
    public function __construct(private CacheServico $cache) {}

    public function handle(int $porPagina, CampoOrdenacaoEntidades $campo, DirecaoOrdenacao $direcao): CursorPaginator
    {
        Gate::authorize('viewAny', Entidade::class);

        $cursor = request()->string('cursor')->value() ?: null;

        $chave = $this->cache->criarChave(
            TagCache::Entidades, TagOperacao::Listar,
            ['campo' => $campo->value, 'cursor' => $cursor, 'direcao' => $direcao->value, 'por_pagina' => $porPagina],
        );

        /** @var CursorPaginator<int, Entidade> $resultado */
        $resultado = $this->cache->lembrar(
            TagCache::Entidades, $chave, TtlCache::Listagem,
            fn (): CursorPaginator => Entidade::orderBy($campo->value, $direcao->value)->cursorPaginate($porPagina),
        );

        return $resultado;
    }
}
```

**`VerEntidadeAction`** — injectar `CacheServico`, encapsular retorno em `lembrar()`:

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
            TagCache::Entidades, $chave, TtlCache::Registo,
            fn (): Entidade => $entidade,
        );

        return $resultado;
    }
}
```

### Actions de escrita

Todas as Actions de escrita: injectar `CacheServico` e chamar `invalidarCache()` **dentro da transação** antes do `return`.

Afectadas: `CriarEntidadeAction`, `ActualizarEntidadeAction`, `EliminarEntidadeAction`, `ConverterEmEmpresaMaeAction`.

```php
// Padrão — dentro de DB::transaction():
$this->cache->invalidarCache(TagCache::Entidades);
```

> `ConverterEmEmpresaMaeAction` — invalida a tag na mesma transação, mesmo que `RemoverMarcacaoEmpresaMaeAction` afecte múltiplos registos.

---

## Feature CategoriaDocumento — Alterações

Mesmo padrão da Entidade. `TagCache::CategoriasDocumento` com `TTL_LISTAGEM = 30s` para listagens e `TTL_REGISTO = 300s` para registos individuais.

**Actions de leitura:** `ListarCategoriasAction`, `VerCategoriaAction`  
**Actions de escrita:** `CriarCategoriaAction`, `ActualizarCategoriaAction`, `EliminarCategoriaAction`

---

## Testes

### Unit — `CacheServico` isolado

`tests/Unit/Shared/Cache/CacheServicoTest.php`

Testar directamente o serviço sem Actions:
- `criarChave()` — mesma chave independentemente da ordem dos params (ksort)
- `criarChave()` com utilizador — inclui `utilizador:{id}` na chave
- `lembrar()` — executa callback no miss, devolve valor cacheado no hit
- `invalidarCache()` — limpa a tag; chamadas `lembrar()` seguintes executam callback novamente

### Unit — Actions com `CacheServico` mockado

Por cada Action de leitura nova ou modificada:
- Mock `CacheServico`, verificar que `lembrar()` é chamado com os argumentos correctos
- Verificar que `Gate::authorize()` é chamado **antes** do `lembrar()`

Por cada Action de escrita:
- Mock `CacheServico`, verificar que `invalidarCache()` é chamado dentro da transação
- Verificar rollback: se excepção lançada, `invalidarCache()` não foi chamado (ou DB revertido)

### Feature — HTTP com `Cache::fake()`

Nos ficheiros Feature existentes de `Entidade` e `CategoriaDocumento`:
- Adicionar asserção: segunda chamada ao mesmo endpoint devolve os mesmos dados (hit de cache)
- Após escrita (POST/PUT/DELETE): verificar que listagem seguinte devolve dados actualizados (cache invalidada)

Exemplo:
```php
it('invalida cache após criar entidade', function (): void {
    $this->getJson('/api/entidades')->assertOk();  // popula cache

    $this->postJson('/api/entidades', [...]);       // invalida cache

    // nova listagem deve incluir a nova entidade — não servir cache stale
    $this->getJson('/api/entidades')->assertJsonCount(1, 'data');
});
```

---

## ArchTest

`TagCache`, `TagOperacao` e `TtlCache` são enums — excluir da regra `are final` se necessário (enums não podem ser `final`).  
`CacheServico` é `final class` — passa a regra automaticamente.

Verificar se `app/Shared/Cache/` precisa de ser adicionado ao `ignoring` de alguma regra existente.

---

## SYSTEM_SPEC a Actualizar

| Ficheiro | Actualização |
|---|---|
| `docs/system_spec/04-infra/cache.md` | Substituir placeholder pelo design implementado |
| `docs/system_spec/06-config.md` | Adicionar `CACHE_STORE`, `REDIS_CACHE_DB`, `REDIS_CLIENT` |
| `docs/system_spec/00-index.md` | Marcar `Cache / Redis` como `implementado` |
