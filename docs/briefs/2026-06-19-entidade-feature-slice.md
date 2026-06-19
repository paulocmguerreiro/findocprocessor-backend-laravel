# Brief — Issue #40: Entidade Feature Slice

**Data:** 2026-06-19
**Issue:** #40
**Slug:** `entidade-feature-slice`
**Branch:** `feat/entidade-feature-slice`
**Tipo:** feat

---

## Contexto

Implementar a camada de lógica da entidade `Entidade` — Actions, Controller e FormRequests —
expondo um CRUD completo via API REST com a regra de negócio de unicidade da Empresa Mãe
(Aplicação), que deve ser automaticamente marcada como cliente e fornecedor.

A camada de modelo (#27) e de persistência (#32) estão implementadas:
- `Entidade` model com scopes `whereEmpresaAplicacao()`, `whereCliente()`, `whereFornecedor()`
- `EntidadePolicy` (todos os métodos retornam `true` nesta fase)
- `CriarEntidadeDto` e `ActualizarEntidadeDto` (sem `fromRequest()` — a adicionar aqui)
- `EntidadeResource` (pronto a usar)
- `EntidadeFactory` (para testes)

---

## O que vamos construir

### Ficheiros novos

| Ficheiro | Descrição |
|---|---|
| `app/Features/Entidade/Listar/CampoOrdenacaoEntidades.php` | Enum de campos de ordenação (seguindo o padrão de `CampoOrdenacaoCategorias`) |
| `app/Features/Entidade/Listar/ListarEntidadesRequest.php` | FormRequest — `viewAny` |
| `app/Features/Entidade/Listar/ListarEntidadesAction.php` | `cursorPaginate()` |
| `app/Features/Entidade/Criar/CriarEntidadeRequest.php` | FormRequest — `create` |
| `app/Features/Entidade/Criar/CriarEntidadeAction.php` | Cria entidade; invoca `RemoverMarcacaoEmpresaMaeAction` se `eEmpresaAplicacao` |
| `app/Features/Entidade/Ver/VerEntidadeRequest.php` | FormRequest — `view` |
| `app/Features/Entidade/Ver/VerEntidadeAction.php` | Devolve entidade |
| `app/Features/Entidade/Actualizar/ActualizarEntidadeRequest.php` | FormRequest — `update` |
| `app/Features/Entidade/Actualizar/ActualizarEntidadeAction.php` | Actualiza; invoca `RemoverMarcacaoEmpresaMaeAction` se `eEmpresaAplicacao` |
| `app/Features/Entidade/Eliminar/EliminarEntidadeRequest.php` | FormRequest — `delete` |
| `app/Features/Entidade/Eliminar/EliminarEntidadeAction.php` | Hard delete |
| `app/Features/Entidade/EmpresaMae/RemoverMarcacaoEmpresaMaeAction.php` | Interna — remove flag; chamada dentro da transação do caller |
| `app/Features/Entidade/EmpresaMae/ConverterEmEmpresaMaeAction.php` | Converte entidade em Empresa Mãe; força os 3 flags |
| `app/Features/Entidade/EmpresaMae/ConverterEmEmpresaMaeRequest.php` | FormRequest — `update` |
| `app/Features/Entidade/EntidadeController.php` | Controller sem lógica — 6 métodos |
| `tests/Feature/Features/Entidade/CriarEntidadeTest.php` | Testes de feature: POST /api/entidades |
| `tests/Feature/Features/Entidade/ListarEntidadesTest.php` | Testes de feature: GET /api/entidades |
| `tests/Feature/Features/Entidade/VerEntidadeTest.php` | Testes de feature: GET /api/entidades/{id} |
| `tests/Feature/Features/Entidade/ActualizarEntidadeTest.php` | Testes de feature: PUT /api/entidades/{id} |
| `tests/Feature/Features/Entidade/EliminarEntidadeTest.php` | Testes de feature: DELETE /api/entidades/{id} |
| `tests/Feature/Features/Entidade/ConverterEmEmpresaMaeTest.php` | Testes de feature: PATCH /api/entidades/{id}/empresa-mae |

### Ficheiros a modificar

| Ficheiro | Modificação |
|---|---|
| `app/Features/Entidade/Criar/CriarEntidadeDto.php` | Adicionar `fromRequest(CriarEntidadeRequest $request): self` |
| `app/Features/Entidade/Actualizar/ActualizarEntidadeDto.php` | Adicionar `fromRequest(ActualizarEntidadeRequest $request): self` |
| `routes/api.php` | Adicionar `apiResource('entidades')` + rota `empresa-mae` |
| `docs/system_spec/01-features.md` | Documentar feature slice Entidade |
| `docs/system_spec/05-routes.md` | Documentar novas rotas |

---

## Regra de negócio crítica — Unicidade da Empresa Mãe

`RemoverMarcacaoEmpresaMaeAction::handle()` **não abre transação própria** — é sempre
invocada dentro da transação do chamador:

```
CriarEntidadeAction::handle()        → DB::transaction { RemoverMarcacao..., Entidade::create() }
ActualizarEntidadeAction::handle()   → DB::transaction { RemoverMarcacao..., $entidade->update() }
ConverterEmEmpresaMaeAction::handle()→ DB::transaction { RemoverMarcacao..., $entidade->update() }
```

O índice único parcial em `entidades.e_empresa_aplicacao = true` actua como guarda final
contra race conditions — mas a limpeza prévia é obrigatória.

---

## Decisões arquitecturais

### 1. Sem Repository — Eloquent directo
Cada Action tem ≤ 1 query Eloquent directa por `handle()`. A excepção é `CriarEntidadeAction`
e `ActualizarEntidadeAction` que invocam `RemoverMarcacaoEmpresaMaeAction` (que faz 1 UPDATE),
mas o desvio é intencional e justificado: `RemoverMarcacaoEmpresaMaeAction` encapsula a lógica
de negócio partilhada e não é um repositório — é uma Action de domínio.

### 2. Enum `CampoOrdenacaoEntidades`
Seguindo o padrão `CampoOrdenacaoCategorias`, a listagem de entidades requer um enum para o
campo de ordenação. Campos: `Nome = 'nome'`. Extensível com `Nif`, `CreatedAt`.

### 3. Route Model Binding — parâmetro `{entidade}`
O controller usa `Entidade $entidade` (em camelCase no PHP mas `{entidade}` na rota). O RMB
do Laravel resolve automaticamente via `HasUuids`. 404 automático se UUID não existe.

### 4. `ConverterEmEmpresaMaeAction` — sem DTO
A operação não recebe dados do body (nenhum campo a validar). O controller passa directamente
a `$entidade` resolvida pelo RMB. O FormRequest serve apenas para autorização (`update`).

### 5. Autorização dupla
`Gate::authorize()` no FormRequest (HTTP) **e** na Action (lógica) — mesma convenção do
slice `CategoriaDocumento`. A Action interna `RemoverMarcacaoEmpresaMaeAction` não faz
autorização própria — é chamada dentro de uma Action já autorizada.

---

## Riscos identificados

- **Race condition na unicidade**: se dois requests criarem Empresa Mãe simultaneamente,
  o índice único parcial lança `UniqueConstraintViolationException`. Não é tratado nesta issue
  (fora de âmbito — a excepção propaga-se como 500 ou pode ser capturada pelo handler global).
- **`fromRequest()` nos DTOs**: os DTOs existem mas sem `fromRequest()`. A ausência faz com que
  os Controllers não possam construir DTOs a partir dos FormRequests — é o primeiro passo a
  implementar.
- **Larastan nível 9**: o array shape de `validated()` tem de ser anotado correctamente em cada
  `fromRequest()` e no Controller — ver padrão em `CriarCategoriaDto::fromRequest()`.

---

## Questões em aberto

- Nenhuma — issue bem definida com dependências concluídas.

---

## Fora de âmbito

- Model, Migration, Factory: issue #27
- DTOs (construtor + invariantes), Resource: issue #32
- Policy: issue #27
- openapi.yaml: mencionado na issue mas não é requisito de aceitação (`composer test` não valida)
- Tratamento de `UniqueConstraintViolationException` em race conditions
