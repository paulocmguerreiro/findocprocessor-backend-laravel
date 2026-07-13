# Plano: Infra de extração — Prism + pdfparser + Tesseract/imagick + config LLM (setup)

**Issue:** #95
**Spec:** docs/specs/2026-07-13-infra-extracao-setup.md
**Data:** 2026-07-13

## Tarefas

### Tarefa 1 — Instalar pacotes Composer
- Ficheiros a criar/alterar: `composer.json`, `composer.lock`
- O que implementar: `composer require prism-php/prism smalot/pdfparser thiagoalessio/tesseract-ocr`. Confirmar que `composer test:types` (Larastan) continua zero erros com os novos pacotes carregados (autoload de classes de terceiros não deve introduzir erros).
- Testes associados: nenhum teste novo (sem código de domínio); `composer test` completo corre no fim da Tarefa 6.
- Commit: `chore(deps): adicionar prism-php/prism, smalot/pdfparser e thiagoalessio/tesseract-ocr`

### Tarefa 2 — Publicar e adaptar `config/prism.php`
- Ficheiros a criar/alterar: `config/prism.php` (publicado + editado)
- O que implementar: `php artisan vendor:publish --tag=prism-config` (ou tag equivalente da versão instalada — confirmar nome exacto ao correr `vendor:publish` sem tag e escolher o do Prism). Editar o array `providers`:
  - `ollama => ['url' => env('LLM_LOCAL_URL', 'http://localhost:11434/v1')]` (camada local, sem `api_key`);
  - provider OpenAI-compatible para a camada cloud (`url` = `env('LLM_CLOUD_URL')`, `api_key` = `env('LLM_CLOUD_KEY')`) — usar o provider `openai` nativo do Prism apontado ao `url` custom (confirma-se contra `config/prism.php` publicado se o provider `openai` aceita `url` custom; se não aceitar, registar como provider próprio via `Prism::extend()` em `AppServiceProvider::boot()` — decisão a validar nesta tarefa, documentar a que se optou no Debrief).
- Testes associados: nenhum (config puro); validar manualmente com `php artisan config:show prism` após `.env` preenchido.
- Commit: `feat(config): publicar e configurar providers Prism (ollama local + openai-compatible cloud)`

### Tarefa 3 — Criar `config/extracao.php`
- Ficheiros a criar/alterar: `config/extracao.php` (novo)
- O que implementar:
  ```php
  return [
      'threshold_caracteres' => 50,
      'ttl_lease' => env('EXTRACAO_TTL_LEASE', 300), // segundos — afinado na #98
      'max_tentativas' => 3,
      'camada_local_activa' => filled(env('LLM_LOCAL_URL')) && filled(env('LLM_LOCAL_MODEL')),
      'camada_cloud_activa' => filled(env('LLM_CLOUD_URL')) && filled(env('LLM_CLOUD_MODEL')) && filled(env('LLM_CLOUD_KEY')),
  ];
  ```
  Nota: `env()` fora de ficheiros de config normalmente é desaconselhado (quebra `config:cache`), mas aqui é o padrão correcto — está dentro de `config/*.php`, single source, mesmo comportamento de qualquer outro valor de config derivado de env. Documentar em `06-config.md` que alterar as vars exige `config:clear`.
- Testes associados: `tests/Unit/Config/ExtracaoConfigTest.php` — testa que `camada_local_activa`/`camada_cloud_activa` derivam correctamente com vars preenchidas/vazias (usa `Config::set` + reload, ou testa a função `filled()` isoladamente contra os cenários). Ver Tarefa 6 para o CA-04 completo.
- Commit: `feat(config): criar config/extracao.php com flags de camada activa`

### Tarefa 4 — Docker: Tesseract, Ghostscript, imagick + `policy.xml`
- Ficheiros a criar/alterar: `Dockerfile`, `docker/imagemagick/policy.xml` (novo)
- O que implementar:
  - `Dockerfile`: adicionar ao `apk add --no-cache` existente: `tesseract-ocr tesseract-ocr-data-por tesseract-ocr-data-eng ghostscript`; acrescentar `imagick` à linha `install-php-extensions` já existente.
  - `docker/imagemagick/policy.xml`: cópia do policy.xml Alpine por omissão com as linhas `<policy domain="coder" rights="none" pattern="PDF" />` (e `PS`/`EPS` equivalentes) alteradas para `rights="read|write"` (ou removidas) — relaxar só o necessário para o parser rasterizar PDFs, não abrir tudo.
  - `Dockerfile`: `COPY docker/imagemagick/policy.xml /etc/ImageMagick-7/policy.xml` (confirmar o caminho exacto do policy.xml na imagem `php:8.5-fpm-alpine` com ImageMagick instalado — pode variar, verificar com `find / -name policy.xml` dentro do container antes de fixar o `COPY`).
- Testes associados: nenhum automatizável no gate `build-and-test` (binários só existem em Docker). Validação manual/CI: CA-02 e CA-03 (ver Tarefa 6 e critérios de aceitação).
- Commit: `chore(docker): adicionar Tesseract/Ghostscript/imagick + relaxar policy.xml do ImageMagick`

### Tarefa 5 — Compose: vars LLM no `x-app-env`
- Ficheiros a criar/alterar: `compose.yaml`
- O que implementar: adicionar ao bloco `x-app-env` (`&app-env`):
  ```yaml
  LLM_LOCAL_URL: ${LLM_LOCAL_URL:-}
  LLM_LOCAL_MODEL: ${LLM_LOCAL_MODEL:-}
  LLM_CLOUD_URL: ${LLM_CLOUD_URL:-}
  LLM_CLOUD_MODEL: ${LLM_CLOUD_MODEL:-}
  LLM_CLOUD_KEY: ${LLM_CLOUD_KEY:-}
  ```
  `app`/`queue` herdam automaticamente via `<<: *app-env`. **Não** adicionar `extra_hosts`/serviço `scheduler` — fora de âmbito (#98).
- Testes associados: nenhum; validar com `docker compose config` que as vars aparecem resolvidas.
- Commit: `chore(docker): injectar vars LLM_LOCAL_*/LLM_CLOUD_* no compose`

### Tarefa 6 — `.env.example` + testes de config + validação Docker
- Ficheiros a criar/alterar: `.env.example`, `tests/Unit/Config/ExtracaoConfigTest.php`
- O que implementar:
  - `.env.example`: acrescentar secção comentada:
    ```
    # Extração por IA — pipeline PdfParser → OCR → LLM local → LLM cloud (ver #94-#98).
    # Comentar/esvaziar qualquer var de uma camada desliga essa camada (fail-safe).
    LLM_LOCAL_URL=
    LLM_LOCAL_MODEL=
    LLM_CLOUD_URL=
    LLM_CLOUD_MODEL=
    LLM_CLOUD_KEY=
    ```
  - Teste unitário cobrindo CA-04 (flags derivadas correctamente, incl. config incompleta → inactiva).
  - Validação manual/local (fora do gate automatizado): `docker compose up --build`, depois dentro do container `php -m | grep imagick`, `tesseract --version`, e um teste manual de rasterização de PDF via `imagick` (CA-02/CA-03) — documentar o resultado no Debrief.
- Testes associados: `tests/Unit/Config/ExtracaoConfigTest.php` (CA-04); `composer test` completo (CA-01).
- Commit: `feat(config): documentar vars LLM em .env.example + testes de config/extracao`

### Tarefa 7 — Documentação system_spec
- Ficheiros a criar/alterar: `docs/system_spec/04-infra/external-apis.md`, `docs/system_spec/04-infra/ambiente-docker.md`, `docs/system_spec/06-config.md`, `docs/system_spec/00-index.md`
- O que implementar: actualizar conforme `SYSTEM_SPEC a actualizar` da Spec — Prism + camadas opcionais, Tesseract/imagick/ghostscript + policy.xml + rede LLM externo, as 5 env vars + `config/extracao.php`/`config/prism.php`, coerência do índice.
- Testes associados: nenhum (documentação).
- Commit: `docs(system-spec): documentar infra de extração (Prism, Tesseract/imagick, config LLM)`

## Ordem de implementação

1. Tarefa 1 (pacotes Composer) — base para tudo, sem dependências.
2. Tarefa 2 (`config/prism.php`) — depende do pacote `prism-php/prism` instalado (Tarefa 1).
3. Tarefa 3 (`config/extracao.php`) — independente das anteriores, mas agrupada por serem ambas config.
4. Tarefa 4 (Docker: Tesseract/Ghostscript/imagick/policy.xml) — independente das configs PHP, mas testável só depois de existir imagem para rebuild.
5. Tarefa 5 (compose: vars LLM) — depende conceptualmente das vars definidas nas Tarefas 2/3 (mesmos nomes), aplicar depois.
6. Tarefa 6 (`.env.example` + testes + validação Docker) — fecha o ciclo, valida Tarefas 1-5 em conjunto (precisa da imagem da Tarefa 4 já buildada).
7. Tarefa 7 (docs) — sempre por último, reflecte o estado final real.

## Testes a escrever

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| Flags de camada activa/inactiva | unit | `tests/Unit/Config/ExtracaoConfigTest.php` | `camada_local_activa`/`camada_cloud_activa` derivam correctamente da presença/ausência das vars (CA-04) |

## Dependências

- Issues bloqueantes: nenhuma.
- Deve ser implementada após: nenhuma (1.ª do mecanismo, conforme #95 e #96/#97 que dela dependem).

## Riscos de implementação

> Consolidados do Brief e da Spec.

- **Formato de `config/prism.php` (versão instalada):** confirmar na Tarefa 2, ao publicar via `vendor:publish`, o schema real de providers (chaves `url`/`api_key`) e se o provider `openai` do Prism aceita `url` custom para o caso OpenAI-compatible — se não aceitar, registar provider próprio via `Prism::extend()`.
- **Caminho do `policy.xml` na imagem Alpine com ImageMagick 7:** não assumir `/etc/ImageMagick-7/policy.xml` sem confirmar — inspeccionar o container antes de fixar o `COPY` na Tarefa 4.
- **`policy.xml` só testável em Docker** — CA-02/CA-03 não são verificáveis pelo gate `build-and-test`; validação manual + documentação no Debrief é obrigatória antes do checkpoint ②.
- **`config('extracao')` cacheado:** documentar em `06-config.md` (Tarefa 7) que alterar as vars LLM exige `config:clear`/rebuild.
- **`LLM_CLOUD_KEY` como secret:** nunca commitar valor real — `.env.example` fica sempre vazio.
- **WARN "Package Freshness"** pode surgir no `checkpoint:scan` por pacotes recém-instalados — não é defeito (ver WRN-001), ignorar se surgir.

## O que NÃO fazer nesta issue

- Não implementar extractores de texto/OCR (`app/Infrastructure/Extracao/*`) — issue #96.
- Não implementar cliente IA (`app/Infrastructure/AI/*`) — issue #97.
- Não criar `extracoes_documento`, enums `EtapaExtracao`/`ResultadoEtapa`, `RegistarEtapaExtracaoAction` — issue #94.
- Não criar Commands `extracao:*`, serviço `scheduler` no compose, nem `extra_hosts` — issue #98.
- Não implementar lógica de salto entre camadas LLM inactivas — issue #98.
- Não escrever testes de domínio (Actions/Models) — não existem nesta issue.
