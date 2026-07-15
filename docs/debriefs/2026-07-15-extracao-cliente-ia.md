# Debrief: Extração — cliente IA via Prism (local+cloud, nonce, structured output)

**Issue:** #97
**Branch:** feat/extracao-cliente-ia
**Data:** 2026-07-15
**Commits:** 5 commits

## O que foi implementado

Cliente de extração por IA (`ClienteExtracaoIAPrism`) que converte texto extraído de um
documento em dados estruturados tipados, via Prism (structured output), suportando duas
camadas configuráveis (`local`/`cloud`) com provider/modelo/URL próprios por `.env`.

- `config/extracao.php`: chaves `local`/`cloud` agrupam `provider`/`modelo`/`url`[/`key`]/`activa`
  por camada (antes só existiam as flags `camada_local_activa`/`camada_cloud_activa` na raiz).
  `config/prism.php` deixa de acoplar os blocos `openai`/`ollama` a `LLM_CLOUD_*`/`LLM_LOCAL_*` —
  voltam a usar env vars nativas do provider (`OPENAI_URL`, `OLLAMA_URL`, etc.), já que
  `ClienteExtracaoIAPrism` passa `url`/`api_key` como override por chamada.
- `CamadaIA` (enum `Local`/`Cloud`) e `VeredictoExtracaoIA` (enum interno `Completo`/
  `Desconhecido`/`Perigoso`/`Incompleto`/`FalhaTecnica`).
- `ResultadoExtracaoIA`: VO com construtor privado + 5 named constructors, invariantes
  (`motivo` obrigatório em `Perigoso`/`FalhaTecnica`; `motivosFalta` obrigatório em `Incompleto`).
- `ClienteIA`: interface com um único método `extrair(string $textoExtraido, CamadaIA $camada): ResultadoExtracaoIA`.
- `ClienteExtracaoIAPrism`: implementação — lê config por camada, monta prompt com o texto
  extraído envolto num nonce aleatório (`Str::random(32)`) para mitigar prompt injection, chama
  `Prism::structured()` com schema `ObjectSchema` raiz, resolve o veredicto por ordem
  (perigoso → desconhecido → validação `espera_*` → completo/incompleto), deriva `idCategoria`
  sempre de `TipoDocumento` (nunca do JSON devolvido pela IA), valida NIF genericamente
  (comprimento 5–20, alfanumérico). Qualquer excepção é convertida em `falhaTecnica`, nunca
  propagada.

## Ficheiros alterados

| Ficheiro | Tipo de alteração | Notas |
| -------- | ----------------- | ----- |
| `config/extracao.php` | alterado | `camada_local_activa`/`camada_cloud_activa` → `local.activa`/`cloud.activa`; novas chaves `provider`/`modelo`/`url`/`key` agrupadas por camada |
| `config/prism.php` | alterado | Remove o acoplamento `LLM_CLOUD_URL`/`LLM_LOCAL_URL` dos blocos `openai`/`ollama`; volta a env vars nativas do provider |
| `.env.example` | alterado | Novas vars `LLM_LOCAL_PROVIDER`/`LLM_CLOUD_PROVIDER` (defaults `ollama`/`anthropic`) |
| `app/Infrastructure/AI/CamadaIA.php` | criado | Enum `Local`/`Cloud` |
| `app/Infrastructure/AI/VeredictoExtracaoIA.php` | criado | Enum interno do VO, 5 estados |
| `app/Infrastructure/AI/ResultadoExtracaoIA.php` | criado | VO com construtor privado, 5 named constructors |
| `app/Infrastructure/AI/ClienteIA.php` | criado | Interface do cliente de extração |
| `app/Infrastructure/AI/ClienteExtracaoIAPrism.php` | criado | Implementação Prism — schema, nonce, resolução de veredicto |
| `tests/Unit/Config/ExtracaoConfigTest.php` | alterado | Asserções para as novas chaves `local.*`/`cloud.*` |
| `tests/Unit/Infrastructure/AI/ResultadoExtracaoIATest.php` | criado | 5 factories + invariantes do construtor privado |
| `tests/Unit/Infrastructure/AI/ClienteExtracaoIAPrismTest.php` | criado | `Prism::fake()`, todos os veredictos, payload (nonce + system prompt) |
| `tests/ArchTest.php` | alterado | Ajuste de regras arquitecturais para incluir `app/Infrastructure/AI/` |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| Provider por camada configurável via `.env` (`LLM_LOCAL_PROVIDER`/`LLM_CLOUD_PROVIDER`) | Mapeamento fixo no código (`CamadaIA::Local → Provider::Ollama`, `CamadaIA::Cloud → Provider::OpenAI`), conforme RF-02/RF-03 originais da Spec | Decisão do utilizador no Checkpoint A da Tarefa 1 — permite trocar de provider cloud (Anthropic, OpenRouter, ...) só por `.env`, sem alterar código |
| `camada_local_activa`/`camada_cloud_activa` movidas para `local.activa`/`cloud.activa` | Manter na raiz de `config/extracao.php` (#95) | Agrupar com `provider`/`modelo`/`url` da mesma camada — consistência de estrutura |
| `ResultadoExtracaoIA` expõe propriedades `public readonly` em vez de getters | Getters de leitura (`veredicto()`, `tipoDocumento()`, ...) conforme desenhado no Plano (Tarefa 3) | Sem lógica associada à leitura — dispensam getters; os 5 métodos booleanos (`ehCompleto()`, etc.) mantêm-se por calcularem algo |
| `idCategoria` sempre derivado de `TipoDocumento::$id_categoria` | Ler `categoria` do JSON devolvido pela IA | RN-03 — nunca confiar em campo potencialmente manipulável pelo texto do documento (mesmo com nonce) |
| Validação de NIF genérica (5–20 caracteres, alfanumérico, sem checksum por país) | Validação de NIF português (checksum) ou por país | Decisão do Checkpoint A — fora do âmbito desta issue, aceite deliberadamente |

## Desvios ao Plano

- `ResultadoExtracaoIA` usa propriedades `public readonly` em vez dos getters (`veredicto()`,
  `tipoDocumento()`, `idCategoria()`, ...) desenhados no Plano (Tarefa 3) — ver "Decisões tomadas".
  Os 5 métodos de consulta booleanos (`ehCompleto()`, `ehDesconhecido()`, `ehPerigoso()`,
  `ehIncompleto()`, `falhouTecnicamente()`) foram implementados como planeado.
- Restante implementação segue o Plano sem outros desvios (Tarefa 1 já documentava, no próprio
  ficheiro do Plano, a revisão do RF-02/RF-03 decidida no Checkpoint A).

## Aprendizagens

O padrão de VO com construtor privado + named constructors (`ResultadoExtracaoIA`) torna
impossível construir um estado inválido em qualquer ponto de entrada — incluindo testes, que só
conseguem produzir estados através das factories que já garantem as invariantes. Isto elimina uma
classe inteira de bugs de "veredicto perigoso sem motivo" que, com um construtor público e
propriedades opcionais, exigiria validação repetida em cada consumidor. Ficou também mais claro o
papel de `config/*.php` como fronteira de `env()`: ao mover `provider`/`url`/`key` para
`config('extracao.local'|'cloud')`, a Action/cliente nunca lê `env()` directamente, o que é o que
torna a suite de testes determinística (`config()->set()` nos testes, sem depender de variáveis de
ambiente reais) e é a mesma razão pela qual `config/prism.php` teve de deixar de acoplar os seus
blocos de provider às vars `LLM_*` — a app injecta o override certo por chamada, em vez de o
Prism ler estático de config.

## SYSTEM_SPEC a actualizar

- `docs/system_spec/04-infra/external-apis.md` — novo cliente `ClienteExtracaoIAPrism`, contrato `ClienteIA`, VO `ResultadoExtracaoIA`
- `docs/system_spec/02-shared/enums.md` — novos enums `CamadaIA` e `VeredictoExtracaoIA`
- `docs/system_spec/06-config.md` — nova estrutura `config('extracao.local'|'cloud')` e novas env vars `LLM_LOCAL_PROVIDER`/`LLM_CLOUD_PROVIDER`

## Verificação final

- [x] Linter a verde
- [x] Testes a verde (1020 passed, ArchTest 8 passed, type-coverage/coverage 100%)
- [x] Nenhum dado sensível em logs
- [x] Nenhum segredo em código
