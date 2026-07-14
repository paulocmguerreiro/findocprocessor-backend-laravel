# FinDocProcessor — Backend Laravel (WIP)

[![CI](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/actions/workflows/ci.yml)
![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Larastan](https://img.shields.io/badge/Larastan-nível%209-2D2D2D)
![License](https://img.shields.io/badge/licença-MIT-blue)

> API REST para processamento de documentos financeiros, organizada em **Vertical Slice Architecture** em Laravel 13 / PHP 8.5. Padrões de arquitectura em [Arquitectura](#arquitectura), garantias de qualidade em [Qualidade](#qualidade).

## Documentação

| Documento | Conteúdo |
| --- | --- |
| [`docs/system_spec/00-index.md`](docs/system_spec/00-index.md) | Porta de entrada da spec de arquitectura — feature por feature |
| [`docs/system_spec/01-features/`](docs/system_spec/01-features) | Actions, DTOs e regras de cada feature slice |
| [`docs/system_spec/02-shared/`](docs/system_spec/02-shared) | Enums, HTTP, estados, padrões (Actions/DTOs/tipagem/nomenclatura), regras de negócio |
| [`docs/system_spec/03-models/`](docs/system_spec/03-models) | Eloquent Models (migrations, factories, policies, resources) |
| [`docs/system_spec/04-infra/`](docs/system_spec/04-infra) | Transações, cache, jobs/queue, APIs externas, autorização |
| [`docs/system_spec/05-routes/`](docs/system_spec/05-routes) | Rotas por feature |
| [`docs/system_spec/06-config.md`](docs/system_spec/06-config.md) | Configuração e variáveis de ambiente |
| [`docs/system_spec/07-testing.md`](docs/system_spec/07-testing.md) | Padrão dual de testes (Unit + Feature) + ArchTest |
| [`openapi.yaml`](openapi.yaml) | Contrato REST completo — importável no [Swagger Editor](https://editor.swagger.io) |
| [`docs/WORKFLOW.md`](docs/WORKFLOW.md) | Workflow de desenvolvimento (Commands → Skills → Agents) e grafo de sequência das fases |

## Stack

- **Laravel 13** / PHP 8.5 — Vertical Slice Architecture
- **Laravel Sanctum** — autenticação API via Bearer tokens
- **Spatie Laravel Permission** — autorização por roles (`admin`, `utilizador`) e permissions granulares
- **spatie/laravel-activitylog** — audit trail persistente (`activity_log`) com atomicidade garantida pela transação
- **Eloquent ORM** — MySQL (dev via Docker + testes)
- **Redis + predis** — cache com invalidação por tags (`CacheServico`, `TagCache`, `TtlCache`)
- **Prism** (`prism-php/prism`) — acesso unificado a LLM local (Ollama) e cloud, provider-agnóstico; camadas opcionais/desligáveis por presença das vars `LLM_LOCAL_*`/`LLM_CLOUD_*`
- **smalot/pdfparser + Tesseract/imagick/Ghostscript** — extracção de texto nativo e OCR de PDF (pipeline de extracção por IA, em construção)
- **ClamAV self-hosted** — scan de malware dos uploads (protocolo `clamd`/`INSTREAM` via socket, sem dependência Composer, sem partilha de ficheiros com terceiros); camada opcional/desligável por presença das vars `CLAMAV_HOST`/`CLAMAV_PORT`
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

Padrões aplicados: Actions `final readonly`, autorização dupla camada (`Gate::authorize()` no FormRequest **e** na Action), `DB::transaction()` em todas as escritas, DTOs como Value Objects, `strict_types=1` em todos os ficheiros, cursor pagination (keyset) nas listagens, cache Redis com invalidação por tags e logging estruturado com `trace_id` por request (propagado a Jobs). Detalhe em [Documentação](#documentação).

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

### Acesso

**Autenticação:** todas as rotas (excepto `POST /api/auth/login`) exigem `Authorization: Bearer <token>`. Obter token via `POST /api/auth/login` com `email` e `password`.

**Autorização:** duas roles — `admin` (acesso total) e `utilizador` (só leitura). Após o seed, usar `admin@findocprocessor.test` / `password` (o seeder cria também o token de dev `dev-token`).

### Notas para consumidores da API

- **Soft delete e restauro:** `categorias-documento`, `entidades` e `utilizadores` usam soft delete — o `DELETE` desactiva (mantém o registo) e existe `PATCH .../restaurar`. **`documentos` não tem soft delete:** o `DELETE` é permanente e **não** há restauro.
- **O parâmetro `?estado=` tem duas semânticas distintas** conforme o recurso:
  - Em `categorias-documento`, `entidades` e `utilizadores` é um **filtro de soft delete**: `todos | somente_ativos | somente_inativos`.
  - Em `documentos` é a **fase do ciclo de vida** (`EstadoDocumento`): `Pendente | AguardaEnvio | Enviado | AguardaResposta | Processado | Erro | Perigoso`.

## Testes

Padrão dual por feature slice: invocação directa (unit) **e** via HTTP (feature).

```bash
composer test          # pipeline completa (lint + arquitectura + tipos + cobertura)
composer test:types    # Larastan nível 9 — zero erros
composer test:coverage # Pest — cobertura 100%
```

## API — estado actual

> **Documentação interactiva:** disponível em `/docs` (Swagger UI) **apenas fora de produção** —
> em produção a rota não é registada, para não expor a superfície da API. O contrato portável é o
> [`openapi.yaml`](openapi.yaml), importável no [Swagger Editor](https://editor.swagger.io) ou no Postman.

### Auth

| Método | Path | Descrição | Auth |
| ------ | ---- | --------- | ---- |
| POST | `/api/auth/login` | Obter token Bearer | — (pública) |
| POST | `/api/auth/logout` | Revogar token actual | Bearer |
| POST | `/api/auth/tokens` | Criar token adicional | Bearer |

### Restantes recursos

Todas as rotas exigem Bearer token. Lista completa de endpoints e parâmetros: [`openapi.yaml`](openapi.yaml) ou `docs/system_spec/05-routes/`.

| Feature | Endpoints | Notas |
| --- | --- | --- |
| Categorias de documento ([spec](docs/system_spec/05-routes/categorias-documento.md)) | 5 (CRUD) | Soft delete + `PATCH .../restaurar` |
| Entidades ([spec](docs/system_spec/05-routes/entidades.md)) | 7 (CRUD + restaurar + empresa-mãe) | Soft delete; `?estado=todos|somente_ativos|somente_inativos` |
| Tipos de documento ([spec](docs/system_spec/05-routes/tipos-documento.md)) | 5 (CRUD) | Sem soft delete — `DELETE` é definitivo |
| Documentos ([spec](docs/system_spec/05-routes/documento.md)) | 8 (CRUD + upload + ficheiro + reprocessar) | Sem soft delete; ciclo de estados `Pendente → AguardaEnvio → Enviado → AguardaResposta → Processado` (ramos `Erro`/`Perigoso`), transições validadas por `RegraTransicaoEstado` (`422` se inválida); `?estado=` filtra pela fase do ciclo de vida |
| Roles & Utilizadores ([spec](docs/system_spec/05-routes/role.md)) | 5 + 8 | Role `admin`; utilizadores com soft delete, restauro e anonimização RGPD (Art. 17.º) |

## Qualidade

- Larastan nível 9 (PHPStan com regras Laravel) — zero erros
- 100% type-coverage (todos os tipos declarados)
- Cobertura de testes 100% (Pest, padrão dual unit + HTTP) — imposta por `--min=100`: o CI **falha** se descer de 100%, logo o badge de CI verde é a prova da cobertura
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
