# Plano: reorganiza estrutura de pastas da feature Documento (WRN-037)

**Issue:** #114
**Spec:** docs/specs/2026-07-20-reorganiza-pastas-documento.md
**Data:** 2026-07-20

> Refactor mecânico. Cada tarefa move um grupo de ficheiros com `git mv` (preserva histórico),
> reescreve o `namespace` dos ficheiros movidos e **todos** os `use` que os referenciam, e corre os
> testes afectados. Regra transversal do "Fluxo ao mover uma Action": mover + actualizar imports na
> **mesma** tarefa, para o build ficar verde ao fim de cada uma. Nenhum corpo de método muda.

## Tarefas

### Tarefa 1 — CA-01 (Pesquisa): dissolver `Pesquisa/Ver/`
- Ficheiros a mover: `Pesquisa/Ver/VerDocumentoAction.php`, `Pesquisa/Ver/VerDocumentoRequest.php` →
  `Pesquisa/` (soltos). Remover pasta `Ver/`.
- Namespace: `App\Features\Documento\Pesquisa\Ver` → `App\Features\Documento\Pesquisa` (nos 2 ficheiros).
- Consumidores a actualizar (`use`):
  - `app/Features/Documento/DocumentoController.php` (linhas 23-24).
  - `tests/Unit/Features/Documento/VerDocumentoActionTest.php`.
- Testes: `--filter=VerDocumento`.
- Commit: `refactor(documento): dissolve Pesquisa/Ver em ficheiros soltos (CA-01)`

### Tarefa 2 — CA-01 + CA-05 (Atribuicao): dissolver `Reivindicar/` e `ReivindicarDocumentoEmEtapa/`
- Ficheiros a mover: `Atribuicao/Reivindicar/ReivindicarDocumentoPendenteAction.php` e
  `Atribuicao/ReivindicarDocumentoEmEtapa/ReivindicarDocumentoEmEtapaAction.php` → `Atribuicao/`
  (soltos). Remover as 2 pastas. **CA-05:** `Atribuicao/` mantém-se subpasta (Triar sai na T5).
- Namespace: `...Atribuicao\Reivindicar` e `...Atribuicao\ReivindicarDocumentoEmEtapa` →
  `...Atribuicao`. (O `use` de `Atribuicao\Triar\TriarDocumentoPendenteAction` dentro de
  `ReivindicarDocumentoPendenteAction` **mantém-se** — Triar só é renomeado na T5.)
- Consumidores a actualizar (`use`):
  - `app/Console/Commands/Extracao/ExecutarScanExtracaoCommand.php` (linha 7 — Reivindicar).
  - `tests/Unit/Features/Documento/ReivindicarDocumentoPendenteActionTest.php`,
    `tests/Unit/Features/Documento/ReivindicarDocumentoEmEtapaActionTest.php`.
- Testes: `--filter=Reivindicar`.
- Commit: `refactor(documento): dissolve Atribuicao/Reivindicar* em ficheiros soltos (CA-01, CA-05)`

### Tarefa 3 — CA-01 (Processamento): dissolver os 6 folders de Action única
- Ficheiros a mover → `Processamento/` (soltos), removendo as pastas de origem:
  - `ProcessarAnaliseTexto/ProcessarAnaliseTextoDocumentoAction.php`
  - `ProcessarAnaliseOcr/ProcessarAnaliseOcrDocumentoAction.php`
  - `ProcessarAnaliseIaLocal/ProcessarAnaliseIaLocalDocumentoAction.php`
  - `ProcessarAnaliseCloud/ProcessarAnaliseCloudDocumentoAction.php`
  - `RegistarEtapaExtracao/{RegistarEtapaExtracaoAction,RegistarEtapaExtracaoDto}.php`
  - `RegistarFalhaTecnicaExtracao/RegistarFalhaTecnicaExtracaoAction.php`
- Namespace: `...Processamento\<Pasta>` → `...Processamento` em cada ficheiro movido.
- Consumidores a actualizar (`use`):
  - Console: `ExecutarParserExtracaoCommand` (ProcessarAnaliseTexto), `ExecutarTesseractExtracaoCommand`
    (ProcessarAnaliseOcr), `ExecutarIaLocalExtracaoCommand` (ProcessarAnaliseIaLocal),
    `ExecutarIaCloudExtracaoCommand` (ProcessarAnaliseCloud).
  - Cross-imports internos entre estes ficheiros (ex.: `ProcessarAnaliseTexto` importa
    `RegistarEtapaExtracao*`+`RegistarFalha*`; `ProcessarAnaliseOcr`/`IaLocal`/`Cloud` importam
    `RegistarEtapa*`/`RegistarFalha*`/`ConcluirExtracao`; `RegistarFalha` importa `RegistarEtapaDto`) —
    todos os que referenciam os namespaces agora encurtados. (Imports de `Marcar*`/`ConcluirExtracao`
    mantêm-se válidos — esses movem-se na T4/T6.)
  - Testes homónimos: `ProcessarAnalise{Texto,Ocr,IaLocal,Cloud}DocumentoActionTest`,
    `RegistarEtapaExtracao{Action,Dto}Test`, `RegistarFalhaTecnicaExtracaoActionTest`.
- Testes: `--filter='ProcessarAnalise|RegistarEtapa|RegistarFalha'`.
- Commit: `refactor(documento): dissolve Processamento/ProcessarAnalise* e Registar* em soltos (CA-01)`

### Tarefa 4 — CA-02: fundir `ReconciliarEntidades/` em `ConcluirExtracao/`
- Ficheiros a mover: `Processamento/ReconciliarEntidades/RegraReconciliarEntidadesDocumento.php` e
  `.../ResultadoReconciliacaoEntidades.php` → `Processamento/ConcluirExtracao/`. Remover pasta
  `ReconciliarEntidades/` (`ConcluirExtracao/` fica com 3 ficheiros).
- Namespace: `...Processamento\ReconciliarEntidades` → `...Processamento\ConcluirExtracao` nos 2.
- Consumidores a actualizar (`use`):
  - `ConcluirExtracaoDocumentoAction` (importa `ReconciliarEntidades\RegraReconciliarEntidadesDocumento`).
  - `tests/Unit/Features/Documento/RegraReconciliarEntidadesDocumentoTest.php`.
- Testes: `--filter='ConcluirExtracao|ReconciliarEntidades'`.
- Commit: `refactor(documento): funde ReconciliarEntidades em ConcluirExtracao (CA-02)`

### Tarefa 5 — CA-03: renomear+mover `Triar` → `ProcessarAnaliseMalware`
- Mover+renomear: `Atribuicao/Triar/TriarDocumentoPendenteAction.php` →
  `Processamento/ProcessarAnaliseMalwareDocumentoAction.php` (solto). Remover pasta `Atribuicao/Triar/`.
- Classe: `TriarDocumentoPendenteAction` → `ProcessarAnaliseMalwareDocumentoAction`; namespace
  `...Atribuicao\Triar` → `...Processamento`. Manter comentários de "triagem de malware"/RN-06 (a
  intenção mantém-se), ajustando só o que referir o nome antigo da classe.
- Renomear teste: `tests/Unit/Features/Documento/TriarDocumentoPendenteActionTest.php` →
  `ProcessarAnaliseMalwareDocumentoActionTest.php` (actualizar `use`, nome de classe nos `expect`, e
  descrições de teste).
- Consumidores a actualizar:
  - `ReivindicarDocumentoPendenteAction` — `use`, tipo do parâmetro do construtor (`private
    TriarDocumentoPendenteAction $triar`), a chamada `$this->triar->handle(...)` e comentários que
    citam `TriarDocumentoPendenteAction`. **Manter** o nome da property `$triar`? Não — renomear para
    reflectir a intenção nova (`$processarAnaliseMalware`) por consistência de nomenclatura; confirmar
    em checkpoint da tarefa. (Regra do escuteiro: nome local, no ficheiro tocado.)
  - Comentários que citam `TriarDocumentoPendenteAction`: `ReivindicarDocumentoEmEtapaAction`,
    `MarcarAnaliseMalwareDocumentoAction` (texto do comentário — actualizar a referência).
- Testes: `--filter='ProcessarAnaliseMalware|Reivindicar'`.
- Verificação: `grep -rn 'TriarDocumentoPendente' app tests` → só comentários históricos deliberados,
  se algum (CA-08).
- Commit: `refactor(documento): renomeia Triar para ProcessarAnaliseMalware no pipeline (CA-03)`

### Tarefa 6 — CA-04: criar `Operacoes/TransicoesEstado/` e consolidar os 8 atalhos
- Criar pasta `app/Features/Documento/Operacoes/TransicoesEstado/`. Mover (soltos) e remover origens:
  - de `Processamento/MarcarAnalise{Texto,Ocr,IaLocal,Cloud,Malware}/*Action.php` (5)
  - de `MarcarErro/{MarcarErroDocumentoAction,MarcarErroDocumentoDto}.php` (raiz)
  - de `MarcarPerigoso/{MarcarPerigosoDocumentoAction,MarcarPerigosoDocumentoDto}.php` (raiz)
  - de `Operacoes/TransicionarProcessado/{TransicionarProcessadoDocumentoAction,...Dto}.php`
- Namespace de todos: `App\Features\Documento\Operacoes\TransicoesEstado`.
- Consumidores a actualizar (`use`) — mapeados por grep:
  - `ConcluirExtracaoDocumentoAction` (MarcarErro+Dto, TransicionarProcessado Action+Dto).
  - `ProcessarAnaliseCloudDocumentoAction` (MarcarErro+Dto, MarcarPerigoso+Dto).
  - `ProcessarAnaliseIaLocalDocumentoAction` (MarcarPerigoso+Dto, MarcarAnaliseCloud).
  - `ProcessarAnaliseOcrDocumentoAction` (MarcarAnaliseIaLocal).
  - `ProcessarAnaliseTextoDocumentoAction` (MarcarAnaliseIaLocal, MarcarAnaliseOcr).
  - `RegistarFalhaTecnicaExtracaoAction` (MarcarErro+Dto).
  - `ProcessarAnaliseMalwareDocumentoAction` (ex-Triar — MarcarErro+Dto, MarcarPerigoso+Dto,
    MarcarAnaliseMalware, MarcarAnaliseTexto).
  - Testes homónimos: `MarcarAnalise{Texto,Ocr,IaLocal,Cloud,Malware}DocumentoActionTest`,
    `MarcarErro{Action,Dto}Test`, `MarcarPerigoso{Action,Dto}Test`,
    `TransicionarProcessado{Action,Dto}Test`.
- Remover pastas esvaziadas: 5× `Processamento/MarcarAnalise*/`, `MarcarErro/`, `MarcarPerigoso/`,
  `Operacoes/TransicionarProcessado/`.
- Testes: `--filter='MarcarAnalise|MarcarErro|MarcarPerigoso|TransicionarProcessado'`.
- Commit: `refactor(documento): consolida atalhos de transição em Operacoes/TransicoesEstado (CA-04)`

### Tarefa 7 — CA-06 + CA-07 + CA-08: verificação global
- `grep -rn 'Documento\\(Atribuicao\\Triar|Atribuicao\\Reivindicar|Atribuicao\\ReivindicarDocumentoEmEtapa|Pesquisa\\Ver|Processamento\\ProcessarAnalise|Processamento\\RegistarEtapaExtracao|Processamento\\RegistarFalhaTecnicaExtracao|Processamento\\ReconciliarEntidades|Processamento\\MarcarAnalise|MarcarErro|MarcarPerigoso|Operacoes\\TransicionarProcessado)' app tests routes bootstrap config` → **zero** resultados (todos os namespaces antigos eliminados).
- `grep -rn 'TriarDocumentoPendente' app tests` → só comentários históricos deliberados (CA-08).
- `composer lint` + `composer refactor` (aplicar), depois `composer test` verde
  (100% coverage/types, arch, Larastan nível 9). CA-07.
- `php artisan checkpoint:scan` — registar WRN se aparecer FAIL pré-existente (esperado: repetição de
  WRN-001/032/038 supply-chain, não introduzido por esta issue).
- Commit: só se `lint`/`refactor` tocarem em algo (`style(documento): pint/rector pós-refactor`).

> **Nota:** a actualização de `docs/system_spec/*` (CA-06, parte docs — incl. reconciliação da nota
> "Anomalias" e do vocabulário em `estrutura-subpastas-features.md`) é feita na **Fase 3a**
> (`/documenta-implementacao` → `actualiza-spec`), não neste plano. A parte de CA-06 aqui é só o grep
> de referências e a garantia de "sem alteração de comportamento".

## Ordem de implementação

1. T1 (Pesquisa/Ver) — independente, o mais isolado.
2. T2 (Atribuicao/Reivindicar*) — independente; deixa Triar no sítio para a T5.
3. T3 (Processamento single-Action) — independente; imports de Marcar*/ConcluirExtracao ainda válidos.
4. T4 (ConcluirExtracao merge) — antes de T6 (T6 actualiza imports de ConcluirExtracao para Marcar*).
5. T5 (rename Triar→ProcessarAnaliseMalware) — antes de T6 (T6 actualiza os imports Marcar* do ex-Triar).
6. T6 (TransicoesEstado) — a maior; consolida e actualiza todos os consumidores dos Marcar*.
7. T7 (verificação global) — no fim; garante grep limpo + `composer test` verde.

## Testes a escrever

Nenhum teste novo — refactor puro. Os testes existentes (dual: Unit + Feature) continuam a passar sem
alteração de asserções; só mudam `use` e, num caso, o nome do ficheiro/classe de teste
(`TriarDocumentoPendenteActionTest` → `ProcessarAnaliseMalwareDocumentoActionTest`, T5).

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| Suite completa Documento (Unit) | unit | `tests/Unit/Features/Documento/*` | mesmas asserções passam com novos FQN |
| Suite completa Documento (Feature) | feature | `tests/Feature/Features/Documento/*` | endpoints inalterados (não importam Actions por FQN) |

## Dependências
- Issues bloqueantes: nenhuma.
- Deve ser implementada após: nenhuma (WRN-037 já fixou a regra).

## Riscos de implementação
> Consolidados do Brief.
- **`use`/namespace desalinhado** — único ponto de falha real (Actions resolvem-se por autoload, sem
  binding). `composer test` (T7) apanha 100%.
- **Rename da T5 é mais do que mover** — muda nome da classe, construtor de `ReivindicarPendente`, teste
  homónimo e comentários noutras 3 Actions. Risco de referência textual órfã → grep de verificação (CA-08).
- **DTOs movidos** (`MarcarErroDto`, `MarcarPerigosoDto`, `TransicionarProcessadoDto`,
  `RegistarEtapaExtracaoDto`) sem `namespace` actualizado → erro Larastan nível 9. Verificar cada um.
- **Larastan apanha `use` não usado** — depois de encurtar namespaces, garantir que não sobram imports
  redundantes (ex.: uma classe que passou a estar no mesmo namespace já não precisa de `use`).

## O que NÃO fazer nesta issue
- Não alterar lógica de negócio, assinaturas ou corpos de método.
- Não substituir os `Marcar*` por chamadas directas a `ExecutorTransicaoDocumento` (fora de âmbito).
- Não renomear `Atribuicao/` nem dissolvê-la para a raiz (CA-05 mantém-na).
- Não mover ficheiros de teste de directório (a pasta é flat) — excepto o rename da T5.
- Não escrever/actualizar `docs/system_spec/*` (é Fase 3a) — aqui só o grep de referências.
- Não tocar em `Operacoes/Transicao/` (o motor), `Operacoes/Reprocessar/`, nem nas CRUD
  (`Corrigir/`, `Criar/`, `Eliminar/`, `RecepcaoUpload/`).
