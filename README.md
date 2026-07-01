# FinDocProcessor — Backend Laravel

[![CI](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/actions/workflows/ci.yml)
![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Larastan](https://img.shields.io/badge/Larastan-nível%209-2D2D2D)
![Coverage](https://img.shields.io/badge/cobertura-100%25-3FB950)
![License](https://img.shields.io/badge/licença-MIT-blue)

> API REST para processamento de documentos financeiros, organizada em **Vertical Slice Architecture** em Laravel 13 / PHP 8.5.

**Regras estruturais/lógicas:**

- **Vertical Slice Architecture** consistente — lógica organizada por caso de uso (`app/Features/`), não por camada técnica.
- **Actions `final readonly`** como unidade de lógica, controllers magros (só fazem dispatch), DTOs como Value Objects.
- **Autorização dupla camada** (`Gate::authorize()` no FormRequest **e** na Action) — cobre HTTP e não-HTTP (Jobs, Artisan, testes).
- **Qualidade imposta por CI:** Larastan nível 9, 100% type-coverage, 100% cobertura de testes, Pint e Rector — zero excepções.
- **Testes em padrão dual** por slice: invocação directa (unit) **e** via HTTP (feature).

## Stack

- **Laravel 13** / PHP 8.5 — Vertical Slice Architecture
- **Laravel Sanctum** — autenticação API via Bearer tokens
- **Spatie Laravel Permission** — autorização por roles (`admin`, `utilizador`) e permissions granulares
- **spatie/laravel-activitylog** — audit trail persistente (`activity_log`) com atomicidade garantida pela transação
- **Eloquent ORM** — SQLite (dev/testes) / MySQL (Docker)
- **Redis + predis** — cache com invalidação por tags (`CacheServico`, `TagCache`, `TtlCache`)
- **Pest 4 + Mockery** — padrão de testes dual (unit + HTTP)
- **Larastan nível 9 + Rector + Laravel Pint** — qualidade e tipagem estática

## Arquitectura

```
app/Features/              ← Actions agrupadas por caso de uso (Vertical Slice)
app/Models/                ← Eloquent Models (UUID PK, @property-read)
app/Policies/              ← Autorização por Gate/Policy
app/Shared/                ← Enums, Http (ApiResponse) e Cache (CacheServico, TagCache, TtlCache)
app/Http/Controllers/      ← Thin controllers (só dispatch para Actions)
app/Http/Middleware/       ← InjectarContextoLog (trace_id UUID por request via Context facade)
app/Infrastructure/        ← AI, FileSystem, Repositories  →  ver Roadmap (ingestão de documentos)
app/Jobs/                  ← Processamento assíncrono       →  ver Roadmap (pipeline de inbox)
```

> As pastas `app/Infrastructure/` e `app/Jobs/` estão reservadas para o pipeline
> de ingestão de documentos (OCR / análise de imagem / IA) descrito no
> [Roadmap](#roadmap) — ainda não contêm implementação.

Padrões aplicados: Actions `final readonly`, autorização dupla camada (`Gate::authorize()` no FormRequest **e** na Action), `DB::transaction()` em todas as escritas, DTOs como Value Objects, `strict_types=1` em todos os ficheiros, cursor pagination (keyset) nas listagens, cache Redis com invalidação por tags e logging estruturado com `trace_id` por request (propagado a Jobs).

> Documentação de arquitectura detalhada em [`docs/system_spec/00-index.md`](docs/system_spec/00-index.md).

## Como correr

### Opção A — Docker (MySQL + Redis, recomendado)

Stack completo a partir de um clone limpo, sem PHP/Composer instalados localmente:

```bash
docker compose up -d --build
```

- API em `http://localhost:8000`
- Migrations e seed correm automaticamente no arranque
- Correr a pipeline de qualidade dentro do container:

```bash
docker compose exec app composer test
```

### Opção B — Local (SQLite)

```bash
# Pré-requisitos: PHP 8.5, Composer, Node.js
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed      # SQLite por omissão em dev
php artisan serve
```

API disponível em `http://localhost:8000`.

### Acesso

**Autenticação:** todas as rotas (excepto `POST /api/auth/login`) exigem `Authorization: Bearer <token>`. Obter token via `POST /api/auth/login` com `email` e `password`.

**Autorização:** duas roles — `admin` (acesso total) e `utilizador` (só leitura). Após o seed, usar `admin@findocprocessor.test` / `password` (o seeder cria também o token de dev `dev-token`).

## Testes

```bash
composer test          # pipeline completa (lint + arquitectura + tipos + cobertura)
composer test:types    # Larastan nível 9 — zero erros
composer test:coverage # Pest — cobertura 100%
```

## API — estado actual

### Auth

| Método | Path | Descrição | Auth |
| ------ | ---- | --------- | ---- |
| POST | `/api/auth/login` | Obter token Bearer | — (pública) |
| POST | `/api/auth/logout` | Revogar token actual | Bearer |
| POST | `/api/auth/tokens` | Criar token adicional | Bearer |

### Categorias de documento

Todas as rotas exigem Bearer token.

| Método | Path                             | Descrição          |
| ------ | -------------------------------- | ------------------ |
| GET    | `/api/categorias-documento`      | Listar (cursor)    |
| POST   | `/api/categorias-documento`      | Criar              |
| GET    | `/api/categorias-documento/{id}` | Ver detalhe        |
| PUT    | `/api/categorias-documento/{id}` | Actualizar (completo) |
| DELETE | `/api/categorias-documento/{id}` | Eliminar           |

### Entidades

Todas as rotas exigem Bearer token.

| Método | Path                                    | Descrição                    |
| ------ | --------------------------------------- | ---------------------------- |
| GET    | `/api/entidades`                        | Listar (cursor; `?estado=todos\|somente_ativos\|somente_inativos`) |
| POST   | `/api/entidades`                        | Criar                        |
| GET    | `/api/entidades/{id}`                   | Ver detalhe                  |
| PUT    | `/api/entidades/{id}`                   | Actualizar (completo)        |
| DELETE | `/api/entidades/{id}`                   | Eliminar (soft delete se referenciada) |
| PATCH  | `/api/entidades/{id}/restaurar`         | Restaurar (reactivar soft-deleted) |
| PATCH  | `/api/entidades/{id}/empresa-mae`       | Converter em empresa-mãe     |

### Documentos

Todas as rotas exigem Bearer token. Ciclo de estados `Pendente → AguardaEnvio → Enviado → AguardaResposta → Processado`, com ramos terminais `Erro` e `Perigoso`. As transições são validadas por `RegraTransicaoEstado` — uma transição inválida devolve `422`.

| Método | Path                                       | Descrição                                  |
| ------ | ------------------------------------------ | ------------------------------------------ |
| GET    | `/api/documentos`                          | Listar (cursor)                            |
| POST   | `/api/documentos`                          | Registar manualmente (`multipart/form-data`) |
| POST   | `/api/documentos/upload`                   | Receber upload (`multipart/form-data`)     |
| GET    | `/api/documentos/{id}`                      | Ver detalhe (com histórico de etapas)      |
| PATCH  | `/api/documentos/{id}`                      | Corrigir metadados                         |
| DELETE | `/api/documentos/{id}`                      | Eliminar                                   |
| GET    | `/api/documentos/{id}/ficheiro`             | Descarregar ficheiro                       |
| POST   | `/api/documentos/{id}/reprocessar`          | Reprocessar (volta a estado anterior)      |

### Roles & Utilizadores

Todas as rotas exigem Bearer token (role `admin`).

| Método | Path                              | Descrição                |
| ------ | --------------------------------- | ------------------------ |
| GET/POST/PUT/DELETE | `/api/roles`         | CRUD de roles            |
| GET    | `/api/utilizadores`               | Listar (cursor; `?estado=todos\|somente_ativos\|somente_inativos`) |
| POST   | `/api/utilizadores`               | Criar utilizador (com `role` opcional) |
| GET    | `/api/utilizadores/{id}`          | Ver detalhe (próprio sempre permitido) |
| PUT    | `/api/utilizadores/{id}`          | Actualizar (password opcional) |
| DELETE | `/api/utilizadores/{id}`          | Eliminar (hard/soft conforme referências) |
| PUT    | `/api/utilizadores/{id}/role`     | Atribuir role a utilizador |
| PATCH  | `/api/utilizadores/{id}/restaurar`  | Restaurar (reactivar soft-deleted) |
| POST   | `/api/utilizadores/{id}/anonimizar` | Anonimizar (RGPD Art. 17.º — dados + soft delete + revoga tokens) |

## Qualidade

- Larastan nível 9 (PHPStan com regras Laravel) — zero erros
- 100% type-coverage (todos os tipos declarados)
- Cobertura de testes 100% (Pest, padrão dual unit + HTTP)
- `strict_types=1` em todos os ficheiros
- Laravel Pint (PSR-12 + opinionated) + Rector (modernização PHP 8.5)
- CI obrigatório: pint ✓ rector ✓ phpstan ✓ testes ✓

## Roadmap

Próximos passos, geridos como issues no repositório:

- **Gestão financeira** _(próximo)_ — movimentos (débito/crédito) associados a documentos e entidades.
- **Pipeline de ingestão** — Jobs + Schedule sobre a pasta de inbox, com OCR, análise de imagem e extração de dados via IA (`app/Infrastructure/AI`).

## Relacionado (roadmap)

- `findocprocessor-frontend` — Dashboard Angular (repositório separado)
- `findocprocessor-backend-dotnet` — Implementação alternativa em .NET

## Licença

[MIT](LICENSE) © Paulo Guerreiro
