# Brief — Issue #38: Cache Redis — Listagens e Queries Frequentes

**Data:** 2026-06-24  
**Slug:** cache-redis-listagens-invalidacao-tags  
**Branch:** feat/cache-redis-listagens-invalidacao-tags  
**Issue:** #38

---

## Contexto

O Redis está configurado em `.env` (`CACHE_DRIVER=redis`, `REDIS_HOST=127.0.0.1:6379`) mas nenhum código o utiliza. As listagens de `Entidade` e `CategoriaDocumento` são lidas com frequência e raramente alteradas — candidatos naturais a cache com invalidação por tags. Esta issue introduz a infraestrutura de cache e aplica-a nas duas features existentes.

---

## Problema

Cada `GET /entidades` e `GET /categorias-documento` faz sempre query à BD, independentemente de os dados terem mudado. Sem cache, o sistema não escala horizontalmente e desperdiça recursos em leituras redundantes.

---

## Decisões de Design (sessão de análise — sobrepõem a issue #38)

### 1. `CacheServico` injectável em vez de `Cache::` inline

A issue menciona uso directo de `Cache::tags([...])->remember()` nas Actions. **Decisão adoptada:** serviço tipado injectável (`CacheServico`) que encapsula a lógica de chaves, tags e TTLs. Vantagens: mockável em testes unitários, sem strings literais dispersas, não limitado a 1 tag por Action.

### 2. Três enums simétricos: `TagCache` + `TagOperacao` + `TtlCache`

A issue menciona constantes `CacheConfig::TTL_CATEGORIAS`. **Decisão adoptada:** três enums typed que cobrem os três eixos de uma chamada de cache — `TagCache` (domínio), `TagOperacao` (operação), `TtlCache` (duração). `TtlCache: int` com `Listagem = 30`, `Registo = 300`, `Longo = 3600`. O método `lembrar()` aceita `TtlCache $ttl` em vez de `int` — impede magic numbers por construção.

### 3. `phpredis` — não `predis/predis`

A issue menciona "predis/predis já instalado". **Correcto:** o projecto usa o driver nativo `phpredis` (extensão PHP), configurado via `REDIS_CLIENT=phpredis` em `config/database.php`. Não é necessário instalar `predis/predis`.

### 4. Observers de cache cross-model — fora de âmbito desta issue

Quando `Documento` for implementado e contiver dados desnormalizados de `Entidade`/`CategoriaDocumento`, será necessário um `EntidadeObserver` que invalide `TagCache::Documentos`. Esse padrão está desenhado mas **não é implementado nesta issue**.

### 5. `serializable_classes` — whitelist explícita

Laravel 13 tem `serializable_classes: false` por defeito (segurança contra gadget chains). Como guardamos objectos Eloquent em cache, é necessário adicionar ao whitelist: `Entidade::class`, `CategoriaDocumento::class`, `CursorPaginator::class`, `Collection::class`.

### 6. Contrato do `CacheServico` — métodos e assinaturas

```php
final class CacheServico
{
    /**
     * Cria a chave de cache.
     * $params é array associativo ordenado por ksort antes do hash
     * → mesma chave independentemente da ordem dos parâmetros.
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

**Padrão de uso nas Actions:**
```php
// Leitura
$chave = $this->cache->criarChave(TagCache::Entidades, TagOperacao::Listar, ['campo' => ..., 'cursor' => ..., ...]);
$resultado = $this->cache->lembrar(TagCache::Entidades, $chave, TtlCache::Listagem, fn () => ...);

// Escrita (dentro de DB::transaction)
$this->cache->invalidarCache(TagCache::Entidades);
```

---

## Scope

**Features afectadas:**
- `app/Features/Entidade/` — 2 Actions de leitura + 4 de escrita
- `app/Features/CategoriaDocumento/` — 2 Actions de leitura + 3 de escrita

**Infraestrutura nova:**
- `app/Shared/Cache/TagCache.php` (enum)
- `app/Shared/Cache/TagOperacao.php` (enum)
- `app/Shared/Cache/CacheServico.php` (serviço injectável)
- `config/cache.php` — `serializable_classes` + default `redis`

**Fora de âmbito:**
- Observers cross-model (aguardar feature Documentos)
- Cache de queries com joins complexos
- Cache HTTP (ETags, Cache-Control)

---

## Riscos Identificados

| Risco | Mitigação |
|---|---|
| `Cache::tags()` exige Redis — testes com driver `array` ignoram tags silenciosamente | `phpunit.xml` já tem `CACHE_STORE=array`; testes Feature usam `Cache::fake()` que suporta tags |
| `serializable_classes` bloqueiam objectos não listados → excepção em produção | Whitelist explícita com todas as classes cacheadas |
| `CursorPaginator` pode conter closures não-serializáveis | Verificar em testes; se problema, recorrer a `toArray()` + `newFromBuilder()` |
| Redis sem password em desenvolvimento | Documentar `REDIS_PASSWORD` no `.env.example` para produção |

---

## Questões em Aberto

- Nenhuma. Decisões tomadas na sessão de análise pré-implementação.

---

## Aprendizagem Esperada

- Como estruturar um serviço de cache genérico e injectável em Vertical Slice
- Padrão de invalidação por tags (vs invalidação por chave individual)
- `serializable_classes` no Laravel 13 — impacto de segurança e configuração correcta
- Diferença entre `phpredis` (extensão nativa) e `predis` (pacote Composer)
