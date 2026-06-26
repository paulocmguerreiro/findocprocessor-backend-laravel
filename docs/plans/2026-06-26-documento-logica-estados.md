# Plano: Documento — lógica (máquina de estados: Actions de transição + listagem + Regra* + Events)

**Issue:** #57
**Spec:** docs/specs/2026-06-26-documento-logica-estados.md
**Data:** 2026-06-26

> **Processo:** após cada tarefa correr `composer lint` + `composer refactor` antes do commit (não só
> no fim); mostrar Checkpoint task e aguardar aprovação explícita antes de commitar. `composer test`
> completo na última tarefa.

## Tarefas

### Tarefa 1 — Enums partilhados + excepção de transição
- Ficheiros: `app/Features/Documento/Reprocessar/ModoReprocessamento.php` (enum: `Modelo`, `Ferramenta`),
  `app/Features/Documento/Listar/CampoOrdenacaoDocumentos.php` (enum: `DataDocumento`, `CriadoEm`),
  `app/Shared/Cache/TagCache.php` (+ caso `Documentos`),
  `app/Shared/Exceptions/TransicaoInvalidaException.php`, `app/Exceptions/Handler.php` ou
  `bootstrap/app.php` (mapear → `422` via `ApiResponse`).
- O que implementar: enums `string` TitleCase PT; excepção tipada com mensagem PT; registo do render
  para `422` (seguir o padrão do exception handler existente — ver `02-shared/http.md`).
- Testes: arch (enums excluídos da regra final), unit do mapeamento da excepção → 422.
- Commit: `feat(documento): enums modo/ordenação + TransicaoInvalidaException (422)`

### Tarefa 2 — `RegraTransicaoEstado` (mapa central De→Para)
- Ficheiros: `app/Features/Documento/Transicao/RegraTransicaoEstado.php`.
- O que implementar: `final readonly`; mapa central das transições válidas (exactamente o grafo da
  issue); `handle(EstadoDocumento $de, EstadoDocumento $para): void` lança `TransicaoInvalidaException`
  se o par não existir; sem `Gate`. Cobrir self-loop `Processado→Processado` (correcção).
- Testes (Unit): cada transição válida passa; amostra de inválidas (`Processado→Enviado`,
  `Pendente→Processado`, `Erro→Processado`) lança.
- Commit: `feat(documento): RegraTransicaoEstado com mapa central de transições`

### Tarefa 3 — `RegraMoverFicheiro` (cross-disk + compensação)
- Ficheiros: `app/Features/Documento/Transicao/RegraMoverFicheiro.php`.
- O que implementar: `final readonly`; move entre discos distintos via `Storage::disk($destino)->put(
  $nome, Storage::disk($origem)->get($nome))` + `delete()` na origem; **verifica o retorno** de cada
  operação (discos `throw=>false`) e lança em falha; compensação best-effort (tenta repor na origem em
  excepção); devolve disco/nome destino para a Action persistir. Mapa estado→disco (reusar tabela de
  `02-shared/estados.md`).
- Testes (Unit, `Storage::fake`): move entrada→enviado→processado; falha simulada lança e compensa;
  consistência disco↔estado.
- Commit: `feat(documento): RegraMoverFicheiro entre discos com compensação`

### Tarefa 4 — `RegraNomearProcessado`
- Ficheiros: `app/Features/Documento/Transicao/RegraNomearProcessado.php`.
- O que implementar: `final readonly`; gera `yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}` com
  `Str::slug()`; extensão derivada de `nome_ficheiro_original`; data de `data_documento`.
- Testes (Unit): nome correcto; slug de nomes com acentos/espaços; extensão preservada.
- Commit: `feat(documento): RegraNomearProcessado (slug fornecedor+categoria)`

### Tarefa 5 — Events de domínio (after_commit)
- Ficheiros: `app/Events/DocumentoProcessado.php`, `DocumentoMarcadoErro.php`,
  `DocumentoMarcadoPerigoso.php`, `DocumentoReprocessado.php`.
- O que implementar: `final`, `implements ShouldDispatchAfterCommit`, `use Dispatchable, SerializesModels`;
  construtor com `public Documento $documento` (e `modo`/`motivo` onde aplicável). **Sem Listeners.**
- Testes (Unit): instanciação + (dispatch assertado nas tarefas das Actions via `Event::fake`).
- Commit: `feat(documento): events de domínio after_commit`

### Tarefa 6 — DTOs de transição + `fromRequest()` nos DTOs #45
- Ficheiros: `app/Features/Documento/RecepcaoUpload/ReceberUploadDocumentoDto.php`,
  `Transicao/TransicionarProcessadoDocumentoDto.php`, `MarcarErro/MarcarErroDocumentoDto.php`,
  `MarcarPerigoso/MarcarPerigosoDocumentoDto.php`, `Reprocessar/ReprocessarDocumentoDto.php`; +
  `fromRequest()` em `Criar/CriarDocumentoManualDto.php` e `Actualizar/ActualizarDocumentoDto.php`.
- O que implementar: `final readonly`, invariantes no construtor (padrão Value Object), `fromRequest()`
  com array shape em `validated()`. `motivo`/`mensagem_erro` não-vazios; `modo` é `ModoReprocessamento`.
- Testes (Unit): construtor rejeita inválidos; `fromRequest` mapeia snake→camel.
- Commit: `feat(documento): DTOs de transição + fromRequest dos DTOs manuais`

### Tarefa 7 — Listagem + Ver
- Ficheiros: `app/Features/Documento/Listar/ListarDocumentosAction.php`,
  `app/Features/Documento/Ver/VerDocumentoAction.php`.
- O que implementar: `ListarDocumentosAction` — `Gate::authorize('viewAny')`, `cursorPaginate` com
  `CampoOrdenacaoDocumentos` + `DirecaoOrdenacao` + filtro opcional `whereEstado`, cache
  (`TagCache::Documentos`, TTL curta) — padrão `ListarEntidadesAction`. `VerDocumentoAction` —
  `Gate::authorize('view', $documento)`; carrega `historico`.
- Testes (Unit): paginação, ordenação, filtro por estado, autorização.
- Commit: `feat(documento): ListarDocumentosAction + VerDocumentoAction`

### Tarefa 8 — Actions de criação (Processado / Pendente)
- Ficheiros: `app/Features/Documento/Criar/RegistarDocumentoManualAction.php`,
  `app/Features/Documento/RecepcaoUpload/ReceberUploadDocumentoAction.php`.
- O que implementar: ambas `Gate::authorize('create')` fora + `DB::transaction()`. Manual: cria em
  `Processado` (todos os campos) + 1 `EtapaDocumento (Processado,"registo manual",id)` + emite
  `DocumentoProcessado`. Upload: calcula `hash_sha256`, escreve no disco `entrada`, cria em `Pendente`,
  + 1 `EtapaDocumento (Pendente,"upload recebido",id)`; trata colisão de hash (erro controlado, não 500);
  invalida cache. `RegraTransicaoEstado` não se aplica à criação (estado inicial).
- Testes (Unit, `Storage::fake`, `Event::fake`): criação, histórico único, evento, colisão de hash.
- Commit: `feat(documento): RegistarDocumentoManual + ReceberUploadDocumento`

### Tarefa 9 — Transições de pipeline (programáticas)
- Ficheiros: `app/Features/Documento/.../MarcarAguardaEnvioDocumentoAction.php`,
  `MarcarEnviadoDocumentoAction.php`, `MarcarAguardaRespostaDocumentoAction.php`,
  `TransicionarProcessadoDocumentoAction.php`, `MarcarErroDocumentoAction.php`,
  `MarcarPerigosoDocumentoAction.php` (uma sub-pasta por slice).
- O que implementar: cada uma — `Gate::authorize('update', $documento)` fora + `DB::transaction()`:
  `RegraTransicaoEstado->handle($de,$para)`, `RegraMoverFicheiro` (quando muda de disco),
  `RegraNomearProcessado` (só `TransicionarProcessado`), `update` do `Documento`, 1 `EtapaDocumento`,
  emite Event quando aplicável (`Transicionar→DocumentoProcessado`, `MarcarErro→DocumentoMarcadoErro`,
  `MarcarPerigoso→DocumentoMarcadoPerigoso`). `MarcarPerigoso` aceita origem `Pendente` ou
  `AguardaResposta`. Invalida cache.
- Testes (Unit, `actingAs`, `Storage::fake`, `Event::fake`): transição válida, rejeição de origem
  errada, movimento de ficheiro, histórico único, evento, rollback sem histórico órfão.
- Commit: `feat(documento): actions de transição de pipeline`

### Tarefa 10 — Transições HTTP retidas (Reprocessar / Corrigir / Eliminar)
- Ficheiros: `app/Features/Documento/Reprocessar/ReprocessarDocumentoAction.php`,
  `Corrigir/CorrigirDocumentoAction.php`, `Eliminar/EliminarDocumentoAction.php`.
- O que implementar: `Reprocessar` — `update`; `Erro→AguardaEnvio` com `modo`; move `erro→entrada`; 1
  `EtapaDocumento (AguardaEnvio, modo->value, id)`; emite `DocumentoReprocessado`. `Corrigir` — `update`;
  `Processado→Processado`; renomeia via `RegraNomearProcessado` se o slug mudar; 1 `EtapaDocumento
  (Processado,"correcção",id)`. `Eliminar` — `delete`; apaga ficheiro do disco actual + `delete()` do
  Documento (histórico via `cascadeOnDelete`). Todas com `DB::transaction()` + cache.
- Testes (Unit): cada transição, renomeação condicional, eliminação de ficheiro.
- Commit: `feat(documento): ReprocessarDocumento + CorrigirDocumento + EliminarDocumento`

### Tarefa 11 — Camada HTTP (Controller + rotas + Requests + Resources + Feature tests)
- Ficheiros: `app/Features/Documento/DocumentoController.php`,
  `app/Features/Documento/DocumentoResource.php` (+ `whenLoaded('historico')`),
  `app/Features/Documento/EtapaDocumentoResource.php`, FormRequests por slice HTTP
  (`CriarDocumentoManualRequest`, `ReceberUploadDocumentoRequest`, `VerDocumentoRequest`,
  `CorrigirDocumentoRequest`, `ReprocessarDocumentoRequest`, `ListarDocumentosRequest`,
  `EliminarDocumentoRequest`), `routes/api.php`.
- O que implementar: Controller zero-lógica (dispatch + `ApiResponse`, padrão `EntidadeController`);
  rotas `apiResource` parcial + `POST documentos/upload` + `POST documentos/{documento}/reprocessar`;
  FormRequests com `authorize()` via `Gate` + `rules()` + `messages()` PT (upload `multipart`,
  validação tipo/dimensão); `EtapaDocumentoResource` (`estado`, `motivo`, `id_utilizador`, `criado_em`).
- Testes (Feature, HTTP): cada endpoint — 201/200/204/422/403; `?include=historico` carrega o histórico.
- Commit: `feat(documento): controller + rotas + requests + resources HTTP`

### Tarefa 12 — Qualidade final
- Ficheiros: nenhum (ou ajustes de cobertura).
- O que implementar: `composer test` completo — 100% coverage, 100% type-coverage, Larastan 9 zero
  erros, Pint + Rector limpos. Corrigir o que faltar.
- Testes: pipeline completa verde.
- Commit: `test(documento): cobertura e tipos a 100% — máquina de estados`

## Ordem de implementação

1. T1 (enums + excepção) — base para Regra* e Actions.
2. T2, T3, T4 (Regra*) — dependem de T1; são as peças injectadas nas Actions.
3. T5 (Events), T6 (DTOs) — dependem de T1; consumidos pelas Actions.
4. T7 (Listar/Ver) — só Eloquent + cache; não depende das Regra* de transição.
5. T8 (criação) — depende de T5, T6, RegraMoverFicheiro (upload escreve no disco).
6. T9 (pipeline) — depende de T2, T3, T4, T5, T6.
7. T10 (HTTP retidas) — depende de T2, T3, T5, T6.
8. T11 (HTTP) — depende de todas as Actions HTTP (T7, T8, T10) existirem.
9. T12 (qualidade) — última; valida o conjunto.

## Testes a escrever

| Teste | Tipo | Ficheiro | Verifica |
| ----- | ---- | -------- | -------- |
| Transições válidas/inválidas | unit | `tests/Unit/Features/Documento/RegraTransicaoEstadoTest.php` | mapa central, excepção 422 |
| Movimento entre discos | unit | `tests/Unit/Features/Documento/RegraMoverFicheiroTest.php` | put+delete, retorno, compensação |
| Nomeação processado | unit | `tests/Unit/Features/Documento/RegraNomearProcessadoTest.php` | slug + extensão |
| DTOs de transição | unit | `tests/Unit/Features/Documento/*DtoTest.php` | invariantes + fromRequest |
| Listar/Ver | unit | `tests/Unit/Features/Documento/Listar*Test.php`, `Ver*Test.php` | paginação, filtro, autorização |
| Criação (manual/upload) | unit | `tests/Unit/Features/Documento/Criar*Test.php`, `Receber*Test.php` | estado, histórico, evento, hash |
| Transições pipeline | unit | `tests/Unit/Features/Documento/Marcar*Test.php`, `Transicionar*Test.php` | estado+ficheiro+histórico+evento+rollback |
| Reprocessar/Corrigir/Eliminar | unit | `tests/Unit/Features/Documento/Reprocessar*Test.php` etc. | transição, rename, apagar ficheiro |
| Endpoints HTTP | feature | `tests/Feature/Features/Documento/*Test.php` | status codes, autorização, historico, upload |

## Dependências

- Issues bloqueantes: nenhuma (#45, #56 mergeadas).
- Deve ser implementada após: #45 (Model/DTOs/state objects) e #56 (`EtapaDocumento`) — já satisfeito.

## Riscos de implementação

> Consolidados do Brief e da Spec.

- **Atomicidade ficheiro↔BD** — filesystem não faz rollback; mitigação: mover dentro da transacção +
  verificar retorno (`throw=>false`) + compensação best-effort (T3, CA-14).
- **`'throw'=>false` nos discos** — falhas silenciosas; obrigatório verificar valor de retorno e lançar.
- **Cross-disk não é `move()`** — usar `put(get())`+`delete()`.
- **Volume de testes duais com 100% coverage** — maior risco de prazo; testar à medida (não acumular
  para T12).
- **`Auth::id()` null fora de HTTP** — testes usam `actingAs`; Jobs reais (autor do upload) diferidos.
- **Colisão de `hash_sha256` no upload** — devolver erro controlado (não 500).

## O que NÃO fazer nesta issue

- Mecanismo de extracção (IA/OCR), pré-scan de malware real, hierarquia de fallback OCR→modelos.
- Autorização real — `DocumentoPolicy` continua stub; sem abilities granulares novas; sem actor de
  sistema (Jobs como autor do upload ficam para a issue de Jobs).
- Jobs reais de pipeline (`WatchInboxJob`, `ProcessBatchJob`).
- Adicionar `correct()` ou mutações aos state objects (#45) — continuam read-only.
- Endpoint dedicado de histórico — só `whenLoaded` no `DocumentoResource`.
- Alterações de schema/migrations/factories.
