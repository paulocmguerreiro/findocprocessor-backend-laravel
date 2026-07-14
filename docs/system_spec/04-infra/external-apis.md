# System Spec — Infra: APIs Externas

> `app/Infrastructure/AI/`, `app/Infrastructure/Malware/`, `app/Infrastructure/Extracao/`

Construção do system prompt implementada (`PromptBuilder`, ver `04-infra/prompt-builder.md`). Acesso a LLM via **Prism** (`prism-php/prism`), provider-agnóstico, configurado; cliente concreto do pipeline de extração (chamada + parsing da resposta) continua pendente.

Scan de malware self-hosted (`ClamAvAnalisadorMalware`) implementado, ver secção dedicada abaixo.

Extractores de texto (nativo + OCR) implementados, ver secção dedicada abaixo.

---

## Implementado

| Componente | Ficheiro | Estado |
|---|---|---|
| `PromptBuilder` — construção do system prompt | `04-infra/prompt-builder.md` | implementado |
| `config/prism.php` — providers Prism (Ollama local + OpenAI-compatible cloud) | `config/prism.php` | implementado |
| `AnalisadorMalware`/`ClamAvAnalisadorMalware` — scan de malware ClamAV | `app/Infrastructure/Malware/` | implementado |
| `ExtractorTextoNativo`/`ExtractorOcr` — extractores de texto de `Documento` | `app/Infrastructure/Extracao/` | implementado |

## Prism — camadas LLM opcionais

Os LLM correm **externos à app** (Ollama local, provider cloud), nunca embebidos no processo PHP. Duas camadas, cada uma **opcional/desligável**, aferida pela **presença das suas env vars** (sem flags dedicados) — config incompleta ⇒ camada inactiva (fail-safe). Ver `06-config.md` para o detalhe de `config/extracao.php`.

| Camada | Provider Prism | Env vars | Config |
|---|---|---|---|
| Local | `ollama` (nativo do Prism) | `LLM_LOCAL_URL`, `LLM_LOCAL_MODEL` | `config/prism.php` → `providers.ollama.url`; sem `api_key` |
| Cloud | `openai` (nativo do Prism, aceita `url` custom — cobre OpenRouter/gateways OpenAI-compatible) | `LLM_CLOUD_URL`, `LLM_CLOUD_MODEL`, `LLM_CLOUD_KEY` | `config/prism.php` → `providers.openai.url` + `api_key` |

Não foi necessário `Prism::extend()` — o provider `openai` nativo aceita `url` custom nativamente (confirmado ao publicar `config/prism.php` via `vendor:publish --tag=prism-config`).

## Malware — ClamAV self-hosted

Scan de malware sobre os ficheiros de `Documento` — **nunca** sai da infra própria (RGPD, sem
serviço de scan de terceiros). Contrato puro em `app/Infrastructure/Malware/`:

| Componente | Ficheiro | Papel |
|---|---|---|
| `AnalisadorMalware` (interface) | `AnalisadorMalware.php` | `analisar(string $caminhoAbsoluto): ResultadoAnaliseMalware` |
| `ResultadoAnaliseMalware` (Value Object) | `ResultadoAnaliseMalware.php` | `limpo()`/`infectado(string $assinatura)`/`naoConfigurado()` — enum interno `EstadoAnaliseMalware` |
| `FalhaAnaliseMalwareException` | `FalhaAnaliseMalwareException.php` | Camada configurada mas o scan falha (timeout, `clamd` inacessível) — nunca lançada para "não configurado" |
| `ClamAvAnalisadorMalware` (implementação) | `ClamAvAnalisadorMalware.php` | Cliente `clamd` via protocolo `INSTREAM` |

### Decisão: INSTREAM sobre socket próprio, sem dependência Composer

`ClamAvAnalisadorMalware` fala `INSTREAM` directamente sobre `stream_socket_client` (`CLAMAV_HOST`/
`CLAMAV_PORT`) — envia os bytes do ficheiro em chunks prefixados pelo tamanho (4 bytes big-endian),
termina com um chunk de tamanho zero, interpreta a resposta (`... OK` → limpo; `... FOUND` →
infectado, assinatura extraída por regex). Timeout curto e explícito na ligação e na leitura
(`stream_set_timeout`) — infra local, ficheiros ≤ 10 MB. Sem volume partilhado com `app`/`queue`.
Decisão confirmada com o utilizador: sem pacote Composer novo (evita aprovação de nova dependência).

### Fail-safe: "não configurado" vs "falha do scan"

Mesmo padrão de `LLM_LOCAL_*`/`LLM_CLOUD_*` — `CLAMAV_HOST`/`CLAMAV_PORT` vazios (sentinela:
`host` vazio ou `port` `0`, ver `06-config.md`) desligam a camada, sem tentativa de ligação
(`ResultadoAnaliseMalware::naoConfigurado()`). **Distinção crítica:** só a ausência de configuração
permite avançar sem veredicto — qualquer falha **estando configurado** (timeout, `clamd` em baixo,
resposta inesperada) lança `FalhaAnaliseMalwareException`, nunca é tratada como "não configurado".

### Pontos de invocação

| Invocador | Contexto | Resultado |
|---|---|---|
| `TriarDocumentoPendenteAction` | Pipeline automático, `Pendente` (mesma transacção que reivindica) | Infectado→`Perigoso`; limpo→`AguardaEnvio`; não configurado→`AguardaEnvio` (motivo "scan desligado"); falha→`Erro` |
| `RegistarDocumentoManualAction` | Registo manual, criação directa | Infectado→`Perigoso`; limpo/não configurado→`Processado`; falha→`Erro` |

Ambos reusam exactamente o mesmo `AnalisadorMalware` — sem segunda interface/implementação.

### Testes sem `clamd` real (RNF-02)

A suite não depende de um `clamd` real: `ClamAvAnalisadorMalwareTest` cobre os ramos "não
configurado" (sem tentativa de socket) e "porta fechada" (timeout rápido) com casos triviais, e
exercita o protocolo `INSTREAM` completo (`OK`/`FOUND`/resposta inesperada/timeout/falha de
escrita/leitura) contra um servidor `clamd` falso (`tests/Support/fake_clamd.php`, socket TCP local
com script PHP). `TriarDocumentoPendenteActionTest`/`RegistarDocumentoManualActionTest` mockam a
interface (Mockery) para os 4 ramos de decisão.

## Extractores de texto — pdfparser nativo + Tesseract OCR

Matéria-prima (`texto_extraido`) para o cliente IA (integração planeada abaixo). Dois serviços
isolados e puros em `app/Infrastructure/Extracao/` — recebem o caminho absoluto de um ficheiro e
devolvem texto, sem escrita em BD, sem chamada a LLM e sem dependência de `Documento`/
`ExtracaoDocumento`. A decisão de transição de `etapa_extracao`, a reivindicação/lease e a contagem
de tentativas ficam a cargo do orquestrador do pipeline (ver `01-features/documento-pipeline.md`).

| Componente | Ficheiro | Papel |
|---|---|---|
| `ExtractorTextoNativo` | `ExtractorTextoNativo.php` | `extrair(string $caminhoAbsoluto): ResultadoExtracao` — texto de PDF digital via `smalot/pdfparser`, aplica o threshold de `config('extracao.threshold_caracteres')` |
| `ExtractorOcr` | `ExtractorOcr.php` | `extrair(string $caminhoAbsoluto): ResultadoExtracao` — rasteriza cada página via `imagick` a `config('extracao.ocr.dpi')` DPI e reconhece com `thiagoalessio/tesseract_ocr` (`config('extracao.ocr.linguas')`) |
| `ResultadoExtracao` (Value Object) | `ResultadoExtracao.php` | `comVeredictoThreshold(string $texto, bool $ultrapassaThreshold)`/`semVeredicto(string $texto)` — construtor privado |
| `FalhaExtracaoTextoException` | `FalhaExtracaoTextoException.php` | Única excepção de falha técnica, partilhada pelos dois extractores (ficheiro corrompido, falha do processo `tesseract`/Ghostscript/`imagick`) |

### Sem interface comum entre os dois extractores

Ao contrário do padrão Repository/Service (`02-shared/padroes-acoes.md`), não há substituição
prevista entre `ExtractorTextoNativo` e `ExtractorOcr` — o orquestrador invoca sempre os dois, em
sequência condicional (nativo primeiro; OCR só se o threshold do nativo falhar), nunca um no lugar
do outro. O único contrato de saída partilhado é o VO `ResultadoExtracao`.

### `ultrapassaThreshold` — só o nativo decide

O threshold de 50 caracteres (`config('extracao.threshold_caracteres')`) só é calculado por
`ExtractorTextoNativo`. `ExtractorOcr` devolve sempre `ultrapassaThreshold: null` — o OCR é o
"último recurso" textual; decidir se o resultado é suficiente ou se avança para as camadas LLM é
responsabilidade do orquestrador do pipeline, não deste extractor.

### `ExtractorOcr` — limpeza de recursos a dois níveis

`Imagick` acumula memória nativa fora do GC do PHP. `ExtractorOcr` liberta `clear()`/`destroy()`
por página processada, dentro do loop de rasterização — não só no fim do método — e remove o
ficheiro temporário da página assim que deixa de ser necessário. Um bloco `finally` ao nível do
método público garante, adicionalmente, que qualquer temporário remanescente de
`storage/app/temp/<uuid-execução>-pagina-*.png` é removido mesmo em falha a meio (ex. página 3 de 5
falha o reconhecimento). Não existe disco Laravel registado para este directório — é scratch space
de processo, não um disco de ciclo de vida do `Documento`; acedido via `storage_path()` directo.

### Testes sem mock do motor OCR/Ghostscript (RNF-05)

Mesma decisão de `ClamAvAnalisadorMalwareTest` — os testes de `ExtractorOcrTest` correm o
`tesseract`/`imagick`/Ghostscript reais (sem rede envolvida, motor local), sem `Mockery`. A fixture
de PDF-imagem é gerada em runtime (`tests/Support/gera_pdf_imagem.php`) via PostScript +
Ghostscript (invocado com `Illuminate\Support\Facades\Process::run()`, array de argumentos, sem
shell) + `imagick`, para não commitar um binário frágil. A asserção sobre o texto reconhecido usa
substring/palavra-chave (`toContain()`), nunca igualdade exacta — evita flakiness entre motores
Tesseract/dados de línguas diferentes consoante o ambiente (host vs. Docker).

### Stub Larastan para `thiagoalessio/tesseract_ocr`

Primeiro caso no projecto de um pacote de terceiros sem tipos suficientes para o Larastan nível 9
reconhecer a API fluente (`TesseractOCR::lang()->run()`). Resolvido com um stub próprio
(`stubs/TesseractOCR.stub.php`, registado em `phpstan.neon` → `stubFiles`), não com anotações
`@phpstan-ignore` dispersas no código de domínio.

---

## Integrações planeadas

| Integração | Tipo | Uso |
|---|---|---|
| Cliente do pipeline de extração (chamada Prism + parsing da resposta) | API REST externa (via Prism) | Envio do prompt construído por `PromptBuilder`, chamada ao provider (local ou cloud), parsing da resposta estruturada |

Detalhes de autenticação, limites de rate e estrutura de pedido/resposta documentados quando esta integração for planeada.

**Modelo de destino do resultado:** o cliente do pipeline de extração grava o resultado de
cada passo via `RegistarEtapaExtracaoAction` — upsert em `App\Models\ExtracaoDocumento`
(`etapa_extracao`, `texto_extraido`, `dados_json`) + `EtapaDocumento` (`passo`/`resultado`). Ver
`03-models/extracao-documento.md` e `01-features/documento.md`.
