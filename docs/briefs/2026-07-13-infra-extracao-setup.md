# Brief: Infra de extração — Prism + pdfparser + Tesseract/imagick + config LLM (setup)

**Issue:** #95
**Data:** 2026-07-13
**Branch:** feat/infra-extracao-setup

## Contexto

Primeira das 4 issues do mecanismo de extração por IA (pipeline
`PdfParser → OCR → LLM local → LLM cloud`, ver #96/#97/#98) e do modelo de dados #94.
Esta issue trata **apenas do setup de infraestrutura** — pacotes Composer,
dependências de sistema na imagem Docker e ficheiros de configuração — para
desbloquear os extractores (#96) e o cliente IA (#97). **Não** implementa
qualquer lógica de pipeline, extractores, cliente IA nem Commands.

Decisões já fixadas nas issues do mecanismo:

- acesso a LLM via **Prism** (`prism-php/prism`), provider-agnóstico;
- os LLM correm **externos à app** (Ollama local, provider cloud), alcançados por
  `host.docker.internal` a partir do container;
- as **camadas LLM são opcionais/desligáveis**, aferidas pela **presença das vars**
  de ambiente (sem flags dedicados) — config incompleta ⇒ camada inactiva (fail-safe);
- testes **mockados** (`Prism::fake()`, `Storage::fake()`); os binários de sistema
  (Tesseract/Ghostscript/imagick) só existem na imagem Docker e são validados pelo
  job de CI `docker-parity`, não pelo gate `build-and-test`.

## O que muda

**Dependências (composer.json / composer.lock)**

- `prism-php/prism` — acesso unificado a LLM local + cloud, structured output.
- `smalot/pdfparser` — extração de texto nativo de PDF.
- `thiagoalessio/tesseract-ocr` — wrapper PHP do Tesseract.

**Imagem Docker (`Dockerfile`, base `php:8.5-fpm-alpine`)**

- `apk add`: `tesseract-ocr`, `tesseract-ocr-data-por`, `tesseract-ocr-data-eng`, `ghostscript`.
- `install-php-extensions imagick` (acrescentar à linha existente).
- **Relaxar o `policy.xml` do ImageMagick** para permitir leitura de PDF/PS
  (o Alpine bloqueia por omissão) — sem isto o OCR de PDFs falha. Ficheiro novo
  em `docker/` (ex.: `docker/imagemagick/policy.xml`) copiado na imagem, ou patch
  in-place do policy default via `sed`.

**Compose (`compose.yaml`)**

- Injectar `LLM_LOCAL_*` / `LLM_CLOUD_*` no bloco `x-app-env` (`&app-env`).
- Garantir alcance ao LLM local externo por `host.docker.internal`
  (+ `extra_hosts: ["host.docker.internal:host-gateway"]` para Linux) nos serviços
  que correrão o pipeline (`app`/`queue`; o serviço `scheduler` é da #98).

**Configuração**

- `config/prism.php` — providers do Prism ligados às env `LLM_LOCAL_*` / `LLM_CLOUD_*`.
- `config/extracao.php` (novo) — `threshold_caracteres = 50`, `ttl_lease`,
  `max_tentativas = 3`, limites por command, e as flags derivadas:
    - `camada_local_activa` = `filled(LLM_LOCAL_URL) && filled(LLM_LOCAL_MODEL)`;
    - `camada_cloud_activa` = `filled(LLM_CLOUD_URL) && filled(LLM_CLOUD_MODEL) && filled(LLM_CLOUD_KEY)`.
- `.env.example` — 5 vars: `LLM_LOCAL_URL`, `LLM_LOCAL_MODEL`, `LLM_CLOUD_URL`,
  `LLM_CLOUD_MODEL`, `LLM_CLOUD_KEY`, com comentário a explicar que
  comentar/esvaziar uma camada = desligá-la.

**Documentação (system_spec)**

- `04-infra/external-apis.md` — Prism + camadas local/cloud opcionais por presença de vars.
- `04-infra/ambiente-docker.md` — Tesseract/imagick/ghostscript, `policy.xml`, rede do LLM externo.
- `06-config.md` — as 5 env vars + `config/extracao.php` + `config/prism.php`.
- `00-index.md` — coerência (novos config files / integração Prism).

## O que NÃO muda

- **Nenhuma lógica de pipeline** — extractores (#96), cliente IA (#97), Commands
  de Schedule e serviço `scheduler` no compose (#98) ficam **fora**.
- **Nenhum modelo/migration/tabela** — `extracoes_documento`, enums `EtapaExtracao`/
  `ResultadoEtapa`, recorder `RegistarEtapaExtracaoAction` são da #94.
- **Nenhuma lógica de salto entre etapas** quando uma camada LLM está inactiva (#98).
- Parser e OCR estão **sempre** incluídos; só as camadas LLM são opcionais.
- O gate `build-and-test` (MySQL) **não** ganha dependência de binários de sistema
  — continua mockado.
- Provider cloud e modelo Ollama concretos ficam como config (`.env`), nunca hardcoded.

## Riscos identificados

- **Formato de `config/prism.php` (Prism v2):** Prism configura providers num array
  `providers` com chaves `url`/`api_key` por provider (ex.: `ollama => ['url' => ...]`,
  provider openai-compatible para cloud com `url` + `api_key`). Publicar o config via
  `vendor:publish` do Prism e depois ligar às env `LLM_*` — não inventar a estrutura.
  Confirmar no `/implementa-plano` o nome exacto dos providers e chaves da versão instalada.
- **`policy.xml` do ImageMagick no Alpine:** é a causa nº1 de OCR de PDF falhar
  silenciosamente (`imagick` recusa ler PDF). A alteração tem de ser verificada no
  container (`docker-parity`), não é testável no gate mockado — risco de passar CI e
  falhar em runtime. Critério de aceitação exige rasterizar um PDF de teste sem erro.
- **`host.docker.internal` em Linux:** só resolve com `extra_hosts: host-gateway`;
  em macOS/Docker Desktop resolve nativamente. Documentar ambos para não partir em CI/Linux.
- **`config('extracao')` cacheado:** as flags `camada_*_activa` derivam de `filled(env(...))`
  em tempo de load do config; com `config:cache` o valor congela no build. Documentar
  que alterar as vars LLM exige `config:clear`/rebuild (coerente com o entrypoint Docker).
- **Novos pacotes recém-publicados** podem disparar o WARN "Package Freshness" do
  `checkpoint:scan` (heurística temporal, ver WRN-001) — não é defeito.
- **Larastan L9 + type-coverage 100%:** os novos config files não têm tipos a declarar
  (arrays), mas qualquer helper introduzido (ex.: no ServiceProvider para registar
  provider Prism) tem de passar o nível 9 sem `mixed`.

## Questões em aberto — resolvidas (Checkpoint A)

- **`config/prism.php`:** publicar via `php artisan vendor:publish` (tag do Prism) e
  adaptar — não escrever o ficheiro à mão, evita divergir do schema da versão instalada.
- **Provider cloud/local do Prism:** camada **local** → provider `ollama` (nativo do
  Prism, `url` = `LLM_LOCAL_URL`, sem `api_key`); camada **cloud** → provider
  **OpenAI-compatible** apontado a `LLM_CLOUD_URL` custom (cobre OpenRouter/gateways),
  com `api_key` = `LLM_CLOUD_KEY`.
- **`policy.xml`:** ficheiro dedicado (`docker/imagemagick/policy.xml`), copiado para a
  imagem — não patch `sed` in-place.
- **Vars LLM no `.env`/compose e âmbito do serviço `scheduler`:** as vars vivem no
  `.env`/`.env.example` e **podem** ser injectadas já no `x-app-env` do `compose.yaml`
  (`app`/`queue`) — sem problema em declará-las aqui. O **serviço `scheduler`** e
  qualquer `extra_hosts`/rede necessária para o pipeline efectivamente correr ficam
  para a #98; se for preciso ajustar isto mais tarde, documenta-se ou acrescenta-se
  à #98, não aqui.
