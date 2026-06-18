# Debrief — Issue #30: Forçar update completo em CategoriaDocumento

**Data:** 2026-06-18
**Issue:** #30
**Branch:** feat/categoria-documento-update-completo
**Pipeline:** Rector ✅ | Pint ✅ | PHPStan ✅ (0 erros) | Pest ✅ (104/104, 339 asserções)

---

## O que foi feito

Refactored a feature `Actualizar/CategoriaDocumento` de semântica PATCH (actualização parcial) para semântica PUT (update completo). Alterados 7 ficheiros: 3 de produção + 4 de testes.

### Ficheiros alterados

| Ficheiro | Alteração |
|---|---|
| `ActualizarCategoriaRequest` | `sometimes` → `required`; mensagens `.required` adicionadas |
| `ActualizarCategoriaDto` | Propriedades `?string`/`?TipoMovimento` → não-nullable; null guards removidos; array shape simplificado |
| `ActualizarCategoriaAction` | `array_filter(..., fn => $valor !== null)` removido; `fill()` directo com 3 campos |
| `ActualizarCategoriaDtoTest` | Testes de campos nulos removidos; asserção de mensagem adicionada; teste whitespace; `fromRequest()` actualizado |
| `ActualizarCategoriaRequestTest` | Helper `payloadCompleto()`; testes de `required` por campo; testes `sometimes` removidos |
| `ActualizarCategoriaActionTest` | DTO instanciado com todos os campos; asserções de `slug` e `tipoMovimento` adicionadas |
| `ActualizarCategoriaTest` | Helper `payloadActualizar()`; testes de update parcial substituídos; teste 422 por payload vazio |

---

## Decisões tomadas

### 1. PUT semântico em vez de PATCH — sem backwards compatibility

Decisão: remover `sometimes` e tornar todos os campos `required`. Não foi adicionado nenhum mecanismo de compatibilidade retroactiva porque:
- O projecto está em desenvolvimento, sem clientes externos
- O frontend nunca usa actualizações parciais na prática
- Manter `sometimes` seria acumular complexidade sem benefício

### 2. `ActualizarCategoriaDto` agora idêntico em estrutura ao `CriarCategoriaDto`

Ambos os DTOs ficaram com o mesmo padrão: propriedades `string` não-nullable, construtor valida invariantes incondicionalmente, `fromRequest()` apenas mapeia. A simetria entre Criar e Actualizar é intencional e reflecte o padrão Value Object do CLAUDE.md.

### 3. `array_filter` removido da Action

O `array_filter` existia apenas para ignorar campos nulos. Com o DTO não-nullable, todos os campos têm sempre valor — o filtro tornou-se código morto. Removido sem substituição.

### 4. Helpers de payload nos testes (`payloadCompleto` / `payloadActualizar`)

Introduzidos para evitar repetir os 3 campos em cada chamada de teste. Padrão `array_merge` com defaults + `$sobrepor` para sobreposição selectiva — mantém legibilidade nos testes de rejeição (só o campo relevante é nulo).

---

## O que ficou por fazer

Nada. Âmbito completo implementado e testado.

---

## Aprendizagens

### `sometimes` vs `required` — semântica PATCH vs PUT em FormRequests

O `sometimes` do Laravel não significa "campo opcional com valor nulo" — significa "valida este campo apenas se estiver presente no payload". A distinção é subtil mas importante: um payload sem o campo passa a validação com `sometimes`; um payload com o campo a `null` ainda falha se não tiver `nullable`.

Esta issue deixou claro que `sometimes` e `nullable` são ortogonais, e que escolher entre eles implica uma decisão de design da API (PATCH vs PUT), não apenas uma preferência de validação.

### Propagação de contrato: FormRequest → DTO → Action

A alteração do FormRequest (`sometimes` → `required`) criou uma cascata de simplificações: o DTO pôde perder o nullable, o que eliminou os null guards no construtor, o que tornou o `array_filter` na Action obsoleto. O contrato mais restritivo na camada HTTP simplificou todas as camadas interiores — ilustra como o design da validação HTTP tem impacto directo na complexidade do domínio.

### Testes como documentação do contrato

Os testes anteriores (ex: "aceita payload vazio", "aceita campos parciais") documentavam implicitamente que o endpoint era PATCH. Reescrever os testes forçou a articular explicitamente o novo contrato ("rejeita payload sem nome") — o que por sua vez confirmou que a decisão de design estava clara e implementada consistentemente.
