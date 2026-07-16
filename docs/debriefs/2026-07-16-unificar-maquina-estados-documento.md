# Debrief: unificar máquina de estados do Documento (fundir EtapaExtracao em EstadoDocumento)

**Issue:** #110
**Branch:** refactor/unificar-maquina-estados-documento
**Data:** 2026-07-16
**Commits:** 4 commits de implementação (81d6741..HEAD)

## O que foi implementado

Fusão das duas dimensões de estado paralelas do `Documento` — os estados de
negócio (`EstadoDocumento`) e os passos de extracção (`EtapaExtracao`) — num único
`EstadoDocumento` de **9 estados**, porque a extracção passou a correr localmente
(deixou de haver o passo remoto que justificava a segunda dimensão).

Novo ciclo:
`Pendente → AnaliseMalware → AnaliseTexto → (AnaliseOcr) → AnaliseIaLocal → (AnaliseCloud) → Processado`,
com `Erro`/`Perigoso` alcançáveis a partir dos estados de análise e `Erro → Pendente`
a reabrir o pipeline.

Alterações principais:
- `EtapaExtracao` eliminado; `EstadoDocumento` reescrito com 9 casos.
- Grafo central de transições (`RegraTransicaoEstado`) e mapa estado→disco
  (`RegraMoverFicheiro`) reescritos.
- Família `Marcar<Estado>DocumentoAction`: +4 novas, 1 renomeada, 2 removidas.
- `RegraEliminarExtracaoTerminal` (nova) — elimina a `ExtracaoDocumento` (scratch
  space com PII) ao entrar em estado terminal (RGPD), invocada pelo
  `ExecutorTransicaoDocumento` dentro da transacção.
- `ReprocessarDocumentoAction` simplificada (delega a atomicidade no Executor).
- Colunas `etapa_extracao` (extracoes_documento) e `passo` (etapas_documento)
  removidas via migrations; caem também dos Resources.

## Ficheiros alterados

| Ficheiro | Tipo | Notas |
| -------- | ---- | ----- |
| `app/Shared/Enums/EstadoDocumento.php` | alterado | 9 casos unificados |
| `app/Shared/Enums/EtapaExtracao.php` | removido | dimensão fundida |
| `app/Shared/States/DocumentoAnalise{Malware,Texto,Ocr,IaLocal,Cloud}.php` | criado/renomeado | state objects read-only dos 5 passos de análise |
| `app/Features/Documento/Transicao/RegraTransicaoEstado.php` | alterado | novo grafo De→[Para] |
| `app/Features/Documento/Transicao/RegraMoverFicheiro.php` | alterado | mapa estado→disco |
| `app/Features/Documento/Transicao/RegraEliminarExtracaoTerminal.php` | criado | invariante RGPD (elimina extracção em terminal) |
| `app/Features/Documento/Transicao/ExecutorTransicaoDocumento.php` | alterado | invoca a nova Regra na transacção |
| `app/Features/Documento/Marcar*/Marcar*DocumentoAction.php` | criado/renomeado/removido | +4, 1 renomeada, 2 removidas |
| `app/Features/Documento/Triar/TriarDocumentoPendenteAction.php` | alterado | `AnaliseMalware` antes do scan |
| `app/Features/Documento/Reprocessar/ReprocessarDocumentoAction.php` | alterado | delega no Executor + delete defensivo |
| `app/Features/Documento/{DocumentoResource,EtapaDocumentoResource}.php` | alterado | caem `etapa_extracao` / `passo` |
| `app/Models/{Documento,ExtracaoDocumento,EtapaDocumento}.php` | alterado | `estado()` 9 casos; colunas removidas |
| `app/Jobs/ReconciliarFicheirosJob.php` | alterado | 5 estados transitórios |
| `database/migrations/2026_07_16_1200*.php` | criado | drop `etapa_extracao`, drop `passo` |
| `database/factories/{Documento,ExtracaoDocumento,EtapaDocumento}Factory.php` | alterado | states dos novos estados |
| `tests/Unit|Feature/Features/Documento/*` | alterado | cobertura das novas origens + RGPD |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| Fundir as 2 dimensões num único enum de 9 estados | Manter `EstadoDocumento` + `EtapaExtracao` | A extracção passou a ser local — a 2ª dimensão (que modelava passos remotos concorrentes) deixou de ter razão de existir; um único enum elimina a coordenação entre dimensões. |
| Agrupar em 4 commits (rename atómico + deltas aditivos) | 9 commits tarefa-a-tarefa | O rename de um backed enum é atómico: não há estado intermédio compilável/verde renomeando casos um a um. T1+T3+T4+T7+T8+T9 num commit verde; T2/T6/T5 aditivos. |
| `RegraEliminarExtracaoTerminal` como `Regra*` dedicada | Lógica inline (`if terminal`) no Executor | O "terminal para RGPD" ≠ "terminal do grafo" (`Erro` tem aresta `Erro→Pendente` mas é terminal de extracção). A Regra isola o invariante com definição e teste exaustivo próprios. |
| `ReprocessarDocumentoAction`: `delete()` defensivo idempotente | `update()` condicional da extracção | A linha já é eliminada ao entrar em `Erro` (RegraEliminarExtracaoTerminal); resta uma rede de segurança idempotente. |
| Reprocessar delega atomicidade no Executor | Manter `DB::transaction()` própria | Evita SAVEPOINT aninhado redundante — a transição já é atómica no Executor. |
| `delete()` por query (`where(...)`) na rede de segurança | `$documento->extracao?->delete()` (relação) | `extracao` é `HasOne` nullable: query-delete é idempotente sem guarda, 1 query, não hidrata PII em memória e apaga qualquer linha residual. |

## Desvios ao Plano

- **Estrutura de commits:** o Plano tinha 9 tarefas; foram reagrupadas em 4 commits
  (aprovado no arranque). Justificação técnica: atomicidade do rename do backed enum.
- **T2 — `RegraEliminarExtracaoTerminal`:** o Plano previa a verificação de terminal
  + eliminação como lógica no `ExecutorTransicaoDocumento`; extraída para `Regra*`
  dedicada a pedido do utilizador (isolamento do invariante).
- **Contrato da API:** `EtapaDocumentoResource` deixa cair `passo` do `historico[]` —
  forçado por RF-07 (coluna removida) mas **não** constava da secção API da Spec.
  A queda de `etapa_extracao` do `DocumentoResource` estava prevista (RF-08).

## Aprendizagens

Foco no objectivo de aprendizagem (Vertical Slice / Actions / Regras / PHP 8.5):

- **Vertical Slice escala por adição, não por modificação:** cada `Marcar<Estado>`
  é uma slice fina sobre o mesmo `ExecutorTransicaoDocumento`. Acrescentar um estado
  = uma slice nova + uma aresta no grafo central; as outras slices não são tocadas.
  A unificação passou a maioria da complexidade para dados (o `match` do grafo),
  não para código espalhado.
- **`Regra*` como unidade de invariante isolável:** só quando o "terminal de RGPD"
  ganhou a sua própria classe e teste exaustivo é que a diferença face ao "terminal
  do grafo" ficou explícita e defensável. Regras concretas injectadas (sem interface)
  são o encaixe certo para invariantes de domínio reutilizados pelo Executor.
- **Backed enum rename é atómico em PHP** e condiciona a estratégia de commits:
  não há como renomear casos incrementalmente mantendo a suite verde — obriga a um
  commit de rename completo antes dos deltas comportamentais.
- **State objects read-only** provaram-se: mudar de 3 para 9 estados não exigiu
  lógica nova nos objectos, só espelhar o construtor — a lógica de transição vive
  toda no grafo + Executor.

## SYSTEM_SPEC a actualizar

- `docs/system_spec/02-shared/estados.md` — grafo dos 9 estados + Action de cada transição
- `docs/system_spec/02-shared/enums.md` — `EstadoDocumento` (9 casos); remover `EtapaExtracao`
- `docs/system_spec/02-shared/regras-negocio.md` — `RegraEliminarExtracaoTerminal` (nova)
- `docs/system_spec/01-features/documento*.md` — família `Marcar<Estado>`, `Triar`, `Reprocessar`
- `docs/system_spec/03-models/*` — `Documento.estado()`, colunas removidas de `ExtracaoDocumento`/`EtapaDocumento`
- `docs/system_spec/00-index.md` — linha da nova Regra
- `openapi.yaml` — `DocumentoResource` (sem `etapa_extracao`), `EtapaDocumentoResource` (sem `passo`)

## Verificação final
- [x] Linter a verde (Pint + Rector)
- [x] Testes a verde (1068/1068, 2463 asserções, coverage 100%, type-coverage 100%, Larastan L9 0 erros)
- [x] Nenhum dado sensível em logs
- [x] Nenhum segredo em código
