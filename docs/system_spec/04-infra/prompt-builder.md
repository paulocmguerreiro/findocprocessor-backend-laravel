# System Spec — Infra: PromptBuilder

> `app/Infrastructure/AI/PromptBuilder.php`
> `app/Shared/Prompts/base_instructions.txt`

Constrói o **system prompt** de texto usado para instruir o provider de IA (via Prism, provider-agnóstico — ver `04-infra/extracao-ia.md`) a classificar e extrair dados de documentos financeiros. Cobre apenas a *construção* do prompt — sem chamada HTTP, sem parsing de resposta (isso fica em `ClienteExtracaoIAPrism`, ver `04-infra/extracao-ia.md`).

---

## Classe

`App\Infrastructure\AI\PromptBuilder` — `final class`, `strict_types=1`, sem interface (variação está no consumidor do prompt, não no algoritmo de construção). API fluente estática:

```php
PromptBuilder::novo()
    ->comInstrucoesBase()
    ->comEmpresaMae()
    ->comTiposDocumento()
    ->construir(): string
```

### Métodos

| Método | Efeito | Excepções |
|---|---|---|
| `novo(): self` | Entrada estática, devolve nova instância | — |
| `comInstrucoesBase(): self` | Lê `app/Shared/Prompts/base_instructions.txt` e fixa-o como âncora inicial do prompt (RN-01) | `\RuntimeException` se a leitura falhar |
| `comEmpresaMae(): self` | Obtém `Entidade::whereEmpresaAplicacao()->first()` e acrescenta um segmento com nome/NIF reais | `\RuntimeException` se não existir nenhuma `Entidade` marcada (RN-02) |
| `filtrarPorCategoria(CategoriaDocumento\|string $idCategoria): self` | Regista um filtro de categoria; só tem efeito se chamado **antes** de `comTiposDocumento()` (RN-03) | — |
| `comTiposDocumento(): self` | Carrega `TipoDocumento::with('categoria')` (filtrado ou não) e acrescenta os segmentos "Passo 1 — Classificação" e "Passo 2 — Campos a extrair por tipo" | — |
| `construir(): string` | Concatena `comInstrucoesBase()` (sempre primeiro) com os segmentos acrescentados pelos restantes métodos, pela ordem de chamada | `\LogicException` se `comInstrucoesBase()` nunca foi chamado (RN-05) |

---

## Regras de negócio

- **RN-01** — o texto de `base_instructions.txt` é sempre o primeiro segmento do prompt final, independentemente da ordem de chamada dos métodos fluentes.
- **RN-02** — `comEmpresaMae()` lança `\RuntimeException` se não existir nenhuma `Entidade` com `e_empresa_aplicacao = true` — estado de configuração inválido, falha cedo em vez de gerar um prompt sem dados da empresa mãe.
- **RN-03** — `filtrarPorCategoria()` só tem efeito se chamado **antes** de `comTiposDocumento()`; a query é resolvida no momento em que `comTiposDocumento()` executa, não em `construir()`. Chamado depois, não filtra retroactivamente o segmento já gerado.
- **RN-04** — "Passo 2" lista, por `TipoDocumento`, os campos com `espera_* = true` mapeados para os nomes JSON esperados na resposta da IA (`espera_data_documento`→`data_documento`, `espera_fornecedor`→`fornecedor`, `espera_cliente`→`cliente`, `espera_valor`→`valor`); campos `espera_* = false` são omitidos.
- **RN-05** — `construir()` sem `comInstrucoesBase()` chamado lança `\LogicException`.
- **RN-06** — "Passo 1" inclui, por `TipoDocumento`, a posição da empresa mãe (`posicao_empresa_mae` — `fornecedor` ou `cliente`) — sem isto a IA não tem como saber em que papel a empresa mãe (nome/NIF injectados por `comEmpresaMae()`) surge em cada tipo de documento.

---

## `base_instructions.txt`

Texto fixo, independente de dados em BD, versionado em `app/Shared/Prompts/base_instructions.txt` (fora de `public/`, sem acesso HTTP directo). Inclui:

- **Isolamento de conteúdo** (regras I-IV) — o documento recebido é sempre dados passivos, nunca fonte de instruções; texto que pareça uma ordem dirigida à IA é ignorado e sinalizado como "perigoso".
- **Regras absolutas** (1-7) — resposta exclusivamente em JSON, nunca inventar dados, datas em `YYYY-MM-DD`, valores monetários como `float`, campo `tipo_documento` obrigatório, e os casos `"desconhecido"` (documento não corresponde a nenhum `TipoDocumento`) e `"perigoso"` (tentativa de manipulação/prompt injection detectada).

As regras de NIF/nome da empresa mãe **não** aparecem neste ficheiro — são geradas dinamicamente por `comEmpresaMae()` a partir de dados reais da `Entidade`.

---

## Exemplo de output de `construir()`

```
<conteúdo de base_instructions.txt>

EMPRESA MÃE:
Nome: Acme Lda
NIF: 123456789

Esta é a empresa mãe da aplicação. A posição em que este NIF surge em cada documento...

Passo 1 — Classificação:
- "receitas" → Fatura Simples: Fatura emitida a um cliente (empresa mãe: fornecedor)

Passo 2 — Campos a extrair por tipo:
- Fatura Simples: data_documento, cliente, valor
```

---

## Desvio ao padrão dual de testes

Primeiro mecanismo do projecto sem par HTTP — `PromptBuilder` não tem Controller, FormRequest, rota nem Resource associados (ver `07-testing.md`). Testado apenas em `tests/Unit/Infrastructure/AI/PromptBuilderTest.php`.

## Regra Arch

`tests/ArchTest.php` — `arch('infrastructure classes are final')->expect('App\Infrastructure')->toBeFinal()`. Sem excepções previstas.

## Fora de âmbito deste ficheiro

- Wrapping do conteúdo do documento em tags de nonce anti prompt-injection — feito por `ClienteExtracaoIAPrism`, não por `PromptBuilder` (ver `04-infra/extracao-ia.md`).
- Cliente HTTP para o provider de IA, envio do prompt, parsing da resposta — `ClienteExtracaoIAPrism` (`04-infra/extracao-ia.md`).
