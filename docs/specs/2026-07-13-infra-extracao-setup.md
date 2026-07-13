# Spec: Infra de extração — Prism + pdfparser + Tesseract/imagick + config LLM (setup)

**Issue:** #95
**Brief:** docs/briefs/2026-07-13-infra-extracao-setup.md
**Data:** 2026-07-13

## Requisitos funcionais

- RF-01: `composer.json`/`composer.lock` incluem `prism-php/prism`, `smalot/pdfparser`
  e `thiagoalessio/tesseract-ocr` instalados e resolvidos.
- RF-02: `config/prism.php` publicado via `php artisan vendor:publish` (tag do Prism) e
  adaptado: provider `ollama` ligado a `LLM_LOCAL_URL`/`LLM_LOCAL_MODEL`; provider
  OpenAI-compatible para a camada cloud ligado a `LLM_CLOUD_URL`/`LLM_CLOUD_MODEL`/`LLM_CLOUD_KEY`.
- RF-03: `config/extracao.php` (novo) expõe `threshold_caracteres` (50), `ttl_lease`,
  `max_tentativas` (3), limites por command (placeholders para a #98), e as flags
  `camada_local_activa` / `camada_cloud_activa` derivadas de `filled(env(...))`.
- RF-04: `.env.example` documenta as 5 vars (`LLM_LOCAL_URL`, `LLM_LOCAL_MODEL`,
  `LLM_CLOUD_URL`, `LLM_CLOUD_MODEL`, `LLM_CLOUD_KEY`) com comentário explicando que
  comentar/esvaziar uma camada a desliga.
- RF-05: `Dockerfile` instala `tesseract-ocr`, `tesseract-ocr-data-por`,
  `tesseract-ocr-data-eng`, `ghostscript` (via `apk add`) e a extensão `imagick`
  (via `install-php-extensions`).
- RF-06: `docker/imagemagick/policy.xml` (ficheiro dedicado, novo) relaxa a política
  por omissão do Alpine para permitir leitura de PDF/PS pelo `imagick`; copiado para
  o caminho de policy do ImageMagick na imagem.
- RF-07: `compose.yaml` injecta `LLM_LOCAL_*`/`LLM_CLOUD_*` no bloco `x-app-env`
  (`app`/`queue` herdam automaticamente por já usarem `<<: *app-env`).

## Requisitos não funcionais

- RNF-01: Zero binários de sistema (Tesseract/Ghostscript/imagick) exigidos pelo gate
  `build-and-test` (MySQL) — validação desses binários fica só no job `docker-parity`.
- RNF-02: `LLM_CLOUD_KEY` tratada como secret — nunca commitada com valor real,
  só declarada vazia/comentada em `.env.example`.
- RNF-03: `composer test` verde (Larastan nível 9 zero erros, type-coverage 100%)
  após adicionar os 3 pacotes — sem introduzir código de domínio nesta issue.
- RNF-04: `config('extracao')` e `config('prism')` carregam sem erro em qualquer
  ambiente (local sem binários, Docker com binários).

## Modelo de dados

Não aplicável — esta issue não cria/altera migrations, tabelas ou Models
(ver `03-models/*`, fora de âmbito; a tabela `extracoes_documento` é da #94).

## Regras de negócio

- RN-01: config incompleta (falta qualquer var exigida por uma camada) ⇒ essa
  camada fica **inactiva** — nunca lança excepção no boot da aplicação
  (fail-safe, conforme decisão já fixada nas issues do mecanismo).
- RN-02: parser e OCR (dependências de sistema) são **sempre** considerados
  disponíveis — não têm flag de activação; só as camadas LLM (local/cloud) são opcionais.

## Dependências

- Issues bloqueantes: nenhuma — issue arranca já (confirmado no corpo da #95).
- Desbloqueia: #96 (extractores), #97 (cliente IA) — ambas dependem desta.
- Relacionada (paralela, sem bloqueio): #94 (modelo de dados).

## Questões resolvidas

| Questão (do Brief) | Decisão |
| ------------------- | ------- |
| Publicar `config/prism.php` via `vendor:publish` ou escrever à mão? | Publicar via `vendor:publish` e adaptar às env `LLM_*`. |
| Provider cloud/local do Prism | Local → provider `ollama` nativo do Prism; Cloud → provider OpenAI-compatible apontado a `LLM_CLOUD_URL` custom. |
| `policy.xml`: ficheiro dedicado vs patch `sed` in-place | Ficheiro dedicado `docker/imagemagick/policy.xml`, copiado na imagem. |
| Vars LLM no `.env`/compose e serviço `scheduler` | Vars no `.env`/`.env.example`, já injectáveis no `x-app-env` (`app`/`queue`). Serviço `scheduler` + `extra_hosts`/rede efectiva do pipeline ficam para a #98. |
| Âmbito geral | Issue **só** acrescenta/regista pacotes (Composer + binários Docker) — tratamento mais leve que uma feature de domínio (sem Actions/testes de domínio); config inclui vars sensíveis (`LLM_CLOUD_KEY`) tratadas como secret no Prism. |

## Critérios de aceitação

> Herdados da issue #95 — não reformulados.

- [ ] CA-01: `composer install` resolve os 3 pacotes; `composer test` verde (Larastan L9, sem novos erros). *(issue)*
- [ ] CA-02: `docker compose up --build` constrói a imagem com Tesseract (`por`+`eng`), `imagick` e Ghostscript; `php -m` lista `imagick`; `tesseract --version` responde no container. *(issue)*
- [ ] CA-03: Um PDF de teste rasteriza via `imagick` sem erro de `policy.xml`. *(issue)*
- [ ] CA-04: `config('extracao')` e `config('prism')` carregam; `camada_local_activa`/`camada_cloud_activa` derivam corretamente da presença das vars (incl. config incompleta → inactiva). *(issue)*
- [ ] CA-05: `.env.example` documenta as 5 vars LLM e explica que comentar/esvaziar desliga a camada. *(issue)*
- [ ] CA-06: Docs `04-infra/*` + `06-config.md` atualizadas + `00-index.md` coerente. *(issue)*
- [ ] CA-07: `LLM_CLOUD_KEY` não aparece com valor real em nenhum ficheiro commitado (`git grep` limpo). *(spec)*

## SYSTEM_SPEC a actualizar

- `docs/system_spec/04-infra/external-apis.md` — secção Prism + camadas local/cloud opcionais por presença de vars (substitui o estado "cliente HTTP pendente").
- `docs/system_spec/04-infra/ambiente-docker.md` — nova secção Tesseract/imagick/ghostscript, `policy.xml`, rede do LLM externo (`host.docker.internal`).
- `docs/system_spec/06-config.md` — as 5 env vars LLM + `config/extracao.php` + `config/prism.php`.
- `docs/system_spec/00-index.md` — linha de estado de `external-apis.md` actualizada (de "parcial" para reflectir o setup feito); sem ficheiro novo de feature (não há slice nova).

## Verificação RGPD/NIS2

- Dados pessoais: nenhum tratado nesta issue (sem lógica de pipeline nem tabelas com PII — isso é #94/#97/#98).
- Superfície de ataque: `LLM_CLOUD_KEY` é a única credencial introduzida — mitigado por viver só em `.env` (nunca commitada) e por `.env` já estar em `.gitignore` (confirmado, ver `docs/process-warnings.md` WRN-002). Sem novas rotas HTTP nem exposição de rede além do container Docker interno.
