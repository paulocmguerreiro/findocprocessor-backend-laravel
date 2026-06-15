# Brief — refactor(categorias): substituir `fresh()` por `refresh()` em ActualizarCategoriaAction

**Issue:** #15
**Data:** 2026-06-15
**Tipo:** refactor
**Slug:** `refactor-fresh-refresh-actualizar-categoria`

---

## Problema

`ActualizarCategoriaAction::handle()` termina com:

```php
return $categoria->fresh() ?? $categoria;
```

`fresh()` retorna uma **nova instância** carregada da BD, ou `null` se o registo não existir. O fallback `?? $categoria` existe apenas para satisfazer o tipo de retorno (`CategoriaDocumento`) face ao `?CategoriaDocumento` que `fresh()` devolve — situação impossível em runtime logo após `save()`.

O resultado é código defensivo para um risco inexistente, com uma assinatura de retorno desnecessariamente ansiosa.

## Solução

`refresh()` actualiza a instância existente **em lugar** (`void`) e não requer null check:

```php
$categoria->refresh();
return $categoria;
```

Comunica a intenção com mais clareza: "recarrega este objecto da BD, sem criar nova instância".

## Ficheiro afectado

| Ficheiro | Linha |
|---|---|
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php` | `handle()` — linha final do `return` |

## Impacto

- Comportamento externo: **inalterado** — o `CategoriaDocumento` devolvido reflecte o estado BD em ambos os casos
- Testes: nenhuma alteração esperada
- SYSTEM_SPEC: atualizar menção de `fresh()` em `01-features.md` (documentação, não contrato)
- Dependências: Issue #11 também toca `ActualizarCategoriaAction` — coordenar se necessário

## Decisão arquitectural

Sem Repository neste slice (CRUD simples, aprovado na Issue #5). A alteração é puramente de semântica de instância Eloquent — não levanta nenhuma questão de padrão adicional.

## Critérios de aceitação

- CA-01: `$categoria->fresh() ?? $categoria` → `$categoria->refresh(); return $categoria;`
- CA-02: Testes existentes passam sem alteração
- CA-03: `composer test` verde (Larastan + Pest)
