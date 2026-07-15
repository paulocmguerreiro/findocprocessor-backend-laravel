# Brief: feat(laravel): Extração — cliente IA via Prism (local+cloud, nonce, structured output)

**Issue:** #97
**Data:** 2026-07-15
**Branch:** feat/extracao-cliente-ia

## Contexto

Terceira das 4 issues do mecanismo de extração por IA (`#95` infra → `#96` extractores de texto →
**`#97` cliente IA** → futura Issue IV orquestração). O `texto_extraido` (produzido pelos
extractores de `#96`, `app/Infrastructure/Extracao/`) precisa de ser convertido em dados
estruturados que completem o `Documento` (`idFornecedor`, `idCliente`, `idCategoria`, `valor`,
`dataDocumento` — o mesmo conjunto exigido por `TransicionarProcessadoDocumentoDto`). Esta issue
implementa esse cliente: monta o pedido (system prompt via `PromptBuilder` de `#88` + nonce-wrapping
do conteúdo do documento), invoca o Prism (camada local Ollama ou cloud OpenAI-compatible, já
configuradas em `#95` — `config/prism.php` + `config/extracao.php`) com **structured output**
dirigido pelos flags `espera_*` do `TipoDocumento` classificado, e devolve um veredicto tipado
(completo/desconhecido/perigoso/incompleto/falha técnica). Não decide nem persiste nada — a
reconciliação NIF+Nome→`Entidade`, a montagem do `TransicionarProcessadoDocumentoDto` e a transição
de estado ficam para a Issue IV (orquestrador).

## O que muda

- **Novo serviço** em `app/Infrastructure/AI/` (ex.: `ClienteExtracaoIA`) — recebe `texto_extraido` +
  o `nonce` de submissão, monta o schema Prism (`ObjectSchema`/`StringSchema`/`NumberSchema`) via
  `Prism\Prism\Schema\*`, invoca `Prism::structured()->using(Provider::Ollama|Provider::OpenAI, $modelo)`
  consoante a camada pedida pelo chamador (parâmetro explícito — o orquestrador da Issue IV decide
  local vs cloud, não este serviço), e faz parsing/validação da resposta.
- **Nonce-wrapping**: geração de um identificador aleatório único por submissão (ex.:
  `Str::random()`/`Str::uuid()`) e envolvimento do `texto_extraido` em `<{nonce}>…</{nonce}>` antes de
  o anexar ao prompt — mitigação de prompt-injection alinhada com a regra IV de
  `base_instructions.txt`. `PromptBuilder` actual não tem este método (secção "Fora de âmbito" do
  próprio `04-infra/prompt-builder.md` confirma-o) — precisa de um novo método
  (`comDocumento(string $texto, string $nonce)` ou equivalente) ou de o wrapping ser feito no próprio
  `ClienteExtracaoIA` sem tocar no `PromptBuilder`. Ver "Questões em aberto".
- **Schema condicional ao `TipoDocumento`**: os 4 campos opcionais (`data_documento`, `fornecedor`,
  `cliente`, `valor`) só entram no schema Prism (e na validação de completude) quando o
  `TipoDocumento` classificado tem o `espera_*` correspondente a `true` — mesmo mapa já usado por
  `PromptBuilder::comTiposDocumento()` (RN-04 de `04-infra/prompt-builder.md`). `fornecedor`/`cliente`
  são objectos `{nif, nome}` (ambos obrigatórios quando o tipo espera essa entidade).
- **Veredicto tipado** (DTO/enum novo, ex.: `VeredictoExtracaoIA` + `ResultadoExtracaoIA`):
  `completo` (dados normalizados + `TipoDocumento` resolvido + categoria derivada),
  `desconhecido` (`tipo_documento` não resolúvel para nenhum `TipoDocumento::nome`), `perigoso`
  (regra 7 do prompt), `incompleto` (falta um campo `espera_*=true`, NIF/Nome incompleto, ou formato
  inválido), e falha técnica (excepção do Prism — timeout, erro de rede, resposta não-JSON).
- **Timeouts por camada** — `config('prism.request_timeout')` é global (default 30s); a issue exige
  "timeouts por camada" configuráveis, o que implica ou usar `withClientOptions(['timeout' => ...])`
  por chamada com um novo valor de config por camada (`config/extracao.php` só tem
  `camada_local_activa`/`camada_cloud_activa`, sem timeout dedicado), ou aceitar o timeout global do
  Prism como suficiente. Ver "Questões em aberto".
- **Testes**: `Prism::fake()` com `StructuredResponseFake` — sem rede real, sem depender de Ollama/
  OpenAI a correr. Padrão dual (`tests/Unit/Infrastructure/AI/ClienteExtracaoIATest.php`, mesmo
  desvio de `PromptBuilder` — sem par HTTP, sem Controller/rota).
- **system_spec**: `04-infra/external-apis.md` (mover a linha da tabela "Integrações planeadas" para
  "Implementado"), `04-infra/prompt-builder.md` (se o nonce-wrapping ficar no `PromptBuilder`) +
  `00-index.md`.

## O que NÃO muda

- **Reconciliação NIF+Nome→`Entidade`** (find-or-create) — Issue IV.
- **Empresa mãe / `posicao_empresa_mae`** já é resolvida por `PromptBuilder::comEmpresaMae()` (dados
  injectados no prompt); esta issue não adiciona lógica de leitura desse campo — apenas consome o
  prompt já construído.
- **Montagem do `TransicionarProcessadoDocumentoDto`** e qualquer transição de estado do `Documento`
  (`ExecutorTransicaoDocumento`/`RegraTransicaoEstado`) — Issue IV.
- **`RegistarEtapaExtracaoAction`** (recorder já implementado em `#94`) não é alterado — o chamador
  futuro (orquestrador) é que grava `dados_json`/`etapa_extracao` usando o veredicto desta issue.
- **Extracção de texto** (parser/OCR) — `#96`, já implementado, apenas consumido como input
  (`texto_extraido`).
- **`prism-php/prism` e `config/prism.php`/`config/extracao.php`** — infra já publicada em `#95`; esta
  issue só lê a config existente, não adiciona providers novos.

## Riscos identificados

- **Tensão `espera_*` ↔ `TransicionarProcessadoDocumentoDto`** (já sinalizada na issue): o DTO da
  Issue IV exige hoje `idFornecedor`/`idCliente`/`idCategoria` não-vazios + `valor≥0` +
  `dataDocumento` sempre — um `TipoDocumento` pode ter `espera_fornecedor=false`, por exemplo. Esta
  issue define a completude **apenas** pelos `espera_*` (RN-02 de `03-models/tipo-documento.md`
  garante que pelo menos um é sempre `true`); a eventual flexibilização do DTO fica registada como
  decisão em aberto para a Issue IV — não é resolvida aqui.
- **`ObjectSchema` como raiz obrigatória para strict mode OpenAI** (confirmado em
  `vendor/prism-php/prism/docs/core-concepts/schemas.md`) — o schema construído tem de ter
  `ObjectSchema` no topo mesmo quando só um subconjunto de campos é exigido; schema dinâmico por
  `TipoDocumento` tem de respeitar isto em qualquer combinação de `espera_*`.
- **NIF sem validação de dígito de controlo** — a issue exige "validar formato do NIF" mas o Model
  `Entidade` armazena `nif` como `string(255)` livre, sem validador de NIF português em nenhum ponto
  actual do código (grep confirma zero `RegraValidarNif`/`NifValidator` no repo). Definir o nível de
  validação aqui (ex.: 9 dígitos numéricos) sem inventar uma regra de negócio nova fora do âmbito —
  ver "Questões em aberto".
- **Prism `Provider::Ollama` usa o provider nativo `ollama`, não o `openai`** — `config/prism.php`
  regista `providers.ollama.url` (sem `api_key`) e `providers.openai.url`/`api_key` para a camada
  cloud (comentários no ficheiro confirmam-no: "`Camada cloud ... provider 'openai'`" /
  "`Camada local ... 'ollama'`"). O cliente tem de escolher `Provider::Ollama` para local e
  `Provider::OpenAI` para cloud — não uma camada configurável por nome livre.
- **`camada_local_activa`/`camada_cloud_activa` só verificam presença de env vars** — não expõem o
  **nome do modelo** (`LLM_LOCAL_MODEL`/`LLM_CLOUD_MODEL`) via `config/extracao.php`; o cliente
  precisa de ler `env()` directamente (fora de um ficheiro de config, contra a convenção Laravel) ou
  `config/extracao.php` ganha novas chaves (`local.modelo`, `cloud.modelo`) nesta issue. Ver
  "Questões em aberto".
- **Testes sem rede real dependem de `Prism::fake()` + `StructuredResponseFake`** — confirmado
  disponível em `vendor/prism-php/prism/src/Testing/` e documentado; risco baixo, mas a asserção do
  payload enviado (nonce presente, schema correcto) tem de usar `$fake->assertRequest(...)` — API
  ainda não usada em nenhum teste deste repo (primeira vez).

## Questões em aberto

Nenhuma — todas resolvidas no Checkpoint A (2026-07-15):

1. **Onde vive o nonce-wrapping** — no `ClienteExtracaoIA`, não no `PromptBuilder`. Justificação do
   utilizador: é o `ClienteExtracaoIA` que sabe como usar o prompt e organizá-lo para o envio à IA
   (o `PromptBuilder` mantém-se limitado à construção do *system prompt*; o nonce-wrapping do
   `texto_extraido` é uma decisão de composição do pedido, não do prompt em si). `PromptBuilder` não
   ganha método novo — a nota "fora de âmbito" em `04-infra/prompt-builder.md` mantém-se para
   `withDocumento()`.
2. **Timeout por camada** — aceitar `config('prism.request_timeout')` global do Prism (30s, único).
   Sem novas env vars `LLM_LOCAL_TIMEOUT`/`LLM_CLOUD_TIMEOUT` nesta issue.
3. **Exposição do nome do modelo por camada** — `config/extracao.php` ganha as chaves do modelo por
   camada (lidas de `LLM_LOCAL_MODEL`/`LLM_CLOUD_MODEL`), para o `ClienteExtracaoIA` nunca chamar
   `env()` directamente.
4. **Nível de validação do NIF** — regra **genérica e sem lógica por país**: não vazio (trim) +
   comprimento entre 5–20 caracteres + alfanumérico. Cobre PT/Irlanda/EUA sem checksum nem formato
   dedicado. Decisão explícita do utilizador: **não** entrar em detalhes de implementação por país
   (PT mod-11, EIN, etc.) nesta issue — validação por país fica para uma issue futura dedicada, se
   vier a ser necessária.
5. **Interface pública do cliente** — `ClienteExtracaoIA` **com interface** (ex.: `ClienteIA`),
   prevendo substituição futura (troca de motor/abordagem fora do Prism). Desvio face a
   `PromptBuilder` (sem interface) — justificado porque aqui há substituição prevista, o critério de
   `02-shared/padroes-acoes.md` ("há mais do que uma implementação plausível?").
