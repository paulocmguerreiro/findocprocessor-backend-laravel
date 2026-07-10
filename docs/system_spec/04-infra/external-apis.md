# System Spec — Infra: APIs Externas

> `app/Infrastructure/AI/`

Construção do system prompt implementada (`PromptBuilder`, ver `04-infra/prompt-builder.md`). Cliente HTTP para o provider de IA continua pendente — issue futura.

---

## Implementado

| Componente | Ficheiro | Estado |
|---|---|---|
| `PromptBuilder` — construção do system prompt | `04-infra/prompt-builder.md` | implementado |

## Integrações planeadas

| Integração | Tipo | Uso |
|---|---|---|
| Provider de IA (LLM — Ollama/OpenRouter/Anthropic) | API REST externa | Envio do prompt construído por `PromptBuilder`, chamada ao provider, parsing da resposta JSON |

Detalhes de autenticação, limites de rate e estrutura de pedido/resposta documentados quando a issue de integração for planeada.
