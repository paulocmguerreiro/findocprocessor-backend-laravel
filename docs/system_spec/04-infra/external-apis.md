# System Spec — Infra: APIs Externas

> `app/Infrastructure/AI/`, `app/Infrastructure/Malware/`

Construção do system prompt implementada (`PromptBuilder`, ver `04-infra/prompt-builder.md`). Acesso a LLM via **Prism** (`prism-php/prism`), provider-agnóstico, configurado; cliente concreto do pipeline de extração (chamada + parsing da resposta) continua pendente.

Scan de malware self-hosted (`ClamAvAnalisadorMalware`) implementado, ver secção dedicada abaixo.

---

## Implementado

| Componente | Ficheiro | Estado |
|---|---|---|
| `PromptBuilder` — construção do system prompt | `04-infra/prompt-builder.md` | implementado |
| `config/prism.php` — providers Prism (Ollama local + OpenAI-compatible cloud) | `config/prism.php` | implementado |
| `AnalisadorMalware`/`ClamAvAnalisadorMalware` — scan de malware ClamAV | `app/Infrastructure/Malware/` | implementado |

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
