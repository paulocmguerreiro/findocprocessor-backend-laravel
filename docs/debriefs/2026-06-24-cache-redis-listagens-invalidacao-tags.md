# Debrief — Issue #38: Cache Redis — Listagens e Queries Frequentes

**Data:** 2026-06-24  
**Branch:** feat/cache-redis-listagens-invalidacao-tags  
**Issue:** #38  
**Estado:** Implementado — pipeline a verde (289 testes, 100% cobertura, PHPStan nível 9)

---

## O que foi implementado

### Infraestrutura (T1–T2)

- **`app/Shared/Cache/TagCache.php`** — enum string com os domínios cacheados (`Entidades`, `CategoriasDocumento`, `Roles`)
- **`app/Shared/Cache/TagOperacao.php`** — enum string com as operações de leitura (`Ver`, `Listar`, `Exportar`)
- **`app/Shared/Cache/TtlCache.php`** — enum int com as durações em segundos (`Curta=30`, `Media=300`, `Longa=3600`, `Alargada=86400`)
- **`app/Shared/Cache/CacheServico.php`** — serviço final injectável: `criarChave()`, `lembrar()`, `invalidarCache()`
- **`config/cache.php`** — `serializable_classes` com whitelist explícita; default `redis`
- **`phpunit.xml`** — `CACHE_STORE=redis` + `PERMISSION_CACHE_STORE=array` para isolar Spatie em testes paralelos
- **`config/permission.php`** — `PERMISSION_CACHE_STORE` via env

### Feature Entidade (T3–T4)

- `ListarEntidadesAction` e `VerEntidadeAction` — `final readonly class`, construtor com `CacheServico`, query encapsulada em `lembrar()`
- `CriarEntidadeAction`, `ActualizarEntidadeAction`, `EliminarEntidadeAction`, `ConverterEmEmpresaMaeAction` — `invalidarCache(TagCache::Entidades)` dentro de `DB::transaction()`

### Feature CategoriaDocumento (T5–T6)

- `ListarCategoriasAction` e `VerCategoriaAction` — mesmo padrão com `TagCache::CategoriasDocumento`
- `CriarCategoriaAction`, `ActualizarCategoriaAction`, `EliminarCategoriaAction` — invalidação dentro da transação

### Testes (T8–T11)

- **`tests/Unit/Shared/Cache/CacheServicoTest.php`** — 6 testes: chave determinística, scope por utilizador, hit/miss, invalidação global e user-scoped
- **13 Action tests** adaptados: `Cache::flush()` → `Cache::tags([tag])->flush()` para não interferir com cache Spatie
- **4 testes comportamentais** redesenhados (verificam existência da chave após 1ª chamada — race-condition-safe em paralelo)
- **Testes Feature end-to-end** — verificam que a listagem reflecte dados novos após operação de escrita

### Documentação (T12)

- `docs/system_spec/04-infra/cache.md` — design completo dos 3 enums, `CacheServico`, padrões de chave, TTLs, serialização, configuração e features activas
- `docs/system_spec/06-config.md` — variáveis Redis adicionadas
- `docs/system_spec/00-index.md` — `Cache / Redis` marcado como `implementado`

---

## Decisões tomadas (vs. plano original)

### `callable` → `\Closure` no `lembrar()`

O plano usava `callable` na assinatura. PHPStan nível 9 rejeita `callable` genérico sem shape — alterado para `\Closure` que é o tipo concreto usado nos callsites (`fn () => ...`). Sem impacto funcional.

### `md5` → `sha256` no hash das chaves

Alterado durante o fix do ArchTest. `md5` levanta aviso em contextos de segurança mesmo que não seja criptográfico neste caso; `sha256` via `hash()` é equivalente em velocidade para strings curtas e elimina a objecção.

### `TtlCache` com 4 casos (vs. 3 no plano)

O plano definiu `Listagem=30`, `Registo=300`, `Longo=3600`. Implementado com semântica mais clara: `Curta=30`, `Media=300`, `Longa=3600`, `Alargada=86400`. Os nomes descrevem a escala temporal, não o tipo de dado — mais reutilizáveis quando a feature Role for cacheada.

### `EloquentCollection` adicionada ao `serializable_classes`

Descoberta em testes: o `CursorPaginator` contém internamente uma `EloquentCollection`, não uma `Collection` base. Sem ela, a desserialização do Redis lançava excepção. Adicionada ao whitelist.

### `PERMISSION_CACHE_STORE=array` via env

Problema descoberto nos testes paralelos: `Cache::tags([...])->flush()` nas Actions de escrita apagava a cache de permissões Spatie do worker adjacente. Solução: `config/permission.php` lê `PERMISSION_CACHE_STORE` (default `redis`); `phpunit.xml` define `array` → cada worker tem cache de permissões isolada sem afectar o Redis partilhado.

### `predis/predis` em vez de `phpredis` nativo

O brief indicava `phpredis` (extensão PHP). Durante a configuração, verificou-se que a extensão não está activa no ambiente de desenvolvimento. `predis/predis` foi adicionado ao `composer.json` e configurado via `REDIS_CLIENT=predis` — zero alteração de comportamento, sem necessidade de activar extensão.

---

## O que ficou fora do âmbito

- **Observers cross-model** — quando `Documento` for implementado com dados desnormalizados de `Entidade`/`CategoriaDocumento`, será necessário um observer que invalide as tags correspondentes. Padrão desenhado no brief mas não implementado.
- **Cache da feature Role** — `TagCache::Roles` existe como caso no enum mas as Actions Role ainda não têm `CacheServico` injectado.
- **Cache HTTP** (ETags, `Cache-Control`) — fora de âmbito desta issue.

---

## Problemas encontrados

### ArchTest rejeitava `App\Shared\Cache`

A regra `are final` do ArchTest apontava para `app/` sem excluir `app/Shared/Cache/`. Os enums (`TagCache`, `TagOperacao`, `TtlCache`) não podem ser `final` em PHP. Solução: adicionar `.ignoring('App\\Shared\\Cache')` à regra.

### Race condition nos testes paralelos de cache

Os testes comportamentais que chamavam `lembrar()` duas vezes e verificavam `$contador === 1` falhavam intermitentemente em paralelo: dois workers podiam executar o callback ao mesmo tempo antes do primeiro ter escrito no Redis. Redesenhados para verificar a *existência da chave* no cache após a primeira chamada (`Cache::tags([...])->has($chave)`) — determinístico sem race condition.

### Cache de permissões Spatie contaminada

`invalidarCache()` chama `Cache::tags([$tag->value])->flush()`. Em paralelo, o worker A podia apagar o cache Spatie do worker B. Mitigado com `PERMISSION_CACHE_STORE=array` em `phpunit.xml`.

---

## Aprendizagens

### Invalidação por tag vs. por chave individual

A tentação inicial é invalidar por chave (`Cache::forget($chave)`), mas isso é frágil: a chave inclui parâmetros de paginação, campo de ordenação e cursor — há dezenas de chaves possíveis por recurso. Com `Cache::tags([$tag])->flush()` invalida-se tudo o que pertence ao domínio em **uma operação**, sem conhecer as chaves individuais. É o padrão correcto para listagens com paginação por cursor.

### `serializable_classes` é segurança, não conveniência

O Laravel 13 introduziu `serializable_classes: false` como padrão para evitar gadget chains em ataques de deserialização. Guardar objectos Eloquent em Redis sem whitelist lança excepção. A lição: qualquer classe PHP que vá para cache Redis precisa de ser explicitamente listada. O corolário é que guardar closures ou objectos com dependências não-serializáveis (PDO, handles de ficheiro) em cache é imediatamente detectado em vez de falhar silenciosamente.

### `CursorPaginator` serializa a `EloquentCollection` interna

`CursorPaginator<int, Entidade>` contém uma `EloquentCollection` — não uma `Collection` base. A whitelist de `serializable_classes` tem de incluir ambas: `Collection::class` e `EloquentCollection::class`. Esta distinção não está documentada explicitamente no Laravel — descoberta via teste.

### Isolamento de cache em testes paralelos com múltiplos drivers

Em testes paralelos com Redis real, dois stores partilhados (cache principal + cache Spatie) no mesmo servidor Redis criam interferência entre workers. A solução é configurar stores diferentes por contexto: `CACHE_STORE=redis` para o código, `PERMISSION_CACHE_STORE=array` para Spatie (por worker). A generalização: cada store com estado partilhado que pode ser apagado por `flush()` é um risco de race condition em testes paralelos.

### `CacheServico` como serviço injectável centraliza 3 decisões

Cada chamada a `Cache::tags([...])->remember(...)` inline implica três decisões ad hoc: como construir a chave, qual a tag, qual o TTL. `CacheServico` eleva essas decisões a contratos tipados (`TagCache`, `TagOperacao`, `TtlCache`) — a Action não tem acesso a strings mágicas nem a números literais. O resultado é que adicionar uma nova feature cacheada exige zero reflexão sobre formato de chaves.

---

## Métricas finais

| Métrica | Valor |
|---|---|
| Ficheiros novos | 4 (3 enums + CacheServico) |
| Ficheiros alterados | ~20 (Actions, config, testes) |
| Testes totais | 289 (100% cobertura) |
| PHPStan | Nível 9, 0 erros |
| Commits | 14 |
