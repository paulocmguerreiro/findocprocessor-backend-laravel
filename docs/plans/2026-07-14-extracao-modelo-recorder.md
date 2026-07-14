# Plano: Extração — registo de passos de IA + histórico unificado (model + recorder)

**Issue:** #94
**Spec:** docs/specs/2026-07-14-extracao-modelo-recorder.md
**Data:** 2026-07-14

## Tarefas

### Tarefa 1 — Enums `EtapaExtracao` + `ResultadoEtapa`

- Ficheiros a criar:
  - `app/Shared/Enums/EtapaExtracao.php` — backed string, 6 casos: `Pendente`, `NecessitaOcr`,
    `TextoPronto`, `NecessitaCloud`, `Concluido`, `Falhado` (values `UPPER_SNAKE`).
  - `app/Shared/Enums/ResultadoEtapa.php` — backed string, 3 casos: `Sucesso`, `Falha`, `EmCurso`.
- O que implementar: enums puros, sem lógica. `App\Shared\Enums` já está no `ignoring()` global do
  ArchTest (`toBeFinal` não aplicável a enums).
- Testes associados: nenhum ficheiro dedicado (mesmo padrão de `EstadoDocumento`/`TipoMovimento` —
  sem teste próprio); cobertura vem do uso nas Tarefas 2, 3 e 5 (Factories + Action exercitam todos
  os casos).
- Commit: `feat(extracao): enums EtapaExtracao + ResultadoEtapa`

---

### Tarefa 2 — Tabela, Model e Factory `ExtracaoDocumento`

- Ficheiros a criar:
  - `database/migrations/2026_07_14_100000_create_extracoes_documento_table.php`
  - `app/Models/ExtracaoDocumento.php`
  - `database/factories/ExtracaoDocumentoFactory.php`
- O que implementar:
  - Migration: `id` uuid PK; `id_documento` uuid `->unique()->constrained('documentos')
    ->cascadeOnDelete()`; `etapa_extracao` string(50) default `'PENDENTE'`; `extracao_reclamada_em`
    timestamp nullable; `extracao_tentativas` unsignedTinyInteger default 0; `texto_extraido`
    longText nullable; `dados_json` json nullable; `timestamps()`; índice composto
    `->index(['etapa_extracao', 'extracao_reclamada_em'])`.
  - Model: `#[Table('extracoes_documento')]`, `#[Fillable([...])]`, `HasFactory`, `HasUuids`, **sem**
    `RegistaActividade`; `casts()` → `etapa_extracao: EtapaExtracao::class`,
    `extracao_reclamada_em: 'datetime'`, `extracao_tentativas: 'integer'`; `@property-read` completo
    (incl. `?Carbon $extracao_reclamada_em`, `?string $texto_extraido`, `?array $dados_json`);
    relação `documento(): BelongsTo`.
  - Factory: `definition()` base = `Pendente`, `extracao_tentativas: 0`, resto `null`; states
    `necessitaOcr()`, `textoPronto()`, `necessitaCloud()`, `concluido()`, `falhado()` (um por caso
    restante de `EtapaExtracao`) + `reclamada()` (`extracao_reclamada_em: now()`, para testar o
    campo de lease mesmo sem orquestrador).
- Testes associados:
  - `tests/Unit/Models/ExtracaoDocumentoTest.php` — casts correctos (`etapa_extracao` →
    `EtapaExtracao`, `extracao_reclamada_em` → `Carbon`, `extracao_tentativas` → `int`); relação
    `documento()`; `id_documento` único (índice) — tentar criar duas linhas para o mesmo documento
    lança `QueryException`; `cascadeOnDelete` — eliminar o `Documento` remove a `ExtracaoDocumento`.
  - Cada state da Factory instanciado pelo menos uma vez (cobertura).
- Commit: `feat(extracao): tabela + Model + Factory ExtracaoDocumento`

---

### Tarefa 3 — `etapas_documento` ganha `passo`/`resultado`

- Ficheiros a criar/alterar:
  - `database/migrations/2026_07_14_100100_add_passo_resultado_to_etapas_documento_table.php` —
    `Schema::table('etapas_documento', ...)` com `$table->string('passo', 50)->nullable()->after('estado')`
    e `$table->string('resultado', 20)->nullable()->after('passo')`.
  - `app/Models/EtapaDocumento.php` — `#[Fillable]` ganha `'passo'`, `'resultado'`; `casts()` ganha
    `'passo' => EtapaExtracao::class`, `'resultado' => ResultadoEtapa::class` (nullable — Eloquent
    resolve `null` sem exigir caso "vazio" do enum); `@property-read ?EtapaExtracao $passo`,
    `@property-read ?ResultadoEtapa $resultado`.
  - `database/factories/EtapaDocumentoFactory.php` — novo state `passoIa(EtapaExtracao $passo =
    EtapaExtracao::NecessitaOcr, ResultadoEtapa $resultado = ResultadoEtapa::Sucesso)` →
    `$this->state(['passo' => $passo, 'resultado' => $resultado])`.
- O que implementar: colunas nullable — sem alterar linhas existentes (ficam `null`/`null`, isto é,
  linha de negócio, exactamente o comportamento actual). Nenhuma migration de dados necessária.
- Testes associados:
  - `tests/Unit/Models/EtapaDocumentoTest.php` (se não existir, criar) ou extensão do teste
    existente mais próximo — cast de `passo`/`resultado`, incluindo o caso `null` (linha de
    negócio) e o caso preenchido (linha de IA via `passoIa()`).
- Commit: `feat(extracao): colunas passo/resultado em etapas_documento`

---

### Tarefa 4 — `Documento::extracao()` (relação `HasOne`)

- Ficheiros a alterar:
  - `app/Models/Documento.php` — método `extracao(): HasOne` (`$this->hasOne(ExtracaoDocumento::class,
    'id_documento')`); `@property-read ?ExtracaoDocumento $extracao`.
- O que implementar: relação simples, sem `withDefault()` — `null` é valor legítimo (documento
  nunca entrou na dimensão de extração, ex.: registo manual).
- Testes associados:
  - Extensão de `tests/Unit/Features/Documento/*` ou teste de Model dedicado — `Documento::factory()
    ->create()->extracao` é `null` sem linha associada; com `ExtracaoDocumento::factory()->for($documento,
    'documento')->create()`, a relação devolve a instância correcta.
- Commit: `feat(extracao): relação Documento::extracao()`

---

### Tarefa 5 — Recorder: `RegistarEtapaExtracaoDto` + `RegistarEtapaExtracaoAction`

- Ficheiros a criar:
  - `app/Features/Documento/RegistarEtapaExtracao/RegistarEtapaExtracaoDto.php`
  - `app/Features/Documento/RegistarEtapaExtracao/RegistarEtapaExtracaoAction.php`
- O que implementar:
  - DTO `final readonly class` (padrão Value Object, `02-shared/padroes-dtos.md`): `EtapaExtracao
    $etapaExtracao`, `ResultadoEtapa $resultado`, `?string $motivo = null`, `?string $textoExtraido =
    null`, `?array $dadosJson = null`, `bool $reclamar = false`, `bool $incrementarTentativas =
    false`. Construtor valida: `$resultado === ResultadoEtapa::Falha` exige `$motivo` não-vazio
    (lança `\InvalidArgumentException`). **Sem** `fromRequest()` — VO interno, nunca originado de
    HTTP (mesmo padrão de `FicheiroDocumentoDto`/`ResultadoReconciliacaoFicheiro`).
  - Action `final readonly class`, injecta `CacheServico` directo (sem interface — mesmo critério
    de `02-shared/padroes-acoes.md`). `handle(Documento $documento, RegistarEtapaExtracaoDto
    $dados): ExtracaoDocumento`, **sem `Gate::authorize`** (acção de sistema, RNF-02), dentro de
    `DB::transaction()`:
    1. `ExtracaoDocumento::query()->updateOrCreate(['id_documento' => $documento->id], ['etapa_extracao'
       => $dados->etapaExtracao, 'extracao_reclamada_em' => $dados->reclamar ? now() : null,
       'texto_extraido' => $dados->textoExtraido, 'dados_json' => $dados->dadosJson])` — **cada
       chamada substitui totalmente `texto_extraido`/`dados_json`**; o chamador (futuro
       orquestrador) é responsável por enviar sempre o valor completo pretendido, não deltas
       (documentar este contrato no PHPDoc da Action).
    2. Se `$dados->incrementarTentativas`, `$extracao->increment('extracao_tentativas')`.
    3. `$documento->historico()->create(['estado' => $documento->status, 'passo' =>
       $dados->etapaExtracao, 'resultado' => $dados->resultado, 'motivo' => $dados->motivo,
       'id_utilizador' => null])`.
    4. `$this->cache->invalidarCache(TagCache::Documentos)`.
    5. Devolve `$extracao->refresh()`.
  - `@throws \Throwable` no PHPDoc (obrigatório, `DB::transaction()`).
- Testes associados:
  - `tests/Unit/Features/Documento/RegistarEtapaExtracaoDtoTest.php` — `Falha` sem `motivo` lança
    `\InvalidArgumentException`; `Sucesso`/`EmCurso` sem `motivo` é válido.
  - `tests/Unit/Features/Documento/RegistarEtapaExtracaoActionTest.php`:
    - happy path: primeira chamada cria a linha `extracoes_documento` + `EtapaDocumento` com
      `passo`/`resultado` preenchidos e `estado` igual ao `status` actual do `Documento`.
    - reinvocação (upsert): segunda chamada para o mesmo documento actualiza a linha existente
      (sem duplicar), e cria uma **segunda** `EtapaDocumento` (append-only mantém-se).
    - `incrementarTentativas: true` em duas chamadas sucessivas → `extracao_tentativas` chega a 2.
    - `reclamar: true` → `extracao_reclamada_em` preenchido; `reclamar: false` (default) → `null`.
    - rollback: forçar excepção entre os passos 1 e 3 (ex.: `EtapaDocumento::creating(fn () =>
      throw ...)`) e confirmar que nem a `ExtracaoDocumento` nem a `EtapaDocumento` ficam
      persistidas (nenhuma alteração parcial).
    - sem `Gate::authorize` — executa sem utilizador autenticado (fora da matriz de autorização,
      `07-testing.md`).
- Commit: `feat(extracao): recorder RegistarEtapaExtracaoAction`

---

### Tarefa 6 — Resources: `EtapaDocumentoResource` + `DocumentoResource`

- Ficheiros a alterar:
  - `app/Features/Documento/EtapaDocumentoResource.php` — adiciona `'passo' => $this->passo?->value,
    'resultado' => $this->resultado?->value`.
  - `app/Features/Documento/DocumentoResource.php` — adiciona `'etapa_extracao' =>
    $this->whenLoaded('extracao', fn (): ?string => $this->extracao?->etapa_extracao->value)`.
    **Não** adicionar `texto_extraido`/`dados_json` a nenhum Resource.
- O que implementar: array shape do PHPDoc de `toArray()` actualizado em ambos os Resources.
- Testes associados:
  - `tests/Unit/Features/Documento/DocumentoResourceTest.php` (extensão) — `etapa_extracao` ausente/`null`
    quando a relação não está carregada ou é `null`; presente quando carregada com
    `ExtracaoDocumento`. Confirma explicitamente que `texto_extraido`/`dados_json` **nunca** aparecem
    no array serializado (RNF-01), mesmo tendo a relação carregada com esses campos preenchidos.
  - Extensão dos testes que já cobrem `EtapaDocumentoResource` (via `VerDocumentoTest`/`DocumentoResourceTest`)
    para `passo`/`resultado` (presentes numa linha de IA, `null` numa linha de negócio).
- Commit: `feat(extracao): expõe etapa_extracao/passo/resultado nos Resources (sem PII)`

---

### Tarefa 7 — Ripple: reset de `extracoes_documento` em `ReprocessarDocumentoAction`

- Ficheiros a alterar:
  - `app/Features/Documento/Reprocessar/ReprocessarDocumentoAction.php`
- O que implementar: `handle()` passa a abrir a sua própria `DB::transaction()` — dentro dela, chama
  `$this->executor->executar(...)` (transacção aninhada/`SAVEPOINT`, `ExecutorTransicaoDocumento`
  **não é alterado**) e, de seguida, `ExtracaoDocumento::query()->where('id_documento',
  $documentoReaberto->id)->update([...Pendente, reclamada_em: null, tentativas: 0, texto/dados:
  null])`. Usa **`update()`**, nunca `create()`/`upsert()` — se não existir linha (documento nunca
  entrou na dimensão de extração, ex.: erro de scan de malware em `Pendente`), a query afecta 0
  linhas e não cria nenhuma. `@throws \Throwable` já implícito por `DB::transaction()` (novo).
- Testes associados (extensão de `ReprocessarDocumentoActionTest.php` e `ReprocessarDocumentoTest.php`):
  - Documento com `ExtracaoDocumento` existente (ex.: `Falhado`, `tentativas: 2`, `texto_extraido`
    preenchido) → após reprocessar, linha resetada (`Pendente`, `tentativas: 0`,
    `texto_extraido: null`, `dados_json: null`, `extracao_reclamada_em: null`).
  - Documento **sem** `ExtracaoDocumento` (erro de scan de malware em `Pendente`) → reprocessar não
    cria linha nova; `Documento::extracao` continua `null`.
  - Rollback: forçar excepção depois da transição de estado (ex.: no `update()` da
    `ExtracaoDocumento`, mockando o Model ou via model event) e confirmar que a transição de
    `Documento`/`EtapaDocumento` também é revertida (atomicidade RN-03) — reutilizar o padrão de
    teste de rollback já usado nas outras Actions de transição.
- Commit: `feat(extracao): ReprocessarDocumentoAction reseta extracoes_documento`

---

### Tarefa 8 — Documentação `system_spec`

- Ficheiros a criar/alterar:
  - `docs/system_spec/03-models/extracao-documento.md` — **novo** (Model, migration, Factory)
  - `docs/system_spec/03-models/etapa-documento.md` — colunas `passo`/`resultado`, Factory `passoIa()`
  - `docs/system_spec/03-models/documento.md` — relação `extracao()`
  - `docs/system_spec/02-shared/enums.md` — `EtapaExtracao`, `ResultadoEtapa`
  - `docs/system_spec/02-shared/estados.md` — secção "modelo de 2 dimensões" (tabela de mapeamento
    da issue: estado de negócio × etapa de extração × lock)
  - `docs/system_spec/01-features/documento.md` — `RegistarEtapaExtracaoAction` na tabela de
    Actions (sem HTTP); ripple em `ReprocessarDocumentoAction`
  - `docs/system_spec/04-infra/queue-jobs.md` — nota: `RegistarEtapaExtracaoAction` é o ponto de
    invocação programática que o futuro orquestrador (#97/#98) vai chamar
  - `docs/system_spec/04-infra/external-apis.md` — referência ao novo modelo na tabela de
    integrações planeadas
  - `docs/system_spec/00-index.md` — linha nova em "Modelos Eloquent" (`ExtracaoDocumento`) +
    contagem de Actions da feature `Documento` actualizada (17)
- Commit: `docs(system_spec): extração — model + recorder (#94)`

## Ordem de implementação

1. Tarefa 1 (enums) — sem dependências, usado por todas as tarefas seguintes.
2. Tarefa 2 (`ExtracaoDocumento`) — depende do enum `EtapaExtracao` (Tarefa 1).
3. Tarefa 3 (`etapas_documento` +colunas) — depende dos 2 enums (Tarefa 1); independente da Tarefa 2,
   mas implementada a seguir por partilhar o tema "histórico".
4. Tarefa 4 (`Documento::extracao()`) — depende do Model `ExtracaoDocumento` (Tarefa 2).
5. Tarefa 5 (recorder) — depende de 1, 2, 3 e 4 (usa `ExtracaoDocumento`, os 2 enums, e
   `$documento->historico()`).
6. Tarefa 6 (Resources) — depende de 3 e 4 (lê `passo`/`resultado` e a relação `extracao`).
7. Tarefa 7 (ripple `ReprocessarDocumentoAction`) — depende de 2 (`ExtracaoDocumento`); implementada
   depois do recorder (Tarefa 5) por partilhar o padrão de teste de reset.
8. Tarefa 8 (docs) — sempre por último, reflecte o estado final.

## Testes a escrever

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| `ExtracaoDocumentoTest` | unit | `tests/Unit/Models/ExtracaoDocumentoTest.php` | Casts, relação `documento()`, unicidade `id_documento`, `cascadeOnDelete` |
| `EtapaDocumentoTest` | unit | `tests/Unit/Models/EtapaDocumentoTest.php` | Casts `passo`/`resultado` (incl. `null`) |
| `RegistarEtapaExtracaoDtoTest` | unit | `tests/Unit/Features/Documento/RegistarEtapaExtracaoDtoTest.php` | Invariante `Falha` exige `motivo` |
| `RegistarEtapaExtracaoActionTest` | unit | `tests/Unit/Features/Documento/RegistarEtapaExtracaoActionTest.php` | Upsert, append-only do histórico, tentativas, lease, rollback, sem Gate |
| `DocumentoResourceTest` (extensão) | unit | `tests/Unit/Features/Documento/DocumentoResourceTest.php` | `etapa_extracao` presente/ausente; PII nunca exposta |
| `ReprocessarDocumentoActionTest` (extensão) | unit | `tests/Unit/Features/Documento/ReprocessarDocumentoActionTest.php` | Reset com/sem linha existente; rollback atómico |
| `ReprocessarDocumentoTest` (extensão) | feature | `tests/Feature/Features/Documento/ReprocessarDocumentoTest.php` | Equivalente via HTTP |

## Dependências

- Issues bloqueantes: nenhuma.
- Deve ser implementada após: nenhuma (`config/extracao.php` já existe, #95).
- Pré-requisito de: orquestrador de pipeline (#97/#98) — quem invoca `RegistarEtapaExtracaoAction`
  em produção.

## Riscos de implementação

> Consolidados do Brief (`## Riscos identificados`) e da Spec — não apagar riscos do Brief.

- RGPD/PII: `texto_extraido`/`dados_json` nunca em Resource (Tarefa 6 tem teste explícito
  anti-regressão).
- Índice composto `(etapa_extracao, extracao_reclamada_em)` sem consumidor real nesta issue —
  aceite, mesmo padrão do índice `(status, updated_at)` antes do #90.
- Concorrência do lease: esta issue só grava/limpa `extracao_reclamada_em` — sem
  `lockForUpdate()`/reivindicação real (fica para #97/#98). Não implementar reivindicação parcial.
- Reset em `ReprocessarDocumentoAction` exige a sua própria `DB::transaction()` nova — risco de
  regressão na Action existente e testada; mitigado por testes de rollback explícitos (Tarefa 7).
- Contrato "substituição total" de `texto_extraido`/`dados_json` no recorder (sem merge/patch) —
  documentado no PHPDoc da Action (Tarefa 5) para o futuro orquestrador não assumir semântica de
  delta.

## O que NÃO fazer nesta issue

- Não implementar o orquestrador, Jobs ou comandos `Schedule` que consomem
  `extracao_reclamada_em`/`etapa_extracao` em produção — fica para #97/#98.
- Não implementar `lockForUpdate()`/reivindicação real sobre `extracoes_documento` — só a coluna e
  o campo existem.
- Não implementar o *enforcement* do tecto de `extracao_tentativas` (transição automática a `Erro`
  ao esgotar) — a coluna só regista o contador.
- Não alterar `TransicionarProcessadoDocumentoAction` nem implementar a purga de `texto_extraido`
  após `PROCESSADO` — diferido para #97/#98 (decisão confirmada no Brief).
- Não alterar `EstadoDocumento`, `RegraTransicaoEstado` nem `ExecutorTransicaoDocumento`.
- Não criar endpoint HTTP nem `FormRequest`/`Controller` para `RegistarEtapaExtracaoAction` — é
  invocação puramente programática (mesmo padrão dos `Marcar*`).
