# Debrief: Infra de extração — Prism + pdfparser + Tesseract/imagick + config LLM (setup)

**Issue:** #95
**Branch:** feat/infra-extracao-setup
**Data:** 2026-07-13
**Commits:** 8 commits (1 de planeamento + 6 de implementação + 1 de documentação)

## O que foi implementado

Setup de infraestrutura para o pipeline `PdfParser → OCR → LLM local → LLM cloud` (#96/#97/#98) — sem lógica de pipeline, extractores, cliente IA nem Commands:

- **Pacotes Composer:** `prism-php/prism` (^0.100.1), `smalot/pdfparser` (^2.12), `thiagoalessio/tesseract_ocr` (^2.13).
- **`config/prism.php`** — publicado via `vendor:publish --tag=prism-config` e adaptado: `providers.ollama.url` = `LLM_LOCAL_URL` (camada local, sem `api_key`); `providers.openai.url`/`api_key` = `LLM_CLOUD_URL`/`LLM_CLOUD_KEY` (camada cloud, provider nativo aceita `url` custom — cobre OpenRouter/gateways OpenAI-compatible).
- **`config/extracao.php`** (novo) — `threshold_caracteres` (50), `ttl_lease` (env, default 300s), `max_tentativas` (3), e as flags `camada_local_activa`/`camada_cloud_activa` derivadas de `filled(env(...))` (fail-safe: config incompleta ⇒ camada inactiva).
- **Dockerfile** — `apk add`: `tesseract-ocr`, `tesseract-ocr-data-por`, `tesseract-ocr-data-eng`, `ghostscript`; `install-php-extensions imagick` acrescentado à linha existente.
- **`compose.yaml`** — as 5 vars `LLM_LOCAL_*`/`LLM_CLOUD_*` injectadas no bloco `x-app-env` (`app`/`queue` herdam via `<<: *app-env`).
- **`.env.example`** — secção nova com as 5 vars vazias + comentário fail-safe.
- **`tests/Unit/Config/ExtracaoConfigTest.php`** (novo) — 6 testes cobrindo CA-04 (flags derivadas correctamente, incl. config incompleta → inactiva; valores fixos).
- **Validação manual em Docker (CA-02/CA-03):** rasterização de um PDF de teste via `Imagick::readImage()` dentro do container `app`, sem qualquer patch ao `policy.xml` — o `policy.xml` por omissão do pacote `imagemagick` do Alpine já permite ler PDF/PS (ao contrário de Debian/Ubuntu). `php -m` confirma `imagick` carregado; `tesseract --version` responde no container.
- **Documentação `system_spec`:** `04-infra/external-apis.md`, `04-infra/ambiente-docker.md`, `06-config.md`, `00-index.md`.

## Ficheiros alterados

| Ficheiro | Tipo de alteração | Notas |
| -------- | ----------------- | ----- |
| `composer.json` / `composer.lock` | alterado | 3 pacotes novos |
| `config/prism.php` | criado | publicado via `vendor:publish`, providers `ollama`/`openai` ligados às vars `LLM_*` |
| `config/extracao.php` | criado | flags `camada_*_activa` + parâmetros do pipeline |
| `Dockerfile` | alterado | Tesseract (por/eng) + Ghostscript + `imagick`; sem `policy.xml` dedicado (ver Decisões) |
| `compose.yaml` | alterado | 5 vars `LLM_*` no `x-app-env` |
| `.env.example` | alterado | secção nova, 5 vars vazias |
| `tests/Unit/Config/ExtracaoConfigTest.php` | criado | 6 testes, cobre CA-04 |
| `docs/system_spec/04-infra/external-apis.md` | alterado | Prism + camadas opcionais documentadas |
| `docs/system_spec/04-infra/ambiente-docker.md` | alterado | secções `imagick`/`policy.xml` e rede LLM externo |
| `docs/system_spec/06-config.md` | alterado | 5 vars + `config/extracao.php`/`config/prism.php` + nota `config:cache` |
| `docs/system_spec/00-index.md` | alterado | linha `external-apis.md` actualizada |
| `docs/briefs/`, `docs/specs/`, `docs/plans/2026-07-13-infra-extracao-setup.md` | criados | artefactos da Fase 1 |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| Não criar `docker/imagemagick/policy.xml` — nenhum patch ao policy | Ficheiro dedicado copiado na imagem (decisão do Brief/Checkpoint A) | Verificação empírica no container: o `policy.xml` por omissão do pacote `imagemagick` do **Alpine** já permite ler PDF/PS — ao contrário do policy restritivo típico em bases Debian/Ubuntu, que motivou a decisão original. Criar um ficheiro idêntico ao default seria código morto. Documentado em `ambiente-docker.md` com nota para revisitar se a imagem base ou o pacote Alpine mudarem. |
| Provider cloud usa `openai` nativo do Prism (não `Prism::extend()`) | Registar provider próprio custom se `openai` não aceitasse `url` custom (risco identificado no Brief) | Confirmado ao publicar `config/prism.php`: o provider `openai` nativo já aceita `url` custom nativamente — cobre OpenRouter/gateways OpenAI-compatible sem código adicional. |
| Nome real do pacote é `thiagoalessio/tesseract_ocr` (underscore) | — | O Brief/Plano escreveram `tesseract-ocr` (hífen) por analogia ao pacote apk; o nome real no Packagist usa underscore. Sem impacto funcional, só nota para não confundir com o pacote apk `tesseract-ocr` (esse sim com hífen). |
| Teste de config usa `$_ENV`/`$_SERVER` directamente + `require config_path(...)` em vez de `Config::set()` + `config('extracao')` | `Config::set()` simulando o valor já resolvido | As flags são calculadas **no momento do load do ficheiro de config** (`filled(env(...))` executa ao `require`), não em runtime — `Config::set()` só substituiria o array já resolvido, não testaria a derivação em si. Escrever/ler `$_ENV`/`$_SERVER` e voltar a `require` o ficheiro reproduz o comportamento real do boot do Laravel. |

## Desvios ao Plano

- **`docker/imagemagick/policy.xml` não criado** (RF-06 da Spec previa este ficheiro) — ver "Decisões tomadas". CA-03 (rasterizar PDF sem erro de `policy.xml`) continua cumprido, só que sem alteração de ficheiro nenhuma, porque o `policy.xml` já estava permissivo. Divergência do RF-06 documentada e justificada em `ambiente-docker.md`.
- Nome do pacote Composer confirmado como `thiagoalessio/tesseract_ocr` (Plano/Brief usavam `tesseract-ocr`) — sem impacto de comportamento.

## Aprendizagens

O ponto mais instrutivo foi perceber que uma decisão de arquitectura tomada no Checkpoint A (Brief) — "policy.xml dedicado, ficheiro novo copiado na imagem" — partia de um pressuposto (policy restritivo por omissão) válido para Debian/Ubuntu mas não verificado para a base Alpine efectivamente usada (`php:8.5-fpm-alpine`). Só a validação empírica em Docker (CA-02/CA-03, que a Spec já assinalava como não-testável no gate mockado) revelou que o pressuposto não se aplicava aqui. Isto reforça o valor de critérios de aceitação que exigem prova em ambiente real quando a lógica depende de comportamento de um binário/pacote de sistema — o gate `build-and-test` (MySQL, mockado) nunca teria apanhado isto, e sem a validação manual o ficheiro `policy.xml` teria sido criado (e commitado) como código morto, ou pior, uma alteração "protectora" que na realidade nada alterava.

Também ficou mais claro o limite do padrão `env()` dentro de ficheiros `config/*.php`: normalmente desaconselhado fora de config (quebra `config:cache`), mas aqui é o único sítio correcto para derivar flags fail-safe a partir de env vars opcionais — e testar essa derivação exige simular o boot real (`$_ENV`/`$_SERVER` + `require` do ficheiro), não `Config::set()`, porque o valor já está "congelado" no array antes de `Config::set()` conseguir intervir. Isto é uma nuance específica de configuração derivada de env, diferente de configuração estática habitual.

## SYSTEM_SPEC a actualizar

- `docs/system_spec/04-infra/external-apis.md` — já actualizado nesta implementação (Tarefa 7)
- `docs/system_spec/04-infra/ambiente-docker.md` — já actualizado nesta implementação (Tarefa 7)
- `docs/system_spec/06-config.md` — já actualizado nesta implementação (Tarefa 7)
- `docs/system_spec/00-index.md` — já actualizado nesta implementação (Tarefa 7)

## Verificação final

- [x] Linter a verde (Pint + Rector)
- [x] Testes a verde (891/891, 100% type coverage, 100% code coverage, Larastan nível 9)
- [x] `docker compose up --build`: `php -m` lista `imagick`, `tesseract --version` responde, PDF de teste rasteriza via `imagick` sem erro de `policy.xml` (CA-02/CA-03)
- [x] `LLM_CLOUD_KEY` sem valor real em nenhum ficheiro commitado (`git grep` limpo, CA-07)
- [x] Nenhum dado sensível em logs
- [x] Nenhum segredo em código
