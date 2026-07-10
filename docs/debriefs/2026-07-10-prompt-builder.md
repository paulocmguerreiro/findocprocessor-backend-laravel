# Debrief: PromptBuilder — construção do system prompt de extracção via IA

**Issue:** #88
**Branch:** feat/prompt-builder
**Data:** 2026-07-10
**Commits:** 6 commits (1 de planeamento + 5 de implementação/documentação)

## O que foi implementado

- `App\Infrastructure\AI\PromptBuilder` (`final class`, `strict_types=1`) — API fluente estática: `novo()`, `comInstrucoesBase()`, `comEmpresaMae()`, `filtrarPorCategoria()`, `comTiposDocumento()`, `construir()`.
- `app/Shared/Prompts/base_instructions.txt` — texto-base estático com isolamento de conteúdo (regras I-IV), regras absolutas (1-7) e os casos "desconhecido"/"perigoso".
- Secções dinâmicas geradas a partir de `Entidade` (empresa mãe) e `TipoDocumento`/`CategoriaDocumento` (classificação + campos a extrair), com filtro opcional por categoria.
- Nova regra Arch `App\Infrastructure` `toBeFinal()`, validada manualmente (confirmado que detecta uma classe não-`final`).
- `docs/system_spec/04-infra/prompt-builder.md` (novo), `external-apis.md` (sai de "pendente") e `00-index.md` actualizados.

## Ficheiros alterados

| Ficheiro | Tipo de alteração | Notas |
| -------- | ----------------- | ----- |
| `app/Infrastructure/AI/PromptBuilder.php` | criado | classe final, 6 métodos públicos |
| `app/Shared/Prompts/base_instructions.txt` | criado | texto estático, conteúdo definido na issue #88 |
| `tests/Unit/Infrastructure/AI/PromptBuilderTest.php` | criado | 11 testes, único par de testes (sem par HTTP) |
| `tests/ArchTest.php` | alterado | nova regra `infrastructure classes are final` |
| `docs/system_spec/04-infra/prompt-builder.md` | criado | doc da classe, RN-01 a RN-05, exemplo de output |
| `docs/system_spec/04-infra/external-apis.md` | alterado | sai de "pendente", passa a "parcial" |
| `docs/system_spec/00-index.md` | alterado | nova linha em Infra |
| `docs/briefs/`, `docs/specs/`, `docs/plans/2026-07-10-prompt-builder.md` | criados | artefactos da Fase 1 (`/planeia-issue`) |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| `comCategoria()` → `filtrarPorCategoria()` | Manter `comCategoria()` (nome do Plano/issue original) | Feedback do utilizador durante o Checkpoint da Tarefa 3: `comCategoria` não deixava claro que era um filtro, e `whereCategoria` mistura PT/EN. `filtrarPorCategoria` é inequívoco. |
| `comTodosTiposDocumento()` → `comTiposDocumento()` | Manter `comTodosTiposDocumento()` (nome do Plano/issue original) | Mesmo feedback: "Todos" era enganador quando `filtrarPorCategoria()` já tinha sido chamado antes — o método passa a incluir só o subconjunto filtrado, nunca "todos". Comportamento mantido: o filtro só tem efeito se chamado **antes** (RN-03), a mudança foi só de nome. |
| `comInstrucoesBase()` usa `Illuminate\Support\Facades\File::get()` em vez de `file_get_contents()` bruto | Sobrepor a função global `file_get_contents()` por namespace num ficheiro de teste dedicado | A sobreposição por namespace funciona isolada, mas "vaza" para outros ficheiros de teste executados no mesmo processo PHP (confirmado experimentalmente — quebrou os restantes testes de `PromptBuilderTest` ao correr sem `--parallel`). `File::get()` lança `FileNotFoundException`, mockável via Facade sem efeitos colaterais nem risco de poluição entre testes. |
| `$tipo->categoria === null ? 'sem-categoria' : $tipo->categoria->slug` em vez de `$tipo->categoria?->slug ?? 'sem-categoria'` | Nullsafe + `??` (mais conciso) | Larastan nível 9 sinaliza o padrão `?->prop ?? default` como `nullsafe.neverNull` (regra de estilo). A verificação explícita `=== null` produz o mesmo resultado sem violar o nível 9. |

## Desvios ao Plano

- Nomes de dois métodos alterados após a Tarefa 3 (ver "Decisões tomadas") — comportamento e assinatura (`CategoriaDocumento|string`) mantidos, só a nomenclatura mudou. Testes e `system_spec` já reflectem os nomes novos.
- `comInstrucoesBase()` implementado com `File::get()` em vez de `file_get_contents()` directo (o Plano especificava `file_get_contents(app_path(...))` explicitamente) — mudança motivada por testabilidade do caminho de erro sob a exigência de 100% code coverage do projecto (`RNF-03`), sem alterar o contrato público do método (`@throws \RuntimeException` mantido).

## Aprendizagens

O caso mais instrutivo desta issue foi o requisito de RN-03 ("`filtrarPorCategoria()` só tem efeito se chamado antes de `comTiposDocumento()`") combinado com um builder fluente: como o filtro é resolvido no momento em que `comTiposDocumento()` executa a query (não em `construir()`), a ordem de chamada tem semântica real, não é só estilo — isto é diferente do padrão habitual do Eloquent Query Builder, onde a ordem de `where()`s normalmente não importa para o resultado final. Documentar isto explicitamente em `04-infra/prompt-builder.md` e testar os dois sentidos (antes/depois) foi essencial para não deixar essa pegadinha implícita.

Também ficou mais claro o limite prático de "100% code coverage" em Infrastructure: nem todo o caminho defensivo (`RuntimeException` numa leitura de ficheiro que falha) é trivial de cobrir sem tocar no disco real ou recorrer a truques frágeis (sobreposição de funções globais por namespace, que só funciona com isolamento de processo). Preferir uma abstracção já testável do framework (`File` facade) em vez de PHP puro (`file_get_contents`) resolve isto de forma nativa ao ecossistema Laravel, sem introduzir Repository/interface desnecessário para uma classe que continua a ser leitura pura sem estado.

## SYSTEM_SPEC a actualizar

- `docs/system_spec/04-infra/prompt-builder.md` — já criado nesta implementação (Tarefa 4)
- `docs/system_spec/04-infra/external-apis.md` — já actualizado nesta implementação (Tarefa 4)
- `docs/system_spec/00-index.md` — já actualizado nesta implementação (Tarefa 4)

## Verificação final

- [x] Linter a verde (Pint + Rector)
- [x] Testes a verde (864/864, 100% type coverage, 100% code coverage, Larastan nível 9)
- [x] Nenhum dado sensível em logs
- [x] Nenhum segredo em código
