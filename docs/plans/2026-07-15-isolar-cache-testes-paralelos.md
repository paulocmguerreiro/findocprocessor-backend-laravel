# Plano: fix(testing): isolar cache Redis por processo Pest em paralelo

**Issue:** #108
**Spec:** docs/specs/2026-07-15-isolar-cache-testes-paralelos.md
**Data:** 2026-07-15

## Tarefas

### Tarefa 1 — Isolamento de cache Redis por processo paralelo

- Ficheiros a criar/alterar:
  - `app/Providers/AppServiceProvider.php` (alterar)
  - `tests/Unit/Providers/AppServiceProviderCacheIsolamentoTest.php` (criar)
- O que implementar:
  - Método público estático puro `AppServiceProvider::prefixoCacheParalelo(string $prefixoBase, int $token): string` — devolve `"{$prefixoBase}test_{$token}_"`. Extraído como método próprio (em vez de lógica inline na closure) para ser testável directamente sem depender de `ParallelTesting`/paratest a correr de facto.
  - Em `boot()`, guardado por `if ($this->app->runningUnitTests())`:
    ```php
    ParallelTesting::setUpProcess(function (int $token): void {
        config(['cache.prefix' => self::prefixoCacheParalelo(config()->string('cache.prefix'), $token)]);
        Cache::purge('redis');
    });
    ```
  - Imports novos: `Illuminate\Support\Facades\Cache`, `Illuminate\Support\Facades\ParallelTesting`.
  - `Cache::purge('redis')` é obrigatório imediatamente a seguir à alteração de `config('cache.prefix')` — sem isto a store `redis` já resolvida mantém o prefixo antigo (confirmado na Spec: `CacheManager` só lê `cache.prefix` na criação do driver).
- Testes associados:
  - `it('gera prefixo com o token do processo')` — chama `AppServiceProvider::prefixoCacheParalelo('app-cache-', 3)` directamente, assert `'app-cache-test_3_'`.
  - `it('isola chaves de cache entre dois tokens diferentes')` — teste de diagnóstico (prova directa de CA-05, mesmo padrão de rigor do WRN-008): aplica `config(['cache.prefix' => AppServiceProvider::prefixoCacheParalelo($base, 1)]); Cache::purge('redis'); Cache::put('chave-teste', 'valor-processo-1', 60);` depois muda para o token `2` (mesmo `purge`) e confirma `Cache::get('chave-teste')` é `null` — prova que o valor do "processo 1" ficou isolado sob o prefixo antigo, invisível ao prefixo do "processo 2". Limpar ambos prefixos no `afterEach` (`Cache::store('redis')->flush()` em cada um, ou reverter `config('cache.prefix')` ao valor original antes do teste).
- Commit: `fix(testing): isola cache Redis por processo Pest em paralelo`

## Ordem de implementação

1. Tarefa 1 (única tarefa) — issue pequena e auto-contida, sem dependências internas.

## Testes a escrever

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| gera prefixo com o token do processo | unit | `tests/Unit/Providers/AppServiceProviderCacheIsolamentoTest.php` | `prefixoCacheParalelo()` devolve o formato esperado (RF-01) |
| isola chaves de cache entre dois tokens diferentes | unit (diagnóstico) | `tests/Unit/Providers/AppServiceProviderCacheIsolamentoTest.php` | prefixo + `Cache::purge('redis')` isola de facto as chaves entre "processos" simulados (CA-05, RF-03) |

## Dependências

- Issues bloqueantes: nenhuma
- Deve ser implementada após: nenhuma

## Riscos de implementação

> Consolidados do Brief (`## Riscos identificados`) e da Spec — não apagar riscos do Brief.

- **`Cache::purge()`/reconexão** (Brief) — mitigado directamente pela Tarefa 1 (`Cache::purge('redis')`
  chamado sempre a seguir à alteração de `config('cache.prefix')`); o teste de diagnóstico prova que
  o purge é suficiente.
- **`REDIS_CACHE_LOCK_CONNECTION`** (Brief) — fora do âmbito: grep confirma zero uso de
  `Cache::lock()` no repo actual; sem locks de cache a isolar por processo nesta issue.
- **`LoginThrottleTest`/`RateLimiter`** (Brief) — `tests/TestCase.php` já desliga
  `ThrottleRequests` por omissão (rate limiting não corre na maioria dos testes); o workaround de
  email único em `LoginThrottleTest` mantém-se sem alteração, continua válido para isolar entre
  testes do mesmo processo.
- **`VerificarProducaoCommandTest`** (Brief) — testa mensagem de erro de validação de config, não
  liga a Redis real; sem impacto esperado, confirmar ao correr a suite completa no fecho da Tarefa 1.
- **Cobertura de código** — o corpo da closure registada em `setUpProcess()` só executa quando os
  testes correm mesmo com `--parallel` (`composer test`/`composer test:coverage` já usam
  `--parallel` por omissão — confirmado em `CLAUDE.md`), pelo que a cobertura de 100% é atingida
  organicamente sem necessidade de mock adicional da `ParallelTesting` facade.

## O que NÃO fazer nesta issue

- Não trocar `CACHE_STORE` para `array` (decisão de Checkpoint A — manter Redis real).
- Não alterar `REDIS_CACHE_DB` nem introduzir uma base Redis por processo (decisão de Checkpoint B —
  só prefixo).
- Não alterar `CacheServico`, `TagCache`, `TagOperacao`, `TtlCache` nem qualquer Action.
- Não remover os `Cache::tags([...])->flush()` existentes nos `beforeEach` dos testes de listagem.
- Não desligar ou reduzir `--processes=4` do paralelismo de testes.
- Não actualizar `docs/system_spec/*.md` nesta fase — fica para `/documenta-implementacao` (Fase 3a).
