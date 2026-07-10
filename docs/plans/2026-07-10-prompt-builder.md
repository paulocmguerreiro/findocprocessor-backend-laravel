# Plano: PromptBuilder — construção do system prompt de extracção via IA

**Issue:** #88
**Spec:** docs/specs/2026-07-10-prompt-builder.md
**Data:** 2026-07-10

## Tarefas

### Tarefa 1 — `base_instructions.txt` + scaffold `PromptBuilder` (`comInstrucoesBase()` + `construir()`)

- Ficheiros a criar:
  - `app/Shared/Prompts/base_instructions.txt`
  - `app/Infrastructure/AI/PromptBuilder.php`
- O que implementar:
  - `base_instructions.txt` — conteúdo definido na issue #88 (isolamento de conteúdo I-IV, regras absolutas 1-7 incl. "desconhecido"/"perigoso").
  - `PromptBuilder` (`final class`, `strict_types=1`): propriedade `private ?string $instrucoesBase = null;` e `private array $segmentosAdicionais = [];` (`@var list<string>`).
  - `public static function novo(): self { return new self(); }`
  - `public function comInstrucoesBase(): self` — `$this->instrucoesBase = file_get_contents(app_path('Shared/Prompts/base_instructions.txt'));` (lançar `\RuntimeException` se `file_get_contents` devolver `false`); `@throws \RuntimeException`.
  - `public function construir(): string` — se `$this->instrucoesBase === null`, lançar `\LogicException('comInstrucoesBase() tem de ser chamado antes de construir().')`; caso contrário, `trim($this->instrucoesBase . "\n\n" . implode("\n\n", $this->segmentosAdicionais))`. `@throws \LogicException`.
- Testes associados:
  - `tests/Unit/Infrastructure/AI/PromptBuilderTest.php` — `construir()` sem `comInstrucoesBase()` lança `\LogicException`; `comInstrucoesBase()->construir()` devolve exactamente o conteúdo do ficheiro (trim); `PromptBuilder::novo()` devolve instância própria (fluente).
- Commit: `feat(prompt-builder): adicionar scaffold PromptBuilder + base_instructions.txt`

### Tarefa 2 — `comEmpresaMae()`

- Ficheiros a alterar:
  - `app/Infrastructure/AI/PromptBuilder.php`
- O que implementar:
  - `public function comEmpresaMae(): self` — `$empresaMae = Entidade::whereEmpresaAplicacao()->first();` (scope já existente em `app/Models/Entidade.php`); se `null`, lançar `\RuntimeException('Nenhuma Entidade está marcada como empresa aplicação (e_empresa_aplicacao).')` (RN-02); caso contrário, `$this->segmentosAdicionais[] = "EMPRESA MÃE:\nNome: {$empresaMae->nome}\nNIF: {$empresaMae->nif}\n..."` (redacção exacta a decidir na implementação, incluir instrução equivalente às antigas regras 6-8 do prompt Python, adaptada a texto dinâmico). `@throws \RuntimeException`.
- Testes associados:
  - `tests/Unit/Infrastructure/AI/PromptBuilderTest.php` — `comEmpresaMae()` com `Entidade::factory()->empresaAplicacao()->create()` (state já existente em `EntidadeFactory`) injecta nome/NIF no texto final; `comEmpresaMae()` sem nenhuma `Entidade` empresa-aplicação lança `\RuntimeException`; ordem — chamado antes/depois de `comInstrucoesBase()` produz sempre `instrucoesBase` primeiro (prova RN-01).
- Commit: `feat(prompt-builder): adicionar comEmpresaMae()`

### Tarefa 3 — `comTodosTiposDocumento()` + `comCategoria()`

- Ficheiros a alterar:
  - `app/Infrastructure/AI/PromptBuilder.php`
- O que implementar:
  - `private CategoriaDocumento|string|null $filtroCategoria = null;`
  - `public function comCategoria(CategoriaDocumento|string $idCategoria): self` — normaliza para UUID string (`$idCategoria instanceof CategoriaDocumento ? $idCategoria->id : $idCategoria`), guarda em `$this->filtroCategoria`; retorna `$this`.
  - `public function comTodosTiposDocumento(): self` — `TipoDocumento::query()->with('categoria')->when($this->filtroCategoria, fn (Builder $q, string $id) => $q->where('id_categoria', $id))->get()`; gera dois segmentos de texto (adicionados a `$this->segmentosAdicionais`, pela ordem: Passo 1 primeiro, Passo 2 depois):
    - **Passo 1 — Classificação**: uma linha por `TipoDocumento`, `- "{$tipo->categoria->slug}" → {$tipo->nome}: {$tipo->descricao}` (nota: `categoria` pode ser `null` se `id_categoria` alguma vez ficar orfão — não deve acontecer dado `restrictOnDelete()`, mas Larastan exige tratar o tipo nullable da relação `BelongsTo`; usar `$tipo->categoria?->slug ?? 'sem-categoria'` defensivamente).
    - **Passo 2 — Campos a extrair por tipo**: para cada `TipoDocumento`, lista os campos com `espera_* = true` mapeados para os nomes JSON (RN-04) — `- {$tipo->nome}: data_documento, fornecedor, valor` (omitir campos com flag `false`; se todos `false`, nunca acontece por RN-02 do `TipoDocumento`, mas o código não deve assumir isso — usar `array_filter`).
  - `@throws` não aplicável (sem excepções lançadas neste método, além das herdadas de Eloquent).
- Testes associados:
  - `tests/Unit/Infrastructure/AI/PromptBuilderTest.php` — `comTodosTiposDocumento()` sem filtro inclui todos os `TipoDocumento`; com `comCategoria()` chamado antes, filtra correctamente; com `comCategoria()` chamado **depois**, não filtra retroactivamente (CA-11, RN-03); Passo 1 e Passo 2 contêm os textos esperados (nome, descrição, slug da categoria, campos `espera_*` correctos); `TipoDocumento` com todos `espera_*` a `false` não é criável via factory (RN-02 do DTO) — não testar esse caso, é impossível por construção.
- Commit: `feat(prompt-builder): adicionar comTodosTiposDocumento() + comCategoria()`

### Tarefa 4 — ArchTest + SYSTEM_SPEC

- Ficheiros a alterar/criar:
  - `tests/ArchTest.php` (alterar — nova regra Arch)
  - `docs/system_spec/04-infra/prompt-builder.md` (novo)
  - `docs/system_spec/04-infra/external-apis.md` (alterar — sai de "pendente")
  - `docs/system_spec/00-index.md` (alterar — nova linha em Infra)
- O que implementar:
  - `tests/ArchTest.php` — `arch('infrastructure classes are final')->expect('App\Infrastructure')->toBeFinal();` (namespace novo, sem excepções previstas).
  - `docs/system_spec/04-infra/prompt-builder.md` — documenta `PromptBuilder` (métodos, RN-01 a RN-05), localização e conteúdo de `base_instructions.txt`, exemplo de output de `construir()`.
  - `docs/system_spec/04-infra/external-apis.md` — actualizar tabela "Integrações planeadas": `PromptBuilder` implementado (link para `prompt-builder.md`); cliente de API (Ollama/OpenRouter/Anthropic) continua pendente.
  - `docs/system_spec/00-index.md` — linha nova na tabela "Infra": `PromptBuilder | 04-infra/prompt-builder.md | implementado`.
- Testes associados: nenhum novo (arch test corre em `composer test:arch`).
- Commit: `docs(prompt-builder): actualizar system_spec + ArchTest App\Infrastructure`

## Ordem de implementação

1. Tarefa 1 — scaffold + `comInstrucoesBase()`/`construir()`, porque `construir()` e a excepção RN-05 são a base testada por todas as tarefas seguintes.
2. Tarefa 2 — `comEmpresaMae()`, porque é independente de `TipoDocumento`/`CategoriaDocumento` e mais simples (uma única Entidade).
3. Tarefa 3 — `comTodosTiposDocumento()` + `comCategoria()`, porque depende do scaffold da Tarefa 1 e reforça o teste de ordenação (RN-01) já coberto na Tarefa 2.
4. Tarefa 4 — ArchTest + documentação, porque só faz sentido depois da classe estar completa e estável.

## Testes a escrever

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| `construir()` sem `comInstrucoesBase()` | unit | `PromptBuilderTest.php` | `\LogicException` (RF-07/RN-05) |
| `comInstrucoesBase()->construir()` | unit | `PromptBuilderTest.php` | conteúdo do `.txt` presente no output |
| `comEmpresaMae()` com Entidade válida | unit | `PromptBuilderTest.php` | nome/NIF presentes no output |
| `comEmpresaMae()` sem Entidade empresa-aplicação | unit | `PromptBuilderTest.php` | `\RuntimeException` (RN-02) |
| Ordem — `comInstrucoesBase()` sempre primeiro | unit | `PromptBuilderTest.php` | RN-01, independente da ordem de chamada |
| `comTodosTiposDocumento()` sem filtro | unit | `PromptBuilderTest.php` | todos os `TipoDocumento` presentes (Passo 1 + Passo 2) |
| `comCategoria()` antes de `comTodosTiposDocumento()` | unit | `PromptBuilderTest.php` | filtra correctamente (RF-06) |
| `comCategoria()` depois de `comTodosTiposDocumento()` | unit | `PromptBuilderTest.php` | não filtra retroactivamente (CA-11/RN-03) |
| Passo 2 — campos `espera_*=false` omitidos | unit | `PromptBuilderTest.php` | RN-04 |
| Arch — `App\Infrastructure` classes `toBeFinal()` | arch | `ArchTest.php` | CA-12 |

## Dependências

- Issues bloqueantes: nenhuma
- Deve ser implementada após: #84, #85 (`TipoDocumento`), #69-#72 (`Entidade`) — todas já fechadas

## Riscos de implementação

> Consolidados do Brief (`## Riscos identificados`) e da Spec.

- Divisão entre texto fixo (`base_instructions.txt`) e texto gerado dinamicamente — verificar no Checkpoint da Tarefa 1 que o `.txt` não duplica nada do que `comEmpresaMae()`/`comTodosTiposDocumento()` vão gerar.
- `Entidade::whereEmpresaAplicacao()->first()` pode devolver `null` em BD "vazia" (ambiente de testes sem seed) — RN-02 cobre isto com `\RuntimeException`, mas a Tarefa 2 tem de testar ambos os casos explicitamente.
- `TipoDocumento->categoria` pode ser `null` em teoria (embora `restrictOnDelete()` deva impedir isto na prática) — tratar defensivamente na Tarefa 3 (Larastan nível 9 exige).
- `App\Infrastructure` é namespace novo sem precedente no projecto — a Tarefa 4 tem de confirmar que `composer test:arch` não tem falsos positivos ao adicionar a nova regra (ex.: se o autoload não estiver a apanhar o namespace, o Arch test passa vazio sem detectar nada — validar manualmente com `--filter`).

## O que NÃO fazer nesta issue

- Não implementar `withDocumento()`/wrapping em `<nonce>` — issue futura.
- Não implementar cliente de API de IA (Ollama/OpenRouter/Anthropic), não fazer chamadas HTTP externas.
- Não adicionar schema JSON estruturado por campo a `TipoDocumento` — usar apenas `descricao` + `espera_*`.
- Não criar Controller, FormRequest, rota ou Resource — sem endpoint HTTP nesta issue.
- Não adicionar Repository/interface para `PromptBuilder` — classe concreta final.
