# Debrief — Issue #40: Entidade Feature Slice

**Data:** 2026-06-19
**Issue:** #40
**Slug:** `entidade-feature-slice`
**Branch:** `feat/entidade-feature-slice`
**Estado:** concluído

---

## O que foi implementado

CRUD completo da entidade `Entidade` via API REST, incluindo a regra de negócio de unicidade
da Empresa Mãe. A implementação cobre a camada de lógica completa sobre o modelo e a persistência
já existentes (Issues #27 e #32).

### Ficheiros criados

| Ficheiro | Tipo |
|---|---|
| `app/Features/Entidade/Listar/CampoOrdenacaoEntidades.php` | Enum |
| `app/Features/Entidade/Listar/ListarEntidadesRequest.php` | FormRequest |
| `app/Features/Entidade/Listar/ListarEntidadesAction.php` | Action |
| `app/Features/Entidade/Criar/CriarEntidadeRequest.php` | FormRequest |
| `app/Features/Entidade/Criar/CriarEntidadeAction.php` | Action |
| `app/Features/Entidade/Ver/VerEntidadeRequest.php` | FormRequest |
| `app/Features/Entidade/Ver/VerEntidadeAction.php` | Action |
| `app/Features/Entidade/Actualizar/ActualizarEntidadeRequest.php` | FormRequest |
| `app/Features/Entidade/Actualizar/ActualizarEntidadeAction.php` | Action |
| `app/Features/Entidade/Eliminar/EliminarEntidadeRequest.php` | FormRequest |
| `app/Features/Entidade/Eliminar/EliminarEntidadeAction.php` | Action |
| `app/Features/Entidade/EmpresaMae/RemoverMarcacaoEmpresaMaeAction.php` | Action interna |
| `app/Features/Entidade/EmpresaMae/RegraUnicidadeEmpresaMae.php` | Value Object de regra |
| `app/Features/Entidade/EmpresaMae/ConverterEmEmpresaMaeAction.php` | Action |
| `app/Features/Entidade/EmpresaMae/ConverterEmEmpresaMaeRequest.php` | FormRequest |
| `app/Features/Entidade/EntidadeController.php` | Controller |
| `app/Features/Entidade/ComFlagsEfectivosEmpresaMae.php` | Trait |
| `tests/Unit/Features/Entidade/*ActionTest.php` (11 ficheiros) | Testes unitários |
| `tests/Feature/Features/Entidade/*Test.php` (6 ficheiros) | Testes de feature |

### Ficheiros modificados

| Ficheiro | Modificação |
|---|---|
| `app/Features/Entidade/Criar/CriarEntidadeDto.php` | `fromRequest()` + trait `ComFlagsEfectivosEmpresaMae` |
| `app/Features/Entidade/Actualizar/ActualizarEntidadeDto.php` | `fromRequest()` + trait |
| `routes/api.php` | `apiResource('entidades')` + `PATCH empresa-mae` |
| `tests/ArchTest.php` | Excluiu `RemoverMarcacaoEmpresaMaeAction` da regra `actions are final` |
| `docs/conventions/tests-dual-pattern.md` | Novo — documenta o padrão dual Unit+Feature |

---

## Decisões tomadas

### 1. `RegraUnicidadeEmpresaMae` como classe de domínio (não Action)

O plano original previa `RemoverMarcacaoEmpresaMaeAction` a ser injectada directamente em cada
Action de escrita. Durante a implementação, surgiu uma questão de design: a lógica "se
`eEmpresaAplicacao = true`, então remover marcação anterior" é uma **regra de negócio**, não
uma sequência de passos procedurais.

Solução adoptada: introduzir `RegraUnicidadeEmpresaMae` como classe intermédia. Aceita um `bool`
e decide internamente se `RemoverMarcacaoEmpresaMaeAction` é invocada. As Actions de escrita
injectam `RegraUnicidadeEmpresaMae` e chamam `$this->regraUnicidade->handle($dados->eEmpresaAplicacao)`.

**Vantagem:** o `if ($eEmpresaAplicacao)` vive numa única classe, e não está espalhado por 3
callers (`CriarEntidadeAction`, `ActualizarEntidadeAction`, `ConverterEmEmpresaMaeAction`).

### 2. Trait `ComFlagsEfectivosEmpresaMae` nos DTOs

Os campos `e_cliente` e `e_fornecedor` têm uma invariante: quando `eEmpresaAplicacao = true`,
ambos devem ser `true` independentemente do que o utilizador enviar.

Solução: trait com dois métodos — `eClienteEfectivo()` e `eFornecedorEfectivo()` — que encapsulam
`$this->eEmpresaAplicacao || $this->eCliente`. Os DTOs usam estes métodos ao persistir, e as Actions
não precisam de saber desta regra.

**Vantagem:** a invariante é respeitada em criar e actualizar sem duplicação.

### 3. `ConverterEmEmpresaMaeAction` sem DTO

A operação não recebe body: só o UUID via RMB. O Controller passa directamente a `$entidade`
(já resolvida pelo Route Model Binding). O FormRequest serve apenas para autorização.

### 4. Padrão dual de testes documentado em `docs/conventions/`

Ao implementar os testes da Issue #40, o padrão dual (Unit programático + Feature HTTP) tornou-se
suficientemente estável para merecer um ficheiro de convenções próprio. Criado
`docs/conventions/tests-dual-pattern.md` e actualizado `CLAUDE.md` com as regras detalhadas.

---

## O que correu bem

- A estrutura de `CategoriaDocumento` (Issues #5, #9, #25) funcionou como template exacto — o
  desvio de design para a regra da Empresa Mãe foi o único ponto de fricção.
- Larastan nível 9 passou sem surpresas: os array shapes nos `fromRequest()` e a tipagem do
  `CursorPaginator` em `ListarEntidadesAction` foram os únicos pontos a ajustar.
- O índice parcial único (`e_empresa_aplicacao = true`) em SQLite requereu a cláusula `WHERE`
  — já estava previsto na migration da Issue #27.

---

## O que correu menos bem / dívida técnica

- **`RemoverMarcacaoEmpresaMaeAction` não é `final`:** o ArchTest `arch('actions are final')` foi
  alargado com um `ignoring` para esta action. A razão é que em testes unitários de
  `RegraUnicidadeEmpresaMae`, é útil mocká-la. Alternativa futura: interface `RemoveEmpresaMaeActionInterface`.
- **Race condition não tratada:** dois requests simultâneos a criar Empresa Mãe podem atingir
  a transação sem o índice único ter sido violado ainda. O `UniqueConstraintViolationException`
  propaga-se como 500. Tratamento global no handler: Issue futura.

---

## Aprendizagens (Vertical Slice / Actions / PHP 8.5)

### 1. A "action interna" não é apenas código partilhado — é uma fronteira de autorização

`RemoverMarcacaoEmpresaMaeAction` não tem `Gate::authorize()` porque é chamada **dentro** de uma
transação de uma Action já autorizada. Esta distinção — entre Actions públicas (com autorização) e
Actions internas (sem) — não estava explícita no CLAUDE.md antes desta issue. É um padrão que vai
aparecer noutras features com lógica de domínio partilhada entre actions.

### 2. `DB::transaction()` e `Gate::authorize()` têm contextos diferentes

Aprendi a ver `Gate::authorize()` como "portão antes da BD" e `DB::transaction()` como "bloco
atómico de persistência". Nunca entram um dentro do outro — o authorize fica **sempre** fora.
Isto ficou mais claro quando se implementaram 3 Actions de escrita que partilham a mesma sub-action.

### 3. Classes de domínio vs. Actions

A distinção entre `RegraUnicidadeEmpresaMae` (classe de domínio — aplica uma regra, sem acesso
HTTP) e `ConverterEmEmpresaMaeAction` (Action — tem autorização, abre transação, devolve entidade)
clarificou que o sufixo `Action` não deve ser usado para encapsular apenas lógica de negócio
pura. Actions têm um contrato mais largo: autorização + transação + retorno de dados. Classes de
regra não têm este contrato.

### 4. Trait como encapsulamento de invariante (não de comportamento UI)

O uso de `ComFlagsEfectivosEmpresaMae` nos DTOs mostrou que traits em PHP 8.5 são uma boa
ferramenta para invariantes transversais a vários DTOs relacionados. A alternativa (método
estático num helper) seria menos coesa. A alternativa (herança) seria demasiado rígida.

---

## Métricas

- **Tests:** 170 passed, 0 failed
- **Coverage:** 100%
- **Type coverage:** 100%
- **Larastan:** 0 erros (nível 9)
- **Rector:** 0 sugestões
- **Commits nesta branch:** 14
