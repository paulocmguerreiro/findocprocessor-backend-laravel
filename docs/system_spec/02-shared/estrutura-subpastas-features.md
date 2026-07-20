# System Spec — Shared: Subpastas Semânticas dentro de uma Feature

> Complementa `02-shared/padroes-acoes.md` (conteúdo da Action) — este ficheiro rege a *organização de
> ficheiros* dentro de `app/Features/<Feature>/`, quando o número de Actions justifica agrupar por
> propósito de negócio.

Por omissão, uma Feature é flat: `app/Features/<Feature>/<Action>/`. Uma subpasta semântica só se
justifica quando o custo de navegação de uma lista longa de Actions supera o custo de mais um nível de
directório — nunca antes disso.

---

## Regra de ouro da coesão (limiar de 3)

- **Menos de 3 Actions com o mesmo propósito de negócio** → ficam na raiz da Feature
  (`app/Features/<Feature>/<Action>/`). Não criar a subpasta só porque "parece fazer sentido" — pastas
  com 1-2 Actions fragmentam sem benefício de navegação.
- **3 ou mais Actions com o mesmo propósito** → agrupar em
  `app/Features/<Feature>/<SubpastaSemantica>/<Action>/`.
- **Coesão de artefactos:** ao mover uma Action para a subpasta, o `Request`, `Dto`/`Resource` e
  excepções específicas dela acompanham-na para a mesma subpasta. Ficheiros partilhados por várias
  Actions da Feature (ex.: um DTO comum) ficam na raiz da Feature, não numa subpasta específica.

---

## Dissolução da subpasta (queda abaixo do limiar)

A regra do limiar aplica-se nos dois sentidos, mas de forma assimétrica:

- **Criar** (categoria atinge 3) é obrigatório — sem margem de decisão, aplica-se sempre.
- **Dissolver com 1-2 Actions remanescentes** (por remoção/fusão/migração de uma Action para outra
  categoria) **nunca é automático** — implica sempre perguntar ao utilizador se quer mover os
  ficheiros restantes de volta para a raiz da Feature (mesmo "Fluxo ao mover uma Action" abaixo,
  em sentido inverso).
- **Subpasta que fica com 0 Actions** (a última Action foi removida/migrada) — remover a pasta vazia
  **sem pedir autorização**. Não há decisão de reorganização a tomar (nada para onde mover, nada a
  julgar) — é limpeza de estrutura morta, tratamento igual a remover um import não usado.

Razão da assimetria 1-2 vs 0: dissolver por causa de uma Action que desapareceu não traz ganho de
navegação quando ainda sobra conteúdo (uma pasta com 2 ficheiros continua perfeitamente navegável) —
o custo do refactor só se justifica se o utilizador achar que faz sentido nesse momento. Perguntar
sempre que a contagem oscila entre 2 e 3 ao longo de vários PRs causaria *flapping*; por isso, se o
utilizador recusar dissolver, a pasta fica como está e **não se volta a perguntar** sobre o mesmo
estado — só há nova pergunta se a contagem dessa categoria mudar outra vez.

---

## Granularidade dentro da subpasta: pasta-por-Action vs ficheiro solto

O limiar de 3 acima decide quando um *grupo de Actions* ganha subpasta. Esta secção decide, um nível
abaixo, quando uma *Action individual* ganha pasta própria dentro dessa subpasta (ou da raiz da
Feature, se não houver subpasta).

- **Ficheiro solto por omissão:** uma Action com menos de 3 artefactos próprios fica como ficheiro
  solto — `<Name>Action.php` conta como o 1.º artefacto; `Request`, `Resource`, `Dto` e excepções
  específicas somam-se a partir daí. Pasta com 1 ficheiro só não acrescenta valor de navegação.
- **Pasta própria ao atingir o 3.º artefacto:** reaproveita o mesmo limiar de 3 da regra acima,
  aplicado um nível abaixo. Ao ganhar o 3.º artefacto próprio, a Action move-se para
  `<Action>/<Name>Action.php` — seguir o "Fluxo ao mover uma Action" já documentado abaixo.
- **Assimetria de dissolução reaproveitada:** se uma Action já com pasta própria perde artefactos e
  cai para 1-2, a pasta **não** se dissolve automaticamente para ficheiro solto — pergunta-se ao
  utilizador, mesmo critério da secção "Dissolução da subpasta" acima. Só desaparece sozinha quando
  fica vazia (a Action foi eliminada).
- **Excepção CRUD tolerada, não permanente:** Actions CRUD simples (`Corrigir`, `Criar`, `Eliminar`)
  mantêm pasta própria mesmo abaixo do limiar de 3 (ex.: `Eliminar/` com 2 artefactos), por serem
  identidade de operação reconhecível por si. Não é isenção definitiva — se o crescimento da Feature
  justificar reagrupá-las por categoria de negócio em vez de por operação CRUD, ficam sujeitas à mesma
  reorganização que qualquer outra Action.

---

## Vocabulário canónico (linguagem do negócio, não de infra)

Proibido nomear subpastas com termos técnicos/de padrão de desenho — não criar `/Jobs`, `/Events`,
`/Flags`, `/Queries`, `/Helpers`, `/CRUD`. O nome descreve a intenção de negócio, não a implementação.

| Categoria | Propósito | Exemplos de Actions (nomenclatura do projecto) |
|---|---|---|
| `Ingestao/` | Receção de dados — upload, leitura de webhook, criação inicial do registo | `RecepcaoUploadDocumentoAction` |
| `Processamento/` | Transformação — OCR, IA, parsing, chamadas a APIs externas | `MarcarAnaliseOcrAction`, `MarcarAnaliseTextoAction`, `MarcarAnaliseIaLocalAction`, `MarcarAnaliseCloudAction`, `MarcarAnaliseMalwareAction`, `RegistarEtapaExtracaoAction`, `ProcessarAnaliseTextoDocumentoAction`, `ProcessarAnaliseOcrDocumentoAction`, `ProcessarAnaliseIaLocalDocumentoAction`, `ProcessarAnaliseCloudDocumentoAction`, `ConcluirExtracaoDocumentoAction`, `RegistarFalhaTecnicaExtracaoAction` |
| `Operacoes/` | Máquina de estados, transições, conversões de papel de registo | `TransicaoAction`, `TransicionarProcessadoDocumentoAction`, `ReprocessarDocumentoAction` |
| `Atribuicao/` | Decisão humana — triagem manual, delegação, atribuição de responsável/permissão | `ReivindicarDocumentoPendenteAction`, `TriarDocumentoPendenteAction`, `ReivindicarDocumentoEmEtapaAction`, `AtribuirRoleAction` |
| `Pesquisa/` | Leitura e saída — listagens, filtros, exportação/download | `ListarDocumentosAction`, `DescarregarDocumentoAction`, `VerDocumentoAction` |

Esta tabela é o vocabulário de referência — não inventar sinónimo novo sem consultar a regra de
consistência abaixo.

> **`Anomalias/` não é categoria de topo:** desvios de fluxo do pipeline (erros de sistema, marcas de
> alerta/perigo — `MarcarErroDocumentoAction`, `MarcarPerigosoDocumentoAction`) são semanticamente parte
> do mesmo conceito de pipeline que `Processamento/`, não um propósito de negócio à parte. Vivem como
> subpasta **interna**: `Processamento/Anomalias/`. Decisão registada em WRN-037 (2026-07-20) — a
> aplicação ao código existente (mover `MarcarErro/`/`MarcarPerigoso/`, hoje na raiz de
> `app/Features/Documento/`) fica pendente de issue dedicada de refactor da feature `Documento`, não é
> aplicada por este ajuste de convenção.

### Exemplo real validado (app/Features/Documento, 26 Actions)

Aplicação do limiar de 3 ao estado actual do repositório, incluída aqui como caso de estudo de como a
trava funciona (categorias abaixo do limiar ficam na raiz, mesmo fazendo sentido semântico). `Atribuicao/`
é o caso documentado de travessia do limiar: ficou 2 (na raiz) até a introdução de
`ReivindicarDocumentoEmEtapaAction` — a 3ª Action obrigou o agrupamento, incl. mover as duas já
existentes (`Reivindicar`, `Triar`) para dentro da subpasta nova, num commit de refactor isolado.

| Categoria | Actions que qualificam | Atinge limiar? |
|---|---|---|
| `Processamento/` | MarcarAnaliseCloud, MarcarAnaliseIaLocal, MarcarAnaliseMalware, MarcarAnaliseOcr, MarcarAnaliseTexto, RegistarEtapaExtracao, ProcessarAnaliseTexto, ProcessarAnaliseOcr, ProcessarAnaliseIaLocal, ProcessarAnaliseCloud, ConcluirExtracao, RegistarFalhaTecnicaExtracao | ✅ 12 — agrupar |
| `Operacoes/` | Transicao, TransicionarProcessado, Reprocessar | ✅ 3 — agrupar |
| `Pesquisa/` | Listar, Descarregar, Ver | ✅ 3 — agrupar |
| `Atribuicao/` | Reivindicar, Triar, ReivindicarDocumentoEmEtapa | ✅ 3 — agrupar (ver nota acima) |
| `Ingestao/` | RecepcaoUpload | ❌ 1 — fica na raiz |

Corrigir, Criar e Eliminar são CRUD simples e ficam sempre na raiz (não têm categoria de negócio
própria), sujeitos à excepção CRUD da secção "Granularidade" acima.

> **Estado-alvo (ainda não aplicado ao código):** `MarcarErro` e `MarcarPerigoso`, hoje na raiz da
> Feature, mudam para `Processamento/Anomalias/` (ver nota acima). Dentro de `Processamento/`, as
> Actions de artefacto único (`ConcluirExtracao`, `RegistarFalhaTecnicaExtracao`, e as 5
> `MarcarAnalise*`) passam a ficheiro solto pela regra de "Granularidade" acima, em vez de pasta
> própria — `RegistarEtapaExtracao` (2 artefactos) mantém-se ficheiro solto pela mesma regra.
> Refactor a tratar em issue dedicada, não neste ajuste de convenção.

---

## Regra de consistência semântica entre Features (dicionário de equivalência)

Antes de criar uma subpasta nova, fazer uma varredura leve dos nomes de subpastas já existentes noutras
Features — **só os nomes de directórios**, nunca o código dos ficheiros:

```bash
find app/Features -maxdepth 2 -type d | sort
```

Se o propósito da subpasta nova for um sinónimo próximo de uma subpasta já usada noutra Feature, **adoptar
o termo já existente** em vez de introduzir um segundo nome para o mesmo conceito. Exemplos de decisão:

- Ia criar `Entidades/` mas já existe `Parceiros/` → usar `Parceiros/`.
- Ia criar `Excecoes/` mas já existe `Anomalias/` → usar `Anomalias/`.
- Ia criar `Custodia/` mas já existe `Atribuicao/` → usar `Atribuicao/`.

Quando a decisão implicar adoptar um nome existente em vez do proposto inicialmente, registar a
justificação no output da tarefa/checkpoint (qual nome foi preterido e porquê).

---

## Fluxo ao mover uma Action para uma subpasta (refactor estrutural)

Aplica-se sempre que uma Action muda de directório físico dentro da Feature (seja ao criar uma Action
nova já na subpasta correcta, seja ao reorganizar Actions existentes por terem atingido o limiar):

1. **Namespace:** actualizar o `namespace` da classe para corresponder ao novo caminho físico.
2. **Imports:** actualizar todos os `use` nos ficheiros que referenciam a classe movida (Controllers,
   testes, Service Providers, outras Actions).
3. **`docs/system_spec`:** procurar (`grep -rn`) referências ao caminho antigo do ficheiro/pasta
   movido em `docs/system_spec/` (caminhos tipo `app/Features/<Feature>/<PastaAntiga>/...` e
   namespaces `App\Features\<Feature>\<PastaAntiga>\...`) e actualizá-las para o caminho novo. O
   spec documenta o estado actual do código — um refactor de pastas que não actualiza o spec deixa-o
   desactualizado. `docs/plans/` e `docs/debriefs/` **não** se actualizam (são registo histórico do
   momento em que a tarefa foi feita).
4. **Sem alteração de comportamento:** um refactor de pastas não muda lógica de negócio — o código
   continua a passar exactamente nos mesmos testes.
5. **Commit isolado:** quando o refactor for retroactivo (mover Actions já existentes, não uma Action
   nova sendo criada na tarefa actual), fica num commit próprio, sem lógica nova misturada — facilita
   revisão e reversão.
6. **Nota de justificação:** se o nome da subpasta foi ajustado para coincidir com uma já existente
   noutra Feature (regra acima), referir isso no output/checkpoint da tarefa.
