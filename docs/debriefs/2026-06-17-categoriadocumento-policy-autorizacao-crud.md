# Debrief — Issue #25: CategoriaDocumento Policy de autorização CRUD

**Data:** 2026-06-17
**Branch:** feat/categoriadocumento-policy-autorizacao-crud
**Spec:** docs/specs/2026-06-17-categoriadocumento-policy-autorizacao-crud.md

---

## O que foi implementado

- `CategoriaDocumentoPolicy` com 5 métodos CRUD (`viewAny`, `view`, `create`, `update`, `delete`), todos a retornar `true` e com `?User $utilizador` nullable para suporte a guests
- `VerCategoriaRequest` e `EliminarCategoriaRequest` — novos FormRequests mínimos (apenas `authorize()`, sem `rules()`)
- 3 FormRequests existentes (`ListarCategoriasRequest`, `CriarCategoriaRequest`, `ActualizarCategoriaRequest`) actualizados: `return true` substituído por `Gate::authorize()`
- Controller `show` e `destroy` injectam os novos FormRequests como primeiro parâmetro
- 5 Actions actualizadas com `Gate::authorize()` em `handle()` e `@throws AuthorizationException`
- 5 testes de guest adicionados (um por endpoint)
- `rector.php` configurado com `withSkip([RemoveUnusedPublicMethodParameterRector::class => ['app/Policies']])`

**Resultados:** 73 testes, 100% coverage, Larastan nível 9 sem erros.

---

## Decisões tomadas

### `Gate::authorize()` nos FormRequests em vez de `$this->authorize()`

O plano usava `$this->authorize('view', ...)` dentro do método `authorize()` do `FormRequest`. Detectado que isto causaria recursão infinita — `authorize()` do `FormRequest` a chamar-se a si próprio. O método `authorize()` com parâmetros (trait `AuthorizesRequests`) existe apenas em Controllers, não em FormRequests. Corrigido para `Gate::authorize()`, que é o mesmo padrão das Actions.

### `?User` nos métodos da Policy é contrato do framework, não dead code

O Rector (`RemoveUnusedPublicMethodParameterRector` via preset `deadCode: true`) removeu os parâmetros `?User $utilizador` e `CategoriaDocumento $categoriaDocumento` dos métodos da Policy porque todos retornam `true` incondicionalmente. Resultado: testes de guest falharam com 403.

O motivo: o Laravel verifica por reflexão se o primeiro parâmetro do método de Policy é nullable (`?User`). Se não for nullable (ou se não existir), guests são bloqueados automaticamente antes de a Policy ser chamada. Parâmetros em métodos de Policy são um **contrato do framework** — a sua ausência muda o comportamento de autorização.

Fix: `rector.php` configurado com `withSkip` para `app/Policies/`.

### `CriarCategoriaRequest` e `ActualizarCategoriaRequest` não podem ser `final`

Adicionei `final` a estes dois FormRequests em T4 (alinhamento com a convenção do projecto). Quebrou os testes unitários `CriarCategoriaDtoTest` e `ActualizarCategoriaDtoTest` que usam `Mockery::mock()` nestas classes. Revertido — classes que precisam de ser mockadas em testes não devem ser `final`. `ListarCategoriasRequest` era já `final` originalmente e não é mockada.

### `ActualizarCategoriaRequestTest` — teste `authorize` actualizado para mock do Gate

O teste unitário `'authorize retorna true'` chamava `(new ActualizarCategoriaRequest)->authorize()` directamente. Com `Gate::authorize()` no método, falha em contexto de teste sem utilizador autenticado. Actualizado para `Gate::shouldReceive('authorize')->once()->with('update', null)` — verifica que a delegação ao Gate ocorre com os argumentos correctos.

### Integridade referencial não é responsabilidade da Policy

Questão levantada durante a implementação: deveria `delete()` verificar registos dependentes? A resposta é não — a Policy responde apenas a "quem pode fazer o quê". "Este registo pode ser eliminado dado o seu estado?" é uma regra de negócio que pertence à `EliminarCategoriaAction`. Quando a relação `CategoriaDocumento → Documento` for implementada, a Action verificará `$categoria->documentos()->exists()` antes de `$categoria->delete()`.

---

## Desvios ao plano

| Desvio | Causa | Resolução |
|---|---|---|
| `$this->authorize()` → `Gate::authorize()` | Recursão infinita em FormRequest | `Gate::authorize()` em todos os FormRequests |
| Rector removeu `?User` da Policy | `deadCode` preset — parâmetros não utilizados | `withSkip` no `rector.php` para `app/Policies/` |
| `final` revertido em 2 FormRequests | Mockery não funciona com classes `final` | Mantidos sem `final`; `VerCategoriaRequest` e `EliminarCategoriaRequest` são `final` (sem testes de mock) |
| `authorize retorna true` → `authorize delega em Gate` | Teste unitário falhava sem auth context | Mock do Gate com `shouldReceive` |

---

## Aprendizagens

### Policy — parâmetros como contrato de framework

A assinatura dos métodos de Policy não é apenas documentação — o Laravel usa reflexão para inferir comportamento. Um método `viewAny(): bool` (sem parâmetros) e um método `viewAny(?User $user): bool` têm comportamentos diferentes para guests. Este é um dos raros casos em Laravel onde remover "dead code" (parâmetros não utilizados) quebra funcionalidade. A Rector `withSkip` para `app/Policies/` é a solução correcta — não forçar "usos" artificiais do parâmetro.

### Gate::authorize() vs $this->authorize() — onde cada um existe

`$this->authorize()` (trait `AuthorizesRequests`) existe em Controllers. Em FormRequests, o método `authorize()` que estamos a definir é o nosso — não há método base com parâmetros para chamar. O Gate deve ser acedido directamente via `Gate::authorize()` ou via `$this->user()->can()`. Este é um subtileza da arquitectura Laravel que o Rector expôs ao tentar "corrigir" a chamada.

### Defence in depth — dupla verificação FormRequest + Action

Esta issue estabelece um padrão de dupla verificação: FormRequest (camada HTTP) + Action (camada de lógica). A redundância é intencional: garante que a autorização se aplica mesmo quando a Action é chamada fora do contexto HTTP (Jobs, Artisan, testes de integração directos à Action). Este padrão deve ser mantido em todas as features futuras.

### Vertical Slice e Policies partilhadas

`CategoriaDocumentoPolicy` ficou em `app/Policies/` (fora dos slices) porque é partilhada pelos 5 métodos CRUD. Em Vertical Slice, a regra geral é "co-localizar" — mas quando um componente é genuinamente transversal a múltiplos slices (ou métodos), a pasta partilhada é a escolha correcta. O auto-discovery do Laravel por convenção de nome (`CategoriaDocumentoPolicy` ↔ modelo `CategoriaDocumento`) elimina a necessidade de registo manual.
