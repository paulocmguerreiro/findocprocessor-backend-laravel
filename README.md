# FinDocProcessor — Backend Laravel

Implementação alternativa do pipeline de processamento de documentos financeiros, em Laravel / PHP. Expõe a mesma API que o backend .NET — o frontend Angular funciona com ambos.

## Stack

- **Laravel 13** / PHP 8.5 — Vertical Slice Architecture
- **Eloquent ORM** — SQLite (dev) / MySQL (prod)
- **predis/predis** — cache Redis
- **Laravel Queue + Schedule** — processamento assíncrono
- **PHPUnit + Mockery**
- **PHPStan (nível máximo) + Rector + Laravel Pint**

## Arquitectura

```
app/Features/              ← Actions agrupadas por caso de uso
app/Shared/                ← States, Contracts, DTOs, Enums, Exceptions
app/Models/                ← Eloquent Models
app/Infrastructure/        ← Repositories, AI, FileSystem, Cache
app/Jobs/                  ← WatchInboxJob, ProcessBatchJob
app/Http/Controllers/      ← Thin controllers (dispatch para Actions)
```

## Como correr (dev)

```bash
# Pré-requisitos: PHP 8.5, Composer, Docker
docker compose -f docker-laravel/docker-compose.yml up -d mysql redis

composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

API disponível em `http://localhost:8000`.

## Testes

```bash
php artisan test
```

## Endpoints principais

| Método | Path              | Descrição                    |
| ------ | ----------------- | ---------------------------- |
| GET    | `/state`          | Lista de documentos          |
| GET    | `/events`         | SSE stream                   |
| POST   | `/upload`         | Upload de ficheiro           |
| POST   | `/documents/manual` | Registo manual             |
| GET    | `/config`         | Configuração pública         |

## Qualidade

- PHPStan nível máximo + Larastan
- Laravel Pint (linter)
- Rector (actualizações automáticas)
- `strict_types=1` em todos os ficheiros
- CI obrigatório: pint ✓ phpstan ✓ testes ✓

## Relacionado

- [`findocprocessor-frontend`](../findocprocessor-frontend) — Dashboard Angular
- [`findocprocessor-backend-dotnet`](../findocprocessor-backend-dotnet) — Implementação alternativa em .NET
