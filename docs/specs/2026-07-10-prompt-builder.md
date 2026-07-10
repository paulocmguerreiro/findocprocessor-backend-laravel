# Spec: PromptBuilder — construção do system prompt de extracção via IA

**Issue:** #88
**Brief:** docs/briefs/2026-07-10-prompt-builder.md
**Data:** 2026-07-10

## Requisitos funcionais

- RF-01: `App\Infrastructure\AI\PromptBuilder` expõe entrada estática `PromptBuilder::novo(): self`.
- RF-02: `comInstrucoesBase(): self` lê `app/Shared/Prompts/base_instructions.txt` (via `file_get_contents(app_path('Shared/Prompts/base_instructions.txt'))`) e fixa esse conteúdo como âncora inicial do prompt final — independente da posição em que o método é chamado na chain.
- RF-03: `comEmpresaMae(): self` obtém `Entidade::whereEmpresaAplicacao()->first()` e acrescenta um segmento de texto com nome/NIF reais da empresa mãe.
- RF-04: `comTodosTiposDocumento(): self` carrega `TipoDocumento::with('categoria')->get()` (ou filtrado, ver RF-06) e acrescenta dois segmentos de texto: "Passo 1 — Classificação" e "Passo 2 — Campos a extrair por tipo".
- RF-05: `construir(): string` concatena o conteúdo de `comInstrucoesBase()` (sempre primeiro) com os segmentos acrescentados pelos restantes métodos, pela ordem em que esses métodos foram chamados.
- RF-06: `comCategoria(CategoriaDocumento|string $idCategoria): self` regista um filtro de categoria; quando definido, `comTodosTiposDocumento()` restringe a query a `TipoDocumento` cuja `categoria.id` corresponda. `comCategoria()` tem de ser chamado antes de `comTodosTiposDocumento()` para ter efeito (ver RN-03).
- RF-07: `construir()` lança `\LogicException` se `comInstrucoesBase()` nunca tiver sido chamado.

## Requisitos não funcionais

- RNF-01: `PromptBuilder` é `final class`, `strict_types=1`, sem interface — decisão intencional documentada no Brief.
- RNF-02: Leitura apenas (sem escrita em BD) — sem `DB::transaction()`, sem `Gate::authorize()`.
- RNF-03: 100% type coverage e 100% code coverage (sem `mixed`; `@throws` declarado em `comInstrucoesBase()` e `construir()`).

## Contratos de API (se aplicável)

Não aplicável — sem endpoint HTTP, sem Controller, sem rota.

## Modelo de dados (se aplicável)

Não aplicável — sem migration nova, sem alteração a Models existentes. `PromptBuilder` é leitura pura sobre `TipoDocumento`, `CategoriaDocumento` (via `->categoria`) e `Entidade` (via scope `whereEmpresaAplicacao`), todos já existentes.

## Regras de negócio

- RN-01: O texto de `app/Shared/Prompts/base_instructions.txt` é sempre o primeiro segmento do prompt final, independentemente da ordem de chamada dos métodos fluentes — único método com posição fixa; todos os outros acrescentam informação após ele, pela ordem de chamada.
- RN-02: Se `Entidade::whereEmpresaAplicacao()->first()` devolver `null` (nenhuma entidade marcada como empresa aplicação), `comEmpresaMae()` lança `\RuntimeException` — estado de configuração inválido, não deve produzir silenciosamente um prompt sem dados da empresa mãe (que geraria classificações erradas a jusante, ver regras 6-8 do prompt original).
- RN-03: `comCategoria()` só tem efeito se chamado **antes** de `comTodosTiposDocumento()` — a query de `TipoDocumento` é resolvida no momento em que `comTodosTiposDocumento()` executa, não em `construir()`. Chamar `comCategoria()` depois de `comTodosTiposDocumento()` não altera o segmento já gerado (comportamento a testar explicitamente, CA-11).
- RN-04: Secção "Passo 2 — Campos a extrair por tipo" — para cada `TipoDocumento`, lista textual simples dos campos com `espera_* = true`, mapeados para os nomes de campo JSON esperados na resposta da IA (`espera_data_documento`→`data_documento`, `espera_fornecedor`→`fornecedor`, `espera_cliente`→`cliente`, `espera_valor`→`valor`); campos com `espera_* = false` são omitidos da lista (não listados como `null` no texto — o `null` é regra de resposta da IA quando o campo é esperado mas ausente no documento, não quando o campo nem é esperado).
- RN-05: `construir()` sem `comInstrucoesBase()` chamado lança `\LogicException` com mensagem clara (ex.: `"comInstrucoesBase() tem de ser chamado antes de construir()."`).

## Dependências

- Issues bloqueantes: nenhuma (usa `TipoDocumento` #84/#85, `CategoriaDocumento` #70/#72, `Entidade` #69/#71 — todas fechadas)

## Questões resolvidas

| Questão (do Brief) | Decisão |
| ------------------ | ------- |
| Comportamento de `construir()` sem nenhuma secção configurada | Lança `\LogicException` (RF-07/RN-05) |
| Formato do bloco "Passo 2 — Campos a extrair por tipo" | Lista textual simples por tipo, campos `espera_*=false` omitidos (RN-04) |
| Ordem interna de composição do prompt | Ordem de chamada dos métodos, com `comInstrucoesBase()` sempre fixo em primeiro lugar (RN-01) — não é uma ordem interna fixa para todas as secções, é uma âncora única + acumulação por ordem de chamada |

## Critérios de aceitação

> Herdados da issue — nunca remover ou reformular os CAs originais sem justificação.

- [ ] CA-01: Classe `App\Infrastructure\AI\PromptBuilder` (`final`, `strict_types=1`) com API fluente estática *(issue)*
- [ ] CA-02: `comInstrucoesBase()` lê `app/Shared/Prompts/base_instructions.txt` *(issue)*
- [ ] CA-03: `comEmpresaMae()` obtém `Entidade::whereEmpresaAplicacao()->first()` e injecta nome/NIF reais *(issue)*
- [ ] CA-04: `comTodosTiposDocumento()` gera secções "Passo 1 — Classificação" e "Passo 2 — Campos a extrair por tipo" *(issue)*
- [ ] CA-05: `comCategoria(CategoriaDocumento|string $idCategoria)` filtra os `TipoDocumento` incluídos *(issue)*
- [ ] CA-06: `construir()` concatena as secções configuradas e devolve `string`; lança `\LogicException` se `comInstrucoesBase()` nunca foi chamado *(issue, resolvido pela Spec)*
- [ ] CA-07: `app/Shared/Prompts/base_instructions.txt` inclui isolamento de conteúdo, regras absolutas, casos "desconhecido" e "perigoso" (conteúdo definido no Brief/issue) *(issue)*
- [ ] CA-08: Testes unitários `tests/Unit/Infrastructure/AI/PromptBuilderTest.php` cobrindo cada secção isoladamente e a composição final; sem par HTTP (desvio documentado) *(issue)*
- [ ] CA-09: `docs/system_spec/04-infra/external-apis.md` actualizado, sai de "pendente" *(issue)*
- [ ] CA-10: `comEmpresaMae()` lança `\RuntimeException` se não existir `Entidade` com `e_empresa_aplicacao = true` *(spec, RN-02)*
- [ ] CA-11: Teste prova que `comCategoria()` chamado depois de `comTodosTiposDocumento()` não filtra retroactivamente o segmento já gerado *(spec, RN-03)*
- [ ] CA-12: `tests/ArchTest.php` estende cobertura a `App\Infrastructure` (`toBeFinal()`) para detectar automaticamente se `PromptBuilder` deixar de ser `final` *(spec, risco do Brief)*

## SYSTEM_SPEC a actualizar

- `docs/system_spec/04-infra/external-apis.md` — sai de "pendente"; documenta `PromptBuilder`, localização do texto-base, e que o cliente de API fica para issue futura
- Ficheiro novo `docs/system_spec/04-infra/prompt-builder.md` (mecanismo novo, sem feature slice HTTP associada — cabe melhor em `04-infra` que em `01-features`) — documenta a classe, os métodos fluentes, RN-01 a RN-05, e o conteúdo/localização de `base_instructions.txt`
- `docs/system_spec/00-index.md` — nova linha em "Infra" apontando para `04-infra/prompt-builder.md`

## Verificação RGPD/NIS2

- Dados pessoais: o prompt gerado inclui nome/NIF da empresa mãe (`Entidade.e_empresa_aplicacao`) — dado empresarial, não de pessoa singular; sem dados de documentos, fornecedores ou clientes nesta issue
- Superfície de ataque: inalterada — sem novo endpoint HTTP, sem input de utilizador; `base_instructions.txt` é ficheiro estático versionado em `app/Shared/Prompts/`, fora de `public/`, sem acesso HTTP directo
