# Brief: reorganiza estrutura de pastas da feature Documento (WRN-037)

**Issue:** #114
**Data:** 2026-07-20
**Branch:** refactor/reorganiza-pastas-documento

## Contexto

A feature `Documento` (`app/Features/Documento/`, 26 Actions, 58 ficheiros) acumulou pastas
pasta-por-Action com 1-2 ficheiros que fragmentam a navegação sem benefício, e espalhou o cluster de
"atalhos de transição" (`Marcar*` + `TransicionarProcessado`) por três sítios diferentes (raiz da
Feature, `Processamento/`, `Operacoes/TransicionarProcessado/`).

A sessão de `/ajusta-workflow` de 2026-07-20 (que resolveu WRN-037, commit `bf0210e`) fixou a **regra
de granularidade pasta-por-Action** em `docs/system_spec/02-shared/estrutura-subpastas-features.md`
(Action com <3 artefactos próprios = ficheiro solto) e mapeou toda a feature. Esta issue **aplica essa
regra ao código real** — é refactor estrutural puro, sem qualquer alteração de comportamento.

## O que muda

Só **localização física e `namespace`** de classes dentro de `app/Features/Documento/`, mais os `use`
que as referenciam e as referências de caminho no `docs/system_spec/`. Nenhuma lógica de negócio muda.

Movimentos (por CA):

- **CA-01 — dissolver pasta-por-Action em ficheiro solto** (Action com <3 artefactos):
  - `Pesquisa/Ver/` → soltos em `Pesquisa/` (Action + Request = 2)
  - `Atribuicao/Reivindicar/` e `Atribuicao/ReivindicarDocumentoEmEtapa/` → soltos em `Atribuicao/`
  - `Processamento/ProcessarAnalise{Texto,Ocr,IaLocal,Cloud}/` → soltos em `Processamento/`
  - `Processamento/RegistarEtapaExtracao/` (Action + Dto = 2) e
    `Processamento/RegistarFalhaTecnicaExtracao/` → soltos em `Processamento/`
- **CA-02 — fundir** `Processamento/ReconciliarEntidades/` em `Processamento/ConcluirExtracao/`
  (`ConcluirExtracaoDocumentoAction` + `RegraReconciliarEntidadesDocumento` +
  `ResultadoReconciliacaoEntidades` = 3 ficheiros → mantém pasta). Remover pasta `ReconciliarEntidades/`.
- **CA-03 — renomear + mover** `Atribuicao/Triar/TriarDocumentoPendenteAction` →
  `Processamento/ProcessarAnaliseMalwareDocumentoAction` (solto). Alinha com o padrão `Processar<Etapa>`
  das outras 4 etapas do pipeline. Inclui renomear a classe, o teste unitário homónimo e ajustar
  comentários de "triagem"/RN-06 que mantêm a intenção.
- **CA-04 — criar** `Operacoes/TransicoesEstado/` (subpasta aninhada nova, 8 Actions soltas): as 5
  `MarcarAnalise*` (de `Processamento/`), `MarcarErro`+Dto e `MarcarPerigoso`+Dto (da raiz da Feature),
  e `TransicionarProcessado`+Dto (de `Operacoes/TransicionarProcessado/`). Remover as pastas de origem.
  Nome decidido em Checkpoint A (2026-07-20): `Marcacoes` foi preterido por descrever o mecanismo
  ("marcar") e não a intenção (transição de estado/etapa); `TransicoesEstado` distingue-se de
  `Operacoes/Transicao/` (o motor `Executor` + `Regra*`), que fica intacto.
- **CA-05 —** `Atribuicao/` mantém-se subpasta com as 2 `Reivindicar*` soltas (decisão explícita de
  **não** dissolver para a raiz, apesar de estar abaixo do limiar de 3).

Ficheiros consumidores a actualizar (`use` / referências), já mapeados:
- `app/Features/Documento/DocumentoController.php` (só `Pesquisa\Ver\*`).
- 5 Console Commands `app/Console/Commands/Extracao/Executar*ExtracaoCommand.php` (importam
  `ProcessarAnalise{Cloud,IaLocal,Texto,Ocr}` e `Atribuicao\Reivindicar`).
- Cross-imports internos entre Actions da feature (os `ProcessarAnalise*`, `ConcluirExtracao`,
  `RegistarFalhaTecnicaExtracao`, `Reivindicar`, e o próprio `Triar`→`ProcessarAnaliseMalware`
  importam Marcar*/ConcluirExtracao/RegistarEtapa/etc.).
- ~24 testes em `tests/Unit/Features/Documento/` (importam as classes movidas por FQN; **os ficheiros
  de teste não mudam de sítio — a pasta de testes é flat** — só mudam os `use`; excepção: o ficheiro
  `TriarDocumentoPendenteActionTest.php` é renomeado por causa da CA-03).
- `docs/system_spec/`: `01-features/documento.md`, `documento-pipeline.md`,
  `documento-reconciliacao.md`, `02-shared/estrutura-subpastas-features.md`,
  `03-models/extracao-documento.md`, `04-infra/malware.md`, `queue-jobs.md`, `transactions.md`.

## O que NÃO muda

- Comportamento, lógica de negócio, contratos de método, assinaturas públicas — **zero**.
- `openapi.yaml`, rotas, verbos, schemas, autorização, superfície de ataque — inalterados.
- `tests/Feature/Features/Documento/` — os testes de feature batem nos endpoints por rota, **não
  importam Actions por FQN** (confirmado por grep: nenhuma referência aos namespaces movidos).
- A pasta `Operacoes/Transicao/` (Executor + Regras de transição) e as CRUD `Corrigir/`, `Criar/`,
  `Eliminar/`, `RecepcaoUpload/`, `Operacoes/Reprocessar/` — ficam onde estão.
- **Fora de âmbito** (herdado da issue): substituir os `Marcar*` por chamadas directas ao
  `ExecutorTransicaoDocumento`, e renomear/alargar a categoria `Atribuicao/`.

## Riscos identificados

- **Namespace/`use` desalinhado** — único ponto de falha real. As Actions resolvem-se por
  autoload/reflection (sem binding explícito no Service Container), pelo que um `use` esquecido ou um
  `namespace` mal reescrito rebenta em runtime, não em compile-time. Mitigação: `composer test`
  (CA-07) apanha 100% — cobertura e type-coverage a 100%, Larastan nível 9.
- **Rename da CA-03 é mais do que mover** — muda também o nome da classe
  (`TriarDocumentoPendenteAction` → `ProcessarAnaliseMalwareDocumentoAction`), o construtor de
  `ReivindicarDocumentoPendenteAction` (que injecta `TriarDocumentoPendenteAction` como property
  `$triar`), o teste homónimo, e comentários que referenciam `TriarDocumentoPendenteAction` noutras 3
  Actions. Risco de deixar uma referência textual órfã ao nome antigo.
- **Reconciliar o spec, não só caminhos** — a CA-04 supersede a decisão *estado-alvo* acabada de
  gravar no spec (WRN-037) de mover `MarcarErro`/`MarcarPerigoso` para `Processamento/Anomalias/` e a
  linha de vocabulário que lista `MarcarAnalise*` sob `Processamento/`. A CA-06 obriga a reescrever
  `estrutura-subpastas-features.md` (apagar as notas "Anomalias"/estado-alvo, mover as `MarcarAnalise*`
  do vocabulário `Processamento/` para `Operacoes/TransicoesEstado/`). Risco de deixar o spec
  auto-contraditório se a reconciliação for parcial.
- **Larastan/type-coverage** — mover DTOs (`MarcarErroDto`, `MarcarPerigosoDto`,
  `TransicionarProcessadoDto`, `RegistarEtapaExtracaoDto`) sem lhes actualizar o `namespace` ou os
  `@throws`/array-shapes dá erro de nível 9. Verificar cada DTO movido.
- **Commit isolado** — o "Fluxo ao mover uma Action" exige commit de refactor sem lógica nova
  misturada; como esta issue é só refactor, o risco é baixo, mas manter a disciplina de commits por CA.

## Questões em aberto

*(Ambas resolvidas em Checkpoint A, 2026-07-20 — registadas aqui para rasto.)*

1. **~~Marcacoes~~ (CA-04) vs Anomalias (spec estado-alvo).** ✅ **Resolvido:** a issue #114 vence.
   Os 8 atalhos de transição consolidam-se todos em `Operacoes/TransicoesEstado/`; a nota "Anomalias"
   e o estado-alvo do spec são **apagados/reescritos** na CA-06, e as `MarcarAnalise*` saem do
   vocabulário `Processamento/` para `Operacoes/TransicoesEstado/`.
2. **Nome da subpasta.** ✅ **Resolvido:** `Marcacoes` foi preterido (descreve o mecanismo, não a
   intenção). Nome final `TransicoesEstado/` — reflecte a transição de estado/etapa e distingue-se de
   `Operacoes/Transicao/` (o motor). Termo novo, sem sinónimo pré-existente noutra Feature — sem
   conflito com a regra de consistência semântica.
