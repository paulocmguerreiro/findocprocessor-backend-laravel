# Plano: refactor(laravel): unificar máquina de estados do Documento (fundir EtapaExtracao em EstadoDocumento)

**Issue:** #110
**Spec:** docs/specs/2026-07-16-unificar-maquina-estados-documento.md
**Data:** 2026-07-16

## Tarefas

### Tarefa 1 — Núcleo do domínio: enum, migrations, models, state objects, regras, factories

Tarefa fundacional e deliberadamente grande — `EstadoDocumento` é consumido por praticamente todo
o ficheiro da feature `Documento` (models, states, regras, ~20 Actions, ~30 ficheiros de teste);
não há forma de a partir em sub-tarefas que deixem `composer test` verde a meio do caminho. As
tarefas seguintes assumem esta base já estável.

- Ficheiros a criar/alterar:
  - `app/Shared/Enums/EstadoDocumento.php` — 9 cases: `Pendente, AnaliseMalware, AnaliseTexto,
    AnaliseOcr, AnaliseIaLocal, AnaliseCloud, Processado, Erro, Perigoso`.
  - `app/Shared/Enums/EtapaExtracao.php` — eliminar.
  - `database/migrations/2026_07_16_XXXXXX_remove_etapa_extracao_from_extracoes_documento_table.php`
    — `dropColumn('etapa_extracao')`; `dropIndex` do composto `(etapa_extracao,
    extracao_reclamada_em)`; novo índice simples em `extracao_reclamada_em`.
  - `database/migrations/2026_07_16_XXXXXX_remove_passo_from_etapas_documento_table.php` —
    `dropColumn('passo')`.
  - `app/Models/Documento.php` — `estado()` match exaustivo sobre os 9 cases (novos state objects).
  - `app/Models/ExtracaoDocumento.php` — remove cast/`@property-read`/`#[Fillable]` de
    `etapa_extracao`.
  - `app/Models/EtapaDocumento.php` — remove cast/`@property-read`/`#[Fillable]` de `passo`.
  - `app/Shared/States/DocumentoAnaliseMalware.php`, `DocumentoAnaliseTexto.php`,
    `DocumentoAnaliseOcr.php`, `DocumentoAnaliseIaLocal.php`, `DocumentoAnaliseCloud.php` — novos,
    grupo "Parciais" (mesmos getters de `DocumentoPendente`: `obterNomeFicheiroOriginal()`,
    `obterHashSha256()` além dos 4 comuns).
  - `app/Shared/States/DocumentoAguardaEnvio.php`, `DocumentoEnviado.php`,
    `DocumentoAguardaResposta.php` — eliminar.
  - `app/Features/Documento/Transicao/RegraTransicaoEstado.php` — `transicoesPermitidas()`
    reescrito conforme RF-03 da Spec (grafo dos 9 estados).
  - `app/Features/Documento/Transicao/RegraMoverFicheiro.php` — `discoParaEstado()` reescrito
    conforme RF-04.
  - `database/factories/DocumentoFactory.php` — states renomeados/ajustados para os 9 casos.
  - `database/factories/ExtracaoDocumentoFactory.php` — remove states baseados em `EtapaExtracao`
    (`necessitaOcr()`, `textoPronto()`, `necessitaCloud()`, `concluido()`, `falhado()`); mantém
    `reclamada()`; adiciona states relevantes ao scratch space (ex.: `comDadosExtraidos()` para
    popular `texto_extraido`/`dados_json`, `comTentativas(int $n)`).
  - `database/factories/EtapaDocumentoFactory.php` — `passoIa()` perde o parâmetro `passo`; passa
    a `passoIa(ResultadoEtapa $resultado = Sucesso)`, só define `resultado`.
- O que implementar: ver acima — é essencialmente um rename/reshape em cadeia a partir do novo
  enum. Nenhuma lógica de negócio nova nesta tarefa (isso vem nas tarefas seguintes).
- Testes associados:
  - `tests/Unit/States/EstadoDocumentoStatesTest.php` — reescrito para os 9 estados.
  - `tests/Unit/Models/DocumentoTest.php`, `ExtracaoDocumentoTest.php`, `EtapaDocumentoTest.php` —
    casts/fillable actualizados.
  - `tests/Unit/Features/Documento/RegraTransicaoEstadoTest.php`,
    `RegraMoverFicheiroTest.php` — todas as combinações De→Para do novo grafo/mapa de discos.
  - Varredura de todo o resto da suite `tests/Unit/Features/Documento/` e
    `tests/Feature/Features/Documento/` que instancia `EstadoDocumento::AguardaEnvio` /
    `::Enviado` / `::AguardaResposta` ou usa os states/factories antigos — actualizar para os
    novos nomes (sem alterar a intenção do teste, só a nomenclatura).
- Commit: `refactor(estados): unifica EstadoDocumento em 9 estados, remove EtapaExtracao`

### Tarefa 2 — `ExecutorTransicaoDocumento`: elimina `ExtracaoDocumento` nos 3 estados terminais

- Ficheiros a criar/alterar:
  - `app/Features/Documento/Transicao/ExecutorTransicaoDocumento.php` — dentro da
    `DB::transaction()` já existente, depois de `$documento->update(...)`, se `$novoEstado` for
    `Processado`/`Erro`/`Perigoso`, `ExtracaoDocumento::query()->where('id_documento',
    $documento->id)->delete()`. Sem efeito quando não há linha (delete de 0 linhas não é erro).
- O que implementar: um `match` (ou `in_array`) exaustivo sobre os 3 terminais, decisão RN-03 da
  Spec de centralizar no Executor (não replicar em cada uma das 3 Actions que lá chegam).
- Testes associados: estender `tests/Unit/Features/Documento/TransicionarProcessadoDocumentoActionTest.php`,
  `MarcarErroDocumentoActionTest.php`, `MarcarPerigosoDocumentoActionTest.php` — cada um ganha um
  caso "com `ExtracaoDocumento` existente → linha eliminada" e um caso "sem `ExtracaoDocumento` →
  sem erro". Não é preciso ficheiro de teste novo (não há `ExecutorTransicaoDocumentoTest`
  dedicado — testado sempre indirectamente pelas Actions, padrão já existente no repo).
- Commit: `feat(transicao): elimina ExtracaoDocumento ao entrar num estado terminal (RGPD)`

### Tarefa 3 — Família `Marcar<Estado>DocumentoAction`: novas, renomeada, removidas

- Ficheiros a criar/alterar:
  - Novas: `app/Features/Documento/MarcarAnaliseMalware/MarcarAnaliseMalwareDocumentoAction.php`
    (`Pendente→AnaliseMalware`), `MarcarAnaliseOcr/MarcarAnaliseOcrDocumentoAction.php`
    (`AnaliseTexto→AnaliseOcr`), `MarcarAnaliseIaLocal/MarcarAnaliseIaLocalDocumentoAction.php`
    (`AnaliseTexto|AnaliseOcr→AnaliseIaLocal`), `MarcarAnaliseCloud/MarcarAnaliseCloudDocumentoAction.php`
    (`AnaliseIaLocal→AnaliseCloud`) — mesma estrutura de `MarcarAguardaEnvioDocumentoAction` actual.
  - Renomeada: `app/Features/Documento/MarcarAguardaEnvio/` → `MarcarAnaliseTexto/`,
    `MarcarAguardaEnvioDocumentoAction` → `MarcarAnaliseTextoDocumentoAction`
    (`AnaliseMalware→AnaliseTexto`).
  - Removidas: `app/Features/Documento/MarcarEnviado/`, `app/Features/Documento/MarcarAguardaResposta/`.
- O que implementar: cada Action nova segue o padrão exacto de `MarcarAguardaEnvioDocumentoAction`
  (`final readonly`, injecta `ExecutorTransicaoDocumento`, `handle(Documento $documento, string
  $motivo = '...'): Documento`, sem `Gate::authorize`).
- Testes associados: `tests/Unit/Features/Documento/MarcarAnaliseMalwareDocumentoActionTest.php`
  (+ `MarcarAnaliseOcr...`, `MarcarAnaliseIaLocal...`, `MarcarAnaliseCloud...`), renomear
  `MarcarAguardaEnvioDocumentoActionTest.php` → `MarcarAnaliseTextoDocumentoActionTest.php`,
  eliminar `MarcarEnviadoDocumentoActionTest.php`/`MarcarAguardaRespostaDocumentoActionTest.php`.
- Commit: `refactor(actions): reorganiza família Marcar<Estado>DocumentoAction para os 9 estados`

### Tarefa 4 — Triagem: `TriarDocumentoPendenteAction` admite `AnaliseMalware` antes do scan

- Ficheiros a criar/alterar:
  - `app/Features/Documento/Triar/TriarDocumentoPendenteAction.php` — injecta
    `MarcarAnaliseMalwareDocumentoAction` (novo) além dos já injectados; `handle()` chama primeiro
    `marcarAnaliseMalware->handle($documento)` (`Pendente→AnaliseMalware`), corre o scan sobre o
    `$documento` já em `AnaliseMalware` (mesmo ficheiro, mesmo disco `entrada`), e ramifica para
    `marcarAnaliseTexto`/`marcarPerigoso`/`marcarErro` (injecção de `MarcarAnaliseTextoDocumentoAction`
    em vez de `MarcarAguardaEnvioDocumentoAction`).
- O que implementar: só reordenação/injecção — a lógica de decisão do scan (infectado/limpo/
  desligado/falha) não muda.
- Testes associados: `tests/Unit/Features/Documento/TriarDocumentoPendenteActionTest.php` — os 4
  cenários existentes passam a verificar a passagem intermédia por `AnaliseMalware`;
  `tests/Unit/Features/Documento/ReivindicarDocumentoPendenteActionTest.php` e
  `tests/Feature/Features/Documento/ReivindicarDocumentoPendenteConcorrenciaTest.php` sem alteração
  de intenção, só de estados esperados.
- Commit: `refactor(triar): admite Pendente→AnaliseMalware antes do scan de malware`

### Tarefa 5 — `TransicionarProcessado`/`MarcarErro`/`MarcarPerigoso`: cobertura das novas origens

- Ficheiros a criar/alterar: nenhum ficheiro de produção (a mecânica já delega no
  `RegraTransicaoEstado` da Tarefa 1 — nenhuma das 3 Actions hardcoda a origem). Actualizar só
  PHPDoc das 3 Actions para reflectir as novas origens (`AnaliseIaLocal`/`AnaliseCloud` para
  `TransicionarProcessadoDocumentoAction`; `AnaliseMalware`/`AnaliseTexto`/`AnaliseOcr`/
  `AnaliseIaLocal`/`AnaliseCloud` para `MarcarErroDocumentoAction`/`MarcarPerigosoDocumentoAction`).
- Testes associados: `tests/Unit/Features/Documento/TransicionarProcessadoDocumentoActionTest.php`,
  `MarcarErroDocumentoActionTest.php`, `MarcarPerigosoDocumentoActionTest.php` — acrescentar casos
  para cada nova origem alcançável (verificar que `RegraTransicaoEstado` aceita todas as listadas
  na Spec RF-03).
- Commit: `test(transicao): cobre as novas origens alcançáveis a partir dos estados de análise`

### Tarefa 6 — `ReprocessarDocumentoAction`: simplifica (deixa de gerir `ExtracaoDocumento`)

- Ficheiros a criar/alterar:
  - `app/Features/Documento/Reprocessar/ReprocessarDocumentoAction.php` — remove a
    `DB::transaction()`/`SAVEPOINT` própria e o `ExtracaoDocumento::query()->update(...)`
    condicional; passa a chamar só `$this->executor->executar($documento,
    EstadoDocumento::Pendente, ...)` (grafo `Erro→Pendente`, RF-03). Mantém, por segurança, um
    `ExtracaoDocumento::query()->where('id_documento', $documento->id)->delete()` defensivo e
    idempotente logo a seguir (a linha já deveria ter sido eliminada ao entrar em `Erro` — Tarefa
    2 — mas só chega a valer a pena remover esta rede de segurança depois de a Tarefa 2 estar
    implementada e testada; ordem importa aqui, ver "Riscos de implementação").
- Testes associados: `tests/Unit/Features/Documento/ReprocessarDocumentoActionTest.php` — remove
  os casos de `update()` condicional antigo, acrescenta caso "sem linha de `ExtracaoDocumento` →
  sem erro" e "com linha residual → eliminada"; `tests/Feature/Features/Documento/ReprocessarDocumentoTest.php`
  sem alteração de intenção.
- Commit: `refactor(reprocessar): remove gestão condicional de ExtracaoDocumento (já eliminada em Erro)`

### Tarefa 7 — `RegistarEtapaExtracaoAction`/`Dto`: remove `etapaExtracao`

- Ficheiros a criar/alterar:
  - `app/Features/Documento/RegistarEtapaExtracao/RegistarEtapaExtracaoDto.php` — remove a
    propriedade `etapaExtracao` do construtor.
  - `app/Features/Documento/RegistarEtapaExtracao/RegistarEtapaExtracaoAction.php` — remove a
    chave `'etapa_extracao' => $dados->etapaExtracao` do array de `updateOrCreate()`.
- Testes associados: `tests/Unit/Features/Documento/RegistarEtapaExtracaoActionTest.php`,
  `RegistarEtapaExtracaoDtoTest.php` — remove asserções sobre `etapa_extracao`.
- Commit: `refactor(recorder): remove etapaExtracao do Dto/Action de RegistarEtapaExtracao`

### Tarefa 8 — `DocumentoResource`: remove `etapa_extracao`

- Ficheiros a criar/alterar:
  - `app/Features/Documento/DocumentoResource.php` — remove a chave `etapa_extracao` e a leitura
    `whenLoaded('extracao', ...)` associada do `toArray()`.
- Testes associados: `tests/Unit/Features/Documento/DocumentoResourceTest.php`,
  `tests/Feature/Features/Documento/VerDocumentoTest.php`/`ListarDocumentosTest.php` — remove
  asserções sobre `etapa_extracao` na resposta.
- Commit: `refactor(resource): remove campo etapa_extracao de DocumentoResource`

### Tarefa 9 — `ReconciliarFicheirosJob`: 5 estados transitórios

- Ficheiros a criar/alterar:
  - `app/Jobs/ReconciliarFicheirosJob.php` — lista de estados passada a `scopeWherePresos()` passa
    de `[AguardaEnvio, Enviado, AguardaResposta]` para `[AnaliseMalware, AnaliseTexto, AnaliseOcr,
    AnaliseIaLocal, AnaliseCloud]`.
- Testes associados: teste de `ReconciliarFicheirosJob` (localizar ficheiro exacto na Tarefa —
  provavelmente `tests/Unit/Jobs/ReconciliarFicheirosJobTest.php` ou equivalente em Feature) —
  cobre pelo menos um dos 5 estados novos, não só os 3 antigos.
- Commit: `refactor(reconciliacao): actualiza lista de estados transitórios para os 5 estados de análise`

## Ordem de implementação

1. Tarefa 1 — base de tudo; nada compila/testa sem isto.
2. Tarefa 2 — antes da Tarefa 6, para a simplificação do `ReprocessarDocumentoAction` assentar
   num comportamento já implementado e testado (o risco do Brief é exactamente inverter esta ordem).
3. Tarefa 3 — depende dos state objects/regras da Tarefa 1.
4. Tarefa 4 — depende da Tarefa 3 (`MarcarAnaliseMalwareDocumentoAction`/`MarcarAnaliseTextoDocumentoAction`
   já têm de existir).
5. Tarefa 5 — depende das Tarefas 1 e 3 (novas origens só existem depois de ambas).
6. Tarefa 6 — depende da Tarefa 2 (ver acima).
7. Tarefa 7 — independente das anteriores, só depende da Tarefa 1 (enum).
8. Tarefa 8 — independente, só depende da Tarefa 1.
9. Tarefa 9 — depende da Tarefa 1 (nomes dos 5 estados transitórios).

## Testes a escrever

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| Grafo de 9 estados | Unit | `RegraTransicaoEstadoTest.php` | Todas as arestas permitidas da Spec RF-03; qualquer par fora do mapa lança `TransicaoInvalidaException` |
| Mapa disco→9 estados | Unit | `RegraMoverFicheiroTest.php` | `discoParaEstado()` para cada um dos 9 casos |
| Eliminação de `ExtracaoDocumento` em `Processado` | Unit | `TransicionarProcessadoDocumentoActionTest.php` | Linha eliminada quando existe; sem erro quando não existe |
| Eliminação de `ExtracaoDocumento` em `Erro` | Unit | `MarcarErroDocumentoActionTest.php` | idem |
| Eliminação de `ExtracaoDocumento` em `Perigoso` | Unit | `MarcarPerigosoDocumentoActionTest.php` | idem |
| `TriarDocumentoPendenteAction` passa por `AnaliseMalware` | Unit | `TriarDocumentoPendenteActionTest.php` | Documento fica em `AnaliseMalware` antes de ramificar; disco continua `entrada` |
| `ReivindicarDocumentoPendenteAction` concorrência | Feature | `ReivindicarDocumentoPendenteConcorrenciaTest.php` | Dois workers não reivindicam o mesmo documento (duas conexões MySQL reais) — estados actualizados |
| `ReprocessarDocumentoAction` sem `ExtracaoDocumento` | Unit | `ReprocessarDocumentoActionTest.php` | `Erro→Pendente` sem erro quando a linha já foi eliminada |
| `DocumentoResource` sem `etapa_extracao` | Unit + Feature | `DocumentoResourceTest.php`, `VerDocumentoTest.php` | Campo ausente da resposta |
| `ReconciliarFicheirosJob` cobre 5 estados | Unit/Feature | (localizar ficheiro) | Documento preso em `AnaliseIaLocal`/`AnaliseCloud` é reconciliado |
| `RegistarEtapaExtracaoAction` sem `etapaExtracao` | Unit | `RegistarEtapaExtracaoActionTest.php`, `Dto` | Upsert não referencia `etapa_extracao`; Dto sem a propriedade |

## Dependências

- Issues bloqueantes: nenhuma.
- Deve ser implementada após: nenhuma.
- Bloqueia: #101 (orquestrador de pipeline) — não implementada nesta issue.

## Riscos de implementação

> Consolidados do Brief (`## Riscos identificados`) e da Spec.

- Fusão de duas dimensões numa só aumenta a área de código tocada por transição — a Tarefa 1 é
  deliberadamente grande por isto; não tentar parti-la mais, arrisca deixar o build vermelho entre
  commits.
- Superfície de testes grande — ~30 ficheiros de teste tocados só na Tarefa 1; Larastan 9 apanha
  `match`/`switch` não exaustivo em código de produção, mas não em testes que ainda referenciem
  `EstadoDocumento::AguardaEnvio`/`::Enviado`/`::AguardaResposta` (cases removidos) — isso só falha
  em runtime (`ValueError` do backed enum), correr `composer test` completo no fim da Tarefa 1 é
  obrigatório antes do checkpoint.
- Ordem Tarefa 2 → Tarefa 6 é estrita — implementar a simplificação do `ReprocessarDocumentoAction`
  antes de `ExecutorTransicaoDocumento` eliminar a linha em `Erro` faria os testes de
  `ReprocessarDocumentoAction` passar "por acaso" sem cobrir o caso real.
- Larastan 9 / 100% coverage e type-coverage — qualquer `match` não exaustivo sobre o novo
  `EstadoDocumento` (9 cases) falha imediatamente; é o comportamento desejado, mas aumenta a
  superfície onde pode acontecer.
- WRN-022 pendente (`docs/process-warnings.md`) — `checkpoint:scan` pode repetir os mesmos 4 WARNs
  não relacionados com esta issue; não é um risco novo desta issue.

## O que NÃO fazer nesta issue

- Não implementar o orquestrador real nem os Commands `extracao:*` (#101).
- Não ligar `AnalisarTextoAction`/OCR/IA a nenhum motor real — `AnaliseTexto`/`AnaliseOcr`/
  `AnaliseIaLocal`/`AnaliseCloud` ficam como scaffolding puro (só transição de estado); a única
  excepção é `AnaliseMalware`, que já reutiliza o scan existente (#90/#91) via
  `TriarDocumentoPendenteAction`.
- Não alterar `app/Infrastructure/AI` nem `app/Infrastructure/Extracao`.
- Não alterar nenhuma rota HTTP nem `./openapi.yaml`.
- Não migrar dados de produção — schema recriado via migrations.
- Não alterar o comportamento de `RegistarDocumentoManualAction` (continua a criar directo em
  `Processado`/`Perigoso`/`Erro`, sem `RegraTransicaoEstado`).
- Não documentar `docs/system_spec/*.md` nesta fase — isso é exclusivo da Fase 3a
  (`/documenta-implementacao`).
