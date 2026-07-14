# Brief: Extração — registo de passos de IA + histórico unificado (model + recorder)

**Issue:** #94
**Data:** 2026-07-14
**Branch:** feat/extracao-modelo-recorder

## Contexto

O pipeline de extração por IA (PdfParser → Tesseract OCR → LLM Local → LLM Cloud, #95) vai correr
de forma assíncrona via `Schedule`, num orquestrador ainda por implementar (#97/#98). Antes desse
orquestrador, é preciso fixar **o modelo de dados** que regista onde está cada documento no
pipeline de extração e o histórico de tudo o que a IA faz — para o utilizador ver, num único feed
cronológico, tanto as transições de negócio do `Documento` (`EstadoDocumento`, 7 casos) como os
passos internos da IA.

**Insight de desenho (da issue):** o passo da IA é **ortogonal** ao estado de negócio — os
micro-passos `parser → ocr → ia-local → ia-cloud` ocorrem todos enquanto o `Documento` está em
`AGUARDA_RESPOSTA`; mudar de passo não implica transição de negócio. Por isso são **duas dimensões
independentes**, e o `EstadoDocumento` (`app/Shared/Enums/EstadoDocumento.php`, 7 casos, mapa de
transições em `RegraTransicaoEstado`) **não é tocado** por esta issue.

Esta issue cobre apenas **modelo + recorder** — o orquestrador e os comandos `Schedule` que
consomem este modelo ficam para a issue seguinte.

## O que muda

- **2 enums novos** em `app/Shared/Enums/`: `EtapaExtracao` (backed string, 6 casos duráveis —
  `Pendente`, `NecessitaOcr`, `TextoPronto`, `NecessitaCloud`, `Concluido`, `Falhado`) e
  `ResultadoEtapa` (backed string — `Sucesso`, `Falha`, `EmCurso`).
- **Tabela nova `extracoes_documento`** (relação 1-1 com `documentos`, FK `id_documento` **UNIQUE**,
  `cascadeOnDelete`) + índice composto `(etapa_extracao, extracao_reclamada_em)` para o futuro
  SELECT do Schedule. A tabela `documentos` fica **inalterada**.
- **Model novo `App\Models\ExtracaoDocumento`** — `HasUuids`, **sem** `RegistaActividade` (dados
  operacionais/PII, mesmo padrão de `EtapaDocumento`).
- **`etapas_documento` ganha 2 colunas nullable** (`passo` cast `EtapaExtracao`, `resultado` cast
  `ResultadoEtapa`) — tabela append-only mantém-se; `NULL` em ambas = linha de negócio (como hoje).
  `estado` continua **não-nulo** em todas as linhas (invariante preservado).
- **Action nova `RegistarEtapaExtracaoAction`** (recorder, `app/Features/Documento/...`) — dentro
  de `DB::transaction()`: upsert em `extracoes_documento` + `historico()->create()` com
  `passo`/`resultado` preenchidos + invalidação de `TagCache::Documentos`. **Não** passa pelo
  `ExecutorTransicaoDocumento` (que valida `De→Para` de negócio e move ficheiro — inaplicável aqui,
  já que esta Action nunca muda `EstadoDocumento`).
- **Ripple:**
  - `Documento` ganha relação `extracao(): HasOne` (+ `@property-read`).
  - `EtapaDocumento` ganha `passo`/`resultado` em `@property-read`, `#[Fillable]`, `casts()`.
  - Factories: `ExtracaoDocumentoFactory` (novo, com states pelos 6 casos de `EtapaExtracao`);
    `EtapaDocumentoFactory` ganha state `passoIa()` (`passo`/`resultado` preenchidos).
  - `EtapaDocumentoResource` ganha `passo`, `resultado`. `DocumentoResource` expõe `etapa_extracao`
    via `whenLoaded('extracao')` — **nunca** expõe `texto_extraido`/`dados_json` (PII).
  - `ReprocessarDocumentoAction` (`Erro → AguardaEnvio`) reseta a linha `extracoes_documento`
    (`texto_extraido`/`dados_json` a `null`, `etapa_extracao = Pendente`, `tentativas = 0`).
- **system_spec:** novo `03-models/extracao-documento.md`; actualizar `03-models/etapa-documento.md`,
  `03-models/documento.md`, `02-shared/enums.md`, `02-shared/estados.md` (modelo de 2 dimensões),
  `04-infra/queue-jobs.md`, `04-infra/external-apis.md`, `00-index.md`.

## O que NÃO muda

- `EstadoDocumento` (7 casos) e o mapa `RegraTransicaoEstado` — inalterados.
- `ExecutorTransicaoDocumento` e as 6 Actions de transição (`Marcar*`,
  `TransicionarProcessadoDocumentoAction`) — não são tocadas; continuam a gravar `EtapaDocumento`
  com `passo`/`resultado` a `null` (linha de negócio).
- Nenhum orquestrador, Job ou comando `Schedule` real de pipeline — fica para a issue seguinte
  (#97/#98), que é quem efectivamente reclama (`extracao_reclamada_em`) e avança `etapa_extracao`.
- Nenhuma rota HTTP nova — `RegistarEtapaExtracaoAction` é invocada apenas programaticamente
  (mesmo padrão de sistema das transições de pipeline, sem `Gate::authorize`).
- Nenhum cliente Prism real (chamada ao LLM + parsing) — isso é a issue #97.
- `texto_extraido`/`dados_json` só saem preenchidos quando o recorder for chamado pelo futuro
  orquestrador; nesta issue os testes preenchem-nos directamente via Factory/Action.

## Riscos identificados

- **RGPD/PII:** `texto_extraido` (`longText`) e `dados_json` (`json`) guardam o conteúdo extraído do
  documento (pode conter NIF, nomes, valores). Mitigação já decidida na issue: tabela **sem**
  `RegistaActividade`, campos fora do `DocumentoResource`/list resource, sem `EtapaDocumentoResource`
  a expor `texto_extraido`/`dados_json` (só `motivo`, que já existia e já é tratado como
  potencialmente sensível em `EtapaDocumento`).
- **Índice composto sem consumidor imediato:** `(etapa_extracao, extracao_reclamada_em)` é
  desenhado para o SELECT do Schedule (#97/#98), que ainda não existe nesta issue — risco de ficar
  sem cobertura de teste de performance real até essa issue; aceitável, é o padrão já usado por
  `ReconciliarFicheirosJob` (índice `(status, updated_at)` também chegou antes do consumidor, #90).
- **Concorrência do lease (`extracao_reclamada_em`):** esta issue só cria a coluna; o
  `lockForUpdate()`/TTL de libertação em crash é comportamento do orquestrador (#97/#98) — sem
  Action de reivindicação nesta issue (paralelo a `ReivindicarDocumentoPendenteAction`, #90, mas
  para a dimensão de extração). Confirmar no Plano que não se está a implementar reivindicação
  parcial sem o lock real.
- **Nullable enum casts em `etapas_documento`:** `passo`/`resultado` são colunas nullable com cast
  para enum — Laravel resolve nativamente (`null` fica `null`, sem exigir enum "vazio"); Larastan
  precisa do `@property-read ?EtapaExtracao`/`?ResultadoEtapa` correcto para não gerar `mixed`.
- **Reset em `ReprocessarDocumentoAction`:** a Action já existe e é `final readonly` só com
  `ExecutorTransicaoDocumento` injectado; adicionar o reset da `extracoes_documento` implica tocar
  numa Action existente e testada (`ReprocessarDocumentoActionTest`) — risco de regressão se o
  reset não for atómico com a transição (mesma `DB::transaction()`).

## Questões em aberto — resolvidas

- **TTL do lease e tecto de tentativas:** `config/extracao.php` (issue #95) já tem `ttl_lease`
  (`EXTRACAO_TTL_LEASE`, default 300s) e `max_tentativas` (3). Esta issue reutiliza estes valores
  para `extracao_tentativas` (coluna `unsignedTinyInteger`, cabe 3 perfeitamente); a coluna só
  regista o contador — **enforcement** do tecto (o que fazer ao esgotar: `Erro` automático vs
  revisão manual) fica para o orquestrador (#97/#98), fora do âmbito do recorder.
- **Nome da tabela/modelo — decidido:** `extracoes_documento` / `App\Models\ExtracaoDocumento`
  (recomendação da issue — evita colisão com `EstadoDocumento`/`EtapaDocumento` já existentes).
- **Purgar `texto_extraido`/`dados_json` após `PROCESSADO` — decidido: diferir.** Implicaria tocar
  em `TransicionarProcessadoDocumentoAction` (fora do "Ripple" listado na issue #94) ou um Job de
  limpeza separado — nenhum dos dois está no âmbito de "model + recorder". Fica para a issue do
  orquestrador (#97/#98); documentar esta decisão explicitamente no Spec.
