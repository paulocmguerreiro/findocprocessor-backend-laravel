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

A imagem (`Dockerfile`) instala extensões via `install-php-extensions`
(inclui `pcov` para cobertura) e fixa `memory_limit=512M` (a análise estática
excede os 128M por omissão). `app` e `queue` partilham a imagem `findocprocessor-app`.

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
