# System Spec — Feature: Documento (reconciliação ficheiro↔BD)

> `app/Features/Documento/Operacoes/Transicao/` (`RegraReconciliarLocalizacaoFicheiro`) +
> `app/Jobs/ReconciliarFicheirosJob.php`. Extraído de `01-features/documento-pipeline.md` (WRN-033)
> por limiar de tamanho (~200 linhas). Actions de transição/triagem/recorder + mapa De→Para
> continuam em `documento-pipeline.md`; Job/Schedule em `04-infra/queue-jobs.md`.

---

## Dimensão de extracção — `ExtracaoDocumento` como scratch space

Com a máquina de estados **unificada**, o passo de análise **é** o `Documento.estado` — deixou de
existir a coluna `EtapaExtracao`/dimensão paralela. `ExtracaoDocumento` reduz-se a **scratch space**
1-1 com o `Documento`: `texto_extraido`/`dados_json` (produtos intermédios da extracção, PII),
`extracao_reclamada_em` (lease) e `extracao_tentativas` (contador). Nenhuma coluna de estado — o
progresso lê-se de `Documento.estado`.

- **`ExtracaoDocumento` é opcional** — `Documento::extracao()` devolve `null` para qualquer documento
  que nunca tenha entrado no pipeline de extracção (registo manual via `RegistarDocumentoManualAction`,
  ou documento marcado `Perigoso`/`Erro` no scan de malware antes de qualquer escrita de extracção).
  Ver `03-models/extracao-documento.md`.
- **Eliminada ao atingir estado terminal** — `RegraEliminarExtracaoTerminal` (invocada pelo
  `ExecutorTransicaoDocumento` dentro da transacção) apaga a linha ao entrar em `Processado`, `Erro`
  ou `Perigoso` (minimização de dados / RGPD). Ver `02-shared/regras-transicao-documento.md`. Um
  documento reaberto (`Erro → Pendente`) recomeça o pipeline sem herdar scratch space antigo;
  `ReprocessarDocumentoAction` mantém um `delete()` defensivo idempotente como rede de segurança.
- **`etapas_documento` regista a história completa** — uma linha de transição de negócio (gravada por
  `ExecutorTransicaoDocumento`) tem `resultado` a `null`; uma linha de tentativa de IA (gravada por
  `RegistarEtapaExtracaoAction`) tem `estado` igual ao passo de análise em curso e `resultado`
  preenchido — permite reconstruir a história numa única query ordenada por `created_at`.
- **Enforcement implementado**: reivindicação real com `lockForUpdate()`/expiração por TTL
  sobre `extracao_reclamada_em` é `ReivindicarDocumentoEmEtapaAction`; a transição automática ao
  esgotar `extracao_tentativas` é `RegistarFalhaTecnicaExtracaoAction`; a reposição do contador a cada
  avanço de etapa é `RegraReporTentativasExtracao`. Todos em `01-features/documento-pipeline.md`.

---

## Contrato de atomicidade ficheiro↔BD

`ExecutorTransicaoDocumento` move o ficheiro **antes** de abrir a `DB::transaction()` (ver
`04-infra/transactions.md`) — o filesystem não participa no rollback da BD. Se a compensação
best-effort (repor o ficheiro na origem) também falhar, existe uma **janela de inconsistência**:
a BD reflecte o estado anterior à transição, mas o ficheiro físico pode estar no disco de destino.

Como o conjunto de discos é fixo (5: `entrada`, `enviado`, `processado`, `erro`, `perigoso`, mapa em
`RegraMoverFicheiro::discoParaEstado()`), esta janela é **detectável e reversível**, não uma
inconsistência permanente:

- **Detecção:** `ReconciliarFicheirosJob` (agendado a cada 5 min, `onOneServer`) varre `Documento`s
  presos num estado transitório (`AnaliseMalware`/`AnaliseTexto`/`AnaliseOcr`/`AnaliseIaLocal`/
  `AnaliseCloud` — os 5 passos de análise) há mais tempo que
  `config('pipeline.reconciliacao_limiar_minutos')` (default 15 min — não é uma janela de
  recência, é um limiar de "parado há mais tempo que uma transição normal demora").
- **Resolução:** `RegraReconciliarLocalizacaoFicheiro` verifica se o ficheiro existe no
  `disco_storage` actual; se não, procura-o nos 4 discos restantes comparando `hash_sha256` (o
  nome mantém-se igual entre discos, excepto no caso `Processado`/`RegraNomearProcessado`, fora do
  âmbito desta reconciliação). Se localizado noutro disco, `ReconciliarFicheirosJob` **repõe
  automaticamente** `disco_storage`/`nome_ficheiro_storage` na BD.
- **Caso irrecuperável:** se o ficheiro não existir em nenhum dos 5 discos, o Job regista
  `Log::error` estruturado (id do documento, disco/nome esperados — sem dados sensíveis) e não
  altera a BD; um ficheiro genuinamente perdido exige intervenção manual, fora do âmbito da
  reconciliação automática.
- **Custo:** proporcional ao nº de documentos presos (scan limitado pelo índice composto
  `(estado, updated_at)`), nunca à tabela `documentos` completa.
