# Plano: Extração — orquestradores Schedule (`extracao:*`) sobre máquina de estados unificada

**Issue:** #111
**Spec:** docs/specs/2026-07-17-extracao-orquestradores-schedule.md
**Data:** 2026-07-17

> **Posicionamento de ficheiros (triagem semântica):** os orquestradores são *transformação*
> (parser/OCR/IA) → nascem em `app/Features/Documento/Processamento/` (subpasta já existente, 6
> Actions), **não** numa `Extracao/` nova (seria sinónimo de `Processamento/`). A nova Action de
> reivindicação por lease é da categoria `Atribuicao/` (reivindicação), que passa de 2 → 3 Actions
> (Reivindicar, Triar, +nova) → **atinge o limiar** e obriga a agrupar `Reivindicar`+`Triar`+nova em
> `app/Features/Documento/Atribuicao/` (refactor estrutural na Tarefa 3).

## Tarefas

### Tarefa 1 — Flexibilizar `TransicionarProcessadoDocumentoDto` + naming com fallback
- Ficheiros a criar/alterar:
  - `app/Features/Documento/Operacoes/TransicionarProcessado/TransicionarProcessadoDocumentoDto.php` — `idFornecedor`/`idCliente`/`valor`/`dataDocumento` passam a **nullable**; invariante mínima: pelo menos um dos lados de entidade preenchido (o da empresa mãe); `idCategoria` continua obrigatório; validar `valor >= 0` só quando não-nulo.
  - `app/Features/Documento/Operacoes/TransicionarProcessado/TransicionarProcessadoDocumentoAction.php` — `findOrFail` do fornecedor só quando `idFornecedor` não-nulo; passar nome extraído + `created_at` ao naming.
  - `app/Features/Documento/Operacoes/Transicao/RegraNomearProcessado.php` — assinatura aceita `?string $nomeFornecedor`, `?DateTimeInterface $dataDocumento`, `?string $nomeFornecedorExtraido`, `DateTimeInterface $createdAt`; fallback: fornecedor nulo → nome extraído (→ nome da empresa mãe se vazio); data nula → `created_at`.
- O que implementar: RF-11, RN-03; permitir documentos "parciais" (extrato/aviso) chegarem a `Processado` sem partir o nome canónico.
- Testes associados: DTO (invariantes nullable + rejeição de ambos os lados nulos); `RegraNomearProcessado` (fallbacks de nome e data); `TransicionarProcessadoDocumentoAction` (fornecedor nulo).
- Commit: `feat(documento): flexibiliza DTO e naming de Processado para documentos parciais`

### Tarefa 2 — Reset de `extracao_tentativas` no funil de transições
- Ficheiros a criar/alterar:
  - `app/Features/Documento/Operacoes/Transicao/ExecutorTransicaoDocumento.php` — ao transicionar para um estado de análise **não-terminal** (avanço correcto), repor `extracao_tentativas = 0` na `ExtracaoDocumento` (se existir; no-op se não existir), dentro da transacção. Nunca em transições para `Erro`.
- O que implementar: RN-05 / RF-13, num só sítio (não replicado pelos orquestradores).
- Testes associados: transição de avanço reseta o contador; transição para `Erro` **não** reseta; documento sem `ExtracaoDocumento` não rebenta.
- Commit: `feat(transicao): reset de tentativas de extracção no avanço correcto de etapa`

### Tarefa 3 — Reivindicação por lease das etapas de análise (+ agrupar `Atribuicao/`)
- Ficheiros a criar/alterar:
  - **Refactor estrutural** (commit isolado ou início desta tarefa): mover `Reivindicar/` e `Triar/` para `app/Features/Documento/Atribuicao/` (namespaces, imports em Controllers/testes/Actions, referências em `docs/system_spec/`).
  - `app/Features/Documento/Atribuicao/ReivindicarDocumentoEmEtapa/ReivindicarDocumentoEmEtapaAction.php` — dado um `EstadoDocumento`, selecciona sob `lockForUpdate` o próximo documento nesse estado com lease **nulo ou expirado** (`extracao_reclamada_em < now - config('extracao.ttl_lease')`), grava `extracao_reclamada_em = now()` (upsert em `extracoes_documento`, cria a linha se não existir) e devolve-o; `null` se não houver candidato. Sem `Gate` (sistema).
- O que implementar: RF-08, RNF-01 — unicidade por documento entre workers.
- Testes associados: elegibilidade (lease nulo/expirado sim; recente não); **teste de concorrência com 2 conexões MySQL** (só um worker reivindica); criação da linha `extracoes_documento` quando ausente.
- Commit: `refactor(documento): agrupa Reivindicar/Triar em Atribuicao` + `feat(extracao): reivindicação por lease das etapas de análise`

### Tarefa 4 — Reconciliação de entidades (empresa mãe / find-or-create / null)
- Ficheiros a criar/alterar:
  - `app/Features/Documento/Processamento/ReconciliarEntidades/RegraReconciliarEntidadesDocumento.php` — dado `ResultadoExtracaoIA` + `TipoDocumento`, resolve `id_fornecedor`/`id_cliente` por lado (RF-10/RN-02): lado `posicao_empresa_mae` → `Entidade::whereEmpresaAplicacao()->firstOrFail()`; lado `espera=true` → find-or-create por `nif` (`firstOrCreate` com `nome`+`nif`+flag); lado `espera=false` sem empresa mãe → `null`. Devolve um VO com os IDs + nome extraído do fornecedor (para o naming).
- O que implementar: RF-10, RN-02, RN-06 (empresa mãe em falta → excepção → `Erro` no orquestrador).
- Testes associados: fatura de fornecedor (ambos resolvidos); extrato (fornecedor `null`); reutiliza entidade existente por NIF; cria inexistente com flags; sem empresa mãe → excepção.
- Commit: `feat(extracao): reconciliação de entidades por lado (empresa mãe / find-or-create)`

### Tarefa 5 — Orquestrador `AnaliseTexto` (parser) + detecção de imagem
- Ficheiros a criar/alterar:
  - `app/Features/Documento/Processamento/ProcessarAnaliseTexto/ProcessarAnaliseTextoDocumentoAction.php` — reclama (Tarefa 3); se `nome_ficheiro_storage` **não for `.pdf`** → `MarcarAnaliseOcrDocumentoAction`; se PDF → `ExtractorTextoNativo::extrair()` (caminho absoluto via `Storage::disk`), `ultrapassaThreshold` → `MarcarAnaliseIaLocalDocumentoAction`, senão → `MarcarAnaliseOcrDocumentoAction`; regista via `RegistarEtapaExtracaoAction` (texto + resultado); falha técnica (`FalhaExtracaoTextoException`) conta tentativa (RF-12) e à 3ª → `MarcarErroDocumentoAction`.
- O que implementar: RF-05, RF-08, RF-12.
- Testes associados: PDF acima/abaixo do threshold; imagem (jpg/png/tiff) salta parser → OCR; falha técnica incrementa e à 3ª vai a `Erro`; `Storage::fake()`.
- Commit: `feat(extracao): orquestrador da etapa AnaliseTexto (parser nativo + imagem)`

### Tarefa 6 — Orquestrador `AnaliseOcr` (Tesseract)
- Ficheiros a criar/alterar:
  - `app/Features/Documento/Processamento/ProcessarAnaliseOcr/ProcessarAnaliseOcrDocumentoAction.php` — reclama; `ExtractorOcr::extrair()`; sucesso → `MarcarAnaliseIaLocalDocumentoAction`; regista; falha técnica conta tentativa → 3ª → `Erro`.
- O que implementar: RF-06, RF-08, RF-12.
- Testes associados: sucesso → `AnaliseIaLocal`; falha técnica → contador → `Erro`; `Storage::fake()`.
- Commit: `feat(extracao): orquestrador da etapa AnaliseOcr (Tesseract)`

### Tarefa 7 — Orquestrador `AnaliseIaLocal` (guarda de camada + veredicto + reconciliação)
- Ficheiros a criar/alterar:
  - `app/Features/Documento/Processamento/ProcessarAnaliseIaLocal/ProcessarAnaliseIaLocalDocumentoAction.php` — reclama; guarda `config('extracao.local.activa')` falsa → salta `→ AnaliseCloud` (não conta tentativa, RN-04); senão `ContratoClienteIA::extrair($texto, CamadaIA::Local)`; encaminhar veredicto (RF-09): completo → reconciliação (Tarefa 4) + `TransicionarProcessadoDocumentoAction`; desconhecido/incompleto → `AnaliseCloud`; perigoso → `MarcarPerigosoDocumentoAction`; falha técnica → contador → 3ª → `Erro`.
- O que implementar: RF-07, RF-09, RF-10/11, RF-12.
- Testes associados: camada inactiva salta sem contar; completo→`Processado` (`Prism::fake()`); desconhecido/incompleto→`AnaliseCloud`; perigoso→`Perigoso`; falha técnica→contador.
- Commit: `feat(extracao): orquestrador da etapa AnaliseIaLocal (veredicto + reconciliação)`

### Tarefa 8 — Orquestrador `AnaliseCloud`
- Ficheiros a criar/alterar:
  - `app/Features/Documento/Processamento/ProcessarAnaliseCloud/ProcessarAnaliseCloudDocumentoAction.php` — reclama; guarda `config('extracao.cloud.activa')` falsa → `MarcarErroDocumentoAction` (`sem LLM cloud disponível`); senão `extrair(..., CamadaIA::Cloud)`; veredicto (RF-09): completo → reconciliação + `Processado`; desconhecido/incompleto → `Erro`; perigoso → `Perigoso`; falha técnica → contador → `Erro`.
- O que implementar: RF-07, RF-09.
- Testes associados: cloud inactiva → `Erro`; completo → `Processado`; desconhecido → `Erro`; perigoso → `Perigoso`.
- Commit: `feat(extracao): orquestrador da etapa AnaliseCloud`

### Tarefa 9 — 5 Commands `extracao:*` + Schedule
> Nomes de classe em PT (triagem semântica); a signature `extracao:run-*` é o contrato CLI da issue e mantém-se.
- Ficheiros a criar/alterar:
  - `app/Console/Commands/Extracao/ExecutarScanExtracaoCommand.php` (`extracao:run-scan`) — loop/lote sobre `ReivindicarDocumentoPendenteAction` (reutiliza; RF-04).
  - `ExecutarParserExtracaoCommand.php` (`extracao:run-parser`) / `ExecutarTesseractExtracaoCommand.php` (`extracao:run-tesseract`) / `ExecutarIaLocalExtracaoCommand.php` (`extracao:run-ia-local`) / `ExecutarIaCloudExtracaoCommand.php` (`extracao:run-ia-cloud`) — cada um dispatch para a respectiva Action orquestradora, respeitando os limites por etapa (RF-03: tesseract/ia-local 1/ciclo; restantes em lote).
  - `routes/console.php` — agendar (RF-02) `everyMinute()`/`everyFiveMinutes()` + `->withoutOverlapping()`.
- O que implementar: RF-01/02/03/04.
- Testes associados: Feature via `Artisan::call` — cada command selecciona pelo estado correcto e ignora documentos manuais (CA-09); limites por etapa.
- Commit: `feat(extracao): commands extracao:* + agendamento no Schedule`

### Tarefa 10 — Upload: tipos de imagem (TIFF/BMP/WEBP) + limite 50 MB
- Ficheiros a criar/alterar:
  - `app/Features/Documento/RecepcaoUpload/ReceberUploadDocumentoRequest.php` — `mimetypes` + `max:51200` + mensagens.
  - `config/filesystems.php`/`config/*` + `.env.example` — `FILESYSTEM_ALLOWED_EXTENSIONS` (+tif/tiff/bmp/webp), `FILESYSTEM_MAX_FILE_SIZE=52428800`.
- O que implementar: RF-14, RF-15 (parte aplicacional).
- Testes associados: aceita PDF/JPG/PNG/TIFF/BMP/WEBP; rejeita fora da lista; aceita ≤ 50 MB, rejeita > 50 MB (Feature HTTP com `UploadedFile::fake`).
- Commit: `feat(upload): aceita TIFF/BMP/WEBP e limite de 50 MB`

### Tarefa 11 — Infra Docker: PHP ini, delegates, clamd.conf, serviço `scheduler`, docs .env/README
- Ficheiros a criar/alterar:
  - `Dockerfile` — `zz-findoc.ini`: `upload_max_filesize=50M`, `post_max_size=52M`; garantir delegates TIFF/WEBP/BMP no imagick e Tesseract/Leptonica (verificar; `apk add` `libwebp`/`tiff` se necessário).
  - `docker/clamav/clamd.conf` (novo) + montagem no serviço `clamav` — `StreamMaxLength`/`MaxScanSize`/`MaxFileSize` ≥ 50 MB.
  - `compose.yaml` — serviço `scheduler` (`php artisan schedule:work`).
  - `.env.example` completo + secção nova no `README.md` a documentar cada variável.
- O que implementar: RF-15 (infra), RF-16, RF-17, RNF-05.
- Testes associados: sem teste unitário (infra); verificação por `docker compose up` / `docker-parity` (CA-11) e `composer test` verde no container.
- Commit: `chore(docker): scheduler, limites de upload (PHP/clamd) e docs de ambiente`

## Ordem de implementação
1. Tarefa 1 (DTO/naming) — fundação; orquestradores de IA dependem dela.
2. Tarefa 2 (reset contador) — fundação transversal do tecto de tentativas.
3. Tarefa 3 (lease + `Atribuicao/`) — todos os orquestradores reclamam por aqui.
4. Tarefa 4 (reconciliação) — usada pelos orquestradores de IA (7/8).
5. Tarefas 5 → 6 → 7 → 8 (orquestradores por etapa) — dependem de 1-4.
6. Tarefa 9 (Commands/Schedule) — dependem das Actions (5-8) e do scan.
7. Tarefas 10 e 11 (upload + infra) — independentes das Actions; 11 alinha o limite do `clamd` com 10.

## Testes a escrever
| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| DTO parcial | unit | `tests/Unit/Features/Documento/.../TransicionarProcessadoDocumentoDtoTest.php` | nullable + rejeição ambos-nulos |
| Naming fallback | unit | `.../RegraNomearProcessadoTest.php` | nome extraído / `created_at` |
| Reset contador | unit | `.../ExecutorTransicaoDocumentoTest.php` | reset no avanço, não em `Erro` |
| Lease etapa | unit | `.../ReivindicarDocumentoEmEtapaActionTest.php` | elegibilidade por TTL |
| Lease concorrência | feature | `.../ReivindicarDocumentoEmEtapaConcorrenciaTest.php` | 2 conexões MySQL, 1 só reivindica |
| Reconciliação | unit | `.../RegraReconciliarEntidadesDocumentoTest.php` | empresa mãe / find-or-create / null |
| Orquestrador parser | unit | `.../ProcessarAnaliseTextoDocumentoActionTest.php` | threshold, imagem, tentativas |
| Orquestrador OCR | unit | `.../ProcessarAnaliseOcrDocumentoActionTest.php` | sucesso / falha |
| Orquestrador IA local | unit | `.../ProcessarAnaliseIaLocalDocumentoActionTest.php` | guarda, veredictos, `Prism::fake()` |
| Orquestrador IA cloud | unit | `.../ProcessarAnaliseCloudDocumentoActionTest.php` | guarda, veredictos |
| Fluxo feliz E2E | feature | `.../PipelineExtracaoTest.php` | PDF→parser→IA-local→`Processado` (`Storage::fake()`+`Prism::fake()`) |
| Commands | feature | `tests/Feature/Console/Extracao/*Test.php` | selecção por estado, ignora manual, limites |
| Upload tipos/limite | feature | `.../ReceberUploadDocumentoTest.php` | TIFF/BMP/WEBP + 50 MB |

## Dependências
- Issues bloqueantes: **nenhuma** (#110 merged).
- Deve ser implementada após: #96, #97, #94, #90/#91 (todas merged).

## Riscos de implementação
> Consolidados do Brief e da Spec.
- `ExtracaoDocumento` inexistente na 1ª reivindicação → o claim (Tarefa 3) tem de criar a linha sob `lockForUpdate`, sem colidir com o contrato "substituição total" do recorder.
- Reset do contador (Tarefa 2) tem de ser no-op quando não há `ExtracaoDocumento`.
- Naming parte com fornecedor/data nulos → coberto pela Tarefa 1 (fallbacks), tem de vir antes dos orquestradores de IA.
- `firstOrFail` da empresa mãe (Tarefa 4) → documento vai a `Erro` com motivo claro se a base não tiver empresa mãe.
- `config:cache` congela `local.activa`/`cloud.activa` → documentar no serviço `scheduler`/README (Tarefa 11).
- Delegates TIFF/WEBP/BMP no imagick/Tesseract (Tarefa 11) — sem delegate, upload aceita mas OCR falha; verificar no container.
- `clamd` `StreamMaxLength` < 50 MB faria falhar o scan → alinhar na Tarefa 11.
- Concorrência real só se prova com 2 conexões MySQL (Tarefa 3) — não basta uma conexão.
- Duplicação de `Entidade` por NIF imperfeito — mitigada pela #99; aqui `firstOrCreate` por `nif` exacto.

## O que NÃO fazer nesta issue
- **Não** alterar a máquina de estados (9 estados, mapa de transições, state objects) — congelada por #110.
- **Não** criar migrations nem alterar tabelas (#94).
- **Não** reescrever os motores de `app/Infrastructure/` — continuam puros; só são invocados.
- **Não** endurecer a validação de `TipoDocumento` (`posicao_empresa_mae` × `espera_*`) — tratado à parte pelo utilizador.
- **Não** implementar agrupar/fundir entidades duplicadas (#99).
- **Não** criar `Extracao/` — os orquestradores vivem em `Processamento/` (evitar sinónimo de subpasta).
- **Não** incluir tarefa de documentação `system_spec` — é da Fase 3a (`/documenta-implementacao`).
