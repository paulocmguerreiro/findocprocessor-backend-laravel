# Brief: Fundação de concorrência do pipeline (after_commit, locking, reconciliação)

**Issue:** #90
**Data:** 2026-07-13
**Branch:** feat/fundacao-concorrencia-pipeline

## Contexto

O pipeline de processamento do `Documento` (orquestrador de IA) ainda não está implementado. As
Actions de transição (`MarcarAguardaEnvio`, `MarcarEnviado`, `MarcarAguardaResposta`,
`TransicionarProcessado`, `MarcarErro`, `MarcarPerigoso`) já existem e seguem `DB::transaction()`
(`04-infra/transactions.md`), mas nada as invoca ainda — essa invocação (Jobs de pipeline) é o
âmbito de uma issue futura.

Esta issue é pré-requisito dessa issue futura: fixa 3 garantias de concorrência/atomicidade que,
se não existirem antes do orquestrador ligar tráfego real, causam corrupção de dados sob carga
(duplo processamento, Jobs a correr sobre dados não commitados, inconsistência ficheiro↔BD).

`config/queue.php` confirma `'default' => 'database'` com `after_commit => false` em todas as
connections (`database`, `beanstalkd`, `sqs`, `redis`) — nenhuma tem `after_commit: true`
globalmente, pelo que o 1b é necessário por Job, não resolvível por config. `config/cache.php`
confirma `'default' => 'redis'`, que suporta atomic locks — pré-condição para `WithoutOverlapping`
(1c) e para `Schedule::...->onOneServer()`.

## O que muda

**1b — `after_commit` em Jobs**
Todo Job futuro despachado a partir de uma Action de escrita do pipeline implementa
`ShouldQueueAfterCommit` (`Illuminate\Contracts\Queue\ShouldQueueAfterCommit` — interface
partilhada por Jobs/Listeners/Notifications; os Events do domínio já a usam, confirmado em
`app/Events/*`). Esta issue estabelece o padrão e documenta-o; não há Jobs de pipeline concretos
ainda para o aplicar (isso é da issue do orquestrador).

**1c — Reivindicação / locking de workers**
Desenho da estratégia de reivindicação, para reuso pelo orquestrador futuro:
- varrimento de `Documento`s em `Pendente`/`AguardaEnvio` dentro de `DB::transaction()` com
  `lockForUpdate()` + re-verificação de `status` após o lock (evita corrida entre leitura e lock);
- `WithoutOverlapping($idDocumento)` na `middleware()` do Job por documento — usa o cache `redis`
  já configurado como default;
- `RegraTransicaoEstado` (já existe, `app/Features/Documento/Transicao/RegraTransicaoEstado.php`)
  actua como último nível de reivindicação — uma transição inválida (documento já mudou de estado)
  falha de forma previsível.

**1d — Reconciliação ficheiro↔BD**
Novo `ReconciliarFicheirosJob`, agendado (`Schedule`), que:
- identifica `Documento`s presos: `status` num estado transitório
  (`AguardaEnvio`/`Enviado`/`AguardaResposta`) com `updated_at` mais antigo que um limiar
  configurável (não uma janela de dias — o objectivo é apanhar documentos parados há mais tempo
  que uma transição normal demora, não filtrar por recência);
- para cada documento preso, verifica coerência `disco_storage`/`nome_ficheiro_storage` face aos
  discos conhecidos (`entrada`, `enviado`, `processado`, `erro`, `perigoso` — mapa fixo em
  `RegraMoverFicheiro::discoParaEstado()`), usando `hash_sha256` (já `unique`, já indexado) para
  confirmar identidade do ficheiro quando o nome também pode ter mudado;
- sinaliza (mínimo desta issue) ou repõe (a decidir na Spec) a inconsistência encontrada.

Documentação: `04-infra/queue-jobs.md`, `04-infra/transactions.md`, `02-shared/estados.md`
(contrato de atomicidade ficheiro↔BD — conceito novo, ainda não coberto nesse ficheiro).

## O que NÃO muda

- Não se implementa o orquestrador de IA nem os Jobs concretos que invocam as Actions de
  transição — isso é âmbito de issue futura (mencionada nas "Notas" da issue #90).
- `ExecutorTransicaoDocumento` e `RegraMoverFicheiro` não mudam de assinatura nem de
  comportamento — o 1d é aditivo (job novo), não uma alteração ao mecanismo de compensação
  best-effort existente (`ExecutorTransicaoDocumento.php:79-84`).
- Não se resolve o WRN-008 aberto (flakiness em paralelo dos testes via Docker/MySQL, issue #101
  separada) — irrelevante para o âmbito desta issue.
- Não se altera `config/queue.php` para `after_commit: true` global — a decisão é aplicar a
  interface por Job (mais explícito, evita afectar Jobs futuros fora do pipeline que não
  precisem desta garantia).

## Riscos identificados

- **1d sem sinal determinístico de "onde está o ficheiro".** Quando a compensação best-effort de
  `ExecutorTransicaoDocumento` falha, a BD fica com o `disco_storage` anterior (rollback da
  transação) mas o ficheiro físico pode estar no disco de destino da transição falhada. Como o
  conjunto de discos é fixo (5) e o estado-alvo é sempre um dos 7 valores de `EstadoDocumento`, a
  reconciliação é uma verificação limitada (não uma procura cega), mas exige leitura em múltiplos
  discos por documento preso — confirmar que o custo fica bem documentado como proporcional ao
  nº de documentos presos, não ao tamanho da tabela (risco de scan cair para full-table-scan se o
  índice em `status` não for combinado com `updated_at`).
- **1c depende de `WithoutOverlapping` + cache `redis`**, que já está configurado como default —
  mas se algum ambiente (dev local, CI) usar cache `array`/`file` sem partilha entre processos, o
  lock não protege entre workers reais. Confirmar em `06-config.md` que produção usa sempre um
  cache store partilhado.
- **1b é um padrão a documentar, não um Job a testar.** Sem Jobs de pipeline concretos ainda,
  não há forma de escrever um teste de regressão real para "Job corre antes do commit" — o teste
  possível nesta issue é um ArchTest ou teste unitário sintético (Job de exemplo/dummy), a
  confirmar na Spec.
- **`ReconciliarFicheirosJob` sinaliza vs repõe** — reposição automática sem confirmação de causa
  raiz é arriscado (pode mascarar um problema maior); sinalizar é mais seguro mas exige um canal
  de alerta que ainda não existe no projecto (sem sistema de notificação a operadores confirmado).

## Questões em aberto

- Qual o limiar de "documento preso" para o `ReconciliarFicheirosJob` (ex.: 15 min)? Devia ser
  configurável via `.env`/`config` ou fixo no código?
- `ReconciliarFicheirosJob`, ao encontrar inconsistência, **repõe automaticamente** o
  `disco_storage`/`nome_ficheiro_storage` na BD para reflectir a localização real do ficheiro, ou
  apenas **sinaliza** (log estruturado / novo estado de alerta) para intervenção manual? A issue
  diz "sinaliza/repõe" sem decidir.
- Nesta issue (sem Jobs de pipeline reais ainda), o 1b fica documentado como padrão obrigatório
  para o futuro, ou implementa-se um Job mínimo de exemplo para provar o padrão com teste real?
- O scan de reivindicação do 1c (`lockForUpdate()` sobre `Pendente`/`AguardaEnvio`) é também
  implementado nesta issue como componente reutilizável (ex.: Action/Repository), ou fica só
  desenhado/documentado para a issue do orquestrador aplicar?
- Frequência do `Schedule` do `ReconciliarFicheirosJob` — a cada minuto, 5 min, 15 min?
