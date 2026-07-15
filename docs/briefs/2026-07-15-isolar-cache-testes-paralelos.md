# Brief: fix(testing): isolar cache Redis por processo Pest em paralelo

**Issue:** #108
**Data:** 2026-07-15
**Branch:** fix/isolar-cache-testes-paralelos

## Contexto

`composer test` corre `pest --parallel --processes=4` contra um único Redis partilhado
(`CACHE_STORE=redis` em `phpunit.xml`, sem `REDIS_CACHE_DB`/prefixo por processo). O MySQL já tem
isolamento nativo por processo (`ParallelTesting` cria `findocprocessor_testing_test_N` — grep
confirma zero uso de `ParallelTesting`/`TEST_TOKEN` em `app/`, `config/`, `tests/`, logo esse
isolamento vem só do Laravel, não de código deste projecto), mas o Redis não tem equivalente.

Isto causa condições de corrida em testes que escrevem e leem cache com tags na mesma tag
partilhada entre workers: `Cache::tags([...])->flush()` de um processo pode apagar chaves escritas
por outro processo antes da asserção correr.

Confirmado duas vezes de forma independente:
- CI do PR #107 (issue #97): `ListarEntidadesActionTest > "cacheia resultados após primeira
  chamada"` falhou com `Failed asserting that false is true` em paralelo; local (mesma suite, gate
  Docker/MySQL) passou 1020/1020; re-run do CI sem alterar código → passou.
- CI run 29337706170 (push doc-only, sem alterar PHP): `ListarTiposDocumentoTest` falhou com
  contagem de resposta 0 em vez de 3; `gh run rerun --failed` passou sem alterar código.

Já existe investigação registada em `docs/process-warnings.md` (WRN-014): não há nenhum mecanismo
no código actual que aplique prefixo por processo às chaves de cache
(`CacheServico::criarChave()`, `app/Shared/Cache/CacheServico.php`, gera a chave só a partir dos
parâmetros da query, sem salt de `ParallelTesting::token()`), e o CI usa uma única instância Redis
partilhada pelos 4 processos. A mesma falta de salt por processo repete-se em
`ListarEntidadesAction`, `ListarCategoriasAction`, `ListarDocumentosAction` e
`ListarUtilizadoresAction` — risco sistémico, não isolado a uma feature.

**Decisão desta sessão:** manter os testes a correr sobre **Redis real** (não trocar para o driver
`array`). Trocar para `array` isolaria automaticamente por processo (o Laravel recria a
`Application` por processo paralelo), mas eliminaria por completo a cobertura de Redis real na
suite — o mesmo princípio que já levou este projecto a exigir MySQL exclusivo nos testes (em vez de
SQLite, ver `docs/system_spec` sobre paridade prod) pesa aqui na direcção oposta: `array` afasta-se
de produção, Redis isolado por processo aproxima-se. A via escolhida resolve o isolamento mantendo
Redis real.

## O que muda

- **Isolamento por processo mantendo Redis real** — via `config('cache.prefix')`
  (`config/cache.php:122`, já lido por `Illuminate\Cache\RedisStore` em **todas** as chaves,
  confirmado no código actual) salgado com `ParallelTesting::token()` no arranque de cada processo
  paralelo (`ParallelTesting::setUpProcess()`, hook nativo do Laravel 13 — confirmado via
  `search-docs`). Cada processo passa a escrever/ler com um prefixo próprio no mesmo Redis
  partilhado, sem colisão de chaves nem de `Cache::tags(...)->flush()` entre workers.
- **Alternativa a decidir na Spec/Plano**: em vez de (ou em complemento a) prefixo, usar
  `REDIS_CACHE_DB` (`config/database.php:177`, já existe, default `1`) diferente por processo —
  Redis tem 16 bases lógicas por omissão, suficiente para `--processes=4`, mas menos escalável que
  o prefixo se o número de processos crescer. A decidir qual mecanismo (ou os dois) na Fase 1
  seguinte — ambos resolvem CA-01 sem sair de Redis real.
- **Onde regista o hook**: `ParallelTesting::setUpProcess()` precisa de correr no arranque de cada
  processo — candidatos a decidir na Spec: `tests/TestCase.php` (já concentra ajustes só-de-teste,
  ex. desligar rate limiting) ou um Service Provider registado apenas em ambiente de testes. Não
  deve ficar em `AppServiceProvider` sem guarda — seria lógica de teste em código de produção
  (contra a convenção do projecto).
- **`docs/system_spec/07-testing.md`** e **`docs/system_spec/04-infra/cache.md`**: documentar o
  novo mecanismo de isolamento por processo (prefixo e/ou DB), substituindo a nota actual
  "`CACHE_STORE=redis` nos testes — Redis real necessário, sem isolamento entre testes".

## O que NÃO muda

- Não desliga o paralelismo dos testes (`--parallel --processes=4` mantém-se — CA-03).
- Não altera `CacheServico`, `TagCache`, `TagOperacao`, `TtlCache` nem qualquer Action de
  leitura/escrita — a mudança fica confinada ao bootstrap de testes e configuração.
- Não troca `CACHE_STORE` para `array` — decisão explícita de manter cobertura de Redis real nos
  testes (paridade com produção).
- Não altera `config/cache.php`/`config/database.php` em produção — os valores por omissão
  (`REDIS_CACHE_DB=1`, prefixo base) mantêm-se; só o comportamento **dentro** do processo de teste
  paralelo passa a variar por `token`.
- Não remove os `Cache::tags([...])->flush()` já existentes em `beforeEach` dos testes de
  listagem — continuam necessários mesmo com isolamento por processo (isolam entre testes do
  *mesmo* processo, papel que o prefixo por processo não cobre).

## Riscos identificados

- **`Cache::purge()`/reconexão** — alterar `config('cache.prefix')` ou `config('database.redis.cache.database')`
  em runtime só tem efeito se a store/conexão Redis já resolvida for purgada (`Cache::purge('redis')`
  e/ou `Redis::purge('cache')`) antes do primeiro uso no processo — caso contrário o container
  mantém a instância antiga com o prefixo/DB original. A confirmar no Plano com um teste de
  diagnóstico (duas invocações do mesmo processo, chave visível só localmente).
- **`REDIS_CACHE_LOCK_CONNECTION`** (`config/cache.php:85`, default `'default'`, não `'cache'`) —
  se a via escolhida for `REDIS_CACHE_DB` por processo, os locks de cache (`Cache::lock()`, se
  algum dia usados) continuam na conexão `default` (DB 0), não isolada por processo; a avaliar se é
  relevante (hoje não há uso de `Cache::lock()` no repo — grep a confirmar no Plano).
- **`LoginThrottleTest`/`RateLimiter`** (`tests/Feature/Features/Auth/LoginThrottleTest.php`) usa
  cache indirectamente via `RateLimiter` (comentário no ficheiro: "a cache Redis é partilhada" —
  usa email único para garantir contador fresco). Com isolamento por processo este workaround deixa
  de ser estritamente necessário para correr em paralelo, mas continua válido para isolar entre
  testes do mesmo processo — não é removido nesta issue.
- **`VerificarProducaoCommandTest`** testa a mensagem de erro "Redis exige password" — não depende
  do prefixo/DB de teste (testa validação de config, não uma ligação Redis real); sem impacto
  esperado, a confirmar ao correr a suite completa.
- **Custo de manter Redis real** — face à alternativa `array`, este caminho exige um hook de
  bootstrap adicional (código de teste, não zero-config) e continua dependente do container Docker
  Redis estar de pé para correr a suite — já é o caso hoje, sem regressão, mas sem o ganho de
  simplicidade que `array` traria.

## Questões em aberto

1. **Prefixo vs. `REDIS_CACHE_DB` vs. ambos** — a decidir na Spec. Prefixo escala melhor (não
   limitado a 16 DBs) e cobre `RedisStore` de forma uniforme; `REDIS_CACHE_DB` separa fisicamente os
   dados por processo (mais fácil de inspeccionar manualmente durante debug) mas está limitado ao
   número de bases lógicas do Redis. Podem coexistir sem conflito.
2. **Local de registo do hook `ParallelTesting::setUpProcess()`** — `tests/TestCase.php` vs. um
   Service Provider dedicado a testes. A decidir na Spec, seguindo a convenção do projecto de não
   colocar lógica de teste em `AppServiceProvider`.
3. **Manter os `Cache::tags(...)->flush()` existentes nos `beforeEach`** — confirmado que continuam
   necessários (isolamento intra-processo, não coberto pelo prefixo por processo); sem decisão
   pendente, apenas a documentar em `07-testing.md`.
