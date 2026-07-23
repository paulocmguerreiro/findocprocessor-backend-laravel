# Debrief: hOCR + extração role-neutral + suite E2E

**Issue:** N/A (trabalho exploratório — sem `/planeia-issue`)
**Branch:** test/pipeline-e2e-simulacao
**Data:** 2026-07-23
**Commits:** 3 (+ 1 de documentação)

## O que foi implementado

Tornar a extração de dados de faturas pelo LLM local **fiável ponta-a-ponta**. Três blocos:

1. **hOCR** — o `ExtractorOcr` passa a pedir o reconhecimento em hOCR (bbox por palavra) e o novo `HocrSimplificador` reconstrói regiões 2D por adjacência (union-find), entregando ao LLM blocos `<block bbox=…>` em vez de texto linear.
2. **Extração role-neutral + resolução por NIF** — o `PromptBuilder` deixa de sugerir a posição da empresa mãe; o modelo lê emissor/destinatário e a `RegraReconciliarEntidadesDocumento` situa a mãe por correspondência de NIF, corrigindo tipo/categoria pela direcção. O schema passa a `emissor`/`destinatario` com todos os campos `requiredFields` (nullable).
3. **Suite E2E** — `tests/E2E/PipelineE2ETest.php` (opt-in, `composer test:e2e`) exercita o pipeline contra Tesseract + Ollama reais, com fixtures A4 e seeder dedicado.

## Ficheiros alterados

| Ficheiro | Tipo | Notas |
| --- | --- | --- |
| `app/Infrastructure/Extracao/HocrSimplificador.php` | criado | Agrupamento 2D union-find de `ocrx_word` → blocos `bbox` |
| `app/Infrastructure/Extracao/ExtractorOcr.php` | alterado | `configFile('hocr')` + injecta `HocrSimplificador` |
| `app/Infrastructure/AI/PromptBuilder.php` | alterado | `comEmpresaMae()`→`comInstrucoesExtracao()` (role-neutral); Passo 1 sem posição |
| `app/Infrastructure/AI/ClienteExtracaoIAPrism.php` | alterado | Schema `emissor`/`destinatario`; `requiredFields`=todos (nullable); gating por `espera_*`; timeout config |
| `app/Features/…/ConcluirExtracao/RegraReconciliarEntidadesDocumento.php` | alterado | Resolução da mãe por NIF + correcção de tipo/categoria pela direcção |
| `config/extracao.php`, `.env.example`, `compose.yaml`, `06-config.md` | alterado | `LLM_TIMEOUT_SEGUNDOS`; `extra_hosts` (container→Ollama host) |
| `database/seeders/SimulacaoPipelineSeeder.php` | criado | Empresa mãe + categorias + tipos (descrições neutras) |
| `tests/E2E/PipelineE2ETest.php`, `tests/Fixtures/faturas/*` | criado | Suite opt-in + 3 fixtures A4 (SVG→PDF/PNG) |
| `tests/Unit/…` (8 ficheiros) | alterado/criado | `HocrSimplificadorTest` novo; testes de IA/reconciliação alinhados ao role-neutral/NIF |
| `docs/system_spec/{prompt-builder,extracao-ia,extracao-texto,documento-pipeline,07-testing}.md` | alterado | Specs alinhados (já nos commits de feature) |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| --- | --- | --- |
| **hOCR + agrupamento 2D** para o "encavalitamento" | Modelo **multimodal** local (enviar a imagem) | Multimodal exigia outro stack e enviava a imagem à cloud; o hOCR mantém "só texto para o LLM" e resolve a estrutura em código, testável e determinístico |
| Agrupar por **regiões 2D (union-find)** | Agrupar por `carea` do Tesseract, ou por linha | `carea` cruzava colunas (NIF do emissor com a data); por linha fragmentava o emissor. O 2D separa emissor de metadados e mantém cada um coeso |
| **Extração role-neutral** (emissor/destinatário) | Manter o prompt a dizer a posição da mãe por tipo | A posição sugerida enviesava o modelo ("a nossa empresa é a compradora") e invertia os papéis nas vendas — era viés do prompt, não do modelo |
| **Resolver a mãe por NIF** (não por nome) | Corresponder por nome | O nome pode abreviar/variar entre documentos; o NIF ou está completo ou não serve — chave estável |
| **`requiredFields` = todos os campos (nullable)** | Reforçar a *descrição* do campo `data_documento` | Reforçar a descrição não resolveu: Ollama/qwen **omitem** campos opcionais mesmo quando presentes. Forçar a chave (com null permitido) tornou a extração fiável |
| **`qwen2.5:7b-instruct`** como default | `qwen2.5:3b` | O 3b, mesmo com todos os fixes, larga NIF/valor e escala à cloud. O 7b extrai tudo, incluindo a venda. O modelo é configurável por env |
| Manter o **gating por `espera_*`** na conclusão | Exigir sempre emissor+destinatário | Preserva recibos/extractos (sem contraparte) sem os marcar incompletos, mantendo a extração neutra |

## Desvios ao Plano

Não houve plano formal — o trabalho começou como uma suite E2E para *confirmar* que o pipeline extraía dados e, ao correr, revelou o "encavalitamento", que puxou a reestruturação (hOCR) e, por consequência, a correcção do viés de papéis. O `system_spec` foi actualizado durante a implementação e **committado com o código** (nos 3 commits de feature, não num commit de docs à parte) — os specs viajam com a mudança que descrevem.

## Aprendizagens

- **Vertical Slice / Actions:** a separação "serviço puro vs orquestrador vs regra de domínio" pagou-se aqui. A `ClienteExtracaoIAPrism` (infra, sem BD) só extrai; a `RegraReconciliarEntidadesDocumento` (regra) decide papéis por NIF; a Action orquestra. Trocar a extração de "papéis fixos" para "neutro + resolução por NIF" tocou **só** na regra e no cliente — a Action não mudou. O acoplamento estava no sítio certo.
- **Injecção directa de classes concretas sem interface** (`HocrSimplificador` no `ExtractorOcr`): seguindo a convenção do projecto (concretas sem substituição prevista injectam-se directamente), o container resolve tudo sem binding. Simplicidade sem cerimónia de interface onde não há variação.
- **O viés vive no prompt, não no modelo:** o mesmo 7b que invertia papéis com o prompt enviesado acerta com o prompt neutro. Antes de trocar de modelo, tirar o *prior* do prompt.
- **Structured output de modelos locais:** campos "opcionais nullable" são omitidos pelo modelo mesmo quando os dados existem; `requiredFields` (com nullable) é a alavanca de fiabilidade — mais eficaz que engordar descrições. Insight reutilizável para qualquer extração local.
- **Cobertura 100% sem `@codeCoverageIgnore`:** guardas de tipo que o PHPStan exige mas o runtime nunca atinge (ex.: `DOMXPath::query()` a devolver `false`) resolvem-se colapsando o ramo (`?: []`, ternário sempre executado) em vez de ignorar cobertura — mantém o 100% honesto.

## SYSTEM_SPEC a actualizar

Já actualizado e committado nos commits de feature (validado neste passo):
- `04-infra/extracao-texto.md` — hOCR + `HocrSimplificador` (agrupamento 2D)
- `04-infra/prompt-builder.md` — `comInstrucoesExtracao()` role-neutral (removido `comEmpresaMae`)
- `04-infra/extracao-ia.md` — schema `emissor`/`destinatario`, `requiredFields` nullable, gating
- `01-features/documento-pipeline.md` — reconciliação por NIF + correcção de tipo pela direcção
- `06-config.md` — `LLM_TIMEOUT_SEGUNDOS`
- `07-testing.md` — suite E2E (default 7b, 3 cenários)

## Verificação final

- [x] Linter a verde (Pint + Rector)
- [x] Testes a verde (`composer test`: 1175 testes, 100% cobertura, Larastan nível 9 zero erros)
- [x] E2E a verde com 7b (`composer test:e2e`: 3/3)
- [x] Nenhum dado sensível em logs
- [x] Nenhum segredo em código
