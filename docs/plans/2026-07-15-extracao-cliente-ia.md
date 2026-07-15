# Plano: Extração — cliente IA via Prism (local+cloud, nonce, structured output)

**Issue:** #97
**Spec:** docs/specs/2026-07-15-extracao-cliente-ia.md
**Data:** 2026-07-15

## Tarefas

### Tarefa 1 — Config: provider/modelo/ligação por camada

> **Revisto durante a implementação** (fora do desenho original desta tarefa, decisão do
> utilizador no checkpoint da Tarefa 1): `camada_local_activa`/`camada_cloud_activa` (#95) movidas
> da raiz para `local.activa`/`cloud.activa` (agrupadas com `modelo`, para consistência), e o
> mapeamento fixo `CamadaIA::Local → Provider::Ollama`/`CamadaIA::Cloud → Provider::OpenAI` (RF-02
> original) tornou-se configurável (`local.provider`/`cloud.provider`), para permitir trocar de
> provider cloud (ex.: Anthropic, OpenRouter) só via `.env`. Ver `docs/process-warnings.md`
> WRN-017/WRN-018 e Spec RF-02/RF-03 (revistas).

- Ficheiros a criar/alterar: `config/extracao.php`, `config/prism.php` (remove o acoplamento
  `LLM_CLOUD_URL`/`LLM_CLOUD_KEY`→bloco `openai` e `LLM_LOCAL_URL`→bloco `ollama` — esses blocos
  voltam a usar env vars nativas do provider, já que `ClienteExtracaoIAPrism` passa `url`/`api_key`
  como override por chamada), `.env.example` (`LLM_LOCAL_PROVIDER`/`LLM_CLOUD_PROVIDER`, defaults
  `ollama`/`anthropic`).
- O que implementar: `'local' => ['provider' => env('LLM_LOCAL_PROVIDER', 'ollama'), 'modelo' =>
  env('LLM_LOCAL_MODEL'), 'url' => env('LLM_LOCAL_URL', 'http://localhost:11434/v1'), 'activa' =>
  ...]` e `'cloud' => ['provider' => env('LLM_CLOUD_PROVIDER', 'anthropic'), 'modelo' =>
  env('LLM_CLOUD_MODEL'), 'url' => env('LLM_CLOUD_URL'), 'key' => env('LLM_CLOUD_KEY', ''),
  'activa' => ...]`. Comentário inline a explicar que estas chaves evitam `env()` fora de ficheiro
  de config no `ClienteExtracaoIAPrism`, e que `provider` é o nome de `Prism\Prism\Enums\Provider`.
- Testes associados: `tests/Unit/Config/ExtracaoConfigTest.php` (já existe — acrescentar
  asserções para `extracao.local.modelo`/`extracao.cloud.modelo`/`extracao.local.provider`/
  `extracao.cloud.provider`/`extracao.local.url`/`extracao.cloud.url`/`extracao.cloud.key`
  reflectirem as respectivas env vars, incl. defaults `ollama`/`anthropic`).
- Commit: `feat(extracao): expõe provider/modelo/ligação LLM por camada em config/extracao.php`

### Tarefa 2 — Enums `CamadaIA` e `VeredictoExtracaoIA`

- Ficheiros a criar: `app/Infrastructure/AI/CamadaIA.php`, `app/Infrastructure/AI/VeredictoExtracaoIA.php`
- O que implementar:
  - `CamadaIA: string { case Local = 'local'; case Cloud = 'cloud'; }` — cases TitleCase PT,
    values lowercase (mesmo padrão de `PosicaoEmpresaMae`).
  - `VeredictoExtracaoIA: string { case Completo = 'COMPLETO'; case Desconhecido = 'DESCONHECIDO';
    case Perigoso = 'PERIGOSO'; case Incompleto = 'INCOMPLETO'; case FalhaTecnica = 'FALHA_TECNICA'; }`
    — values UPPER_SNAKE (mesmo padrão de `ResultadoEtapa`/`EtapaExtracao`), interno ao VO da
    Tarefa 3 (sem uso directo fora de `app/Infrastructure/AI/`).
- Testes associados: nenhum ficheiro de teste dedicado — cobertos indirectamente pelos testes do
  VO (Tarefa 3) e do cliente (Tarefa 5); `composer test:type-coverage` exige tipagem completa, sem
  lógica a testar isoladamente (mesmo critério de `EstadoAnaliseMalware`, sem teste próprio).
- Commit: `feat(extracao): adiciona enums CamadaIA e VeredictoExtracaoIA`

### Tarefa 3 — VO `ResultadoExtracaoIA`

- Ficheiros a criar: `app/Infrastructure/AI/ResultadoExtracaoIA.php`,
  `tests/Unit/Infrastructure/AI/ResultadoExtracaoIATest.php`
- O que implementar: `final readonly class` com construtor **privado**, named constructors
  estáticos:
  - `completo(TipoDocumento $tipoDocumento, string $idCategoria, ?DateTimeInterface $dataDocumento, ?string $nifFornecedor, ?string $nomeFornecedor, ?string $nifCliente, ?string $nomeCliente, ?float $valor): self`
  - `desconhecido(): self`
  - `perigoso(string $motivo): self`
  - `incompleto(array $motivosFalta): self` — `@param list<string> $motivosFalta`
  - `falhaTecnica(string $motivo): self`

  Getters de leitura (`veredicto()`, `tipoDocumento()`, `idCategoria()`, `dataDocumento()`,
  `nifFornecedor()`, `nomeFornecedor()`, `nifCliente()`, `nomeCliente()`, `valor()`, `motivo()`,
  `motivosFalta()`) + métodos de consulta booleanos (`ehCompleto()`, `ehDesconhecido()`,
  `ehPerigoso()`, `ehIncompleto()`, `falhouTecnicamente()`) — mesmo padrão de
  `ResultadoAnaliseMalware` (`estaLimpo()`/`estaInfectado()`/`foiConfigurado()`).
  Invariante no construtor privado: `motivo` obrigatório (`\InvalidArgumentException` se vazio)
  quando `veredicto` é `Perigoso`/`FalhaTecnica`; `motivosFalta` não pode ser vazio quando
  `Incompleto`.
- Testes associados: `ResultadoExtracaoIATest` — as 5 factories (happy path de cada uma), e as
  invariantes do construtor privado (`Perigoso`/`FalhaTecnica` sem motivo →
  `InvalidArgumentException`; `Incompleto` sem `motivosFalta` → idem).
- Commit: `feat(extracao): adiciona VO ResultadoExtracaoIA com veredicto tipado`

### Tarefa 4 — Interface `ClienteIA`

- Ficheiros a criar: `app/Infrastructure/AI/ClienteIA.php`
- O que implementar: `interface ClienteIA { public function extrair(string $textoExtraido, CamadaIA $camada): ResultadoExtracaoIA; }`
  com `@throws` vazio (excepções do Prism são sempre capturadas e convertidas em
  `ResultadoExtracaoIA::falhaTecnica()` — RF-07.4 da Spec, nunca propagadas).
- Testes associados: nenhum (interface pura, sem lógica).
- Commit: `feat(extracao): adiciona interface ClienteIA`

### Tarefa 5 — Implementação `ClienteExtracaoIAPrism`

- Ficheiros a criar: `app/Infrastructure/AI/ClienteExtracaoIAPrism.php`,
  `tests/Unit/Infrastructure/AI/ClienteExtracaoIAPrismTest.php`
- O que implementar (`final class implements ClienteIA`):
  1. Ler `config("extracao.{$camada->value}")` (array `provider`/`modelo`/`url`[/`key`, só cloud]);
     `$provider = Provider::from($config['provider'])` (`Prism\Prism\Enums\Provider`); montar
     `$providerConfig = array_filter(['url' => $config['url'], 'api_key' => $config['key'] ?? null])`
     — passado como override a `using()` (RF-02/RF-03 revistas — provider configurável por `.env`,
     não fixo no código).
  2. System prompt: `PromptBuilder::novo()->comInstrucoesBase()->comEmpresaMae()->comTiposDocumento()->construir()`.
  3. Nonce: `Str::random(32)`; monta a mensagem de utilizador com o texto de reforço + `$textoExtraido` envolto em `<{nonce}>…</{nonce}>` (ver RF-05 da Spec — cita o nonce concreto, nunca o genérico do `base_instructions.txt`).
  4. Schema Prism (`ObjectSchema` raiz — RNF-01): `tipo_documento` (`StringSchema`, obrigatório), `motivo` (`StringSchema`, nullable), `data_documento` (`StringSchema`, nullable), `fornecedor`/`cliente` (`ObjectSchema` aninhado `{nif, nome}`, nullable), `valor` (`NumberSchema`, nullable). Todos os campos de domínio nullable ao nível do schema (RF-06).
  5. Chamada: `Prism::structured()->using($provider, $modelo, $providerConfig)->withSchema($schema)->withSystemPrompt($systemPrompt)->withPrompt($mensagemComNonce)->asStructured()`, dentro de `try/catch (\Throwable)` — qualquer excepção → `ResultadoExtracaoIA::falhaTecnica($excepcao->getMessage())` (RF-07.4, RNF-06: nunca incluir `$textoExtraido` na mensagem capturada).
  6. Resolução do veredicto (RF-07, ordem exacta da Spec): `perigoso` → `desconhecido` (tipo não resolúvel via `TipoDocumento::where('nome', ...)->first()`) → validação de completude por `espera_*` (RF-08) → `completo` com `idCategoria` derivado (RF-09, RN-03) ou `incompleto` com lista de motivos.
  7. Validação de NIF (RF-10, RN-04): método privado `nifValido(?string $nif): bool` — `trim() !== ''`, comprimento 5–20, `ctype_alnum(str_replace(' ', '', $nif))`.
- Testes associados (`Prism::fake()` + `StructuredResponseFake`, sem rede real):
  - `completo` — `TipoDocumento` com todos os `espera_*=true`, JSON completo e válido.
  - `completo` com `espera_fornecedor=false` — JSON sem `fornecedor` não falha (RF-08).
  - `incompleto` — falta `valor` quando `espera_valor=true`; falta `nome`/`nif` de `fornecedor`/`cliente` esperado; NIF fora do intervalo 5–20 ou não alfanumérico.
  - `desconhecido` — `tipo_documento` sem `TipoDocumento` correspondente.
  - `perigoso` — `tipo_documento = "perigoso"` com `motivo` preenchido, mesmo com outros campos presentes (RN-01, precedência).
  - `falhaTecnica` — `Prism::fake()` a lançar excepção (ex.: resposta que falha `withSchema()`), confirmar que não propaga.
  - Payload: `$fake->assertRequest(...)` confirma o nonce concreto no prompt de utilizador e o system prompt do `PromptBuilder` presente na chamada.
  - `categoria` nunca lida do JSON — `idCategoria` do resultado bate sempre com `$tipoDocumento->id_categoria`, mesmo que o JSON (adversarial) inclua uma `categoria` diferente.
- Commit: `feat(extracao): implementa ClienteExtracaoIAPrism (schema, nonce, veredicto)`

## Ordem de implementação

1. Tarefa 1 (config) — pré-requisito de leitura para a Tarefa 5.
2. Tarefa 2 (enums) — sem dependências, usado pelas Tarefas 3 e 4/5.
3. Tarefa 3 (VO `ResultadoExtracaoIA`) — depende do enum `VeredictoExtracaoIA` (Tarefa 2).
4. Tarefa 4 (interface `ClienteIA`) — depende dos enums (Tarefa 2) e do VO (Tarefa 3) na assinatura.
5. Tarefa 5 (`ClienteExtracaoIAPrism`) — depende de todas as anteriores; é a única tarefa que
   integra `PromptBuilder`, Prism e `TipoDocumento`.

## Testes a escrever

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| Config modelo por camada | unit | `tests/Unit/Config/ExtracaoConfigTest.php` | `extracao.local.modelo`/`extracao.cloud.modelo` reflectem env vars |
| Factories + invariantes do VO | unit | `tests/Unit/Infrastructure/AI/ResultadoExtracaoIATest.php` | 5 named constructors, `InvalidArgumentException` em estados inválidos |
| Veredicto completo (todos os campos) | unit | `tests/Unit/Infrastructure/AI/ClienteExtracaoIAPrismTest.php` | `espera_*` todos `true`, JSON completo → `Completo` |
| Veredicto completo (campo não esperado ausente) | unit | idem | `espera_fornecedor=false` sem `fornecedor` → ainda `Completo` |
| Veredicto incompleto | unit | idem | falta de campo esperado, NIF inválido → `Incompleto` + motivos |
| Veredicto desconhecido | unit | idem | `tipo_documento` não resolúvel |
| Veredicto perigoso (precedência) | unit | idem | `"perigoso"` vence mesmo com outros campos presentes |
| Veredicto falha técnica | unit | idem | excepção do Prism não propaga, vira `FalhaTecnica` |
| Payload do pedido (nonce + system prompt) | unit | idem | `$fake->assertRequest()` confirma nonce concreto e system prompt do `PromptBuilder` |
| Categoria sempre derivada | unit | idem | `idCategoria` ignora qualquer `categoria` no JSON, deriva de `TipoDocumento` |

## Dependências

- Issues bloqueantes: nenhuma — `#95`/`#88`/`#96` já implementadas em `main`.
- Deve ser implementada após: nenhuma (paralela seria `#96`, já concluída).

## Riscos de implementação

> Consolidados do Brief (`## Riscos identificados`) e da Spec — não apagar riscos do Brief.

- Tensão `espera_*` ↔ `TransicionarProcessadoDocumentoDto` (Brief) — fora do âmbito de resolver
  aqui; `ResultadoExtracaoIA` devolve os campos como estão (incluindo `null` quando não esperados),
  a flexibilização do DTO fica para a Issue IV.
- `ObjectSchema` como raiz obrigatória (Spec RNF-01) — atenção ao construir o schema com campos
  nested (`fornecedor`/`cliente`) para não violar o requisito de estrutura do modo strict OpenAI.
- ~~Mapeamento `CamadaIA::Local → Provider::Ollama` / `CamadaIA::Cloud → Provider::OpenAI` fixo no
  código~~ — **revisto na Tarefa 1**: o provider por camada é lido de
  `config('extracao.local.provider')`/`config('extracao.cloud.provider')`
  (`Prism\Prism\Enums\Provider::from(...)`), com `url`/`api_key` passados como override a
  `using()`. Risco residual: só cobre os providers que o Prism já conhece nativamente
  (`Provider` backed enum) — um provider fora dessa lista continua a exigir código novo
  (`PrismManager::extend()` ou equivalente), não é resolvido por esta configuração.
- Validação de NIF genérica (5–20, alfanumérico) pode aceitar valores que não são NIFs reais de
  nenhum país — aceite deliberadamente (Checkpoint A), não é escopo desta issue resolver.
- Primeira utilização de `$fake->assertRequest()` no repo (Spec RNF-05) — API nova para a suite,
  confirmar contra `vendor/prism-php/prism/src/Testing/PrismFake.php` se o teste não passar à
  primeira.

## O que NÃO fazer nesta issue

- Não implementar reconciliação NIF+Nome→`Entidade` (find-or-create) — Issue IV.
- Não montar `TransicionarProcessadoDocumentoDto` nem chamar `TransicionarProcessadoDocumentoAction`
  — Issue IV.
- Não adicionar `Gate::authorize()` a `ClienteExtracaoIAPrism` — não é uma Action, não tem endpoint.
- Não adicionar timeout por camada (`withClientOptions`) nem novas env vars de timeout — decisão
  do Checkpoint A, aceitar `config('prism.request_timeout')` global.
- Não implementar validação de NIF por país (checksum PT, formato EIN, etc.) — decisão do
  Checkpoint A, regra genérica apenas.
- Não alterar `PromptBuilder` (sem `withDocumento()`/`comDocumento()`) — o nonce-wrapping fica
  inteiramente em `ClienteExtracaoIAPrism`.
- Não tocar em `RegistarEtapaExtracaoAction`, `ExtracaoDocumento` ou qualquer transição de estado do
  `Documento`.
