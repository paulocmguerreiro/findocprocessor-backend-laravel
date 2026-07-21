# System Spec — Infra: Extração via IA (Prism)

> `app/Infrastructure/AI/`

Construção do system prompt implementada (`PromptBuilder`, ver `04-infra/prompt-builder.md`). Acesso a LLM via **Prism** (`prism-php/prism`), provider-agnóstico, configurado. Cliente concreto do pipeline de extração (`ClienteExtracaoIAPrism`) implementado, ver secção dedicada abaixo.

---

## Implementado

| Componente | Ficheiro | Estado |
|---|---|---|
| `PromptBuilder` — construção do system prompt | `04-infra/prompt-builder.md` | implementado |
| `config/prism.php` — providers Prism (Ollama local + OpenAI-compatible cloud) | `config/prism.php` | implementado |
| `ClienteIAInterface`/`ClienteExtracaoIAPrism` — cliente do pipeline de extração | `app/Infrastructure/AI/` | implementado |

## Prism — camadas LLM opcionais

Os LLM correm **externos à app** (Ollama local, provider cloud), nunca embebidos no processo PHP. Duas camadas, cada uma **opcional/desligável**, aferida pela **presença das suas env vars** (sem flags dedicados) — config incompleta ⇒ camada inactiva (fail-safe). Ver `06-config.md` para o detalhe de `config/extracao.php`.

| Camada | Provider Prism (configurável) | Env vars | Defaults |
|---|---|---|---|
| Local | `LLM_LOCAL_PROVIDER` (`Prism\Prism\Enums\Provider::from()`) | `LLM_LOCAL_PROVIDER`, `LLM_LOCAL_URL`, `LLM_LOCAL_MODEL` | `ollama` |
| Cloud | `LLM_CLOUD_PROVIDER` (`Prism\Prism\Enums\Provider::from()`) | `LLM_CLOUD_PROVIDER`, `LLM_CLOUD_URL`, `LLM_CLOUD_MODEL`, `LLM_CLOUD_KEY` | `anthropic` |

O provider por camada **não é fixo no código** — é lido de `config('extracao.local.provider')`/
`config('extracao.cloud.provider')` e resolvido para `Prism\Prism\Enums\Provider` em runtime.
Trocar de provider cloud (ex.: Anthropic → OpenRouter) é só `.env`, sem alterações de código —
`ClienteExtracaoIAPrism` passa `url`/`api_key` como override a `Prism::structured()->using()`, por
cima dos defaults de `config/prism.php` para esse provider (`config/prism.php` já não acopla os
seus blocos `openai`/`ollama` a `LLM_CLOUD_*`/`LLM_LOCAL_*` — usa as env vars nativas do provider,
ex. `OPENAI_URL`, `OLLAMA_URL`). Risco residual: só cobre providers já conhecidos nativamente pelo
Prism (`Provider` backed enum) — um provider fora dessa lista exige `PrismManager::extend()`.

## `ClienteExtracaoIAPrism` — cliente do pipeline de extração

Implementação de `ClienteIAInterface` (`app/Infrastructure/AI/ClienteIAInterface.php`): contrato
`extrair(string $textoExtraido, CamadaIA $camada): ResultadoExtracaoIA`. Nunca propaga excepções —
qualquer falha ao montar o pedido, invocar o Prism ou resolver o veredicto é convertida em
`ResultadoExtracaoIA::falhaTecnica()`. Bind em `AppServiceProvider`:
`$this->app->bind(ClienteIAInterface::class, ClienteExtracaoIAPrism::class)`.

| Componente | Ficheiro | Papel |
|---|---|---|
| `ClienteIAInterface` (interface) | `ClienteIAInterface.php` | Contrato único, sem `@throws` — excepções sempre capturadas |
| `ClienteExtracaoIAPrism` (implementação) | `ClienteExtracaoIAPrism.php` | Chamada Prism (`structured()`), resolução de veredicto |
| `CamadaIA` (enum) | `CamadaIA.php` | `Local`/`Cloud` — identifica a sub-árvore de `config('extracao.*')` a usar; decisão de qual camada invocar é sempre do chamador |
| `ResultadoExtracaoIA` (Value Object) | `ResultadoExtracaoIA.php` | Construtor privado + 5 named constructors (`completo`/`desconhecido`/`perigoso`/`incompleto`/`falhaTecnica`); propriedades `public readonly`, sem getters (sem lógica associada à leitura) |
| `VeredictoExtracaoIA` (enum) | `VeredictoExtracaoIA.php` | Estado interno do VO — `Completo`/`Desconhecido`/`Perigoso`/`Incompleto`/`FalhaTecnica` |

### Prompt: system prompt + nonce anti prompt-injection

`ClienteExtracaoIAPrism` monta o system prompt via `PromptBuilder::novo()->comInstrucoesBase()
->comEmpresaMae()->comTiposDocumento()->construir()` e o prompt de utilizador com o
`$textoExtraido` envolto num nonce aleatório de 32 caracteres (`Str::random(32)`), gerado por
chamada: `<{nonce}>...</{nonce}>`, com uma frase de reforço a explicitar que o conteúdo entre as
tags é dado a extrair, nunca uma instrução — mesmo que pareça uma ordem dirigida ao modelo. O nonce
concreto (não um marcador genérico) é o que impede o texto do documento de conter uma sequência de
fecho previsível.

### Schema Prism — `ObjectSchema` raiz obrigatória

`Prism::structured()->withSchema()` usa um `ObjectSchema` raiz (`classificacao_extracao`) com
`tipo_documento` (obrigatório), `motivo`/`data_documento`/`valor` (nullable) e `fornecedor`/
`cliente` (`ObjectSchema` aninhado `{nif, nome}`, nullable) — estrutura exigida pelo modo strict de
alguns providers (ex. OpenAI). Todos os campos de domínio são nullable ao nível do schema — a
validação de completude é feita depois, em `resolverVeredicto()`, não pelo schema.

### Resolução do veredicto — ordem exacta

1. `tipo_documento === "perigoso"` → `perigoso($motivo)` (precedência sobre qualquer outro campo
   presente na resposta).
2. `tipo_documento` não resolúvel via `TipoDocumento::where('nome', ...)->first()` →
   `desconhecido()`.
3. Validação de completude por `espera_*` do `TipoDocumento` resolvido (`espera_data_documento`,
   `espera_fornecedor`, `espera_cliente`, `espera_valor`) — qualquer campo esperado em falta ou
   inválido acumula um motivo em `motivosFalta`; se não vazio → `incompleto($motivosFalta)`.
4. Caso contrário → `completo(...)`, com `idCategoria` **sempre** derivado de
   `$tipoDocumento->id_categoria` — nunca lido de um eventual campo `categoria` na resposta do
   modelo (fonte de verdade na app, não em texto potencialmente manipulado).

Validação de NIF (`fornecedor`/`cliente`) genérica e deliberadamente simples — comprimento 5–20
caracteres (sem espaços) e `ctype_alnum()`, sem checksum por país; casos concretos por país ficam
para análise futura.

### Testes sem rede real

`ClienteExtracaoIAPrismTest` usa `Prism::fake()` + `StructuredResponseFake` — cobre os 5
veredictos, o caso `espera_fornecedor=false` sem `fornecedor` (ainda `Completo`), e usa
`$fake->assertRequest()` para confirmar o nonce concreto e o system prompt do `PromptBuilder` no
pedido efectivamente montado. Sem chamada de rede em nenhum teste.

---

## Orquestração

`ClienteExtracaoIAPrism` continua um serviço puro — não escreve em BD, não decide qual camada
invocar, não monta `TransicionarProcessadoDocumentoDto` nem chama Actions de transição. Quem decide
`CamadaIA::Local`/`Cloud` e encaminha o veredicto são `ProcessarAnaliseIaLocalDocumentoAction` e
`ProcessarAnaliseCloudDocumentoAction`; a reconciliação NIF/Nome→`Entidade` (find-or-create) é
`RegraReconciliarEntidadesDocumento`; a gravação do resultado é `ConcluirExtracaoDocumentoAction`
(veredicto completo) via `TransicionarProcessadoDocumentoAction`. Ver
`01-features/documento-pipeline.md` ("Orquestradores de etapa") para o detalhe.

**Modelo de destino do resultado:** cada orquestrador de etapa grava o resultado do passo via
`RegistarEtapaExtracaoAction` — upsert em `App\Models\ExtracaoDocumento` (`texto_extraido`,
`dados_json`) + `EtapaDocumento` (`resultado`; o passo é o `estado` actual do `Documento`). Ver
`03-models/extracao-documento.md` e `01-features/documento.md`.
