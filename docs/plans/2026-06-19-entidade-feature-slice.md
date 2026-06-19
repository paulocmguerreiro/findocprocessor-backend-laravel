# Plano — Issue #40: Entidade Feature Slice

**Data:** 2026-06-19
**Issue:** #40
**Slug:** `entidade-feature-slice`
**Branch:** `feat/entidade-feature-slice`

---

## Ordem de implementação

A ordem segue a cadeia de dependências: Enum → DTOs → FormRequests → Actions → Controller → Rotas → Testes.

---

## T1 — Enum `CampoOrdenacaoEntidades`

**Ficheiro:** `app/Features/Entidade/Listar/CampoOrdenacaoEntidades.php`

- Backed enum string com `case Nome = 'nome'`
- Padrão: `CampoOrdenacaoCategorias`

**Verificação:** `composer lint && composer refactor`

---

## T2 — `fromRequest()` nos DTOs existentes

**Ficheiros:**
- `app/Features/Entidade/Criar/CriarEntidadeDto.php`
- `app/Features/Entidade/Actualizar/ActualizarEntidadeDto.php`

- Adicionar `static fromRequest(CriarEntidadeRequest $request): self` com array shape `@var`
- `(bool)` cast nos campos booleanos de `validated()`
- `@throws \InvalidArgumentException`

**Verificação:** `composer lint && composer refactor`

---

## T3 — FormRequests (6)

**Ficheiros:**
- `app/Features/Entidade/Listar/ListarEntidadesRequest.php`
- `app/Features/Entidade/Criar/CriarEntidadeRequest.php`
- `app/Features/Entidade/Ver/VerEntidadeRequest.php`
- `app/Features/Entidade/Actualizar/ActualizarEntidadeRequest.php`
- `app/Features/Entidade/Eliminar/EliminarEntidadeRequest.php`
- `app/Features/Entidade/EmpresaMae/ConverterEmEmpresaMaeRequest.php`

- Cada um: `authorize()` com `Gate::authorize()` + `rules()` + `messages()` em PT
- `CriarEntidadeRequest` e `ActualizarEntidadeRequest`: não `final` (mockáveis)
- Restantes: `final`

**Verificação:** `composer lint && composer refactor`

---

## T4 — Action interna `RemoverMarcacaoEmpresaMaeAction`

**Ficheiro:** `app/Features/Entidade/EmpresaMae/RemoverMarcacaoEmpresaMaeAction.php`

- `handle(): void` — sem `Gate::authorize()`, sem `DB::transaction()` própria
- `Entidade::whereEmpresaAplicacao()->update(['e_empresa_aplicacao' => false])`
- `@throws \Throwable` (propaga da transação do caller)

**Verificação:** `composer lint && composer refactor`

---

## T5 — Actions CRUD (5)

**Ficheiros:**
- `app/Features/Entidade/Listar/ListarEntidadesAction.php`
- `app/Features/Entidade/Ver/VerEntidadeAction.php`
- `app/Features/Entidade/Criar/CriarEntidadeAction.php`
- `app/Features/Entidade/Actualizar/ActualizarEntidadeAction.php`
- `app/Features/Entidade/Eliminar/EliminarEntidadeAction.php`

Padrão por action:
- `ListarEntidadesAction`: `Gate::authorize viewAny` + `cursorPaginate()`
- `VerEntidadeAction`: resolve UUID + `Gate::authorize view` (sem transação)
- `CriarEntidadeAction`: `Gate::authorize create` fora + `DB::transaction` com lógica de empresa mãe
- `ActualizarEntidadeAction`: resolve UUID + `Gate::authorize update` fora + `DB::transaction` com lógica de empresa mãe + `refresh()`
- `EliminarEntidadeAction`: resolve UUID + `Gate::authorize delete` fora + `DB::transaction delete`

**Verificação:** `composer lint && composer refactor`

---

## T6 — `ConverterEmEmpresaMaeAction`

**Ficheiro:** `app/Features/Entidade/EmpresaMae/ConverterEmEmpresaMaeAction.php`

- Resolve UUID + `Gate::authorize('update', $entidade)` fora da transação
- `DB::transaction()`: `RemoverMarcacaoEmpresaMaeAction::handle()` + `$entidade->update([...3 flags...])` + `refresh()`
- Injecção de `RemoverMarcacaoEmpresaMaeAction` via construtor

**Verificação:** `composer lint && composer refactor`

---

## T7 — `EntidadeController`

**Ficheiro:** `app/Features/Entidade/EntidadeController.php`

- `final class EntidadeController extends Controller`
- 6 métodos: `index`, `store`, `show`, `update`, `destroy`, `converterEmEmpresaMae`
- Injecção de Actions via parâmetros de método
- Route Model Binding: `Entidade $entidade`
- Usa `ApiResponse::devolverPaginado/devolverCriado/devolverSucesso/devolverVazio`

**Verificação:** `composer lint && composer refactor`

---

## T8 — Rotas

**Ficheiro:** `routes/api.php`

```php
Route::apiResource('entidades', EntidadeController::class);
Route::patch('entidades/{entidade}/empresa-mae', [EntidadeController::class, 'converterEmEmpresaMae']);
```

**Verificação:** `php artisan route:list --path=entidades` + `composer lint`

---

## T9 — Testes de feature

**Ficheiros (6):**
- `tests/Feature/Features/Entidade/ListarEntidadesTest.php`
- `tests/Feature/Features/Entidade/CriarEntidadeTest.php`
- `tests/Feature/Features/Entidade/VerEntidadeTest.php`
- `tests/Feature/Features/Entidade/ActualizarEntidadeTest.php`
- `tests/Feature/Features/Entidade/EliminarEntidadeTest.php`
- `tests/Feature/Features/Entidade/ConverterEmEmpresaMaeTest.php`

Cobertura obrigatória por ficheiro:
- **Listar**: lista vazia, estrutura correcta, per_page, cursor sem duplicados, 422 per_page>100, 422 sort inválido, 422 direction inválida, guest pode listar
- **Criar**: 201 com recurso, 201 com eEmpresaAplicacao=true (remove anterior + força flags), 422 campos em falta, 422 nif em branco, guest pode criar
- **Ver**: 200 com recurso, 404 UUID inexistente
- **Actualizar**: 200 actualizado, 200 com eEmpresaAplicacao=true (remove anterior + força flags), 404, 422 campos em falta
- **Eliminar**: 204, 404
- **ConverterEmEmpresaMae**: 200 com 3 flags, remove marcação anterior, 404

**Verificação final:** `composer test`

---

## Commits

| Após tarefa | Mensagem |
|---|---|
| T1 | `feat(entidade): enum CampoOrdenacaoEntidades` |
| T2 | `feat(entidade): fromRequest() em CriarEntidadeDto e ActualizarEntidadeDto` |
| T3 | `feat(entidade): FormRequests (6) — Listar, Criar, Ver, Actualizar, Eliminar, EmpresaMae` |
| T4 | `feat(entidade): RemoverMarcacaoEmpresaMaeAction — action interna partilhada` |
| T5 | `feat(entidade): Actions CRUD — Listar, Ver, Criar, Actualizar, Eliminar` |
| T6 | `feat(entidade): ConverterEmEmpresaMaeAction` |
| T7 | `feat(entidade): EntidadeController` |
| T8 | `feat(entidade): rotas apiResource + empresa-mae` |
| T9 | `feat(entidade): testes de feature (6 ficheiros)` |

---

## Pipeline final

```bash
composer test   # Larastan + Rector dry-run + Pint dry-run + arch + type-coverage + coverage
```

Zero erros exigidos antes de fechar a issue.
