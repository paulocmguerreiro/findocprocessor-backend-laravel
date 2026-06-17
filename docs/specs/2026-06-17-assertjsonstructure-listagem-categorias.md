# Spec — Issue #12: assertJsonStructure nos testes de listagem

**Data:** 2026-06-17
**Branch:** feat/assertjsonstructure-listagem-categorias
**Brief:** docs/briefs/2026-06-17-assertjsonstructure-listagem-categorias.md

---

## Contrato de resposta verificado

```
GET /api/categorias-documento
→ 200 OK
{
  "data": [
    { "id": "uuid", "nome": "string", "slug": "string", "tipo_movimento": "string" }
  ],
  "links": { "prev": "url|null", "next": "url|null" },
  "meta":  { "per_page": int, "next_cursor": "string|null", "prev_cursor": "string|null", "path": "string" }
}
```

---

## Ficheiros afectados

| Ficheiro | Operação |
|---|---|
| `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php` | Modificar — adicionar assertions |

Nenhum ficheiro de produção é alterado.

---

## Critérios de aceitação verificados

### CA-01 — Estrutura dos items em `data`
Já satisfeito no teste `'devolve lista de categorias com estrutura correcta'`:
```php
'data' => [['id', 'nome', 'slug', 'tipo_movimento']]
```
**Sem alteração necessária.**

### CA-02 — Estrutura do envelope (adaptado para cursor pagination)
A issue #9 foi implementada com `cursorPaginate`, não com `paginate`. Os campos de meta são:
- `per_page`, `next_cursor`, `prev_cursor`, `path`
- **Não** `total`, `current_page`, `per_page`, `last_page` (esses são de offset pagination)

O teste `'devolve lista de categorias com estrutura correcta'` já valida o envelope completo.
Os 4 testes indicados abaixo validam comportamento mas não estrutura — são os alvos desta issue.

### CA-03 — `composer test` verde
Verificado na T4.

---

## Alterações por teste

### T1 — `'devolve lista vazia quando não existem categorias'`

**Adicionar** após `assertJsonPath('meta.per_page', 15)`:
```php
->assertJsonStructure([
    'data',
    'links' => ['prev', 'next'],
    'meta'  => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
])
```
Nota: `'data'` como string (não array shape com items) porque o array está vazio.

### T2 — `'respeita o parâmetro per_page na paginação'`

**Adicionar** à chain de assertions da variável `$resposta`:
```php
->assertJsonStructure([
    'data' => [['id', 'nome', 'slug', 'tipo_movimento']],
    'links' => ['prev', 'next'],
    'meta'  => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
])
```

### T3 — `'navega para a página seguinte via cursor sem duplicados'`

**Adicionar** em ambas as páginas (`$pagina1` e `$pagina2`):
```php
->assertJsonStructure([
    'data' => [['id', 'nome', 'slug', 'tipo_movimento']],
    'links' => ['prev', 'next'],
    'meta'  => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
])
```
Nota: `$pagina1` e `$pagina2` já são variáveis — adicionar à chain existente.

### T4 — `'cursor além do fim devolve lista vazia'`

**Adicionar** após `assertJsonPath('links.next', null)`:
```php
->assertJsonStructure([
    'data',
    'links' => ['prev', 'next'],
    'meta'  => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
])
```

---

## Fora de âmbito

- Testes `'rejeita per_page acima do máximo'`, `'rejeita sort inválido'`, `'rejeita direction inválida'` — respostas 422, estrutura de erro diferente.
- Alterar `CategoriaDocumentoResource`, `ListarCategoriasAction` ou qualquer ficheiro de produção.
