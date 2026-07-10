# Brief: PromptBuilder — construção do system prompt de extracção via IA

**Issue:** #88
**Data:** 2026-07-10
**Branch:** feat/prompt-builder

## Contexto

A camada de modelo para classificação de documentos financeiros já existe: `TipoDocumento` (nome, `descricao`, `id_categoria`, `posicao_empresa_mae`, 4 flags `espera_*`), `CategoriaDocumento` (nome, slug, `tipo_movimento`) e `Entidade` (com flag `e_empresa_aplicacao` a identificar a empresa mãe). Falta o mecanismo que transforma estes registos + um conjunto de instruções fixas num **system prompt** de texto, pronto a ser usado por um provider de IA (Ollama local, OpenRouter, Anthropic) para classificar e extrair dados de documentos financeiros. Esta issue cobre apenas a *construção* deste prompt — o envio à API, a chamada ao provider e o parsing da resposta ficam para uma issue futura (`docs/system_spec/04-infra/external-apis.md`, actualmente "pendente").

O mecanismo é modelado como builder fluente (estilo Eloquent Query Builder), decisão do utilizador para permitir compor o prompt por partes e acrescentar helpers no futuro sem alterar a assinatura pública.

## O que muda

- Nova classe `App\Infrastructure\AI\PromptBuilder` (novo namespace `App\Infrastructure`, primeira classe nele).
- Novo ficheiro estático `app/Shared/Prompts/base_instructions.txt` — texto fixo (isolamento de conteúdo, regras absolutas, casos "desconhecido"/"perigoso"), independente de dados em BD.
- `PromptBuilder` lê `TipoDocumento::with('categoria')->get()` e `Entidade::whereEmpresaAplicacao()->first()` (scope já existente no Model) para gerar as secções dinâmicas do prompt (empresa mãe, Passo 1 — classificação, Passo 2 — campos a extrair por tipo).
- `docs/system_spec/04-infra/external-apis.md` passa de "pendente" para conteúdo real, documentando o `PromptBuilder`.
- `docs/system_spec/00-index.md` — nova linha em Infra ou Features apontando para a documentação do `PromptBuilder`.

## O que NÃO muda

- Sem envolvimento do documento em tags `<nonce>` — sem `withDocumento()` ou equivalente nesta issue.
- Sem chamada real a qualquer provider de IA (Ollama/OpenRouter/Anthropic), sem cliente HTTP, sem parsing de resposta — issue futura.
- Sem novo schema JSON estruturado por campo em `TipoDocumento` (ex.: `numero_fatura`, `iva`, `itens[]`) — usa-se apenas `descricao` (texto livre) + os 4 `espera_*` já existentes.
- Sem alteração aos Models `TipoDocumento`, `CategoriaDocumento`, `Entidade`, `Documento` — leitura apenas, nenhuma migration nova.
- Sem endpoint HTTP, sem Controller, sem rota — mecanismo interno, sem par HTTP no padrão dual de testes.
- Sem interface/contrato (`ContratoPromptBuilder` ou similar) — classe concreta `final`, decisão intencional (não há mais que uma implementação plausível do algoritmo; a variação está no consumidor do prompt, issue futura).
- Sem `Gate::authorize()` nem `DB::transaction()` — não é uma Action de negócio, é leitura pura sem efeitos secundários.

## Riscos identificados

- **Divisão entre texto fixo e texto gerado dinamicamente**: se a fronteira entre `base_instructions.txt` (estático) e as secções geradas por `PromptBuilder` (dinâmicas, a partir de `TipoDocumento`) não ficar bem definida na Spec, há risco de duplicar informação (ex.: regras de classificação hardcoded no `.txt` E geradas dinamicamente) ou de esquecer de propagar `posicao_empresa_mae`/`espera_*` para o texto final.
- **Ordem de composição do prompt**: como a API é fluente e o utilizador pode chamar os métodos em qualquer ordem (`comEmpresaMae()` antes ou depois de `comTodosTiposDocumento()`), a Spec tem de fixar se `construir()` respeita a ordem de chamada ou impõe uma ordem fixa interna (instruções base → empresa mãe → classificação). Ordem errada pode gerar um prompt semanticamente correcto mas didacticamente confuso para o modelo de IA (impacto funcional real, não cosmético).
- **`Entidade::whereEmpresaAplicacao()->first()` pode devolver `null`** se não existir nenhuma `Entidade` marcada como empresa aplicação (estado de BD válido antes da configuração inicial) — `comEmpresaMae()` precisa de decidir explicitamente o comportamento (omitir secção silenciosamente vs. lançar excepção), decisão sem precedente no projecto porque nenhuma Action actual depende deste scope para produzir output determinístico.
- **Ficheiro `.txt` fora de `app/Features`**: `App\Shared\Prompts` e `App\Infrastructure\AI` são namespaces novos, não cobertos pela regra Arch `actions are final` (que só cobre `App\Features`) nem por nenhuma outra regra Arch existente em `tests/ArchTest.php`. Sem uma regra Arch nova, nada impede que `PromptBuilder` deixe de ser `final` no futuro sem detecção automática — decidir na Spec se vale a pena estender `tests/ArchTest.php`.
- **Teste sem endpoint HTTP**: é a primeira feature/mecanismo do projecto sem nenhum par HTTP no padrão dual de testes (`07-testing.md`). O desvio tem de ficar explícito na Spec para não ser lido como omissão.

## Questões em aberto

- **Comportamento de `construir()` sem nenhuma secção configurada** (CA-06 da issue deixa em aberto): lançar `\LogicException`/`\InvalidArgumentException`, ou devolver string vazia? Proposta para a Spec: lançar excepção — um `PromptBuilder` sem `comInstrucoesBase()` chamado nunca é um uso válido, e falhar cedo evita enviar um prompt vazio/incompleto a um provider de IA no futuro.
- **Formato exacto do bloco "Passo 2 — Campos a extrair por tipo"** (a issue só fixa a fonte de dados — os 4 `espera_*` — não o formato de texto): lista de nomes de campo por tipo, ou um JSON de exemplo por tipo? Proposta para a Spec: lista textual simples (`- <tipo_documento.nome>: data_documento, fornecedor, valor` — omitindo os campos com `espera_* = false`), mais legível para o modelo de IA do que JSON aninhado e mais simples de gerar/testar.
- **Ordem interna de composição** (ver Riscos): propor fixar ordem interna determinística em `construir()` (instruções base → empresa mãe → classificação), independentemente da ordem em que os métodos fluentes foram chamados — simplifica a implementação e o teste, e evita prompts com ordem inconsistente conforme o consumidor.
