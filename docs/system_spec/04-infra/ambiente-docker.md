# System Spec — Infra: Ambiente Docker e paridade de testes

> Ficheiros: `Dockerfile`, `compose.yaml`, `docker/`, `.github/workflows/ci.yml`

Stack Docker para desenvolvimento/demonstração e estratégia de paridade entre o
ambiente de testes e o stack real (MySQL + Redis).

## Stack (`compose.yaml`)

| Serviço | Imagem | Papel |
|---|---|---|
| `app` | build local (PHP 8.5 FPM) | Aplicação; corre migrations + seed no arranque |
| `web` | `nginx:1.27-alpine` | Reverse proxy → `app:9000`; expõe `http://localhost:8000` |
| `queue` | mesma imagem que `app` | `php artisan queue:work` |
| `mysql` | `mysql:8.4` | Base de dados (`findocprocessor` + `findocprocessor_testing`) |
| `redis` | `redis:7-alpine` | Cache e queue |
| `clamav` | `clamav/clamav-debian:1.4` | Scan de malware (`clamd`, protocolo INSTREAM) — issue #91 |

A imagem (`Dockerfile`) instala extensões via `install-php-extensions`
(inclui `pcov` para cobertura e `imagick`, para rasterização de PDF/PS do
pipeline de extração, #95) e fixa `memory_limit=512M` (a análise estática
excede os 128M por omissão). Via `apk add` instala também `tesseract-ocr`
(+ dados `por`/`eng`) e `ghostscript` (delegate do ImageMagick para PDF).
`app` e `queue` partilham a imagem `findocprocessor-app`.

### `imagick` e `policy.xml` (#95)

O `apk imagemagick` (base Alpine) instala um `policy.xml` **aberto** por
omissão (`/etc/ImageMagick-7/policy.xml`) — ao contrário de imagens
Debian/Ubuntu, **não restringe** a leitura/escrita de PDF/PS. Verificado
empiricamente (rasterização de um PDF de teste via `Imagick::readImage()`
dentro do container `app`, sem qualquer patch ao `policy.xml`). Por isso
**não existe** `docker/imagemagick/policy.xml` neste repo — seria um ficheiro
morto, idêntico ao default do pacote. Se no futuro se mudar a imagem base
(ex.: Debian) ou o pacote `imagemagick` do Alpine passar a restringir PDF,
revisitar esta secção.

### Rede: LLM externo ao container (#95)

As camadas LLM (`LLM_LOCAL_*`/`LLM_CLOUD_*`, ver `06-config.md`) apontam para
serviços **fora** do container `app`/`queue` (Ollama local, provider cloud).
As 5 env vars são injectadas no `x-app-env` do `compose.yaml` (vazias por
omissão — fail-safe, camada inactiva). Alcançar um LLM local a correr no host
requer `host.docker.internal` (nativo no Docker Desktop macOS/Windows; em
Linux requer `extra_hosts: ["host.docker.internal:host-gateway"]` no
serviço) — a configuração efectiva de rede/`extra_hosts` para o pipeline
correr fica para a issue #98, fora do âmbito da #95 (que só injecta as vars).

### `clamav` — scan de malware (#91)

Imagem `clamav/clamav-debian:1.4` (multi-arch — inclui `arm64`; `clamav/clamav:1.4`, a imagem
"oficial" mais divulgada, só publica `amd64` e falha em Macs Apple Silicon). Sem `ports:` — só
acessível na rede `findoc` interna, via o alias de serviço `clamav` (`CLAMAV_HOST=clamav`,
`CLAMAV_PORT=3310` em `x-app-env`). Volume nomeado `clamav-data:/var/lib/clamav` persiste as
assinaturas entre reinícios (`freshclam` corre embutido, autoupdate sem intervenção da app).

Healthcheck via `clamdcheck.sh` (script embutido na imagem) com `start_period: 300s` — o arranque
carrega as assinaturas em RAM e pode demorar minutos na primeira vez; `app`/`queue` declaram
`depends_on: clamav: condition: service_healthy`, só arrancando depois do `clamd` estar pronto a
aceitar ligações.

## Entrypoint (`docker/entrypoint.sh`)

Idempotente — suporta reinícios do container:

- Garante `.env` (a partir de `.env.example`) e gera `APP_KEY` se faltar.
- Só o `app` prepara a BD (`RUN_MIGRATIONS=true`): corre **migrations sempre**
  (versionadas) e **seed só quando a BD está vazia** (evita violar constraints
  de unicidade em reinícios). O `queue` apenas aguarda.

## Estratégia de paridade de testes

A suite corre **exclusivamente em MySQL** — o mesmo motor de produção, sem modo alternativo.

- `composer test` — pipeline completa: preflight (valida MySQL + Redis via `/dev/tcp`), lint, arch, types, type-coverage e coverage.
- A BD de testes é `findocprocessor_testing` (criada por `docker/mysql/init.sql` na primeira inicialização do volume), nunca toca na BD de desenvolvimento.
- O paralelo (`--parallel`) cria BDs temporárias `findocprocessor_testing_test_N`; o utilizador `findoc` tem `GRANT ALL ON *.*` para esse efeito.
- Uso típico: `docker compose exec app composer test`.

> Se o volume `mysql-data` já existir de um arranque anterior com collation diferente,
> recriar o volume: `docker compose down -v && docker compose up -d`.

## CI (`.github/workflows/ci.yml`)

Dois jobs:

| Job | O que faz |
|---|---|
| `build-and-test` | `composer test` (MySQL) — pipeline de qualidade completa com serviço `mysql:8.4`, gate obrigatório |
| `docker-parity` | `docker compose up --build` + `php artisan about --only=Environment` — valida que a imagem constrói e que a app arranca. **Não** corre a suite |

O `test:preflight` corre automaticamente dentro de `composer test` — não é necessário step separado no CI.
O job `build-and-test` executa um step de `GRANT ALL ON *.*` antes da suite para permitir ao paralelo criar BDs temporárias.
