# Brief — Issue #12: test(categorias): adicionar assertJsonStructure nos testes de listagem

**Data:** 2026-06-17
**Branch:** feat/assertjsonstructure-listagem-categorias
**Issue:** #12
**Tipo:** test

---

## Contexto

`ListarCategoriasTest` cobre contagem, paginação por cursor, validação de parâmetros e navegação entre páginas. Contudo, apenas um teste (`'devolve lista de categorias com estrutura correcta'`) valida a estrutura JSON da resposta com `assertJsonStructure`. Os restantes testes de listagem verificam comportamento (counts, cursors, validação) mas não garantem que o envelope de resposta (`data`, `links`, `meta`) se mantém íntegro — um campo renomeado na `CategoriaDocumentoResource` ou no `ApiResponse` passaria despercebido.

---

## Estado actual descoberto

Ao ler o ficheiro de testes antes de planear, verificou-se que:

- **CA-01 já satisfeito:** O teste `'devolve lista de categorias com estrutura correcta'` já contém:
  ```php
  ->assertJsonStructure([
      'data' => [['id', 'nome', 'slug', 'tipo_movimento']],
      'links' => ['prev', 'next'],
      'meta' => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
  ]);
  ```
- **CA-02 adaptado:** A issue #9 foi implementada com cursor pagination (`cursorPaginate`), não com offset pagination como o CA-02 original previa. Os campos de meta são `per_page, next_cursor, prev_cursor, path` — não `total, current_page, per_page, last_page`. O teste existente já usa os campos correctos.
- **Âmbito restante:** Dos 7 testes no ficheiro, apenas 1 tem `assertJsonStructure`. Os outros 6 não validam a estrutura do envelope de resposta.

---

## Decisão de âmbito

A issue pede "testes de listagem incluem assertJsonStructure" (plural implícito). O trabalho restante é:

1. Adicionar `assertJsonStructure` do envelope (`links`, `meta`) aos testes que verificam comportamento de paginação mas não validam estrutura.
2. No teste de lista vazia, validar o envelope mas sem verificar itens de `data` (array vazio — nada a validar por dentro).
3. Nos testes de validação de parâmetros inválidos (`per_page > 100`, `sort inválido`, `direction inválida`), **não** adicionar `assertJsonStructure` — são respostas 422 com estrutura de erro, fora do contrato de listagem.

---

## Testes a alterar

| Teste | Acção |
|---|---|
| `'devolve lista vazia quando não existem categorias'` | Adicionar `assertJsonStructure` para envelope (sem items em `data`) |
| `'devolve lista de categorias com estrutura correcta'` | Já tem — nenhuma alteração |
| `'respeita o parâmetro per_page na paginação'` | Adicionar `assertJsonStructure` para envelope + items |
| `'navega para a página seguinte via cursor sem duplicados'` | Adicionar `assertJsonStructure` para envelope + items em ambas as páginas |
| `'cursor além do fim devolve lista vazia'` | Adicionar `assertJsonStructure` para envelope (sem items) |
| `'rejeita per_page acima do máximo'` | Não alterar — resposta 422 |
| `'rejeita sort inválido'` | Não alterar — resposta 422 |
| `'rejeita direction inválida'` | Não alterar — resposta 422 |

---

## Estrutura esperada (contrato)

```php
// Envelope completo com items
->assertJsonStructure([
    'data' => [['id', 'nome', 'slug', 'tipo_movimento']],
    'links' => ['prev', 'next'],
    'meta'  => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
])

// Envelope sem items (lista vazia ou cursor além do fim)
->assertJsonStructure([
    'data',
    'links' => ['prev', 'next'],
    'meta'  => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
])
```

---

## Riscos identificados

- Nenhum — adição pura de assertions, sem alteração de comportamento de produção.
- `assertJsonStructure` é permissiva (não falha em chaves extra) — os testes validam o mínimo obrigatório do contrato, não a estrutura exacta. Isso é intencional: se a Resource adicionar campos, os testes continuam a passar.

---

## Questões em aberto

- Nenhuma — âmbito claro, sem dependências externas.

---

## Decisão de Repository

Não aplicável — issue de testes apenas.

---

## Aprendizagens antecipadas

- `assertJsonStructure` sem `*` valida a presença de chaves; `[['campo']]` valida que cada item do array tem essa chave.
- Para lista vazia, usar `'data'` (string) em vez de `'data' => [['campo']]` para evitar falha quando o array está vazio.
