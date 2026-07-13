# System Spec — Infra: APIs Externas

> `app/Infrastructure/AI/`

Construção do system prompt implementada (`PromptBuilder`, ver `04-infra/prompt-builder.md`). Acesso a LLM via **Prism** (`prism-php/prism`), provider-agnóstico, configurado (#95); cliente concreto do pipeline de extração (chamada + parsing da resposta) continua pendente — issue #97.

---

## Implementado

| Componente | Ficheiro | Estado |
|---|---|---|
| `PromptBuilder` — construção do system prompt | `04-infra/prompt-builder.md` | implementado |
| `config/prism.php` — providers Prism (Ollama local + OpenAI-compatible cloud) | `config/prism.php` | implementado (#95) |

## Prism — camadas LLM opcionais (#95)

Os LLM correm **externos à app** (Ollama local, provider cloud), nunca embebidos no processo PHP. Duas camadas, cada uma **opcional/desligável**, aferida pela **presença das suas env vars** (sem flags dedicados) — config incompleta ⇒ camada inactiva (fail-safe). Ver `06-config.md` para o detalhe de `config/extracao.php`.

| Camada | Provider Prism | Env vars | Config |
|---|---|---|---|
| Local | `ollama` (nativo do Prism) | `LLM_LOCAL_URL`, `LLM_LOCAL_MODEL` | `config/prism.php` → `providers.ollama.url`; sem `api_key` |
| Cloud | `openai` (nativo do Prism, aceita `url` custom — cobre OpenRouter/gateways OpenAI-compatible) | `LLM_CLOUD_URL`, `LLM_CLOUD_MODEL`, `LLM_CLOUD_KEY` | `config/prism.php` → `providers.openai.url` + `api_key` |

Não foi necessário `Prism::extend()` — o provider `openai` nativo aceita `url` custom nativamente (confirmado ao publicar `config/prism.php` via `vendor:publish --tag=prism-config`).

## Integrações planeadas

| Integração | Tipo | Uso |
|---|---|---|
| Cliente do pipeline de extração (chamada Prism + parsing da resposta) | API REST externa (via Prism) | Envio do prompt construído por `PromptBuilder`, chamada ao provider (local ou cloud), parsing da resposta estruturada — issue #97 |

Detalhes de autenticação, limites de rate e estrutura de pedido/resposta documentados quando a issue #97 for planeada.
