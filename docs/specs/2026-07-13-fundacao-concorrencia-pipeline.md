# Spec: Fundação de concorrência do pipeline (after_commit, locking, reconciliação)

**Issue:** #90
**Brief:** docs/briefs/2026-07-13-fundacao-concorrencia-pipeline.md
**Data:** 2026-07-13

## Requisitos funcionais

- RF-01: Documentar o padrão obrigatório `ShouldQueueAfterCommit` para Jobs de pipeline futuros
  (1b) — sem Job de pipeline concreto nesta issue (o orquestrador de IA é issue futura).
- RF-02: Implementar um componente de reivindicação reutilizável (Repository/Action) que varre
  `Documento`s em `Pendente`/`AguardaEnvio` dentro de `DB::transaction()` com `lockForUpdate()` e
  re-verificação de `status` após o lock (1c) — pronto para a issue do orquestrador injectar.
- RF-03: Implementar `ReconciliarFicheirosJob`, agendado via `Schedule`, que detecta
  `Documento`s presos num estado transitório há mais tempo que um limiar configurável e,
  quando a localização real do ficheiro diverge de `disco_storage`/`nome_ficheiro_storage`,
  **repõe automaticamente** os campos na BD para reflectir a localização real (1d).
- RF-04: Documentar o contrato de atomicidade ficheiro↔BD em `02-shared/estados.md`.

## Requisitos não funcionais

- RNF-01: A interface correcta para Jobs é `Illuminate\Contracts\Queue\ShouldQueueAfterCommit`
  (confirmado em `vendor/laravel/framework/src/Illuminate/Queue/Queue.php:408-419`,
  `shouldDispatchAfterCommit()`) — **não** `ShouldDispatchAfterCommit` (exclusiva de
  `Illuminate\Contracts\Broadcasting\ShouldBroadcast` / Broadcasting Events). Os docs actuais
  (`04-infra/queue-jobs.md:44`, `02-shared/contratos-por-camada.md:52`) usam o nome errado —
  corrigir como parte desta issue.
- RNF-02: A query de reivindicação (RF-02) e o scan de reconciliação (RF-03) não podem degradar
  para full-table-scan — ambos filtram por `status` (já indexado,
  `2026_06_25_174831_create_documentos_table.php:15`) combinado com `updated_at`. Acrescentar
  índice composto `(status, updated_at)` em migration nova.
- RNF-03: `WithoutOverlapping`/`onOneServer` dependem de um cache store partilhado com suporte a
  locks atómicos — `config/cache.php` já usa `redis` como default (`CACHE_STORE=redis`,
  confirmado em `.env.example:21`). Documentar esta dependência em `06-config.md`.
- RNF-04: `ReconciliarFicheirosJob` verifica apenas os discos conhecidos (`entrada`, `enviado`,
  `processado`, `erro`, `perigoso` — mapa fixo de `RegraMoverFicheiro::discoParaEstado()`), nunca
  varre a tabela `documentos` completa — o custo é proporcional ao nº de documentos presos.

## Regras de negócio

- RN-01 (1b): Todo Job despachado a partir de uma Action de escrita do pipeline implementa
  `ShouldQueueAfterCommit`. Documentado como padrão obrigatório; sem Job de pipeline concreto
  ainda para aplicar — validado por um teste unitário sintético (Job de exemplo) que confirma o
  comportamento da interface, e por ArchTest que garanta que qualquer `final class ... implements
  ShouldQueue` em `app/Jobs/` também implementa `ShouldQueueAfterCommit` (ArchTest cobre Jobs
  futuros automaticamente, sem exigir revisão manual por issue).
- RN-02 (1c): Reivindicação de `Documento`s pendentes:
  1. transação abre `lockForUpdate()` sobre a linha candidata;
  2. re-verifica `status` após obter o lock (outro worker pode ter mudado o estado entre a
     leitura inicial e o lock);
  3. só prossegue se o `status` continuar elegível — caso contrário, ignora (já reivindicado).
  `RegraTransicaoEstado` (existente) actua como último nível de validação — uma transição
  inválida lança `TransicaoInvalidaException`.
- RN-03 (1c): O componente de reivindicação é reutilizável (não acoplado a um Job concreto) —
  a issue do orquestrador injecta-o no Job por documento, que por sua vez declara
  `middleware(): array { return [new WithoutOverlapping($this->idDocumento)]; }`.
- RN-04 (1d): `ReconciliarFicheirosJob` corre agendado a cada 5 minutos
  (`Schedule::job(new ReconciliarFicheirosJob)->everyFiveMinutes()->onOneServer()`).
  Frequência escolhida por equilíbrio entre detectar inconsistências cedo e não sobrecarregar o
  scan — ajustável no futuro sem mudança de contrato.
- RN-05 (1d): Limiar de "documento preso" configurável via `.env`
  (`PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS`, default `15`) — exposto em `config/pipeline.php`
  seguindo o padrão de `config/extracao.php` (`env()` com default, sem Facade `config()` direto
  nas Actions/Jobs).
- RN-06 (1d): Para cada `Documento` preso (`status` em `AguardaEnvio`/`Enviado`/`AguardaResposta`
  E `updated_at < now() - limiar`), verifica existência do ficheiro em `disco_storage` actual.
  Se ausente, procura nos restantes 4 discos conhecidos usando `hash_sha256` para confirmar
  identidade (o nome pode ter mudado numa renomeação falhada). Se encontrado noutro disco,
  actualiza `disco_storage`/`nome_ficheiro_storage` na BD para reflectir a localização real
  (reposição automática — decisão do Brief). Se não encontrado em nenhum disco, regista log
  estruturado de erro (sem reposição possível — ficheiro perdido é um caso que exige intervenção
  manual, fora do âmbito de reposição automática).

## Dependências

- Issues bloqueantes: nenhuma.
- Bloqueia: issue futura do orquestrador de IA (mencionada nas "Notas" da issue #90).

## Questões resolvidas

| Questão (do Brief) | Decisão |
| ------------------- | ------- |
| Limiar de "documento preso" — configurável ou fixo? | Configurável via `.env` (`PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS`, default 15 min) |
| `ReconciliarFicheirosJob` repõe ou sinaliza? | Repõe automaticamente quando o ficheiro é localizado noutro disco; loga erro (sem reposição) se o ficheiro não for encontrado em nenhum disco |
| 1b — documentado ou Job de exemplo com teste real? | Só documentado — validado por ArchTest + teste unitário sintético, sem Job de pipeline real nesta issue |
| 1c — implementado nesta issue ou só desenhado? | Implementado nesta issue como componente reutilizável (RF-02) |
| Frequência do `Schedule` do `ReconciliarFicheirosJob`? | A cada 5 minutos, `onOneServer()` |

## Critérios de aceitação

- [ ] CA-01: Padrão `ShouldQueueAfterCommit` documentado em `04-infra/queue-jobs.md` e
      `04-infra/transactions.md`, com o nome de interface corrigido face ao erro actual. *(issue)*
- [ ] CA-02: ArchTest garante que todo `Job` em `app/Jobs/` implementando `ShouldQueue` também
      implementa `ShouldQueueAfterCommit`. *(spec)*
- [ ] CA-03: Componente de reivindicação (RF-02) testado: dois "workers" simulados a competir
      pelo mesmo `Documento` — só um reivindica com sucesso. *(spec)*
- [ ] CA-04: `RegraTransicaoEstado` continua a rejeitar transições inválidas concorrentes
      (teste de regressão, já coberto — confirmar que nada quebra). *(issue)*
- [ ] CA-05: `ReconciliarFicheirosJob` testado: documento preso com ficheiro no disco correcto →
      nenhuma alteração; documento preso com ficheiro no disco errado → reposição automática;
      documento preso sem ficheiro em nenhum disco → log de erro, sem alteração. *(spec)*
- [ ] CA-06: `ReconciliarFicheirosJob` não altera documentos fora da janela do limiar nem fora
      dos 3 estados transitórios (teste negativo). *(spec)*
- [ ] CA-07: Índice composto `(status, updated_at)` criado via migration; query de reivindicação
      e de reconciliação usam-no (confirmável via `EXPLAIN` ou teste de contagem de queries).
      *(spec)*
- [ ] CA-08: Contrato de atomicidade ficheiro↔BD documentado em `02-shared/estados.md`. *(issue)*

## SYSTEM_SPEC a actualizar

- `docs/system_spec/04-infra/queue-jobs.md` — secção `ShouldQueueAfterCommit` (corrigir nome),
  Jobs planeados (`ReconciliarFicheirosJob`), Schedule.
- `docs/system_spec/04-infra/transactions.md` — secção "Nota Jobs" (corrigir nome da interface),
  padrão de reivindicação com `lockForUpdate()`.
- `docs/system_spec/02-shared/estados.md` — contrato de atomicidade ficheiro↔BD (novo).
- `docs/system_spec/02-shared/contratos-por-camada.md` — item 9 da Camada de Lógica (corrigir
  `ShouldDispatchAfterCommit` → `ShouldQueueAfterCommit` para Jobs).
- `docs/system_spec/06-config.md` — nova var `PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS`; dependência
  de cache partilhado (`redis`) para `WithoutOverlapping`/`onOneServer`.

## Verificação RGPD/NIS2

- Dados pessoais: nenhum dado pessoal novo é processado — a reconciliação opera sobre metadados
  de localização de ficheiro (`disco_storage`, `nome_ficheiro_storage`, `hash_sha256`), já
  existentes no `Documento`.
- Superfície de ataque: nenhuma superfície nova exposta via HTTP — `ReconciliarFicheirosJob` é
  100% interno/agendado, sem endpoint. Sem alteração de permissões ou autorização.
