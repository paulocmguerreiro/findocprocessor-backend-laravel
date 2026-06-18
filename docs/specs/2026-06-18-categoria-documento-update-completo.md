# Spec — Issue #30: Forçar update completo em CategoriaDocumento

**Data:** 2026-06-18
**Issue:** #30

---

## Contrato HTTP

### `PUT /api/categorias-documento/{id}`

**Payload obrigatório (todos os campos):**

```json
{
  "nome": "string (required, max:255)",
  "slug": "string (required, max:255, unique ignore self)",
  "tipo_movimento": "debito | credito | neutro (required)"
}
```

**Respostas:**

| Código | Situação |
|---|---|
| `200` | Update bem-sucedido — devolve `CategoriaDocumentoResource` |
| `404` | Categoria não encontrada |
| `422` | Campo obrigatório ausente ou inválido |
| `403` | Sem autorização |

**Payload incompleto → 422** com mensagens por campo:
- `nome` ausente: `"O nome da Categoria é obrigatório."`
- `slug` ausente: `"O identificador da URL da Categoria é obrigatório."`
- `tipo_movimento` ausente: `"O tipo de movimento é obrigatório."`

---

## Contrato DTO — `ActualizarCategoriaDto`

```php
final readonly class ActualizarCategoriaDto
{
    public function __construct(
        public string $nome,           // não-nullable
        public string $slug,           // não-nullable
        public TipoMovimento $tipoMovimento,  // não-nullable
    ) { ... }
}
```

**Invariantes (construtor):**
- `trim($nome) === ''` → `\InvalidArgumentException('nome não pode ser vazio.')`
- `trim($slug) === ''` → `\InvalidArgumentException('slug não pode ser vazio.')`

**`fromRequest()`:**
- Array shape: `array{nome: string, slug: string, tipo_movimento: string}`
- Sem `?? null` — todos os campos são `required` no FormRequest

---

## Contrato Action — `ActualizarCategoriaAction`

```php
$categoria->fill([
    'nome'           => $dados->nome,
    'slug'           => $dados->slug,
    'tipo_movimento' => $dados->tipoMovimento,
])->save();
```

- Actualiza sempre os 3 campos — sem `array_filter`
- Semântica PUT: o recurso é substituído pelo payload recebido

---

## Critérios de aceitação

- [ ] `ActualizarCategoriaRequest` rejeita payload sem `nome` com mensagem `"O nome da Categoria é obrigatório."`
- [ ] `ActualizarCategoriaRequest` rejeita payload sem `slug` com mensagem `"O identificador da URL da Categoria é obrigatório."`
- [ ] `ActualizarCategoriaRequest` rejeita payload sem `tipo_movimento` com mensagem `"O tipo de movimento é obrigatório."`
- [ ] `ActualizarCategoriaDto` lança `InvalidArgumentException` se `nome` for string vazia ou whitespace
- [ ] `ActualizarCategoriaDto` lança `InvalidArgumentException` se `slug` for string vazia
- [ ] `ActualizarCategoriaAction` actualiza os 3 campos no mesmo `fill()`
- [ ] `composer test` verde: Rector 0 alterações, Pint pass, PHPStan 0 erros, Pest 100% cobertura

---

## O que NÃO muda

- Rota (`PUT /api/categorias-documento/{id}`) — já usa PUT
- `CategoriaDocumentoResource` — sem alterações
- `CategoriaDocumentoController` — sem alterações
- Política de autorização — sem alterações
