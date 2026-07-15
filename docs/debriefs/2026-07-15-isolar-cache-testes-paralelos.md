# Debrief: fix(testing): isolar cache Redis por processo Pest em paralelo

**Issue:** #108
**Branch:** fix/isolar-cache-testes-paralelos
**Data:** 2026-07-15
**Commits:** 2 commits

## O que foi implementado

`AppServiceProvider::boot()` regista, apenas quando `$this->app->runningUnitTests()` é verdadeiro,
um callback `ParallelTesting::setUpTestCase()` que salga `config('cache.prefix')` com o token do
processo Pest (`prefixoCacheParalelo()`, método público estático puro) e chama `Cache::purge('redis')`
logo a seguir para forçar a store `redis` a ser reconstruída com o novo prefixo. Cada processo
paralelo passa a escrever/ler chaves Redis sob um prefixo próprio (`{prefixoBase}test_{token}_`),
eliminando a colisão de `Cache::tags([...])->flush()` entre workers que causava os falsos negativos
intermitentes em CI (WRN-014). Dois testes unitários novos: um directo ao formato do prefixo, outro
de diagnóstico que prova (não apenas assume) que dois tokens diferentes não veem as chaves de cache
um do outro.

## Ficheiros alterados

| Ficheiro | Tipo de alteração | Notas |
| -------- | ----------------- | ----- |
| `app/Providers/AppServiceProvider.php` | alterado | +hook `ParallelTesting::setUpTestCase()` guardado por `runningUnitTests()`, +método `prefixoCacheParalelo()` |
| `tests/Unit/Providers/AppServiceProviderCacheIsolamentoTest.php` | criado | teste de formato do prefixo + teste de diagnóstico de isolamento (CA-05) |
| `docs/briefs/2026-07-15-isolar-cache-testes-paralelos.md` | criado | Fase 1 |
| `docs/specs/2026-07-15-isolar-cache-testes-paralelos.md` | criado | Fase 1 |
| `docs/plans/2026-07-15-isolar-cache-testes-paralelos.md` | criado | Fase 1 |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| Isolar via prefixo de chave (`config('cache.prefix')`) salgado por processo | `REDIS_CACHE_DB` diferente por processo | Prefixo é lido uniformemente por `RedisStore` em toda chave gravada, sem depender do limite de 16 bases lógicas do Redis se `--processes` crescer |
| Manter Redis real nos testes (não trocar `CACHE_STORE` para `array`) | Driver `array` (isola automaticamente por processo, `Application` recriada) | Paridade com produção — mesmo princípio que já exige MySQL exclusivo nos testes; perder a cobertura de Redis real não compensa a simplicidade |
| Hook registado em `ParallelTesting::setUpTestCase()`, **não** `setUpProcess()` | `setUpProcess()` (exemplo oficial do Laravel, decidido na Spec) | **Desvio ao Plano** — ver secção seguinte |
| `prefixoCacheParalelo()` extraído como método público estático puro | Lógica inline na closure do hook | Testável directamente sem depender de `ParallelTesting`/paratest a correr de facto |

## Desvios ao Plano

**Mecanismo de hook: `setUpTestCase()` em vez de `setUpProcess()`.** A Spec e o Plano especificavam
`ParallelTesting::setUpProcess()`, seguindo o exemplo oficial da documentação Laravel. Na
implementação confirmou-se que o Pest `--parallel` corre com o `WrapperRunner` próprio do Pest, não
com o `ParallelRunner` do `illuminate/testing` — e é este último que invoca
`callSetUpProcessCallbacks()`. Com o `WrapperRunner`, um callback registado via `setUpProcess()` fica
registado mas o closure nunca é executado (confirmado experimentalmente: cache não isolava, teste de
diagnóstico falhava). `setUpTestCase()` é invocado directamente por
`Illuminate\Foundation\Testing\Concerns\InteractsWithTestCaseLifecycle` em cada teste, independente do
runner usado — por isso é o hook fiável nesta stack. Documentado como comentário no próprio código
(`AppServiceProvider.php`) para não se repetir o mesmo beco sem saída em revisões futuras.

Efeito prático: o prefixo passa a ser reaplicado por **teste** (não por processo), o que é
estritamente mais frequente do que o desenhado na Spec mas produz o mesmo resultado de isolamento
entre processos — o custo extra é uma escrita de config + purge por teste em vez de uma vez por
processo, ainda desprezável (RNF-01/CA-03 confirmado: suite completa 34.5s, sem regressão face aos
~50s de referência do CI).

## Aprendizagens

O Pest 4 `--parallel` não é um wrapper fino sobre o `ParallelTesting` do Illuminate — usa o seu
próprio `WrapperRunner`, o que quebra silenciosamente qualquer hook desenhado a partir da
documentação oficial do Laravel sem validação empírica (`setUpProcess()` fica registado, nunca
executa, sem erro nem aviso). A lição fica registada como comentário inline porque não é óbvio a
partir do código do próprio hook — só se descobre a correr o teste de diagnóstico e ver a asserção
falhar apesar do código "parecer certo". Isto reforça o valor do teste de diagnóstico do Plano (CA-05):
sem ele, a issue teria fechado com uma falsa sensação de resolução (hook registado, testes verdes por
acaso, condição de corrida ainda latente em CI real).

## SYSTEM_SPEC a actualizar
- `docs/system_spec/07-testing.md` — secção "Listagens com cache": substituir a nota "`CACHE_STORE=redis` nos testes não isola entre testes" pela descrição do isolamento por processo via `setUpTestCase()` + prefixo
- `docs/system_spec/04-infra/cache.md` — secção "Configuração": acrescentar nota sobre o prefixo salgado por processo/teste paralelo

## Verificação final
- [x] Linter a verde (`composer test:lint`)
- [x] Testes a verde (`composer test` — 1022 testes, 100% coverage/types)
- [x] Nenhum dado sensível em logs
- [x] Nenhum segredo em código
