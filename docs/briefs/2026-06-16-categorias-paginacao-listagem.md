# Brief — Issue #9: Paginação na listagem de categorias

**Data:** 2026-06-16
**Issue:** [#9](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/9)
**Slug:** categorias-paginacao-listagem
**Tipo:** feat
**Branch:** feat/categorias-paginacao-listagem

---

## Contexto

`ListarCategoriasAction::handle()` devolve `CategoriaDocumento::all()` — sem tecto, carrega toda a tabela em memória. A listagem deve suportar paginação controlada pelo cliente via query params `page` e `per_page`, com defaults e limite definidos em FormRequest.

---

## Problema a resolver

Um `CategoriaDocumento::all()` cresce linearmente com os dados e não tem mecanismo de controlo. A paginação é a solução standard Laravel para este padrão.

---

## O que muda

### Novos ficheiros

- `app/Features/CategoriaDocumento/Listar/ListarCategoriasRequest.php` — valida `page` (integer ≥ 1, default 1) e `per_page` (integer 1–100, default 15); mensagens em PT

### Ficheiros alterados

| Ficheiro | O que muda |
|---|---|
| `ListarCategoriasAction` | Assinatura: `(): Collection` → `(int $perPage, int $page): LengthAwarePaginator`; body: `::all()` → `::paginate($perPage, page: $page)` |
| `CategoriaDocumentoController::index()` | Injeta `ListarCategoriasRequest`, extrai `page`/`per_page` com defaults, chama `ApiResponse::devolverPaginado()` |
| `ApiResponse` | Novo método `devolverPaginado(AnonymousResourceCollection): JsonResponse` — chama `->response()` na collection (Laravel resolve meta de paginação automaticamente) |
| `ListarCategoriasTest` | Assertions actualizadas: `meta.current_page`, `meta.per_page`, `meta.last_page`, `meta.total`, `links`; testes novos para `page`/`per_page` custom e `page` fora do range |

### Ficheiros NÃO alterados

- `CategoriaDocumentoResource` — `toArray()` transforma cada item individualmente; independente de paginação
- `routes/api.php` — sem alterações; `GET /api/categorias-documento` já aceita query params
- `openapi.yaml` — fora de âmbito desta issue (declarado na issue)

---

## Breaking change

A resposta da listagem muda de formato:

**Antes:**
```json
{"data": [...], "meta": {"total": 3}}
```

**Depois (Laravel pagination standard):**
```json
{
  "data": [...],
  "links": {"first": "...", "last": "...", "prev": null, "next": null},
  "meta": {"current_page": 1, "from": 1, "last_page": 1, "path": "...", "per_page": 15, "to": 3, "total": 3}
}
```

Aceite — declarado na issue #9 como breaking change intencional.

---

## Decisão arquitectural: sem Repository

CRUD simples — 1 query Eloquent na Action, sem lógica partilhada entre Actions. Mantém-se o desvio documentado na issue #5 (critério CLAUDE.md: ≤ 1 query por `handle()`, sem joins, aggregates, raw SQL ou partilha).

---

## Riscos identificados

- `paginate()` com `page` muito alta devolve `data: []` com meta correcta — comportamento esperado (CA-03), não erro a tratar
- `per_page` sem limite → queries pesadas; mitigado com `max:100` no FormRequest
- `ListarCategoriasTest` testa via HTTP (feature test), não instancia `ListarCategoriasAction` directamente — a mudança de assinatura não quebra testes de outras features

---

## Questões em aberto

Nenhuma — critérios de aceitação da issue cobrem todos os casos relevantes.
