# Brief — Issue #22: Corrigir nomenclatura CategoriaDocumento

**Data:** 2026-06-17
**Branch:** `feat/corrigir-nomenclatura-categorias`
**Tipo:** refactor
**Prioridade:** P2

---

## Contexto

As convenções do projecto exigem camelCase em todas as propriedades e variáveis PHP, nomes que transmitam intenção (NOUN+Intent+[?Escala] para variáveis, VERB+Intent para métodos), e snake_case apenas nas colunas da base de dados.
A extensão [Escala] deve entrar para variaveis em que o valor represente uma quantidade ex: $tempoExecucaoSegundos

Após análise da feature `CategoriaDocumento` foram identificadas quatro categorias de violação introduzidas durante a implementação inicial:

1. **Propriedade `$tipo_movimento` nos DTOs** — snake_case em PHP viola a convenção camelCase; deve ser `$tipoMovimento`
2. **Variável genérica `$validated`** — nome sem intenção contextual; deve ser `$dadosValidados` nos DTOs e `$parametrosValidados` no Controller
3. **Variável `$campos`** em `ActualizarCategoriaAction` — nome sem intenção; deve ser `$camposParaActualizar`
4. **Parâmetro `$request`** em `store()` e `update()` do Controller — inconsistente com `index()` que já usa `$pedido`

---

## Objectivo

Corrigir as quatro categorias de violação sem alterar nenhum contrato público (API, base de dados, testes de integração).

---

## Critérios de aceitação

- **CA-01:** `$tipo_movimento` nos DTOs renomeado para `$tipoMovimento`; array key `'tipo_movimento'` nos fill() e create() mantém-se (coluna BD)
- **CA-02:** `$validated` → `$dadosValidados` nos DTOs; `$validated` → `$parametrosValidados` no Controller (incluindo PHPDoc)
- **CA-03:** `$campos` → `$camposParaActualizar` em `ActualizarCategoriaAction`
- **CA-04:** `$request` → `$pedido` em `store()` e `update()` do Controller
- **CA-05:** testes actualizados onde usam named argument `tipo_movimento:` → `tipoMovimento:`
- **CA-06:** `composer test` passa sem erros

---

## Invariantes em risco

- A chave `'tipo_movimento'` nos arrays passados a `CategoriaDocumento::create()` e `->fill()` **não muda** — é o nome da coluna da BD
- O parâmetro de route model binding `$categorias_documento` **não muda** — imposto pelo nome da rota/recurso
- Métodos impostos pelo framework (`handle`, `store`, `update`, etc.) **não mudam**

---

## Ficheiros afectados

| Ficheiro                                                                   | Alterações                       |
| -------------------------------------------------------------------------- | -------------------------------- |
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php`              | CA-01, CA-02                     |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php`    | CA-01, CA-02                     |
| `app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php`           | CA-01 (acesso à propriedade DTO) |
| `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php` | CA-01, CA-03                     |
| `app/Features/CategoriaDocumento/CategoriaDocumentoController.php`         | CA-02, CA-04                     |
| `tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaActionTest.php` | CA-05                            |

---

## SYSTEM_SPEC a actualizar

Nenhum — refactoring interno sem alteração de contrato externo.

---

## Fora de âmbito

- Renomear colunas da base de dados
- Alterar parâmetros de route model binding
- Alterar métodos impostos pelo framework
- Alterar o `openapi.yaml`

---

## Riscos identificados

Nenhum. Alterações são puramente internas ao código PHP, sem impacto na API ou na BD.

---

## Questões em aberto

Nenhuma.
