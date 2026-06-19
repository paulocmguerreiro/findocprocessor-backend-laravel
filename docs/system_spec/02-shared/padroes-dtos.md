# System Spec — Shared: Padrões de DTOs

> Padrão transversal a todos os DTOs. Vivem dentro da slice, co-localizados com a Action (`app/Features/<Feature>/<Action>/`).

Os DTOs adoptam o padrão **Value Object**: nunca podem existir num estado inválido, independentemente do contexto de criação (HTTP, Job, Artisan, teste).

---

## Regras estruturais

- `final readonly class` — imutável.
- O **construtor** valida invariantes estruturais e lança `\InvalidArgumentException`.
- `fromRequest()` **só mapeia** — sem `if/throw` de tipos redundantes.
- Propriedades em camelCase PHP (`$tipoMovimento`), mesmo que a coluna BD seja snake_case (`'tipo_movimento'`).
- `@throws` declarado sempre que há `throw` (ver `02-shared/padroes-tipagem.md`).

---

## Divisão de responsabilidades

| Camada | Responsabilidade |
|---|---|
| `FormRequest` | required, formato, unicidade BD, regras HTTP |
| DTO (construtor) | invariantes estruturais — não-vazio, formato mínimo |
| Action | regras de negócio — unicidade entre entidades, consistência |

A validação **estrutural** (campo não vazio, formato mínimo) fica no construtor para garantir o contrato em qualquer contexto. A validação de **input HTTP** (required, max, unique BD) fica no FormRequest. As **regras de negócio** (unicidade entre entidades, invariantes de domínio cruzadas) ficam na Action.

---

## Exemplo canónico

```php
final readonly class CriarXxxDto
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $nome,
        public ?string $descricao,
    ) {
        if (trim($this->nome) === '') {
            throw new \InvalidArgumentException('nome não pode ser vazio.');
        }

        // campos nullable: só valida se não for null
        if ($this->descricao !== null && trim($this->descricao) === '') {
            throw new \InvalidArgumentException('descricao não pode ser vazio.');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromRequest(CriarXxxRequest $request): self
    {
        /** @var array{nome: string, descricao?: string} $dadosValidados */
        $dadosValidados = $request->validated();

        return new self(
            nome: $dadosValidados['nome'],
            descricao: $dadosValidados['descricao'] ?? null,
        );
    }
}
```

- `@var` array shape → Larastan conhece a forma do array (sem `mixed` nas variáveis derivadas).
- Construtor com `throw` → contrato runtime em qualquer contexto de invocação.
- `fromRequest()` só mapeia — sem `is_string()` / guards de tipo redundantes.
- `@throws` → callers informados estaticamente sem inspeccionarem a implementação.

---

## Variantes

- **Update completo (PUT):** todos os campos obrigatórios — o `ActualizarXxxDto` tem estrutura idêntica ao `CriarXxxDto` e valida invariantes incondicionalmente. Array shape sem `?`.
- **Campos nullable:** validar a invariante só quando o valor não for `null`.
- **Promoção condicional** (ex: flag A força flag B): **não** usar constructor promotion — atribuição manual no corpo do construtor.
- **Booleans:** sem validação de "vazio" — `bool` não tem estado vazio.
- `fromRequest()` é adicionado na **issue de lógica**, quando os FormRequests existem; nos DTOs criados na issue de modelo o método pode ainda não existir.

Exemplos concretos por feature em `01-features/<slug>.md`.
