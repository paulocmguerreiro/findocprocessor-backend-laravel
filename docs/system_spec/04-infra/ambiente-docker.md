# System Spec — Infra: Ambiente Docker e paridade de testes

> Ficheiros: `Dockerfile`, `compose.yaml`, `docker/`, `phpunit.mysql.xml`, `.github/workflows/ci.yml`

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

A suite corre em **dois modos**, com a mesma bateria de testes:

| Modo | Comando | BD | Quando |
|---|---|---|---|
| Rápido (default) | `composer test` | sqlite `:memory:` (forçado em `phpunit.xml`) | Por tarefa e no CI — hermético, paralelo, rápido |
| Paridade real | `composer test:mysql` | MySQL `findocprocessor_testing` (forçado em `phpunit.mysql.xml`) | Local, gate de publicação — o mais próximo de produção |

- O `force="true"` em `phpunit.xml` garante que o modo rápido **nunca** usa a BD
  do ambiente (mesmo dentro do container, onde `DB_CONNECTION=mysql`).
- O `test:mysql` usa uma BD **dedicada** (`findocprocessor_testing`, criada por
  `docker/mysql/init.sql` na primeira inicialização do volume) — nunca toca na BD
  de desenvolvimento. Não usa `--parallel` para dispensar privilégio de `CREATE DATABASE`.
- Uso típico: `docker compose exec -T app composer test:mysql`.
- A cobertura 100% continua a ser garantida pelo modo rápido (`composer test`).

> Se o volume `mysql-data` já existir de um arranque anterior, a BD de teste só é
> criada após `docker compose down -v && docker compose up -d`.

## CI (`.github/workflows/ci.yml`)

Dois jobs:

| Job | O que faz |
|---|---|
| `build-and-test` | `composer test` (sqlite) — pipeline de qualidade completa, gate obrigatório |
| `docker-parity` | `docker compose up --build` + `migrate:status` — valida que a imagem constrói e que a app arranca e liga ao MySQL. **Não** corre a suite |

A suite contra MySQL (`composer test:mysql`) corre **apenas localmente** — o CI
valida apenas que o Docker constrói e arranca, não repete a suite contra MySQL.
