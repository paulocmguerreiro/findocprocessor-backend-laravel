# Debrief — Issue #16: refactor(categorias) — @var array shape + @throws nos DTOs

**Data:** 2026-06-15
**Branch:** `refactor/dto-phpdoc-categorias`
**Issue:** #16
**Duração:** sessão única

---

## O que foi implementado

Refactor puramente de documentação estática — nenhuma lógica runtime alterada:

- **`CriarCategoriaDto.fromRequest()`** — adicionado `@throws \UnexpectedValueException` no PHPDoc + `@var array{nome: string, slug: string, tipo_movimento: string} $validated` antes de `$request->validated()`
- **`ActualizarCategoriaDto.fromRequest()`** — mesmo padrão com campos opcionais: `@var array{nome?: string, slug?: string, tipo_movimento?: string} $validated`
- **`phpstan.neon`** — adicionado `treatPhpDocTypesAsCertain: false` para aceitar o padrão simultâneo de anotação estática + runtime guard sem falsos positivos do Larastan

Pipeline final: 62 testes, 188 assertions, 100% coverage, Larastan nível 9 sem erros.

---

## Decisões tomadas

### D1 — `treatPhpDocTypesAsCertain: false` no phpstan.neon

**Problema:** Sem esta flag, o Larastan via PHPStan produzia falso positivo ao ver o `@var array{...}` seguido de guard `is_string()`. O PHPStan interpretava o guard como "código morto" — a anotação já dizia que era `string`, logo o `is_string()` nunca poderia ser `false`. Resultado: reportava erro "always true" para a condição do guard.

**Solução:** `treatPhpDocTypesAsCertain: false` instrui o PHPStan a não confiar cegamente nas anotações PHPDoc para efeitos de análise de fluxo. As anotações continuam a servir como "dicas de tipo" (eliminam `mixed`), mas não fazem o PHPStan assumir que a condição é impossível.

**Porquê não eliminar o guard:** O guard `if/throw` é o contrato runtime — ao contrário de `assert()`, não é desactivável em produção. A anotação é estática; o guard é defensivo. Ambos têm papéis distintos.

### D2 — Array shape com `?` para campos opcionais vs obrigatórios

`CriarCategoriaDto` — todos os campos `required` no FormRequest → shape sem `?`:
```php
/** @var array{nome: string, slug: string, tipo_movimento: string} $validated */
```

`ActualizarCategoriaDto` — campos `sometimes` no FormRequest → shape com `?` (chave pode estar ausente):
```php
/** @var array{nome?: string, slug?: string, tipo_movimento?: string} $validated */
```

A distinção é semântica: `?` em array shape PHPDoc não significa "pode ser null" — significa "a chave pode não existir". Por isso o acesso ainda é `$validated['nome'] ?? null` (não `$validated['nome']`).

---

## O que correu bem

- A alteração foi minimal e cirúrgica — 4 linhas de PHPDoc em 2 ficheiros + 1 linha no phpstan.neon.
- O impacto nos testes foi zero: nenhum teste foi alterado, todos continuaram a passar.
- O padrão ficou documentado nas regras do CLAUDE.md (Regras A e B) como convenção obrigatória para futuros DTOs.

---

## O que foi mais difícil

**Falso positivo do Larastan nível 9 com `treatPhpDocTypesAsCertain` (default: `true`):** O PHPStan assume por defeito que as anotações PHPDoc são verdades absolutas para análise de fluxo. Ao ver `@var array{nome: string}` + `if (! is_string($nome))`, conclui que a condição é `always false` e reporta erro. A flag `treatPhpDocTypesAsCertain: false` é a saída correcta — mas não é óbvia nem documentada de forma proeminente.

---

## Aprendizagens (Vertical Slice + Tipagem + Larastan)

### PHPDoc como camada de comunicação, não como contrato de execução

`@var array{...}` e `@throws` são anotações estáticas — comunicam intenção à IDE, ao Larastan e a outros programadores, mas não têm efeito em runtime. Os guards `if/throw` são o contrato runtime. As duas camadas têm responsabilidades distintas e complementam-se: a estática elimina `mixed` da análise de tipos; a runtime garante que dados inválidos nunca chegam ao domínio.

### `treatPhpDocTypesAsCertain` — a tensão entre análise estática e código defensivo

PHPStan/Larastan assumem por defeito que as anotações são verdade absoluta (`treatPhpDocTypesAsCertain: true`). Isso é útil para eliminar verificações desnecessárias — mas entra em conflito com código defensivo que valida o que a anotação já "garante". O setting `false` resolve a tensão: as anotações continuam a melhorar a inferência de tipos, mas o PHPStan não as usa para marcar código defensivo como "dead code".

### Array shape PHPDoc — `?key` vs `key?` vs tipo nullable

Em PHPStan array shapes, `nome?: string` significa "a chave `nome` pode não existir no array" — não que o valor seja nullable. Um `nome: ?string` significaria "a chave existe mas o valor pode ser null". A distinção importa: `$validated['nome'] ?? null` trata correctamente a ausência da chave; `$validated['nome']` causaria `undefined array key` se a chave não existir.

---

## Ficheiros criados/editados

| Ficheiro | Operação |
|---|---|
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php` | Editado (+4 linhas PHPDoc) |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php` | Editado (+4 linhas PHPDoc) |
| `phpstan.neon` | Editado (+1 linha `treatPhpDocTypesAsCertain: false`) |

---

## Métricas finais

| Métrica | Valor |
|---|---|
| Testes totais | 62 |
| Assertions | 188 |
| Cobertura | 100% |
| Type coverage | 100% |
| Larastan erros | 0 |
| Rector sugestões | 0 |
| Pint erros | 0 |
