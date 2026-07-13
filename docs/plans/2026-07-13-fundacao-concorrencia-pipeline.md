# Plano: Fundação de concorrência do pipeline (after_commit, locking, reconciliação)

**Issue:** #90
**Spec:** docs/specs/2026-07-13-fundacao-concorrencia-pipeline.md
**Data:** 2026-07-13

## Tarefas

### Tarefa 1 — Índice composto `(status, updated_at)` em `documentos`

- Ficheiros a criar: `database/migrations/YYYY_MM_DD_HHMMSS_add_status_updated_at_index_to_documentos_table.php`
- O que implementar: `$table->index(['status', 'updated_at'])` numa migration nova (não editar a
  migration original já publicada). Sem alteração ao Model (índice não afecta `@property-read`).
- Testes associados: nenhum teste dedicado — validado indirectamente pelas queries das Tarefas 3 e 7.
- Commit: `feat(documento): índice composto status+updated_at para queries de pipeline`

### Tarefa 2 — Repository de concorrência do pipeline (interface + Eloquent)

- Ficheiros a criar:
  - `app/Infrastructure/Repositories/ContratoRepositorioPipelineDocumento.php` (interface)
  - `app/Infrastructure/Repositories/EloquentRepositorioPipelineDocumento.php`
- O que implementar: primeiro Repository do projecto (critério "Obrigatório" cumprido — query com
  `lockForUpdate()` + filtros por `status`/`updated_at`, reutilizada por ≥ 2 consumidores futuros).
  - `bloquearProximoPendente(EstadoDocumento $estado): ?Documento` — `Documento::whereEstado($estado)
    ->lockForUpdate()->first()`. **Deve ser chamado dentro de uma transacção já aberta pelo
    consumidor** (o Repository não abre `DB::transaction()` — quem chama é responsável, ver
    Tarefa 3).
  - `listarDocumentosPresos(array $estados, int $limiarMinutos): Collection<int, Documento>` —
    `Documento::whereIn('status', $estados)->where('updated_at', '<', now()->subMinutes($limiarMinutos))
    ->cursor()`.
  - Binding `interface → implementação` em `app/Providers/AppServiceProvider.php`.
- Testes associados:
  - `tests/Unit/Infrastructure/Repositories/EloquentRepositorioPipelineDocumentoTest.php` —
    `bloquearProximoPendente()` devolve o documento correcto e `null` quando não há candidatos;
    `listarDocumentosPresos()` respeita o limiar e os estados filtrados (positivo e negativo).
- Commit: `feat(documento): repository de concorrência do pipeline (lockForUpdate + presos)`

### Tarefa 3 — Action de reivindicação (`ReivindicarDocumentoPendenteAction`)

- Ficheiros a criar: `app/Features/Documento/Reivindicar/ReivindicarDocumentoPendenteAction.php`
- O que implementar: `final readonly class`, injecta `ContratoRepositorioPipelineDocumento` +
  `MarcarAguardaEnvioDocumentoAction`. `handle(): ?Documento`:
  1. `DB::transaction()` — abre aqui (é o ponto de entrada, sem Action chamante);
  2. `$documento = $this->repositorio->bloquearProximoPendente(EstadoDocumento::Pendente)`;
  3. se `null`, devolve `null` (nada a reivindicar);
  4. devolve `$this->marcarAguardaEnvio->handle($documento)` — a própria
     `RegraTransicaoEstado` (já existente) actua como último nível de validação.
  - Sem `Gate::authorize()` — acção de sistema/pipeline, mesmo padrão de
    `MarcarAguardaEnvioDocumentoAction` (sem utilizador autenticado).
  - `@throws \Throwable` no PHPDoc (`DB::transaction()`).
- Testes associados:
  - `tests/Unit/Features/Documento/ReivindicarDocumentoPendenteActionTest.php` — reivindica com
    sucesso um `Pendente`; devolve `null` sem `Pendente`s disponíveis.
  - **Teste de concorrência (CA-03)** — `tests/Feature/Features/Documento/ReivindicarDocumentoPendenteConcorrenciaTest.php`:
    usa uma segunda conexão MySQL real (`DB::connection('mysql_teste_concorrente')` apontada à
    mesma BD de teste) para simular um segundo worker: conexão A abre transacção e
    `lockForUpdate()` a linha (sem commit); conexão B, com
    `SET SESSION innodb_lock_wait_timeout = 1`, tenta o mesmo `lockForUpdate()` e é bloqueada
    (`QueryException` de lock wait timeout) — prova a exclusão mútua. Depois do commit da
    conexão A, a conexão B consegue obter o lock. Ver `docs/system_spec/07-testing.md` a
    actualizar com este padrão (primeiro teste de concorrência do projecto).
- Commit: `feat(documento): action de reivindicação de documentos pendentes com locking`

### Tarefa 4 — `config/pipeline.php` + `.env.example`

- Ficheiros a criar/alterar: `config/pipeline.php` (novo, padrão de `config/extracao.php`),
  `.env.example`
- O que implementar:
  ```php
  return [
      'reconciliacao_limiar_minutos' => (int) env('PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS', 15),
  ];
  ```
  `.env.example`: `PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS=15` (secção de pipeline, junto às vars
  `FILESYSTEM_*`/`LLM_*`).
- Testes associados: `tests/Unit/ConfigPipelineTest.php` — default e override via `env()`
  (mesmo padrão dos testes de config de `extracao.php`, se existirem — confirmar em `tests/Unit/`).
- Commit: `feat(config): var PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS`

### Tarefa 5 — `RegraReconciliarLocalizacaoFicheiro`

- Ficheiros a criar/alterar:
  - `app/Features/Documento/Transicao/RegraReconciliarLocalizacaoFicheiro.php` (novo)
  - `app/Features/Documento/Transicao/RegraMoverFicheiro.php` (expor o mapa estado→disco —
    extrair `discoParaEstado()` para um método público ou para uma pequena função partilhada,
    para não duplicar o mapa entre as duas Regras)
- O que implementar: `final readonly class`, sem `Gate::authorize()` (Regra, não Action).
  `handle(Documento $documento): ResultadoReconciliacaoFicheiro` (DTO simples: `encontrado: bool`,
  `disco: ?string`, `nome: ?string`, `coerente: bool`):
  1. Verifica `Storage::disk($documento->disco_storage)->exists($documento->nome_ficheiro_storage)`
     — se existir, `coerente = true`, devolve sem alteração;
  2. Caso contrário, itera os 4 discos restantes (mapa de `RegraMoverFicheiro`), lendo cada
     ficheiro candidato e comparando `hash('sha256', $conteudo)` com `$documento->hash_sha256`
     para confirmar identidade (o nome pode ter mudado numa renomeação falhada);
  3. Se encontrado noutro disco → devolve `encontrado: true` com o disco/nome reais;
  4. Se não encontrado em nenhum disco → devolve `encontrado: false` (ficheiro perdido).
- Testes associados: `tests/Unit/Features/Documento/RegraReconciliarLocalizacaoFicheiroTest.php`
  — ficheiro no disco correcto (coerente); ficheiro movido para outro disco conhecido (localizado
  por hash); ficheiro ausente em todos os discos (não encontrado).
- Commit: `feat(documento): regra de reconciliação de localização de ficheiro por hash`

### Tarefa 6 — `ReconciliarFicheirosJob`

- Ficheiros a criar: `app/Jobs/ReconciliarFicheirosJob.php`
- O que implementar: `final class ReconciliarFicheirosJob implements ShouldQueue, ShouldQueueAfterCommit`.
  Injecta `ContratoRepositorioPipelineDocumento` + `RegraReconciliarLocalizacaoFicheiro` no
  `handle()` (resolução via container, padrão Job Laravel). `$tries = 1`, `$timeout` declarado
  (ex.: 120s — scan limitado, sem chamadas externas).
  1. `$presos = $this->repositorio->listarDocumentosPresos([AguardaEnvio, Enviado,
     AguardaResposta], config('pipeline.reconciliacao_limiar_minutos'))`;
  2. para cada documento: `$resultado = $regra->handle($documento)`;
  3. se `coerente`, ignora; se `encontrado` (noutro disco), `DB::transaction()` →
     `$documento->update(['disco_storage' => ..., 'nome_ficheiro_storage' => ...])` (reposição
     automática, decisão da Spec);
  4. se não `encontrado`, `Log::error(...)` estruturado (sem dados sensíveis — id do documento,
     disco/nome esperados) — sem alteração à BD.
- Testes associados: `tests/Unit/Jobs/ReconciliarFicheirosJobTest.php` — os 3 cenários do CA-05
  (coerente sem alteração; incoerente com reposição; perdido com log); CA-06 (documento fora da
  janela do limiar ou fora dos 3 estados não é tocado).
- Commit: `feat(documento): job de reconciliação ficheiro↔BD agendado`

### Tarefa 7 — Agendamento + ArchTest

- Ficheiros a alterar: `routes/console.php`, `tests/ArchTest.php`
- O que implementar:
  - `routes/console.php`: `Schedule::job(new ReconciliarFicheirosJob)->everyFiveMinutes()
    ->onOneServer()->name('reconciliar-ficheiros');`
  - `tests/ArchTest.php`: nova regra `arch('jobs implementam ShouldQueueAfterCommit')` —
    `expect('App\Jobs')->toImplement(ShouldQueueAfterCommit::class)` para todas as classes que
    implementam `ShouldQueue` (garante que qualquer Job futuro do pipeline segue o padrão 1b sem
    revisão manual).
- Testes associados: o próprio ArchTest é o teste; confirmar que `composer test:arch` passa.
- Commit: `feat(documento): agenda ReconciliarFicheirosJob + ArchTest ShouldQueueAfterCommit`

### Tarefa 8 — Documentação (system_spec)

- Ficheiros a alterar:
  - `docs/system_spec/04-infra/queue-jobs.md` — corrigir `ShouldDispatchAfterCommit` →
    `ShouldQueueAfterCommit`; adicionar `ReconciliarFicheirosJob` à tabela de Jobs; documentar o
    Schedule.
  - `docs/system_spec/04-infra/transactions.md` — corrigir o mesmo nome de interface na "Nota
    Jobs"; documentar o padrão de reivindicação com `lockForUpdate()` (Tarefa 3).
  - `docs/system_spec/02-shared/estados.md` — nova secção "Contrato de atomicidade ficheiro↔BD":
    a janela de inconsistência possível (compensação best-effort falhada), como
    `ReconciliarFicheirosJob` a resolve, o limiar configurável.
  - `docs/system_spec/02-shared/contratos-por-camada.md` — corrigir item 9 da Camada de Lógica.
  - `docs/system_spec/02-shared/regras-negocio.md` — actualizar a "Limitação" de
    `RegraMoverFicheiro` (deixa de ser "requer reconciliação manual"; passa a
    "reconciliada automaticamente por `ReconciliarFicheirosJob`, ver `RegraReconciliarLocalizacaoFicheiro`")
    + nova entrada no catálogo para `RegraReconciliarLocalizacaoFicheiro`.
  - `docs/system_spec/04-infra/repositories.md` — actualizar "Estado actual": primeiro Repository
    implementado (`ContratoRepositorioPipelineDocumento`), critério "Obrigatório" aplicado.
  - `docs/system_spec/06-config.md` — nova var `PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS`;
    dependência de cache partilhado (`redis`) para `WithoutOverlapping`/`onOneServer`.
  - `docs/system_spec/07-testing.md` — documentar o padrão de teste de concorrência com dupla
    conexão MySQL (Tarefa 3), primeiro do projecto.
  - `docs/system_spec/00-index.md` — nenhuma linha nova de feature (não é uma feature slice com
    endpoint); confirmar se `ReivindicarDocumentoPendenteAction` deve constar na contagem de
    Actions de `01-features/documento.md` (actualizar esse ficheiro também).
- Testes associados: nenhum (documentação).
- Commit: `docs(system_spec): concorrência do pipeline — after_commit, locking, reconciliação`

## Ordem de implementação

1. Tarefa 1 (índice) — pré-requisito de performance para as Tarefas 2 e 6.
2. Tarefa 2 (Repository) — usado pelas Tarefas 3 e 6.
3. Tarefa 4 (config) — usado pela Tarefa 6, sem dependência das anteriores (pode correr em paralelo com 2/3).
4. Tarefa 3 (reivindicação) — depende de 1 e 2.
5. Tarefa 5 (Regra de reconciliação) — depende de 1 (mapa de discos já existe, sem dependência directa do índice, mas mantém-se depois por afinidade de feature).
6. Tarefa 6 (Job) — depende de 2, 4 e 5.
7. Tarefa 7 (Schedule + ArchTest) — depende de 6.
8. Tarefa 8 (documentação) — última, cobre tudo.

## Testes a escrever

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| Repository — bloqueio + listagem de presos | unit | `EloquentRepositorioPipelineDocumentoTest.php` | `bloquearProximoPendente()` e `listarDocumentosPresos()` |
| Reivindicação — sucesso/sem candidatos | unit | `ReivindicarDocumentoPendenteActionTest.php` | Fluxo feliz + `null` |
| Reivindicação — concorrência real (2 conexões) | feature | `ReivindicarDocumentoPendenteConcorrenciaTest.php` | Exclusão mútua via `lockForUpdate()` |
| Regra de reconciliação — 3 cenários | unit | `RegraReconciliarLocalizacaoFicheiroTest.php` | Coerente / localizado noutro disco / perdido |
| Job de reconciliação — 3 cenários + negativo | unit | `ReconciliarFicheirosJobTest.php` | CA-05, CA-06 |
| Config `pipeline.php` | unit | `ConfigPipelineTest.php` | Default + override `.env` |
| ArchTest — Jobs implementam `ShouldQueueAfterCommit` | arch | `ArchTest.php` | RN-01/CA-02 |

## Dependências

- Issues bloqueantes: nenhuma.
- Deve ser implementada após: nenhuma (esta issue é o pré-requisito da issue futura do orquestrador).

## Riscos de implementação

> Consolidados do Brief e da Spec.
- Teste de concorrência real com duas conexões MySQL é o primeiro do projecto — pode exigir
  ajuste de configuração de conexões de teste (`config/database.php`, `phpunit.xml`) para expor
  uma segunda conexão apontada à mesma BD de teste isolada por worker (`findocprocessor_testing_test_N`,
  ver `04-infra/ambiente-docker.md`). Validar cedo (Tarefa 3) antes de assumir o padrão nas
  restantes tarefas.
- Reposição automática (RN-06) altera dados de produção sem confirmação humana — se a causa raiz
  da inconsistência original persistir (ex.: disco intermitente), o Job pode entrar em ciclo de
  correcções repetidas. Fora do âmbito desta issue mitigar isso (não há requisito de
  circuit-breaker no Brief/Spec), mas o log estruturado (Tarefa 6) permite detectar o padrão.
- `RegraReconciliarLocalizacaoFicheiro` lê o conteúdo completo de cada ficheiro candidato para
  calcular o hash (sem `hash_file` directo em discos `local`, que aceitaria caminho, porque os
  discos são abstraídos por `Storage::disk()`) — aceitável para o volume esperado de documentos
  presos (não a tabela toda, ver RNF-04), mas documentar como não escalável se os discos
  migrarem para `s3` no futuro (custo de `GetObject` completo por candidato, não só `HeadObject`).

## O que NÃO fazer nesta issue

- Não implementar o orquestrador de IA nem os Jobs concretos que invocam
  `MarcarEnviado`/`MarcarAguardaResposta`/`TransicionarProcessado`/`MarcarErro`/`MarcarPerigoso`.
- Não alterar `ExecutorTransicaoDocumento` nem `RegraMoverFicheiro` além da extracção do mapa
  estado→disco para reutilização (Tarefa 5) — o mecanismo de compensação best-effort mantém-se.
- Não implementar canal de alerta/notificação a operadores — o log estruturado é o único
  mecanismo de sinalização desta issue.
- Não tornar a frequência do `Schedule::everyFiveMinutes()` configurável — só o limiar de
  "documento preso" é configurável (decisão da Spec).
