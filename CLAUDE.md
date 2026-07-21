# FinDocProcessor — Backend Laravel

## STACK_CONFIG

```
STACK:        laravel
GITHUB_REPO:  paulocmguerreiro/findocprocessor-backend-laravel
TEST_RUNNER:  composer test
TEST_PATTERN: **/*.php
```

---

## ARQUITECTURA

**Abordagem:** Vertical Slice — features agrupadas por caso de uso. Actions como unidade de lógica. Laravel idiomático onde faz sentido (Jobs, Schedule, Eloquent, ServiceProvider).

### Estrutura de features

```
app/Features/<Feature>/<Action>/
  <Name>Action.php
  <Name>Request.php     (se aplicável)
  <Name>Resource.php    (se aplicável)
```

Acção com menos de 3 artefactos próprios fica como ficheiro solto, não pasta própria.
> Detalhe: `docs/system_spec/02-shared/estrutura-subpastas-features.md`

### Padrões obrigatórios

- Controllers sem lógica — só fazem dispatch para Actions
- Actions injectam interface quando há substituição prevista (Repository, API externa); classes concretas sem substituição prevista (CacheServico, Regra*, Executor) são injectadas directamente — ver `docs/system_spec/02-shared/padroes-acoes.md`
- **Autorização dupla camada:** `Gate::authorize()` no FormRequest **e** na Action — cobre os dois contextos de invocação (HTTP e fora de HTTP: Jobs, Artisan, testes).
  > Detalhe: `docs/system_spec/02-shared/padroes-acoes.md`
- Transições de estado do `Documento` via Actions de transição (`ExecutorTransicaoDocumento` + `RegraTransicaoEstado`) — nunca `if($doc->estado ==)`. Os state objects (`$documento->estado()`) são read-only (sem `correct()`).
  > Detalhe: `docs/system_spec/02-shared/estados.md`
- `EstadoDocumento` é PHP 8.5 backed enum (string)
- `strict_types=1` em todos os ficheiros PHP
- Jobs e Schedule são Laravel nativos — não reinventar
- **Repositório entre Action e Eloquent Model:** obrigatório quando há lógica de query complexa, dispensável em CRUD simples — o critério completo (obrigatório/dispensável) e o desvio documentado no Brief.
  > Detalhe: `docs/system_spec/04-infra/repositories.md`
- Modelos do domínio usam `HasUuids` como chave primária — nunca IDs incrementais
- `@property-read` obrigatório em todos os Eloquent Models (tipagem completa das colunas para PHPStan e IA)
- **`DB::transaction()` obrigatório em todas as Actions de escrita** — `Gate::authorize()` fica fora da transação, a persistência fica dentro; padrão completo (rollback, `@throws \Throwable`, Jobs `after_commit`).
  > Detalhe: `docs/system_spec/04-infra/transactions.md`

---

## CONVENÇÕES DE NOMENCLATURA

Código de domínio em **Português de Portugal** (classes, métodos, variáveis, enums); inglês apenas quando o framework impõe o nome (`handle()`, `Controller`, `->where()`, etc.). Métodos em VERBO+Intenção, variáveis em NOME+Intenção[+Escala], FKs em `id_<entidade>`, enums em TitleCase PT.

> Detalhe (tabela PT/EN, exemplos de métodos, variáveis, FKs e enums): `docs/system_spec/02-shared/convencoes-nomenclatura.md`

---

## CONVENÇÕES DE TIPAGEM

Eliminar `mixed` anotando `@var` array shape em `validated()`; declarar `@throws` em todo o método que lança excepção. DTOs seguem o padrão Value Object — nunca existem num estado inválido (construtor valida invariantes em qualquer contexto de invocação).

> Detalhe (Regra A array shape, Regra B `@throws`): `docs/system_spec/02-shared/padroes-tipagem.md`
> Detalhe (padrão Value Object dos DTOs, `fromRequest()`, divisão de responsabilidades): `docs/system_spec/02-shared/padroes-dtos.md`

---

### O que NÃO fazer

- Não colocar lógica nos Controllers
- Não aceder directamente ao Eloquent Model nas Actions sem Repository, excepto em CRUD simples (ver critérios em "Padrões obrigatórios")
- Não duplicar lógica entre Actions
- Não omitir `strict_types=1`
- Não usar `if($doc->estado == ...)` nas Actions

### Ciclo de estados

```
PENDENTE → AGUARDA_ENVIO → ENVIADO → AGUARDA_RESPOSTA → PROCESSADO
                                                       ↘ ERRO
                                                       ↘ PERIGOSO
```

> Mapa completo de transições (incl. `Erro → AguardaEnvio`, `Pendente → Perigoso`, self-loop `Processado → Processado`) e Action de cada uma: `docs/system_spec/02-shared/estados.md`

### Segurança e conformidade

- `strict_types=1` obrigatório em todos os ficheiros PHP
- Campos sensíveis não são logados em claro
- Larastan nível 9 — zero erros (equivalente a PHPStan nível máximo com regras Laravel)
- Rector sem sugestões pendentes antes de cada PR
- Upload: sempre `multipart/form-data`

---

## SYSTEM_SPEC_MAP

> Entrada: ler sempre `docs/system_spec/00-index.md` para descoberta. Depois abrir apenas o ficheiro indicado.
>
> **Ficheiro novo → actualizar sempre `00-index.md`.** Sempre que se cria um ficheiro novo em `docs/system_spec/` (nova feature slice, novo Model, novo Repository, novo enum num ficheiro próprio, etc. — não apenas features), o `00-index.md` é obrigatoriamente actualizado com uma linha na tabela correcta. A "porta de entrada" lista sempre tudo o que existe.

| Tipo de alteração                              | Ficheiro system_spec a actualizar                             |
| ---------------------------------------------- | ------------------------------------------------------------- |
| Nova Action ou Feature (feature existente)     | `01-features/<slug>.md`                                       |
| Nova Feature (slice nova)                      | criar `01-features/<slug>.md` + actualizar `00-index.md`      |
| Novo enum partilhado                           | `02-shared/enums.md`                                          |
| Novo componente HTTP ou handler de erro        | `02-shared/http.md`                                           |
| Novo estado ou contrato                        | `02-shared/estados.md`                                        |
| Novo Model ou relação Eloquent                 | `03-models/<slug>.md`                                         |
| Novo Repository                                | `04-infra/repositories.md`                                    |
| Novo Job ou configuração de Queue              | `04-infra/queue-jobs.md`                                      |
| Cache ou Redis                                 | `04-infra/cache.md`                                           |
| API externa — extração via IA (Prism)          | `04-infra/extracao-ia.md`                                     |
| API externa — scan de malware (ClamAV)         | `04-infra/malware.md`                                         |
| API externa — extractores de texto (pdfparser/OCR) | `04-infra/extracao-texto.md`                              |
| Nova rota API                                  | `05-routes/<slug>.md`                                         |
| Alteração de contrato / rota API (OpenAPI)     | `./openapi.yaml` (raiz) — escrito na Fase 3a (`/documenta-implementacao`) |
| Nova configuração ou .env var                  | `06-config.md`                                                |
| Nova classe `Regra*` (invariante de domínio)   | `02-shared/regras-negocio.md`                                 |
| Novo/alterado teste ou convenção de teste      | `07-testing.md`                                               |

---

## AGENTIC WORKFLOW

### Sessão nova

```
1. Reparar vendor (SEMPRE — partir do princípio que pode estar inválido):
   composer vendor:repair

2. Verificar: docs/process-warnings.md (se existir)
3. Verificar: docs/workflow-state.md (se existir → avisar sessão em curso)
   Ou usar: /mostra-workflow
```

### Commands disponíveis

Workflow em 3 camadas: **Commands → Skills → Agents**
Referência completa: `findocprocessor-workflow/.claude/CLAUDE.md`

| Command                                    | Fase    | Produz                                             |
| ------------------------------------------ | ------- | -------------------------------------------------- |
| `/cria-issue <descrição>`                  | —       | Issue #N (genérica)                                |
| `/cria-issue-modelo [entidade]`            | —       | Issue para migration + model + factory + testes    |
| `/cria-issue-persistencia [entidade]`      | —       | Issue para interface + repositório + DTOs + testes |
| `/cria-issue-logica [entidade]`            | —       | Issue para Actions + Controller + Events + testes  |
| `/planeia-issue [#N]`                      | Fase 1  | Brief + Spec + Plano                               |
| `/implementa-plano [#N] [--stack laravel]` | Fase 2  | Código + Commits                                   |
| `/documenta-implementacao [#N]`            | Fase 3a | Debrief + system_spec + Changelog                  |
| `/publica-implementacao [#N]`              | Fase 3b | PR no GitHub                                       |
| `/mostra-workflow`                         | —       | Estado actual do workflow                          |

> Grafo de sequência completo e explicação de Commands → Skills → Agents: `docs/WORKFLOW.md`.

### Modo de trabalho

**Sempre Modo SDD Activo** — checkpoints A, B, por tarefa, ②, D e E são obrigatórios.

### Objectivo de aprendizagem

Este projecto serve para aprender Vertical Slice Architecture em Laravel. A secção **"Aprendizagens"** no Debrief (gerado por `escreve-debrief` em `/documenta-implementacao`) é **obrigatória e prioritária** — deve documentar o que ficou mais claro sobre Vertical Slice, Actions, Repository pattern ou PHP 8.5 após implementar a issue. Não omitir nem preencher com "N/A".

---

## STACK TÉCNICO

- Laravel 13 / PHP 8.5 (strict_types=1)
- Laravel Pint — formatação de código (PSR-12 + opinionated)
- Rector — modernização e qualidade de código (PHP 8.5 + Laravel rules)
- Larastan (larastan/larastan) — PHPStan com regras Laravel (nível 9)
- Eloquent ORM (SQLite em dev → MySQL via Docker em prod)
- predis/predis (dev + prod via Docker)
- Pest 4 + Mockery (Pest é retrocompatível com PHPUnit)
- Laravel Queue + Schedule

---

## FERRAMENTAS DE QUALIDADE

```bash
composer vendor:repair       # Detecta e repara vendor/ corrompido (bin/repair-vendor.sh)
composer vendor:repair:force # Força reinstalação completa do vendor/
composer vendor:update       # Actualiza constraints no composer.json (composer outdated) + composer update

composer lint                # Pint (aplica formatação) — usar antes de commitar
composer refactor            # Rector process (aplica modernizações) — usar antes de commitar
composer test:lint           # Rector --dry-run + Pint --test — verifica sem alterar
composer test:arch           # Pest — testes arquitecturais (presets + regras custom)
composer test:types          # PHPStan/Larastan nível 9 — zero erros exigidos
composer test:type-coverage  # Pest type-coverage --min=100 — 100% tipos declarados
composer test:coverage       # Pest --parallel --coverage --min=100 — 100% cobertura
composer test                # Pipeline completa — usar localmente e no CI
```

> **Regra obrigatória para IA:** Após gerar ou alterar qualquer código, executar `composer test` e corrigir todos os erros reportados antes de finalizar a tarefa.

> **Nota Rector:** Em desenvolvimento, `composer refactor` aplica as sugestões. Em CI, `composer test` corre Rector com `--dry-run` — nunca auto-corrige.

---

## CONVENÇÕES DE TESTES

Padrão dual obrigatório por feature slice: cada Action tem testes em dois locais — `tests/Unit/Features/` (invocação directa, programática) e `tests/Feature/Features/` (via HTTP). O ArchTest exclui enums, FormRequests não-final e traits da regra `actions are final`.

> Detalhe (padrão dual completo, estrutura de ficheiros, ArchTest): `docs/system_spec/07-testing.md`

---

## MCP LARAVEL-BOOST — USO OBRIGATÓRIO

> **OBRIGATÓRIO:** Antes de gerar ou alterar qualquer código Laravel, executar as ferramentas MCP `laravel-boost` indicadas abaixo. Não saltar este passo.

| Situação                                   | Ferramenta MCP obrigatória                        |
| ------------------------------------------ | ------------------------------------------------- |
| Antes de qualquer alteração de código      | `search-docs` (uma ou mais queries temáticas)     |
| Antes de escrever migration ou Model       | `database-schema` (inspeccionar estrutura actual) |
| Antes de escrever query ou repositório     | `database-query` (verificar dados reais)          |
| Antes de partilhar um URL com o utilizador | `get-absolute-url`                                |
| Quando há erro ou excepção em browser      | `browser-logs`                                    |
| Quando há erro em tempo de execução        | `last-error`                                      |

**Sequência obrigatória antes de gerar código:**

1. `search-docs` — pesquisar documentação relevante (Laravel 13, Pest 4, etc.)
2. `database-schema` — se envolver modelos, migrations ou queries
3. Só então gerar o código

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
    - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
