# FinDocProcessor — Backend Laravel

Implementação de pipeline de processamento de documentos financeiros, em Laravel / PHP. 

## Stack

- **Laravel 13** / PHP 8.5 — Vertical Slice Architecture
- **Laravel Sanctum** — autenticação API via Bearer tokens
- **Spatie Laravel Permission** — autorização por roles (`admin`, `utilizador`) e permissions granulares
- **Eloquent ORM** — SQLite (dev) / MySQL (prod via Docker)
- **Redis + predis** — cache com invalidação por tags (`CacheServico`, `TagCache`, `TtlCache`)
- **Pest 4 + Mockery** — padrão de testes dual (unit + HTTP)
- **Larastan nível 9 + Rector + Laravel Pint** — qualidade e tipagem estática

Planeado (ver [Roadmap](#roadmap)): Laravel Queue + Schedule para processamento assíncrono e recurso a sistemas de IA para extração de dados.

## Arquitectura

```
app/Features/              ← Actions agrupadas por caso de uso (Vertical Slice)
app/Models/                ← Eloquent Models (UUID PK, @property-read)
app/Policies/              ← Autorização por Gate/Policy
app/Shared/                ← Enums, Http (ApiResponse) e Cache (CacheServico, TagCache, TtlCache)
app/Http/Controllers/      ← Thin controllers (só dispatch para Actions)
app/Http/Middleware/       ← InjectarContextoLog (trace_id UUID por request via Context facade)
app/Infrastructure/        ← Repositories, AI, FileSystem  (scaffold — ver Roadmap)
app/Jobs/                  ← Jobs de processamento assíncrono       (scaffold — ver Roadmap)
```

Padrões aplicados: Actions `final readonly`, autorização dupla camada (`Gate::authorize()` no FormRequest **e** na Action), `DB::transaction()` em todas as escritas, DTOs como Value Objects, `strict_types=1` em todos os ficheiros, logging estruturado com `trace_id` por request (propagado a Jobs).

## Como correr (dev)

```bash
# Pré-requisitos: PHP 8.5, Composer
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate          # SQLite por omissão em dev
php artisan serve
```

API disponível em `http://localhost:8000`.

**Autenticação:** todas as rotas (excepto `POST /api/auth/login`) exigem `Authorization: Bearer <token>`. Obter token via `POST /api/auth/login` com `email` e `password`.

**Autorização:** duas roles disponíveis — `admin` (acesso total) e `utilizador` (só leitura). Em desenvolvimento, usar `admin@findocprocessor.test` / `password` com token `dev-token` (criado pelo seeder).

## Testes

```bash
composer test          # pipeline completa (lint + types + arquitectura + cobertura)
composer test:types    # Larastan nível 9 — zero erros
composer test:coverage # Pest — cobertura 100%
```

## Estado actual

Features implementadas até ao momento.

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
| GET    | `/api/entidades`                        | Listar (cursor)              |
| POST   | `/api/entidades`                        | Criar                        |
| GET    | `/api/entidades/{id}`                   | Ver detalhe                  |
| PUT    | `/api/entidades/{id}`                   | Actualizar (completo)        |
| DELETE | `/api/entidades/{id}`                   | Eliminar                     |
| PATCH  | `/api/entidades/{id}/empresa-mae`       | Converter em empresa-mãe     |

## Qualidade

- Larastan nível 9 (PHPStan com regras Laravel)
- Laravel Pint (formatação PSR-12 + opinionated)
- Rector (modernização PHP 8.5 + regras Laravel)
- `strict_types=1` em todos os ficheiros
- Cobertura de testes 100% (Pest, padrão dual unit + HTTP)
- CI obrigatório: pint ✓ rector ✓ phpstan ✓ testes ✓

## Roadmap

Próximos passos, geridos como issues no repositório:

- **Logging estruturado** — Actions, Controllers, erros e contexto de request _(próximo)_
- **Documento** — model layer (migration + model + factory + policy + DTOs + resource)
- **Processamento assíncrono** — Jobs + Schedule sobre a pasta de inbox

## Relacionado (roadmap)

- `findocprocessor-frontend` — Dashboard Angular (repositório separado)
- `findocprocessor-backend-dotnet` — Implementação alternativa em .NET
