# Brief: Extração — orquestradores Schedule (`extracao:*`) sobre máquina de estados unificada

**Issue:** #111
**Data:** 2026-07-17
**Branch:** feat/extracao-orquestradores-schedule

## Contexto

Última issue do mecanismo de extracção por IA. Todas as peças já existem e são **puras/isoladas**:
os extractores de texto (`ExtractorTextoNativo`/`ExtractorOcr`, #96), o cliente IA
(`ClienteExtracaoIAPrism`/`ContratoClienteIA`, #97), o analisador de malware
(`ContratoAnalisadorMalware`/`ClamAvAnalisadorMalware`, #90/#91), o recorder
(`RegistarEtapaExtracaoAction`, #94), as 8 Actions de transição + reivindicação/triagem, e a máquina
de estados **unificada** de 9 estados (#110, já merged). Nenhuma delas está ligada: os motores não
tocam BD nem decidem transições, e as Actions de transição não invocam motores.

Falta a **cola** — os orquestradores agendados (`Schedule`) que, a cada ciclo, seleccionam
`Documento`s por `estado`, os reivindicam (lease + `lockForUpdate`), resolvem o caminho absoluto do
ficheiro, chamam o motor da etapa, interpretam o resultado, registam o micro-passo
(`RegistarEtapaExtracaoAction`) e disparam a Action de transição adequada. É o que fecha o pipeline
`Pendente → … → Processado|Erro|Perigoso` end-to-end.

### Dois pontos de entrada no scan (contexto de domínio, confirmado pelo utilizador)

1. **Registo manual** (`RegistarDocumentoManualAction`, já existe) — o documento é submetido a scan e,
   se limpo, vai **directo a `Processado`** (não entra no pipeline de extracção). **Fora do âmbito
   dos Commands `extracao:*`** — corrobora a CA-09 (documento manual é ignorado pelos orquestradores,
   porque nunca fica em `Pendente`/estados de análise).
2. **Upload** (`ReceberUploadDocumentoAction`, já existe) — cria o documento em `Pendente` e é **este**
   que percorre o pipeline automático: `Pendente → scan → AnaliseTexto (parser) → [AnaliseOcr] →
   AnaliseIaLocal → [AnaliseCloud] → Processado`. É este ponto de entrada que os Commands `extracao:*`
   conduzem.

### Reconciliação de entidades e empresa mãe (esclarecido pelo utilizador)

A flag `posicao_empresa_mae` (`Fornecedor`/`Cliente`) diz **em que lado** está a empresa mãe — um
**singleton** garantido por `RegraUnicidadeEmpresaMae` (`Entidade` com `e_empresa_aplicacao=true`,
marcada também `e_cliente=true`+`e_fornecedor=true`). Um lado com `espera_*=false` **não** é uma
entidade normal a extrair: a contraparte real (banco/entidade que só aparece por nome em
avisos/extratos) **não existe na app nem deve** ser criada. A reconciliação resolve, **por lado**
(fornecedor e cliente, independentemente):

| Condição do lado | Resolução | `id_<lado>` |
|---|---|---|
| é o lado `posicao_empresa_mae` | empresa mãe (`whereEmpresaAplicacao()->firstOrFail()`) | preenchido |
| `espera_<lado>=true` (contraparte) | find-or-create de `Entidade` por `nif` (do `ResultadoExtracaoIA`) | preenchido |
| `espera_<lado>=false` e **não** é o lado da empresa mãe | não cria entidade | **`null`** |

Exemplos: **fatura de fornecedor** (posição=cliente→empresa mãe; fornecedor `espera=true`→find-or-create)
resolve ambos; **extrato** (posição=cliente→empresa mãe; fornecedor `espera_fornecedor=false`→`null`,
pois o banco só aparece por nome) deixa `id_fornecedor` a `null`.

**Correcção à análise anterior:** a validação de `TipoDocumento` (`Criar`/`Actualizar`) só impede que
os **quatro** `espera_*` sejam falsos ao mesmo tempo — **não** impede `espera_fornecedor=false` +
`espera_cliente=false` em simultâneo, nem cruza `posicao_empresa_mae` com os `espera_*`. Logo o caso
"lado a `null`" **existe** (extrato/aviso) → a flexibilização do `TransicionarProcessadoDocumentoDto`
**é necessária** (ver Questões em aberto #2), e é **mais ampla** do que a issue enunciou (não só
fornecedor/cliente).

## O que muda

**Camada de lógica (`app/Features/Documento/`)** — Actions orquestradoras, uma por etapa, finas o
suficiente para conterem só a sequência «reivindica → resolve caminho → chama motor → interpreta →
regista → transiciona», sem lógica de motor (que continua em `app/Infrastructure/`):
- Orquestrador da etapa `AnaliseTexto` (parser nativo; `>threshold` → `AnaliseIaLocal`, senão →
  `AnaliseOcr`; **não-PDF salta o parser** → `AnaliseOcr`).
- Orquestrador da etapa `AnaliseOcr` (Tesseract; sucesso → `AnaliseIaLocal`).
- Orquestrador da etapa `AnaliseIaLocal` (`ContratoClienteIA` camada `Local`).
- Orquestrador da etapa `AnaliseCloud` (`ContratoClienteIA` camada `Cloud`).
- Orquestrador da etapa de malware — **reutiliza** `ReivindicarDocumentoPendenteAction` +
  `TriarDocumentoPendenteAction` (§ Questões em aberto — decisão de colapso).
- Reconciliação de entidades (lado da empresa mãe → singleton `e_empresa_aplicacao`; lado contraparte
  → find-or-create de `Entidade` por NIF; posicionamento por `TipoDocumento.posicao_empresa_mae`) +
  construção do `TransicionarProcessadoDocumentoDto`.
- Guarda de camada LLM activa/inactiva (`config('extracao.local.activa')`/`cloud.activa`), com
  encaminhamento de salto (local inactiva → `AnaliseCloud`; cloud inactiva → `Erro`).
- Tecto de tentativas técnicas (`extracao_tentativas`, 3ª falha da mesma etapa → `Erro`; **reset a 0
  sempre que transita correctamente para a frente, nunca em `Erro`** — regra do utilizador; saltos
  semânticos/de camada **não** contam como falha técnica).

**Reivindicação por lease (`extracao_reclamada_em`)** — enforcement real do lease/TTL sobre
`ExtracaoDocumento` (hoje só existe a coluna + índice, sem consumidor), com limites por etapa (OCR e
IA-local = 1 doc/ciclo; parser e cloud em lote), preservando o padrão de concorrência de #90
(`lockForUpdate` + `WithoutOverlapping` + Jobs `after_commit`).

**Console / Schedule (`app/Console/Commands/` + `routes/console.php`)** — **5 Commands `extracao:*`**,
um por estado activo do pipeline (`Pendente`/scan, `AnaliseTexto`/parser, `AnaliseOcr`/tesseract,
`AnaliseIaLocal`/ia-local, `AnaliseCloud`/ia-cloud; os estados terminais não têm schedule). Finos (só
dispatch para a Action orquestradora, chamada **sincronamente**, como os Controllers), agendados
(`scan`/`parser`/`tesseract`/`ia-local` `everyMinute()`, `ia-cloud` `everyFiveMinutes()`, todos
`->withoutOverlapping()`). **Concorrência em 2 camadas:** `withoutOverlapping()` impede o mesmo command
sobrepor-se a si próprio; o lease (`extracao_reclamada_em` TTL) + `lockForUpdate` garante unicidade
**por documento** (dois workers nunca processam o mesmo documento; um doc com lease activo não é
reapanhado). Não há Job-por-documento com `WithoutOverlapping($idDocumento)` — o lease já dá essa
garantia.

**DTO + naming** — `TransicionarProcessadoDocumentoDto` **flexibilizado**: `idFornecedor`,
`idCliente`, `valor` e `dataDocumento` passam a **nullable**, cada um gated pelo respectivo `espera_*`
(invariante mínima: o lado da empresa mãe está sempre preenchido; `idCategoria` continua obrigatório,
vem de `TipoDocumento.id_categoria`). `TransicionarProcessadoDocumentoAction` deixa de fazer
`findOrFail` incondicional. `RegraNomearProcessado` ganha **fallbacks** (política decidida pelo
utilizador): fornecedor `null` → usar o **nome extraído** (`nomeFornecedor`) sem criar `Entidade`
(fallback para o nome da empresa mãe se o extraído vier vazio); data `null` → usar `created_at` do
documento como prefixo. Isto obriga a **passar o nome extraído e o `created_at`** ao naming (a
assinatura de `RegraNomearProcessado` deixa de assumir fornecedor/data sempre presentes).

**Infra Docker / config** — serviço `scheduler` (`php artisan schedule:work`) em `compose.yaml`;
`.env.example` completo; nova secção no `README.md` a documentar cada variável.

**Upload — tipos de imagem + limite de tamanho (decidido pelo utilizador).**
- **Tipos aceites alargados** para OCR directo: além de `application/pdf`/`image/jpeg`/`image/png`,
  passam a ser aceites **`image/tiff`, `image/bmp`, `image/webp`**. Toca no `mimetypes` de
  `ReceberUploadDocumentoRequest` e em `FILESYSTEM_ALLOWED_EXTENSIONS` (config/.env).
- **Limite de upload 10 MB → 50 MB.** Cascata de alterações **obrigatórias** (senão 50 MB não passa
  ou falha o scan):
  - `ReceberUploadDocumentoRequest`: `max:10240` → `max:51200`; `.env`/`06-config.md`
    `FILESYSTEM_MAX_FILE_SIZE` → `52428800`.
  - **PHP (Dockerfile `zz-findoc.ini`):** acrescentar `upload_max_filesize=50M` + `post_max_size=52M`
    (hoje ausentes → defaults 2M/8M; o limite Laravel de 10 MB já hoje os excede — a subida corrige a
    incoerência). **nginx já tem `client_max_body_size 50M`** — sem alteração.
  - **ClamAV (`clamd.conf` próprio montado no serviço `clamav`):** `StreamMaxLength`/`MaxScanSize`/
    `MaxFileSize` ≥ 50 MB (defaults ~25 MB ficam abaixo → ficheiros >25 MB falhariam o INSTREAM →
    `Erro`). Actualizar a nota correspondente em `04-infra/malware.md`/`06-config.md`.
- **Encaminhamento de imagem:** detecção de não-PDF no orquestrador de `AnaliseTexto` (via extensão de
  `nome_ficheiro_storage`, que o `hashName()` do upload preserva) → salto directo para `AnaliseOcr`
  (Tesseract sobre a imagem), saltando o parser nativo.

**system_spec** — `04-infra/queue-jobs.md`, `04-infra/ambiente-docker.md`, `02-shared/estados.md`,
`06-config.md`, `04-infra/malware.md` (limites de tamanho do `clamd`), `04-infra/extracao-texto.md`
(novos formatos de imagem), `01-features/documento-pipeline.md`, `01-features/documento.md` (upload:
tipos + limite) + `00-index.md`.

## O que NÃO muda

- **Máquina de estados** (9 estados, mapa de transições, state objects) — congelada por #110; esta
  issue só a **conduz**, não a altera.
- **Modelo/tabelas** (#94) — sem migrations novas; `extracoes_documento`/`etapas_documento`/
  `documentos` mantêm-se. (A flexibilização do DTO não é uma alteração de schema — `id_fornecedor`/
  `id_cliente` já são nullable no `Documento`.)
- **Motores de `app/Infrastructure/`** (#96/#97/#90) — continuam puros (sem BD, sem estado, sem
  `Documento`); esta issue chama-os, não os reescreve.
- **Infra/pacotes/config de extracção** (#95) — sem dependências Composer novas; sem novos providers.
- **Agrupar/fundir entidades duplicadas** — é a #99 (fora de âmbito); aqui só find-or-create por NIF.
- **Recorder** (`RegistarEtapaExtracaoAction`) — contrato "substituição total" mantém-se; só passa a
  ter um chamador real.

## Riscos identificados

- **`ExtracaoDocumento` inexistente na 1ª reivindicação de uma etapa.** Um `Documento` que entra em
  `AnaliseTexto` pela primeira vez **não tem** linha em `extracoes_documento` (só é criada pelo
  recorder). O lease (`extracao_reclamada_em`) vive nessa linha → reivindicar exige criar/`upsert` a
  linha ao reclamar, sob `lockForUpdate`, sem colidir com o contrato "substituição total" do recorder.
- **Reset do contador de tentativas ao mudar de etapa.** `extracao_tentativas` é uma coluna única por
  documento (não por etapa). Como o tecto é "3ª falha **da mesma etapa**", o contador tem de ser
  reposto a 0 na transição de etapa — caso contrário falhas acumuladas de etapas anteriores empurram
  prematuramente para `Erro`. Definir onde (recorder? transição? orquestrador) sem duplicar lógica.
- **Naming canónico assume `fornecedor` e `data` não-nulos.** `RegraNomearProcessado` gera
  `yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.ext` — precisa de `dataDocumento->format()` e
  `nomeFornecedor`. Em documentos onde o fornecedor é `null` (extrato) ou a data é `null`
  (`espera_data=false`), o naming actual parte. A flexibilização do DTO **tem** de vir acompanhada de
  uma política de fallback do nome canónico (Questões em aberto #2).
- **Resolução da empresa mãe assume o singleton existente.** A reconciliação faz
  `Entidade::whereEmpresaAplicacao()->firstOrFail()`. Se nenhuma entidade estiver marcada como empresa
  mãe (base ainda não configurada), o `firstOrFail` lança → o documento vai a `Erro`. Aceitável
  (config em falta é erro operacional, não silencioso), mas a mensagem de erro tem de ser clara.
- **`config:cache` congela `local.activa`/`cloud.activa`.** As flags derivam de `filled(env(...))` em
  load-time (documentado em `06-config.md`). Um `scheduler` a correr com config cacheada não vê
  alterações a `LLM_*` sem `config:clear` — o serviço `scheduler` do `compose.yaml` e o `README` têm
  de o tornar explícito.
- **Duplicação de `Entidade` por NIF/Nome imperfeitos.** Find-or-create por NIF cria entidades a mais
  quando o modelo devolve NIF/nome ligeiramente diferentes — risco aceite, mitigado pela #99; aqui só
  garantir idempotência estrita por `nif` exacto.
- **Lease partilhado depende de cache/locks atómicos.** `withoutOverlapping` e o lease por TTL
  assumem `CACHE_STORE=redis` (store partilhado) — num store por-processo (`array`/`file`) deixam de
  proteger entre workers reais (já documentado em `config/pipeline.php`).
- **Concorrência real só se testa com 2 conexões MySQL.** O teste de lease/`lockForUpdate` tem de
  seguir o padrão já usado no teste de reivindicação (#90) — não basta uma conexão em memória.
- **Delegates de imagem TIFF/WEBP/BMP.** O `imagick` (ImageMagick) e o `Tesseract`/Leptonica da imagem
  têm de ter os delegates dos novos formatos. O Dockerfile instala `imagick` via
  `install-php-extensions` e `tesseract-ocr` via `apk` — sem garantir `libtiff`/`libwebp`. Verificar
  `magick -list format` / capacidades do Leptonica no container; se em falta, `apk add` das libs.
  Sem delegate, o upload aceita o ficheiro mas o OCR falha → `Erro`.
- **Limite de 50 MB versus recursos do scan/OCR.** Ficheiros de 50 MB aumentam o tempo de INSTREAM
  (ClamAV) e de rasterização/OCR (imagick+tesseract, sensível em M1 8GB) — reforça a necessidade do
  lease com TTL folgado e dos limites por etapa (OCR = 1 doc/ciclo).

## Questões em aberto

Nenhuma — todas resolvidas no Checkpoint A (ver secção seguinte).

### Decidido pelo utilizador (Checkpoint A)

- **Reset de `extracao_tentativas`:** reposto a 0 **sempre que o documento transita correctamente
   para a frente** (qualquer avanço de etapa que não seja para `Erro`); **nunca** reposto numa
   transição para `Erro`. Local de implementação a definir na Spec (candidato natural: no
   `ExecutorTransicaoDocumento`, o funil único de todas as transições, ao entrar num estado de análise
   não-terminal — evita espalhar a regra pelos 4 orquestradores). Em `Erro` a linha
   `extracoes_documento` é eliminada por `RegraEliminarExtracaoTerminal`, pelo que o contador
   desaparece de qualquer modo.
- **Colapso da etapa de malware (confirmado):** o Command de scan selecciona por `Pendente` e
   reutiliza `ReivindicarDocumentoPendenteAction` + `TriarDocumentoPendenteAction` — sem caminho novo
   que seleccione por `AnaliseMalware` (que nunca é estado de repouso).
- **Flexibilização do DTO (confirmada):** `idFornecedor`/`idCliente`/`valor`/`dataDocumento` nullable,
   gated pelo `espera_*`; reconciliação por lado (empresa mãe no lado `posicao_empresa_mae`;
   find-or-create por NIF no lado `espera=true`; `null` no lado `espera=false` que não é empresa mãe).
- **Política do nome canónico (`RegraNomearProcessado`):** fornecedor `null` → usar `nomeFornecedor`
   extraído **sem criar `Entidade`** (fallback: nome da empresa mãe se o extraído vier vazio); data
   `null` → usar `created_at` do documento como prefixo `yyyy-mm-dd`.
- **`TipoDocumento` fora de âmbito:** a variedade de tipos é tratada no orquestrador/DTO; qualquer
   endurecimento de validação de `TipoDocumento` é tratado à parte pelo utilizador.
- **Duplicação de entidades:** mitigada posteriormente pela issue #99 (agrupar entidades duplicadas).
