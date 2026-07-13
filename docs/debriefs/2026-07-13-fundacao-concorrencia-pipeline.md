# Debrief: Fundação de concorrência do pipeline (after_commit, locking, reconciliação)

**Issue:** #90
**Branch:** feat/fundacao-concorrencia-pipeline
**Data:** 2026-07-13
**Commits:** 8 commits (1 de planeamento + 6 de implementação + 1 de documentação)

## O que foi implementado

Três garantias de concorrência/atomicidade, pré-requisito da issue futura do orquestrador de IA (que ainda não existe — nenhum Job de pipeline concreto invoca as Actions de transição):

- **1b — `ShouldQueueAfterCommit`:** padrão documentado e imposto por ArchTest (`tests/ArchTest.php`) — qualquer `Job` em `app/Jobs/` que implemente `ShouldQueue` tem de implementar também `Illuminate\Contracts\Queue\ShouldQueueAfterCommit`. Corrigido o nome errado da interface (`ShouldDispatchAfterCommit`) que estava documentado em `04-infra/queue-jobs.md` e `02-shared/contratos-por-camada.md` — essa interface é exclusiva de Events/Broadcasting.
- **1c — Reivindicação de `Documento`s pendentes:** `ReivindicarDocumentoPendenteAction` (`app/Features/Documento/Reivindicar/`) — `DB::transaction()` + `Documento::query()->wherePendente()->lockForUpdate()->first()`, seguido de `MarcarAguardaEnvioDocumentoAction` (a `RegraTransicaoEstado` existente actua como último nível de validação). Testado com 2 conexões MySQL reais a competir pelo mesmo documento (primeiro teste de concorrência do projecto).
- **1d — `ReconciliarFicheirosJob`:** agendado (`Schedule::job(...)->everyFiveMinutes()->onOneServer()`), varre `Documento`s presos num estado transitório (`AguardaEnvio`/`Enviado`/`AguardaResposta`) há mais de `config('pipeline.reconciliacao_limiar_minutos')` (default 15 min, `.env` `PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS`). `RegraReconciliarLocalizacaoFicheiro` (nova) verifica se o ficheiro existe no disco esperado; se não, procura-o nos outros 4 discos conhecidos por `hash_sha256`. Encontrado noutro disco → reposição automática de `disco_storage`/`nome_ficheiro_storage`; não encontrado em nenhum → `Log::error` estruturado, sem alteração à BD.
- Índice composto `documentos_status_updated_at_index` (`status`, `updated_at`) — garante que a reivindicação e a reconciliação não degradam para full-table-scan.

## Ficheiros alterados

| Ficheiro | Tipo de alteração | Notas |
| -------- | ----------------- | ----- |
| `database/migrations/2026_07_13_112928_add_status_updated_at_index_to_documentos_table.php` | criado | índice composto, migration nova (não editada a original) |
| `app/Features/Documento/Reivindicar/ReivindicarDocumentoPendenteAction.php` | criado | `final readonly`, sem `Gate::authorize()` (acção de sistema) |
| `app/Models/Documento.php` | alterado | scope `documentosPresos()` novo; `wherePendente()` (já existia) reutilizado |
| `config/pipeline.php`, `.env.example` | criado/alterado | `PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS` (default 15) |
| `app/Features/Documento/Transicao/RegraReconciliarLocalizacaoFicheiro.php` | criado | invariante de domínio, sem `Gate::authorize()` própria |
| `app/Features/Documento/Transicao/ResultadoReconciliacaoFicheiro.php` | criado | DTO Value Object (`coerente`/`encontrado`/`disco`/`nome`) |
| `app/Features/Documento/Transicao/RegraMoverFicheiro.php` | alterado | `discoParaEstado()` de `private` para `public` — reutilizado sem duplicar o mapa |
| `app/Jobs/ReconciliarFicheirosJob.php` | criado | `ShouldQueue, ShouldQueueAfterCommit`; `$tries = 1`, `$timeout = 120` |
| `routes/console.php` | alterado | `Schedule::job(new ReconciliarFicheirosJob)->everyFiveMinutes()->onOneServer()` |
| `tests/ArchTest.php` | alterado | regra `jobs implementam ShouldQueueAfterCommit` |
| `docs/system_spec/04-infra/{queue-jobs,transactions,repositories}.md`, `02-shared/{estados,contratos-por-camada,regras-negocio}.md`, `01-features/documento.md`, `00-index.md`, `06-config.md`, `07-testing.md` | alterados | ver secção seguinte — já commitados como Tarefa 8 do Plano |
| `tests/Unit/Features/Documento/{ReivindicarDocumentoPendenteActionTest,RegraReconciliarLocalizacaoFicheiroTest,ResultadoReconciliacaoFicheiroTest}.php`, `tests/Feature/Features/Documento/ReivindicarDocumentoPendenteConcorrenciaTest.php`, `tests/Unit/Jobs/ReconciliarFicheirosJobTest.php`, `tests/Unit/Config/PipelineConfigTest.php` | criados | ver "Testes a escrever" do Plano |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| **Sem Repository (`ContratoRepositorioPipelineDocumento`) — reverteu-se para scopes no `Documento`** (`wherePendente()`, `documentosPresos()`) chamados directamente pela Action/Job | Plano (Tarefa 2) previa Repository — critério "Query partilhada entre ≥ 2 Actions" | Cada scope tem apenas **1 consumidor real** hoje (`ReivindicarDocumentoPendenteAction` e `ReconciliarFicheirosJob`, respectivamente); a justificação original era "reutilização futura pelo orquestrador", que a tabela de critérios em `04-infra/repositories.md` explicitamente não aceita como critério (exige reutilização actual, não projectada). `lockForUpdate()` também não é, por si só, "lógica de query complexa". Documentado como precedente em `04-infra/repositories.md` — o ficheiro mantém-se "Pendente" para o primeiro Repository real. |
| `ReivindicarDocumentoPendenteAction` sem `Gate::authorize()` | Adicionar autorização por consistência com o resto das Actions | É uma acção de sistema/pipeline sem utilizador autenticado — mesmo padrão já usado em `MarcarAguardaEnvioDocumentoAction` e nas restantes Actions de transição programática (#57). |
| Teste de concorrência real com 2 conexões MySQL (`SET SESSION innodb_lock_wait_timeout = 1` na segunda) em vez de mock/simulação | Testar só a lógica sequencial (candidato encontrado / `null`) | É o único jeito de provar exclusão mútua real de `lockForUpdate()` entre workers concorrentes — um teste sequencial não distinguiria "protegido por lock" de "só correu uma vez por acaso". Primeiro teste de concorrência do projecto; padrão documentado em `07-testing.md` para reuso. |
| `ReconciliarFicheirosJob` **repõe automaticamente** (não só sinaliza) quando localiza o ficheiro noutro disco | Sinalizar sempre e exigir intervenção manual (mais conservador) | Decidido na Spec (questão em aberto do Brief): o conjunto de discos é fixo e a identidade é confirmada por hash — reposição automática é segura neste caso restrito. Só o caso "não encontrado em nenhum disco" fica sem reposição (log de erro), por ser irrecuperável automaticamente. |
| `RegraMoverFicheiro::discoParaEstado()` passou de `private` a `public` | Duplicar o mapa estado→disco em `RegraReconciliarLocalizacaoFicheiro` | Evita duas fontes de verdade para o mesmo mapa fixo — `RegraReconciliarLocalizacaoFicheiro` usa o mapa existente para listar os 4 discos candidatos, sem repetir o `match`. |

## Desvios ao Plano

- **Tarefa 2 do Plano (Repository) não foi implementada como tal** — ver "Decisões tomadas". As Tarefas 3 e 6, que dependiam do Repository, consomem scopes do `Documento` directamente. Sem impacto nos critérios de aceitação (CA-03, CA-05, CA-06, CA-07 continuam cumpridos via scopes + índice composto).
- Restante execução seguiu o Plano tarefa a tarefa (índice → config → regra de reconciliação → reivindicação → Job → Schedule/ArchTest → documentação).

## Aprendizagens

O ponto mais instrutivo foi perceber que o critério "Repository obrigatório" da tabela em `04-infra/repositories.md` (`docs/system_spec/02-shared/padroes-acoes.md`) já continha a resposta certa antes de a Tarefa 2 do Plano ter sido escrita: "Query partilhada entre ≥ 2 Actions" foi lido inicialmente como "vai ser reutilizado pelo orquestrador futuro", mas a issue do orquestrador ainda não existe — não há um segundo consumidor **real**, só um projectado. Isto é o mesmo erro que a regra tenta prevenir noutra roupagem: introduzir uma camada de abstracção por antecipação de reuso, em vez de esperar pelo segundo consumidor real para a extrair. A correcção só ficou óbvia ao escrever o precedente em `repositories.md` — nomear explicitamente "reutilização futura ≠ reutilização actual" tornou-se um critério mais afiado do que a redacção original da tabela.

Também ficou mais claro como Laravel resolve `DB::transaction()` aninhado: a `MarcarAguardaEnvioDocumentoAction` (chamada de dentro da transacção de `ReivindicarDocumentoPendenteAction`) abre a sua própria transacção via `ExecutorTransicaoDocumento`, e isso não quebra o `lockForUpdate()` da transacção exterior — o driver resolve como `SAVEPOINT`. Sem o teste de concorrência real (2 conexões MySQL), esta garantia teria ficado por confirmar empiricamente.

## SYSTEM_SPEC a actualizar

Já actualizados nesta implementação (Tarefa 8 do Plano, commit `0d3a8b8`) — sem trabalho adicional nesta fase:

- `docs/system_spec/04-infra/queue-jobs.md` — nome de interface corrigido; `ReconciliarFicheirosJob` na tabela de Jobs implementados.
- `docs/system_spec/04-infra/transactions.md` — nome de interface corrigido; padrão de reivindicação com `lockForUpdate()`.
- `docs/system_spec/02-shared/estados.md` — nova secção "Contrato de atomicidade ficheiro↔BD".
- `docs/system_spec/02-shared/contratos-por-camada.md` — item 9 da Camada de Lógica corrigido.
- `docs/system_spec/02-shared/regras-negocio.md` — `RegraMoverFicheiro` deixa de exigir reconciliação manual; nova entrada `RegraReconciliarLocalizacaoFicheiro`.
- `docs/system_spec/04-infra/repositories.md` — precedente documentado (ver "Decisões tomadas").
- `docs/system_spec/06-config.md` — `PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS` + dependência de cache partilhado (`redis`).
- `docs/system_spec/07-testing.md` — padrão de teste de concorrência com dupla conexão MySQL.
- `docs/system_spec/01-features/documento.md`, `00-index.md` — `ReivindicarDocumentoPendenteAction` incluída na contagem de Actions da feature.

## Verificação final

- [x] Linter a verde (Pint + Rector)
- [x] Testes a verde (908/908, 100% type coverage, 100% cobertura, Larastan nível 9)
- [x] Nenhum dado sensível em logs (`Log::error` regista apenas id/disco/nome, sem conteúdo de ficheiro)
- [x] Nenhum segredo em código
