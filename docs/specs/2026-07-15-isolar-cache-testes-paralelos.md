# Spec: fix(testing): isolar cache Redis por processo Pest em paralelo

**Issue:** #108
**Brief:** docs/briefs/2026-07-15-isolar-cache-testes-paralelos.md
**Data:** 2026-07-15

## Requisitos funcionais

- RF-01: cada processo Pest em paralelo (`--processes=N`) escreve/lê chaves de cache Redis sob um
  prefixo próprio, derivado de `ParallelTesting::token()` — nenhum processo vê nem invalida chaves
  escritas por outro processo.
- RF-02: o mecanismo de isolamento activa-se automaticamente no arranque de cada processo de teste,
  sem exigir alterações a testes individuais nem à assinatura de `CacheServico`.
- RF-03: a store Redis usada pelo isolamento (`config('cache.stores.redis')`, ligação `cache`) é
  reconstruída com o novo prefixo antes do primeiro uso no processo (`Cache::purge('redis')`
  imediatamente após alterar `config('cache.prefix')`).

## Requisitos não funcionais

- RNF-01 (CA-03): sem regressão de desempenho relevante face ao tempo actual de CI (~50s na suite)
  — o custo do hook é uma escrita de config + purge por processo (não por teste), overhead
  desprezável.
- RNF-02: a suite continua a correr exclusivamente sobre Redis real (container Docker) — sem trocar
  para o driver `array` (decisão de Checkpoint A, ver Brief).
- RNF-03: nenhuma alteração a código de produção (`app/Shared/Cache/`, Actions) — o isolamento fica
  confinado a `tests/` e configuração de ambiente de teste.

## Regras de negócio

Não aplicável — issue de infra de testes, sem impacto em regras de domínio.

## Mecanismo de isolamento (decisão técnica desta Spec)

| Aspecto | Decisão |
|---|---|
| Mecanismo | Prefixo de chave (`config('cache.prefix')`), não `REDIS_CACHE_DB` por processo |
| Porquê | `config/cache.php:122` já define `cache.prefix`, lido por `Illuminate\Cache\RedisStore` em toda chave gravada — mecanismo único e uniforme, sem depender do limite de 16 bases lógicas do Redis (relevante se `--processes` crescer no futuro) |
| Onde regista o hook | `AppServiceProvider::boot()`, guardado por `$this->app->runningUnitTests()` — padrão documentado pelo Laravel (`ParallelTesting::setUpProcess()` é o exemplo oficial em `AppServiceProvider`), guarda evita qualquer efeito fora de testes |
| Reconstrução da store | `Cache::purge('redis')` chamado no mesmo closure, imediatamente após `config(['cache.prefix' => ...])` — sem isto a store já resolvida manteria o prefixo antigo (confirmado: `CacheManager` só lê `cache.prefix` na criação do driver) |
| `Cache::tags(...)->flush()` existentes | Mantidos sem alteração — continuam a isolar entre testes do *mesmo* processo; o prefixo por processo resolve apenas a colisão *entre* processos |

## Dependências

- Issues bloqueantes: nenhuma

## Questões resolvidas

| Questão (do Brief) | Decisão |
|---|---|
| Prefixo vs. `REDIS_CACHE_DB` vs. ambos | Só prefixo — mais simples, uniforme, sem limite de 16 DBs. `REDIS_CACHE_DB` não é alterado nesta issue. |
| Local de registo do hook `ParallelTesting::setUpProcess()` | `AppServiceProvider::boot()`, guardado por `$this->app->runningUnitTests()` — segue o exemplo oficial do Laravel; a guarda impede qualquer efeito em produção/dev normal, resolvendo a preocupação de "lógica de teste em código de produção". |
| Manter `Cache::tags(...)->flush()` nos `beforeEach` | Sim, mantidos — continuam a cobrir isolamento intra-processo, papel distinto do prefixo por processo. |

## Critérios de aceitação

> Herdados da issue — nunca remover ou reformular os CAs originais sem justificação.
- [ ] CA-01: cada processo Pest em paralelo usa um prefixo de chave Redis isolado, à semelhança do
      isolamento já existente para o MySQL *(issue — mecanismo escolhido: prefixo, não
      `REDIS_CACHE_DB`)*
- [ ] CA-02: `composer test` (local e CI) corre de forma estável e repetível em paralelo, sem
      falhas intermitentes relacionadas com `Cache::tags(...)` *(issue)*
- [ ] CA-03: não há regressão de desempenho relevante face ao tempo actual de CI (~50s na suite)
      *(issue)*
- [ ] CA-04: `docker compose exec app composer test` (paridade Docker/MySQL) também corre estável em
      paralelo, com o mesmo isolamento activo *(spec)*
- [ ] CA-05: teste de diagnóstico confirma que dois processos com tokens diferentes não veem as
      chaves de cache um do outro (prova directa do isolamento, não só ausência de flakiness
      observada) *(spec)*

## SYSTEM_SPEC a actualizar

- `docs/system_spec/07-testing.md` — secção "Listagens com cache" (nota actual sobre
  `CACHE_STORE=redis` não isolar entre testes) passa a documentar o isolamento por processo via
  prefixo + `ParallelTesting::setUpProcess()`, mantendo a nota sobre `flush()` no `beforeEach` para
  isolamento intra-processo
- `docs/system_spec/04-infra/cache.md` — secção "Configuração", acrescentar nota sobre o
  comportamento em testes (prefixo salgado por processo paralelo)

## Verificação RGPD/NIS2

- Dados pessoais: não — chaves de cache de testes não contêm dados pessoais reais (fixtures/factories)
- Superfície de ataque: inalterada — mudança confinada a bootstrap de testes
