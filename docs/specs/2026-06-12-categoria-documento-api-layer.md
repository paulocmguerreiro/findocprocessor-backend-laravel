# Spec — Issue #3: CategoriaDocumento API Layer

**Data:** 2026-06-12
**Branch:** feat/categoria-documento-api-layer
**Issue:** #3 — feat(laravel): CategoriaDocumento — API layer (Resource + FormRequests)

---

## Âmbito

Criar a camada de apresentação/API para `CategoriaDocumento` dentro de Vertical Slice:

```
app/Features/CategoriaDocumento/
  CategoriaDocumentoResource.php
  Criar/
    CriarCategoriaRequest.php
  Actualizar/
    ActualizarCategoriaRequest.php
```

---

## Contratos

### CategoriaDocumentoResource

**Namespace:** `App\Features\CategoriaDocumento`
**Extends:** `Illuminate\Http\Resources\Json\JsonResource`

Resposta JSON (output de todos os endpoints futuros que retornem uma categoria):

```json
{
  "id": "019741b2-...",
  "nome": "Fatura de Fornecedor",
  "slug": "fatura-de-fornecedor",
  "tipo_movimento": "debito"
}
```

- `tipo_movimento` exposto como string via `->value` do enum `TipoMovimento`
- Timestamps omitidos intencionalmente
- Tipagem completa no `toArray()` via PHPDoc

### CriarCategoriaRequest

**Namespace:** `App\Features\CategoriaDocumento\Criar`
**Extends:** `Illuminate\Foundation\Http\FormRequest`

| Campo            | Regras (programáticas)                                                          |
|------------------|---------------------------------------------------------------------------------|
| `nome`           | `required`, `string`, `max:255`                                                 |
| `slug`           | `required`, `string`, `max:255`, `Rule::unique('categorias_documento', 'slug')` |
| `tipo_movimento` | `required`, `string`, `Rule::in(array_column(TipoMovimento::cases(), 'value'))` |

- `authorize()` retorna `true`
- Usar `Rule::unique()` e `Rule::in()` de `Illuminate\Validation\Rule`

#### Mensagens personalizadas (`messages()`)

| Chave                        | Mensagem                                                              |
|------------------------------|-----------------------------------------------------------------------|
| `nome.required`              | `'O nome da Categoria é obrigatório.'`                                |
| `nome.string`                | `'O nome da Categoria deve ser texto.'`                               |
| `nome.max`                   | `'O nome da Categoria não pode ter mais de 255 caracteres.'`          |
| `slug.required`              | `'O slug da Categoria é obrigatório.'`                                |
| `slug.string`                | `'O slug da Categoria deve ser texto.'`                               |
| `slug.max`                   | `'O slug da Categoria não pode ter mais de 255 caracteres.'`          |
| `slug.unique`                | `'Já existe uma Categoria com este slug.'`                            |
| `tipo_movimento.required`    | `'O tipo de movimento é obrigatório.'`                                |
| `tipo_movimento.string`      | `'O tipo de movimento deve ser texto.'`                               |
| `tipo_movimento.in`          | `'O tipo de movimento indicado não é válido.'`                        |

### ActualizarCategoriaRequest

**Namespace:** `App\Features\CategoriaDocumento\Actualizar`
**Extends:** `Illuminate\Foundation\Http\FormRequest`

| Campo            | Regras (programáticas)                                                                          |
|------------------|-------------------------------------------------------------------------------------------------|
| `nome`           | `sometimes`, `string`, `max:255`                                                                |
| `slug`           | `sometimes`, `string`, `max:255`, `Rule::unique('categorias_documento', 'slug')->ignore($uuid)` |
| `tipo_movimento` | `sometimes`, `string`, `Rule::in(array_column(TipoMovimento::cases(), 'value'))`                |

- `authorize()` retorna `true`
- `$uuid` obtido via `$this->route('categoria')` (parâmetro de rota com o UUID do registo)
- `Rule::unique()->ignore()` recebe o UUID para excluir o registo actual da validação de unicidade

#### Mensagens personalizadas (`messages()`)

As mesmas chaves de `CriarCategoriaRequest`, acrescidas de:

| Chave                        | Mensagem                                                              |
|------------------------------|-----------------------------------------------------------------------|
| `nome.string`                | `'O nome da Categoria deve ser texto.'`                               |
| `nome.max`                   | `'O nome da Categoria não pode ter mais de 255 caracteres.'`          |
| `slug.string`                | `'O slug da Categoria deve ser texto.'`                               |
| `slug.max`                   | `'O slug da Categoria não pode ter mais de 255 caracteres.'`          |
| `slug.unique`                | `'Já existe uma Categoria com este slug.'`                            |
| `tipo_movimento.string`      | `'O tipo de movimento deve ser texto.'`                               |
| `tipo_movimento.in`          | `'O tipo de movimento indicado não é válido.'`                        |

> Nota: campos com `sometimes` não disparam `required` — as mensagens `*.required` são omitidas.

---

## Testes — cobertura exigida

### Unit tests (`tests/Unit/Features/CategoriaDocumento/`)

**CategoriaDocumentoResourceTest**
- Retorna os 4 campos esperados (id, nome, slug, tipo_movimento como string)
- Não inclui timestamps
- `tipo_movimento` é o valor string do enum (ex: `'debito'`)

**CriarCategoriaRequestTest**
- `authorize()` retorna `true`
- Valida payload completo e válido (passes)
- Rejeita: `nome` em falta — mensagem `'O nome da Categoria é obrigatório.'`
- Rejeita: `slug` em falta — mensagem `'O slug da Categoria é obrigatório.'`
- Rejeita: `tipo_movimento` em falta — mensagem `'O tipo de movimento é obrigatório.'`
- Rejeita: `slug` duplicado — mensagem `'Já existe uma Categoria com este slug.'`
- Rejeita: `tipo_movimento` inválido — mensagem `'O tipo de movimento indicado não é válido.'`

**ActualizarCategoriaRequestTest**
- `authorize()` retorna `true`
- Aceita payload parcial (só `nome`)
- Aceita payload vazio (nenhum campo — tudo `sometimes`)
- Aceita `slug` igual ao registo actual (deve ignorar na unicidade)
- Rejeita: `slug` de outro registo existente — mensagem `'Já existe uma Categoria com este slug.'`
- Rejeita: `tipo_movimento` inválido quando presente — mensagem `'O tipo de movimento indicado não é válido.'`

---

## Invariantes

- `strict_types=1` em todos os ficheiros
- Larastan nível 9 — zero erros
- 100% code coverage + 100% type coverage
- Sem lógica de negócio nas classes desta issue

---

## Critérios de aceitação

- CA-01: `CategoriaDocumentoResource` expõe id, nome, slug, tipo_movimento (sem timestamps)
- CA-02: `CriarCategoriaRequest` rejeita payloads inválidos com mensagens em português (nome em falta, slug duplicado, tipo_movimento inválido)
- CA-03: `ActualizarCategoriaRequest` aceita actualizações parciais (campos omitidos não causam erro)
- CA-04: `ActualizarCategoriaRequest` exclui o próprio registo na validação de unicidade do slug
- CA-05: Larastan nível 9 — zero erros
- CA-06: 100% code coverage + 100% type coverage (`composer test`)
- CA-07: Mensagens de erro em português de Portugal via `messages()` em ambos os FormRequests
