# Debrief — Issue #22: Corrigir nomenclatura CategoriaDocumento

**Data:** 2026-06-17
**Branch:** `feat/corrigir-nomenclatura-categorias`
**Issue:** [#22](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/22)
**Tipo:** refactor
**Estado:** concluído

---

## Resumo

Corrigidas quatro categorias de violação das convenções de nomenclatura na feature `CategoriaDocumento`. Todas as alterações são internas ao código PHP — zero impacto na API, na BD ou nos contratos externos.

---

## Critérios de aceitação

| CA | Descrição | Estado |
|----|-----------|--------|
| CA-01 | `$tipo_movimento` → `$tipoMovimento` nos DTOs | ✅ |
| CA-02 | `$validated` → `$dadosValidados` / `$parametrosValidados` | ✅ |
| CA-03 | `$campos` → `$camposParaActualizar` em `ActualizarCategoriaAction` | ✅ |
| CA-04 | `$request` → `$pedido` em `store()` e `update()` do Controller | ✅ |
| CA-05 | Named args nos testes: `tipo_movimento:` → `tipoMovimento:` | ✅ |
| CA-06 | `composer test` passa sem erros | ✅ 68 testes, 100% cobertura |

---

## Ficheiros alterados

| Ficheiro | Alterações |
|----------|------------|
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php` | CA-01, CA-02 |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php` | CA-01, CA-02 |
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php` | CA-01 (acesso à propriedade) |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php` | CA-01, CA-03 |
| `app/Features/CategoriaDocumento/CategoriaDocumentoController.php` | CA-02, CA-04 |
| `tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaActionTest.php` | CA-05 |

---

## Decisões tomadas

### D1 — Chave `'tipo_movimento'` nos arrays Eloquent manteve-se snake_case

A propriedade PHP do DTO passou para camelCase (`$tipoMovimento`), mas as chaves dos arrays passados a `create()` e `fill()` mantiveram-se `'tipo_movimento'` — são nomes de colunas BD onde snake_case é a convenção. A convenção camelCase aplica-se a identificadores PHP (propriedades, variáveis, parâmetros); não se aplica a strings que representam nomes de colunas.

### D2 — Parâmetro `$categorias_documento` não foi alterado

Este parâmetro é imposto pelo route model binding do Laravel (deriva do nome do recurso na rota). Está explicitamente fora de âmbito da issue.

### D3 — SYSTEM_SPEC não requer actualização

O refactoring é puramente interno — nenhum contrato externo (API, BD, eventos, repositórios) foi alterado. Conforme indicado na issue.

---

## Aprendizagens

### Distinção PHP-identifier vs. string-literal nas convenções de nomenclatura

A convenção camelCase aplica-se a **identificadores PHP** (nomes de propriedades, variáveis, parâmetros, métodos). Não se aplica a **string literals** que representam conceitos externos ao PHP — nomeadamente nomes de colunas da BD.

Isto cria um padrão recorrente nos DTOs deste projecto:

```php
// propriedade PHP → camelCase
public TipoMovimento $tipoMovimento;

// acesso na Action → camelCase (PHP)
'tipo_movimento' => $dados->tipoMovimento,
//  ↑ snake_case (chave BD)    ↑ camelCase (prop PHP)
```

Esta assimetria intencional é fácil de confundir durante a implementação inicial — especialmente quando a propriedade e a chave têm o mesmo significado semântico. A regra a memorizar: *"se o Laravel vai usar este nome para falar com a BD, é snake_case; se é PHP puro, é camelCase"*.

### Nomes contextuais eliminam ambiguidade em `fromRequest()`

A variável `$validated` é genérica — pode existir em qualquer contexto Laravel. Substituir por `$dadosValidados` (DTO) ou `$parametrosValidados` (Controller) comunica imediatamente a intenção e o escopo. Em DTOs onde o único trabalho é transformar dados validados em valores tipados, o nome contextual torna o código auto-documentado sem comentários adicionais.

### Consistência de parâmetro como forma de documentação implícita

`$pedido` em `index()` + `$pedido` em `store()` + `$pedido` em `update()` comunica que todos os métodos do Controller recebem um `$pedido` HTTP. `$request` em alguns e `$pedido` noutros criava ruído desnecessário — o leitor tinha de verificar se a diferença de nome tinha significado semântico.

---

## Métricas

- **Testes:** 68 passed, 0 failed
- **Cobertura:** 100%
- **PHPStan:** 0 erros (nível 9)
- **Rector:** 0 alterações pendentes
- **Pint:** 0 alterações de formatação
- **Ficheiros alterados:** 6
- **Linhas alteradas:** 29 inserções / 29 remoções (refactoring puro)
