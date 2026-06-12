# Debrief — Issue #3: CategoriaDocumento API Layer

**Data:** 2026-06-12
**Branch:** feat/categoria-documento-api-layer
**Issue:** #3 — feat(laravel): CategoriaDocumento — API layer (Resource + FormRequests)

---

## O que foi implementado

- `CategoriaDocumentoResource` — JsonResource que expõe `id`, `nome`, `slug`, `tipo_movimento` (string via `.value`), sem timestamps
- `CriarCategoriaRequest` — FormRequest com campos `required`, `Rule::unique()` para slug, `Rule::in()` para tipo_movimento, mensagens em português de Portugal
- `ActualizarCategoriaRequest` — FormRequest com campos `sometimes`, `Rule::unique()->ignore($uuid)` para excluir o registo actual, sem mensagens `*.required`
- Testes unitários para as 3 classes (16 testes no total)

---

## Decisões tomadas

| Decisão | Justificação |
|---|---|
| `final` em Resource e FormRequests | Regra `actions are final` aplica-se a tudo em `App\Features` |
| `App\Features` ignorado no preset Laravel do ArchTest | O preset assume estrutura tradicional (`App\Http\Requests`); Vertical Slice quebra essa convenção intencionalmente |
| `setRouteResolver` com classe anónima `readonly` | Simular `$this->route('categoria')` sem HTTP nem Mockery — testável em isolamento total |
| PHPDoc `array{key: type}` em `toArray()` | Array shape permite ao Larastan validar cada chave individualmente; `array<string, string>` não o faz |
| `--memory-limit=512M` no `composer test:types` | PHPStan com Larastan nível 9 esgota o limite padrão de 128M |
| Describe separado com `RefreshDatabase` nos testes de unicidade | `uses()` em Pest não funciona dentro de `it()` — deve estar no topo do describe block |

---

## Problemas encontrados

1. **ArchTest: Laravel preset rejeita FormRequests em `App\Features`** — o preset assume `App\Http\Requests`. Fix: `->ignoring(['App\Shared\Enums', 'App\Features'])`.
2. **ArchTest: `actions are final`** — as 3 classes novas não tinham `final`. Fix: declarar `final`.
3. **PHPStan crash por memória** — 128M insuficiente com Larastan nível 9. Fix: `--memory-limit=512M` no `composer.json`.
4. **`uses(RefreshDatabase::class)` dentro de `it()`** — não funciona em Pest. Fix: describe block dedicado para testes com BD.

---

## Aprendizagens

**Vertical Slice e a HTTP layer**
O maior _insight_ desta issue foi perceber que numa Vertical Slice Architecture, a HTTP layer (FormRequests, Resources) também entra na slice — `app/Features/CategoriaDocumento/Criar/CriarCategoriaRequest.php` em vez de `app/Http/Requests/`. Isto concentra tudo o que pertence a um caso de uso num único lugar. O custo é que ferramentas como o preset Laravel do Pest arch assumem a estrutura tradicional e precisam de ser ajustadas.

**Testar FormRequests sem HTTP**
`setRouteResolver` com uma classe anónima `readonly` é a forma idiomática de simular parâmetros de rota em testes unitários. Evita Mockery e dependências de HTTP, mantendo o teste completamente isolado. A classe `readonly` foi sugerida pelo Rector (PHP 8.3+), o que também mostrou como o Rector serve de guia para modernização progressiva do código.

**PHPDoc array shape**
`array{id: string, nome: string}` em vez de `array<string, string>` muda o que o Larastan consegue validar: com array shape, detecta chaves em falta ou tipos errados por chave; com o genérico, só verifica que é um array de strings. Em Resources onde a estrutura de output é contratual, o array shape é a escolha correcta.

---

## Ficheiros produzidos

| Ficheiro | Tipo |
|---|---|
| `app/Features/CategoriaDocumento/CategoriaDocumentoResource.php` | Novo |
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaRequest.php` | Novo |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaRequest.php` | Novo |
| `tests/Unit/Features/CategoriaDocumento/CategoriaDocumentoResourceTest.php` | Novo |
| `tests/Unit/Features/CategoriaDocumento/CriarCategoriaRequestTest.php` | Novo |
| `tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaRequestTest.php` | Novo |
| `tests/ArchTest.php` | Alterado |
| `composer.json` | Alterado |
