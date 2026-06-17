# Brief — Issue #25: CategoriaDocumento Policy de autorização CRUD

**Data:** 2026-06-17
**Slug:** categoriadocumento-policy-autorizacao-crud
**Branch:** feat/categoriadocumento-policy-autorizacao-crud
**Issue:** https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/25

---

## Contexto

Os FormRequests de `CategoriaDocumento` têm `authorize(): bool { return true; }` — sem controlo de acesso real. Esta issue cria a `CategoriaDocumentoPolicy` e integra-a nos FormRequests.

Adicionalmente, `show` e `destroy` no Controller não usam FormRequests de momento — estas 2 classes serão criadas nesta issue para que todos os 5 endpoints tenham autorização via Policy.

---

## Problema

- 3 FormRequests existentes (`Listar`, `Criar`, `Actualizar`) têm `return true` hardcoded — sem Policy
- 2 endpoints (`Ver`, `Eliminar`) sem FormRequest — sem autorização configurável
- Sem Policy, não é possível adicionar lógica de autorização real nas issues futuras de autenticação

---

## Solução proposta

1. Criar `app/Policies/CategoriaDocumentoPolicy.php` com 5 métodos CRUD e `?User $user` (nullable para guest support)
2. Criar `VerCategoriaRequest` em `app/Features/CategoriaDocumento/Ver/`
3. Criar `EliminarCategoriaRequest` em `app/Features/CategoriaDocumento/Eliminar/`
4. Actualizar 3 FormRequests existentes: substituir `return true` por `$this->authorize()`
5. Actualizar Controller `show` e `destroy` para usar os novos FormRequests (injecção via parâmetro de método)
6. **Adicionar `Gate::authorize()` em todas as 5 Actions** — defence in depth: a Policy é verificada mesmo quando a Action é invocada programaticamente (ex: Jobs, comandos Artisan, testes unitários de Action)
7. Testes de feature: guest e autenticado (ambos 2xx nesta fase)

---

## Decisões arquitecturais

### Policy fora dos feature slices

`CategoriaDocumentoPolicy` fica em `app/Policies/` — não dentro de um feature slice — porque é partilhada pelos 5 métodos CRUD. Laravel auto-descobre a Policy por convenção de nome (`CategoriaDocumentoPolicy` ↔ `CategoriaDocumento` model).

### `?User $user` nullable

Laravel 13 passa `null` para o user quando o request não está autenticado e o parâmetro é `?User`. Sem este nullable, guests receberiam 403 automaticamente antes de chegar à Policy. Como a autenticação obrigatória é futura, todos os métodos usam `?User $user`.

### Gate::authorize() nas Actions — defence in depth

Cada Action chama `Gate::authorize()` no início de `handle()`, antes de qualquer lógica de negócio:

- `ListarCategoriasAction` e `CriarCategoriaAction` — sem modelo: `Gate::authorize('viewAny'/'create', CategoriaDocumento::class)`
- `VerCategoriaAction`, `ActualizarCategoriaAction`, `EliminarCategoriaAction` — recebem `CategoriaDocumento|string`: resolver o modelo primeiro (findOrFail se string), depois `Gate::authorize('view'/'update'/'delete', $categoria)`

**Ordem para Actions com modelo:** resolver → autorizar → executar lógica. Consequência: para uso programático com UUID inexistente, `ModelNotFoundException` precede `AuthorizationException` — comportamento aceitável.

**`@throws \Illuminate\Auth\Access\AuthorizationException`** obrigatório no PHPDoc das Actions (Regra B do CLAUDE.md).

### FormRequest::authorize() com $this->authorize()

O método `authorize()` do `FormRequest` chama `$this->authorize()` (que usa o Gate/Policy internamente) — não acede ao user directamente. Isto é o padrão correcto para delegar à Policy.

**Dupla verificação intencional:** FormRequest verifica a Policy na camada HTTP; Action verifica de novo na camada de lógica. Redundância deliberada para garantir que a Policy se aplica em qualquer caminho de execução.

### VerCategoriaRequest e EliminarCategoriaRequest com Route Model Binding

O Controller já usa Route Model Binding (`CategoriaDocumento $categorias_documento`). Os novos FormRequests usam `$this->route('categorias_documento')` para obter a instância resolvida — compatível com o binding existente.

---

## Componentes afectados

| Ficheiro | Estado | Operação |
|---|---|---|
| `app/Policies/CategoriaDocumentoPolicy.php` | Novo | Criar |
| `app/Features/CategoriaDocumento/Ver/VerCategoriaRequest.php` | Novo | Criar |
| `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaRequest.php` | Novo | Criar |
| `app/Features/CategoriaDocumento/Listar/ListarCategoriasRequest.php` | Existente | Actualizar `authorize()` |
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaRequest.php` | Existente | Actualizar `authorize()` |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaRequest.php` | Existente | Actualizar `authorize()` |
| `app/Features/CategoriaDocumento/CategoriaDocumentoController.php` | Existente | Injectar novos FormRequests em `show` e `destroy` |
| `app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php` | Existente | Adicionar `Gate::authorize('viewAny', ...)` |
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php` | Existente | Adicionar `Gate::authorize('create', ...)` |
| `app/Features/CategoriaDocumento/Ver/VerCategoriaAction.php` | Existente | Adicionar `Gate::authorize('view', $categoria)` |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php` | Existente | Adicionar `Gate::authorize('update', $categoria)` |
| `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php` | Existente | Adicionar `Gate::authorize('delete', $categoria)` |
| `tests/Feature/CategoriaDocumento/` | Existente | Adicionar testes de autorização |

---

## Riscos identificados

- **Auto-discovery da Policy:** Laravel 13 faz auto-discover de `CategoriaDocumentoPolicy` por convenção de nome. O modelo está em `app/Models/CategoriaDocumento.php` e a Policy ficará em `app/Policies/CategoriaDocumentoPolicy.php`. Convenção satisfeita — sem binding manual necessário.
- **Larastan nível 9:** `$this->authorize()` em `FormRequest` não recebe type-hints automáticos. Pode ser necessário `/** @return void */` ou cast explícito para satisfazer o analyser estático.
- **Testes de guest:** `actingAsGuest` não existe no Pest/Laravel padrão — o teste faz request sem `actingAs()`, o que simula guest.

---

## Questões em aberto

- Nenhuma — a issue está bem especificada, incluindo o mapeamento exacto FormRequest → método Policy.

---

## Fora de âmbito

- Autenticação obrigatória — issue futura
- Autorização por role/ownership — issue futura
- Policy com injecção de repositório — sem ownership nesta entidade
- openapi.yaml — comportamento externo inalterado

---

## SYSTEM_SPEC a actualizar

- `docs/system_spec/01-features.md` — documentar Policy e FormRequests novos na secção CategoriaDocumento
