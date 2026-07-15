# Skill: executa-triagem-semantica

Executa uma revisão semântica — nomenclatura, legibilidade/duplicação, nomenclatura de interfaces —
que nenhuma ferramenta automática (Pint/Rector/Larastan/`checkpoint:scan`) cobre, porque exige leitura
de intenção, não sintaxe. Não tem checklist fixo: lê os ficheiros de `docs/system_spec/` relevantes
para o tipo de ficheiro em causa **em tempo real**, para nunca ficar desactualizada quando o spec
ganha uma regra nova.

> **Categoria:** executa
> **Usado em:** `/implementa-plano` (`alvo=tarefa-planeada` antes de Implementar; `alvo=codigo` por
> tarefa, antes do checkpoint) · `/planeia-issue` (`alvo=plano`, depois de `escreve-plan`)
> **Produz:** relatório ✅ limpo, informação de contexto (sem correcção), ou violações corrigidas

## Contrato

**Input:** `alvo`: `tarefa-planeada` | `codigo` | `plano`

**Output:**
- `tarefa-planeada` / `plano` → nenhuma correcção; só informa o contexto de escrita/planeamento
- `codigo` → relatório ✅ limpo, ou lista de violações corrigidas antes do checkpoint

---

## Tabela — tipo de ficheiro → specs a consultar

| Tipo de ficheiro | Specs a ler |
|---|---|
| Model (`app/Models/*.php`) | `03-models/00-convencoes-models.md` |
| Action (`*Action.php`) | `02-shared/padroes-acoes.md` (+ `02-shared/estados.md` se envolver `Documento`) |
| Interface (`Contrato*.php`) | `02-shared/contratos-por-camada.md` |
| DTO / Value Object | `02-shared/padroes-dtos.md` |
| Repository (`*Repository.php`) | `04-infra/repositories.md` |
| Job (`app/Jobs/*.php`) | `04-infra/queue-jobs.md` |
| Migration (`database/migrations/*.php`) | `03-models/00-convencoes-models.md` |
| *(qualquer ficheiro PHP)* | `02-shared/convencoes-nomenclatura.md` + `02-shared/padroes-tipagem.md` — **sempre**, nomenclatura e tipagem são transversais |

Se um tipo de ficheiro não constar da tabela (categoria nova no repositório), acrescentar uma linha
antes de prosseguir — não inventar regra sem fonte.

---

## Comportamento por `alvo`

### `alvo=tarefa-planeada` (leve — antes de "Implementar")

1. Ler o título/descrição da tarefa actual no Plano e inferir que tipo(s) de ficheiro vai criar/alterar
   (ex.: "Criar `CriarCategoriaAction`" → Action).
2. Carregar (`Read`) só os specs correspondentes na tabela.
3. Sem ciclo de correcção — o conteúdo lido serve apenas para informar a escrita do código a seguir.
   Não produzir relatório nem pausar.

### `alvo=plano` (usado em `/planeia-issue`, depois de `escreve-plan`)

1. Ler o Plano (`docs/plans/YYYY-MM-DD-<slug>.md`) e extrair os nomes de ficheiros/classes/métodos
   **previstos** em cada tarefa.
2. Classificar cada nome pelo tipo (sufixo/prefixo) e carregar os specs correspondentes da tabela.
3. Confrontar os nomes previstos com as regras normativas desses specs (nomenclatura, prefixo
   `Contrato<Nome>`, etc.) — sinalizar nomes já previstos incorrectamente no próprio Plano, antes de a
   tarefa ser escrita.
4. Sem correcção automática do Plano — reportar ao utilizador e ajustar o Plano só se confirmado.

### `alvo=codigo` (usado em `/implementa-plano`, por tarefa, antes do checkpoint)

1. Listar os ficheiros alterados nesta tarefa (`git status --porcelain` / `git diff --name-only`).
2. Classificar cada ficheiro pelo tipo e carregar (`Read`) os specs correspondentes da tabela — se já
   foram lidos no passo `alvo=tarefa-planeada` desta mesma tarefa, não é necessário reler.
3. Reler os ficheiros alterados **linha a linha** (não é grep mecânico) contra as regras normativas
   ("obrigatório"/"sempre"/"nunca") de cada spec carregado, aplicáveis ao tipo do ficheiro.
4. Se limpo → `✅ Triagem semântica limpa`, segue sem pausar.
5. Se houver violações → corrigir directamente (tratamento igual a lint/Rector — correcção normal de
   qualidade, não é um FAIL bloqueante tipo `checkpoint:scan`) e listar o que foi corrigido no
   checkpoint da tarefa.

---

## Regras

- Fonte da verdade é sempre o conteúdo actual do `.md` de spec — nunca assumir a regra de memória/treino.
- Carregamento condicional: só ler os specs dos tipos de ficheiro efectivamente presentes na tarefa/Plano.
- Nunca substitui a leitura humana no checkpoint — é um passo adicional, não um atalho.
- `alvo=tarefa-planeada` e `alvo=plano` nunca corrigem automaticamente — só `alvo=codigo` corrige.
