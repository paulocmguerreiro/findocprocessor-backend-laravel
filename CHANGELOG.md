# Changelog — FinDocProcessor Backend Laravel

Formato: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)

---

## [Unreleased]

### Changed
- **Issue #10** — `CLAUDE.md`: Repository pattern qualificado com critérios objectivos
  - Regra "Repositório entre Action e Eloquent Model" substituída por regra condicional: obrigatório em queries complexas (joins, aggregates, raw SQL, partilha entre ≥ 2 Actions); dispensável em CRUD simples (≤ 1 query Eloquent por `handle()`)
  - Secção "O que NÃO fazer" alinhada com a nova regra — excepção CRUD simples documentada com remissão cruzada

### Added
- **Issue #5** — `CategoriaDocumento`: camada de lógica (Actions + Controller + DTOs)
  - 5 Actions CRUD: `ListarCategoriasAction`, `CriarCategoriaAction`, `VerCategoriaAction`, `ActualizarCategoriaAction`, `EliminarCategoriaAction`
  - 2 DTOs `final readonly`: `CriarCategoriaDto`, `ActualizarCategoriaDto` com `fromRequest()` + guards `is_string()` (Larastan nível 9)
  - `CategoriaDocumentoController` sem lógica — dispatch puro com Route Model Binding e injecção de Actions
  - `Route::apiResource('categorias-documento', ...)` → 5 endpoints REST (`GET`, `POST`, `GET/{id}`, `PUT/{id}`, `DELETE/{id}`)
  - Actions aceitam `CategoriaDocumento|string` — compatíveis com RMB (HTTP) e testes unitários (UUID directo)
  - Fix `ActualizarCategoriaRequest`: parâmetro de rota corrigido para `categorias_documento` (gerado pelo `apiResource`)
  - 62 testes (5 feature + unit por Action e DTO), 188 assertions, 100% coverage
- **Issue #6** — Envelope universal de resposta JSON: `ApiResponse` + Problem Details RFC 7807
  - `ApiResponse` em `App\Shared\Http` — factory estática com `devolverSucesso`, `devolverCriado`, `devolverVazio`, `devolverColeccao`
  - Exception handler centralizado em `bootstrap/app.php` — mapeia 5 classes de excepção para Problem Details (422/404/403/401/500)
  - Stack traces nunca expostos; mensagens de `detail` em português de Portugal
  - 9 testes de feature: `ApiResponseTest` (4) + `ExceptionHandlerTest` (5)
- **Issue #3** — `CategoriaDocumento`: camada de API (Resource + FormRequests)
  - `CategoriaDocumentoResource` em `App\Features\CategoriaDocumento` — expõe `id`, `nome`, `slug`, `tipo_movimento` (string)
  - `CriarCategoriaRequest` com validação completa (`required`, `Rule::unique`, `Rule::in`) e mensagens em português
  - `ActualizarCategoriaRequest` com campos `sometimes` e `Rule::unique()->ignore($uuid)` para actualizações parciais
  - 16 testes unitários: Resource, CriarRequest (incl. unicidade com BD), ActualizarRequest (incl. ignore de slug próprio)
  - Fix `ArchTest`: `ignoring('App\Features')` no preset `laravel` — Vertical Slice coloca FormRequests/Resources dentro da slice
  - Fix `composer.json`: `--memory-limit=512M` no `test:types` (PHPStan/Larastan nível 9)
- **Issue #1** — `CategoriaDocumento`: camada de modelo completa
  - Enum `TipoMovimento` (`Debito`, `Credito`, `Neutro`) em `App\Shared\Enums`
  - Migration `categorias_documento` com UUID PK, índice em `nome`, único em `slug`
  - Model `CategoriaDocumento` com `HasUuids`, `#[Fillable]`, `#[Table]`, cast para `TipoMovimento`
  - Factory com `definition()` aleatório e states `comMovimentoDebito/Credito/Neutro`
  - 11 testes unitários: model, factory states, constraints BD
  - Fix `ArchTest`: `.ignoring('App\Shared\Enums')` no preset `laravel`
- Estrutura inicial do projecto (scaffolding)

---

_Actualizado automaticamente pela Fase 3 (documenta-issue) após cada Issue._
