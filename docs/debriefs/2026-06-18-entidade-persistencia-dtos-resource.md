# Debrief — Issue #32: Entidade — persistence layer (DTOs + resource + testes)

**Data:** 2026-06-18
**Issue:** #32
**Branch:** `feat/entidade-persistencia-dtos-resource`
**Estado:** Implementado ✅

---

## O que foi implementado

### Ficheiros criados

| Ficheiro | Descrição |
|---|---|
| `app/Features/Entidade/Criar/CriarEntidadeDto.php` | Value Object para criação de Entidade |
| `app/Features/Entidade/Actualizar/ActualizarEntidadeDto.php` | Value Object para update completo de Entidade |
| `app/Features/Entidade/EntidadeResource.php` | Serialização JSON da resposta API |
| `tests/Unit/Features/Entidade/CriarEntidadeDtoTest.php` | 5 testes: 4 invariantes + happy path |
| `tests/Unit/Features/Entidade/ActualizarEntidadeDtoTest.php` | 5 testes: 4 invariantes + happy path |
| `tests/Unit/Features/Entidade/EntidadeResourceTest.php` | 3 testes: 6 campos, sem timestamps, booleans |

### Resultados da pipeline

```
Rector:        ✅ 0 ficheiros alterados
Pint:          ✅ passou (2 fixers binary_operator_spaces aplicados)
PHPStan (L9):  ✅ 0 erros
Tests:         ✅ 117/117 passed
Coverage:      ✅ 100%
Type coverage: ✅ 100%
```

---

## Decisões tomadas

### Repositório dispensado
CRUD simples: 0 queries complexas, 0 joins, 0 aggregates, 0 queries partilhadas entre Actions. Conforme critério CLAUDE.md — desvio explícito registado no Brief.

### `fromRequest()` ausente nos DTOs
Os FormRequests da `Entidade` ainda não existem — serão criados na issue de lógica (Actions + Controller). Adicionado nessa fase para manter coesão.

### Booleans sem validação de trim
`bool` não tem estado "vazio". Apenas `nome` e `nif` têm invariante de não-vazio. A invariante de negócio `eEmpresaAplicacao → eCliente/eFornecedor` pertence à Action (fora do âmbito desta issue).

### `@mixin Entidade` no Resource
Necessário para que o Larastan infira `$this->nome`, `$this->nif`, etc. sem `mixed`. Padrão estabelecido em `CategoriaDocumentoResource`.

### Pint: binary_operator_spaces
O alinhamento em colunas (`'id'  =>`, `'nome' =>`) é corrigido pelo Pint para espaço único — comportamento esperado e consistente com o resto do projecto.

---

## Critérios de aceitação verificados

| CA | Estado |
|---|---|
| CA-01: `CriarEntidadeDto` é `final readonly class` | ✅ |
| CA-02: `ActualizarEntidadeDto` é `final readonly class` | ✅ |
| CA-03: Construtor lança `\InvalidArgumentException` para `nome`/`nif` vazios | ✅ |
| CA-04: `EntidadeResource` serializa os 6 campos (sem timestamps) | ✅ |
| CA-05: Testes happy path para criação e actualização | ✅ |
| CA-06: Testes `nome` vazio → `\InvalidArgumentException` | ✅ |
| CA-07: Testes `nif` vazio → `\InvalidArgumentException` | ✅ |
| CA-08: Testes serialização Resource (campos + tipos) | ✅ |
| CA-09: 100% coverage + 100% type coverage | ✅ |

---

## Aprendizagens

### DTOs sem `fromRequest()` — separação de fases no Vertical Slice

Esta issue demonstra que num Vertical Slice os DTOs podem existir como contratos puros antes de terem os seus métodos de conveniência HTTP. O DTO encapsula invariantes de domínio independentemente do contexto de invocação (HTTP, Job, teste directo). O `fromRequest()` é apenas um mapper de conveniência que só faz sentido existir quando o FormRequest existe — adicioná-lo prematuramente criaria uma dependência para uma classe que ainda não existe. A separação em fases (modelo → persistência → lógica) expõe este padrão com clareza: o DTO é testável e utilizável na Fase 2 sem qualquer dependência HTTP.

### `final readonly` como invariante arquitectural

`final readonly class` nos DTOs não é apenas estilo — é um contrato que o compilador PHP impõe: uma vez construído, o DTO não pode ser modificado. Combinado com o construtor que valida invariantes, garante que um DTO nunca pode existir num estado inválido em qualquer contexto de invocação. Esta propriedade é especialmente valiosa quando a Action é invocada directamente em Jobs ou testes de integração, onde o FormRequest não está presente.

### Cobertura 100% sem `fromRequest()`

Sem o `fromRequest()`, os DTOs têm exactamente 2 branches por campo validado (`trim === ''` ou não). Os 5 testes por DTO (4 invariantes + 1 happy path) cobrem todos os caminhos. A ausência de métodos HTTP simplifica os testes — sem Mockery, sem `shouldReceive`, apenas instanciação directa.

---

## Fora de âmbito (confirmado)

- `fromRequest()` nos DTOs — issue de lógica
- Interface e Repositório Eloquent — dispensados (CRUD simples)
- Service Provider binding — não necessário sem repositório
- Actions, Controller, Events — issue de lógica
- FormRequests — issue de lógica
- Endpoints API — issue de lógica
