# Spec: Extração — orquestradores Schedule (`extracao:*`) sobre máquina de estados unificada

**Issue:** #111
**Brief:** docs/briefs/2026-07-17-extracao-orquestradores-schedule.md
**Data:** 2026-07-17

## Requisitos funcionais

### Orquestradores e agendamento
- **RF-01:** Existem **5 Commands `extracao:*`** finos (só dispatch para a Action orquestradora do
  passo, sem lógica — como os Controllers), um por estado activo do pipeline:
  `extracao:run-scan` (`Pendente`), `extracao:run-parser` (`AnaliseTexto`),
  `extracao:run-tesseract` (`AnaliseOcr`), `extracao:run-ia-local` (`AnaliseIaLocal`),
  `extracao:run-ia-cloud` (`AnaliseCloud`). Os estados terminais não têm Command.
- **RF-02:** `routes/console.php` agenda `run-scan`/`run-parser`/`run-tesseract`/`run-ia-local`
  `everyMinute()` e `run-ia-cloud` `everyFiveMinutes()`, **todos** `->withoutOverlapping()`.
- **RF-03:** Cada Command selecciona `Documento`s pelo seu estado (scope `whereEstado`) e reivindica
  os candidatos antes de processar (RF-08). `run-tesseract` e `run-ia-local` processam **1 doc/ciclo**
  (M1 8GB); `run-parser`, `run-scan` e `run-ia-cloud` processam em lote.

### Etapa de scan de malware (colapso na reivindicação)
- **RF-04:** `extracao:run-scan` selecciona por `Pendente` e **reutiliza**
  `ReivindicarDocumentoPendenteAction` + `TriarDocumentoPendenteAction` — que já fazem
  `Pendente → AnaliseMalware → (AnaliseTexto | Perigoso | Erro)` atomicamente, sob `lockForUpdate`.
  **Não** existe Command nem selecção por `AnaliseMalware` (nunca é estado de repouso).

### Etapa parser nativo + encaminhamento de imagem
- **RF-05:** `extracao:run-parser` (estado `AnaliseTexto`): se o `Documento` **não for PDF** (detecção
  por extensão de `nome_ficheiro_storage`, que o `hashName()` preserva), salta o parser e transiciona
  `AnaliseTexto → AnaliseOcr`. Se for PDF, invoca `ExtractorTextoNativo::extrair()`; `ultrapassaThreshold`
  verdadeiro → `AnaliseTexto → AnaliseIaLocal`; falso → `AnaliseTexto → AnaliseOcr`.
- **RF-06:** `extracao:run-tesseract` (estado `AnaliseOcr`): invoca `ExtractorOcr::extrair()`; sucesso →
  `AnaliseOcr → AnaliseIaLocal`.

### Etapas de IA (local/cloud) + guarda de camada
- **RF-07:** `extracao:run-ia-local` (estado `AnaliseIaLocal`) e `extracao:run-ia-cloud` (estado
  `AnaliseCloud`) invocam `ContratoClienteIA::extrair($texto, CamadaIA::Local|Cloud)`.
  - **Guarda de camada (antes de invocar o provider):** se `config('extracao.local.activa')` for falsa,
    `run-ia-local` salta `AnaliseIaLocal → AnaliseCloud`; se `config('extracao.cloud.activa')` for
    falsa, `run-ia-cloud` salta `AnaliseCloud → Erro` (motivo `sem LLM cloud disponível`). Ambas
    inactivas ⇒ texto pronto acaba em `Erro`. O salto é registado no histórico e **não** conta como
    falha técnica.
- **RF-08:** Reivindicação por lease das etapas de análise (`AnaliseTexto`/`AnaliseOcr`/`AnaliseIaLocal`/
  `AnaliseCloud`): antes de processar, o candidato é reclamado atomicamente (`lockForUpdate` na
  selecção + escrita de `extracao_reclamada_em = now()` em `extracoes_documento`, criando a linha se
  não existir). Só são elegíveis documentos com lease **nulo ou expirado** (`< now - config('extracao.ttl_lease')`).

### Encaminhamento do veredicto de IA (§5 da issue)
- **RF-09:** Mapeamento `ResultadoExtracaoIA` → transição:
  - `ehCompleto()` → reconciliação (RF-10) → `TransicionarProcessadoDocumentoAction` (`→ Processado`).
  - `ehDesconhecido()`: em `AnaliseIaLocal` → `AnaliseCloud`; em `AnaliseCloud` → `Erro`.
  - `ehPerigoso()` → `MarcarPerigosoDocumentoAction` (`→ Perigoso`).
  - `ehIncompleto()` (IA local) → `AnaliseCloud`; (IA cloud) → `Erro`.
  - `estaEmFalhaTecnica()` → falha técnica (RF-12), não é veredicto semântico.

### Reconciliação de entidades e empresa mãe (§3 da issue)
- **RF-10:** A partir de `ResultadoExtracaoIA` completo, resolver **por lado** (fornecedor e cliente):
  1. lado igual a `TipoDocumento.posicao_empresa_mae` → **empresa mãe**
     (`Entidade::whereEmpresaAplicacao()->firstOrFail()`, singleton), **sem** find-or-create;
  2. lado com `espera_<lado> = true` → **find-or-create** de `Entidade` por `nif` exacto (nome/nif +
     flag `e_fornecedor`/`e_cliente`);
  3. lado com `espera_<lado> = false` e **não** empresa mãe → **`null`** (não cria entidade).
  `id_categoria` vem sempre de `TipoDocumento.id_categoria`.
- **RF-11:** Construir `TransicionarProcessadoDocumentoDto` com os IDs/valor/data resolvidos (nullable
  conforme RF-10 / `espera_*`) e invocar `TransicionarProcessadoDocumentoAction`.

### Tecto de tentativas técnicas (§6 da issue)
- **RF-12:** `extracao_tentativas` conta **falhas técnicas** da etapa actual (excepção do motor/cliente,
  `FalhaExtracaoTextoException`, `estaEmFalhaTecnica()`). À **3ª** falha da mesma etapa → `MarcarErro`
  (`→ Erro`). Saltos semânticos (threshold, veredicto) e saltos por camada inactiva **não** incrementam.
- **RF-13:** `extracao_tentativas` é **reposto a 0** em qualquer transição correcta para a frente (não
  para `Erro`); nunca reposto numa transição para `Erro`. (Ver RN-05.)

### Upload — tipos e limite (decisão do Checkpoint A)
- **RF-14:** O upload passa a aceitar `application/pdf`, `image/jpeg`, `image/png`, **`image/tiff`,
  `image/bmp`, `image/webp`** (`mimetypes` de `ReceberUploadDocumentoRequest` + mensagem +
  `FILESYSTEM_ALLOWED_EXTENSIONS`).
- **RF-15:** Limite de upload **50 MB**: `ReceberUploadDocumentoRequest` `max:51200`;
  `FILESYSTEM_MAX_FILE_SIZE=52428800`; PHP `upload_max_filesize=50M`/`post_max_size=52M`
  (Dockerfile `zz-findoc.ini`); ClamAV `StreamMaxLength`/`MaxScanSize`/`MaxFileSize` ≥ 50 MB
  (`clamd.conf` próprio montado no serviço `clamav`). nginx já tem `client_max_body_size 50M`.

### Infra Docker / configuração (§8 da issue)
- **RF-16:** `compose.yaml` ganha serviço **`scheduler`** (`php artisan schedule:work`, partilhando a
  imagem `app`/`queue`, mesmas `depends_on`/env).
- **RF-17:** `.env.example` completo (LLM local/cloud, extração, ClamAV, filesystem) e **nova secção no
  `README.md`** a documentar cada variável de ambiente exigida.

## Requisitos não funcionais
- **RNF-01 (concorrência):** duas camadas — `->withoutOverlapping()` no schedule (impede o mesmo
  command sobrepor-se) **e** lease (`extracao_reclamada_em` TTL) + `lockForUpdate` (unicidade por
  documento; dois workers nunca processam o mesmo). Depende de `CACHE_STORE=redis` (locks partilhados).
- **RNF-02 (motores puros):** os motores de `app/Infrastructure/` continuam sem tocar BD/estado/
  `Documento`; são invocados **a partir** das Actions orquestradoras (interface `ContratoClienteIA`;
  concreto para `ExtractorTextoNativo`/`ExtractorOcr`).
- **RNF-03 (testes):** fluxo feliz end-to-end com `Storage::fake()` + `Prism::fake()`; teste de
  lease/`lockForUpdate` com **2 conexões MySQL reais** (padrão #90); padrão dual (Unit programático +
  Feature via Console/`Artisan::call`). Sem rede real em nenhum teste.
- **RNF-04 (qualidade):** `composer test` verde — Larastan nível 9 zero erros, 100% coverage e
  type-coverage, arch, sem `mixed`, `strict_types=1`.
- **RNF-05 (delegates):** o container tem de reconhecer TIFF/BMP/WEBP no imagick **e** no
  Tesseract/Leptonica; se em falta, acrescentar as libs no Dockerfile (`apk`). Sem delegate → upload
  aceita mas OCR falha → `Erro`.
- **RNF-06 (segurança/PII):** `texto_extraido`/`dados_json` nunca em Resource nem em log em claro;
  `nif` continua excluído do audit trail (`Entidade`/`Documento`).

## Modelo de dados

Sem migrations novas (fora de âmbito — #94). Uso das colunas existentes de `extracoes_documento`:

| Campo | Uso nesta issue |
| ----- | --------------- |
| `extracao_reclamada_em` | Lease de reivindicação por etapa (RF-08); escrito ao reclamar, lido para elegibilidade (TTL). |
| `extracao_tentativas` | Contador de falhas técnicas da etapa (RF-12); reset a 0 no avanço correcto (RF-13). |
| `texto_extraido` / `dados_json` | Produtos intermédios gravados pelo recorder (`RegistarEtapaExtracaoAction`). |

`TransicionarProcessadoDocumentoDto` — flexibilizado: `idFornecedor`/`idCliente`/`valor`/
`dataDocumento` passam a **nullable** (gated por `espera_*`); invariante mínima: pelo menos o lado da
empresa mãe preenchido; `idCategoria` continua obrigatório.

## Regras de negócio
- **RN-01 (transições só por Action):** toda a mudança de estado passa por Actions de transição +
  `ExecutorTransicaoDocumento` (`RegraTransicaoEstado`); nunca `if ($doc->estado == ...)`.
- **RN-02 (reconciliação por lado):** conforme RF-10 — empresa mãe (singleton) no lado
  `posicao_empresa_mae`; find-or-create por NIF no lado esperado; `null` no lado não-esperado sem
  empresa mãe.
- **RN-03 (nome canónico com fallback):** `RegraNomearProcessado` — fornecedor `null` → usar o
  `nomeFornecedor` extraído **sem criar `Entidade`** (fallback: nome da empresa mãe se vazio); data
  `null` → usar `created_at` do documento como prefixo `yyyy-mm-dd`.
- **RN-04 (salto ≠ falha):** saltos semânticos (threshold, veredicto `desconhecido`/`incompleto`) e
  saltos por camada inactiva registam-se no histórico mas **não** incrementam `extracao_tentativas`.
- **RN-05 (reset do contador):** `extracao_tentativas` reposto a 0 em qualquer transição correcta para
  a frente, nunca em `Erro` — implementado num só sítio (candidato: `ExecutorTransicaoDocumento` ao
  entrar num estado de análise não-terminal), não replicado pelos orquestradores.
- **RN-06 (empresa mãe em falta):** se `whereEmpresaAplicacao()->firstOrFail()` não encontrar entidade,
  o documento vai a `Erro` com motivo claro (config operacional em falta, não silencioso).
- **RN-07 (documento manual ignorado):** documentos criados por `RegistarDocumentoManualAction` (scan →
  `Processado` directo) nunca ficam em `Pendente`/estados de análise → naturalmente ignorados pelos
  Commands (CA-09).

## Dependências
- Issues bloqueantes: **nenhuma** — #110 (máquina de estados unificada) está merged.
- Pré-requisitos merged: #96 (extractores), #97 (cliente IA), #94 (modelo/recorder), #90/#91
  (concorrência/scan). Transitivos: #95, #88.

## Questões resolvidas
| Questão (do Brief) | Decisão |
| ------------------ | ------- |
| Etapa de malware: Command novo ou reutilizar reivindicação? | Reutilizar `ReivindicarDocumentoPendenteAction` (selecção por `Pendente`); sem caminho por `AnaliseMalware`. |
| Flexibilizar `TransicionarProcessadoDocumentoDto`? | Sim — `idFornecedor`/`idCliente`/`valor`/`dataDocumento` nullable, gated por `espera_*`. |
| Nome canónico com fornecedor `null` | Usar `nomeFornecedor` extraído sem criar `Entidade`; fallback nome da empresa mãe. |
| Nome canónico com data `null` | Usar `created_at` do documento. |
| Onde repor `extracao_tentativas` | No funil único (`ExecutorTransicaoDocumento`) ao avançar correctamente; nunca em `Erro`. |
| Tipos de imagem aceites | Alargar para TIFF + BMP + WEBP. |
| Limite de upload | Subir para 50 MB (com cascata PHP/clamd; nginx já 50M). |
| Endurecer validação de `TipoDocumento` | Fora de âmbito — tratado à parte pelo utilizador. |

## Critérios de aceitação
> Herdados da issue — não reformulados.
- [ ] CA-01: cada Command selecciona pelo `Documento.estado` correcto, respeita o lease (ignora reclamados recentes; recupera expirados) e os limites por etapa. *(issue)*
- [ ] CA-02: `Pendente → AnaliseMalware` (admissão); scan `AnaliseMalware → AnaliseTexto` (limpo) | `Perigoso` | `Erro`. *(issue)*
- [ ] CA-03: upload **imagem** (jpg/png): `AnaliseTexto` salta parser → `AnaliseOcr` → OCR → `AnaliseIaLocal`. *(issue)*
- [ ] CA-04: entidade existente reutilizada por NIF; inexistente **criada** com nome+nif+flags. *(issue)*
- [ ] CA-05: camada **inactiva**: local inactiva → `AnaliseIaLocal` salta para `AnaliseCloud`; cloud inactiva → `AnaliseCloud → Erro`; ambas inactivas → texto pronto → `Erro`. *(issue)*
- [ ] CA-06: fluxo feliz end-to-end (PDF: parser→IA-local→`Processado`) com `Storage::fake()` + `Prism::fake()`. *(issue)*
- [ ] CA-07: `"desconhecido"` local→`AnaliseCloud`→`Erro`; `"perigoso"`→`Perigoso`; inválido local→`AnaliseCloud`. *(issue)*
- [ ] CA-08: 3ª falha técnica da mesma etapa → `Erro`; contador reset ao avançar/reprocessar; saltos por camada não contam. *(issue)*
- [ ] CA-09: documento manual (sem entrar no pipeline) é ignorado pelos Commands. *(issue)*
- [ ] CA-10: os motores de `app/Infrastructure/` são invocados a partir das Actions orquestradoras (interface para `ContratoClienteIA`, concreto para os extractores); os motores continuam puros. *(issue)*
- [ ] CA-11: `compose.yaml` tem `scheduler`; `README.md` documenta o `.env`; `docker-parity` arranca. `composer test` verde. *(issue)*
- [ ] CA-12: system_spec: `04-infra/queue-jobs.md`, `04-infra/ambiente-docker.md`, `02-shared/estados.md`, `06-config.md` + `00-index.md`. *(issue)*
- [ ] CA-13: reconciliação de **extrato** (posição=cliente→empresa mãe; `espera_fornecedor=false`) → `id_fornecedor` a `null`, nome do ficheiro usa o nome extraído do fornecedor; `Processado`. *(spec)*
- [ ] CA-14: upload aceita TIFF/BMP/WEBP e rejeita tipos fora da lista; upload de 50 MB é aceite (validação Laravel + PHP) e ≤ limite do scan. *(spec)*
- [ ] CA-15: nome canónico com `data_documento` ausente usa `created_at`; com fornecedor ausente usa o nome extraído (fallback empresa mãe). *(spec)*
- [ ] CA-16: reivindicação concorrente (2 conexões MySQL) de um documento numa etapa de análise — só um worker o processa; o outro salta-o. *(spec)*

## SYSTEM_SPEC a actualizar
- `docs/system_spec/01-features/documento-pipeline.md` — Actions orquestradoras + mapa Command↔estado.
- `docs/system_spec/01-features/documento.md` — upload: tipos de imagem + limite 50 MB; DTO/naming flexibilizados.
- `docs/system_spec/02-shared/estados.md` — condução da máquina de estados pelos orquestradores (sem alterar o mapa).
- `docs/system_spec/04-infra/queue-jobs.md` — Commands `extracao:*` + Schedule + serviço `scheduler`.
- `docs/system_spec/04-infra/ambiente-docker.md` — serviço `scheduler`; PHP upload ini; clamd.conf.
- `docs/system_spec/04-infra/malware.md` — limites de tamanho do `clamd` (≥ 50 MB).
- `docs/system_spec/04-infra/extracao-texto.md` — novos formatos de imagem para OCR + delegates.
- `docs/system_spec/06-config.md` — `.env` (upload 50 MB, formatos), `config/extracao.php` (lease/tentativas consumidos), clamd.
- `docs/system_spec/00-index.md` — actualizar linhas afectadas (sem ficheiros novos previstos).

## Verificação RGPD/NIS2
- **Dados pessoais:** `texto_extraido`/`dados_json` (PII) e `nif` — nunca em Resource, log em claro nem
  audit trail; `ExtracaoDocumento` eliminada ao atingir estado terminal (`RegraEliminarExtracaoTerminal`).
  Reconciliação por NIF cria `Entidade` (nome+nif) — dado de negócio, mitigação de duplicados na #99.
- **Superfície de ataque:** upload até 50 MB — reforça a importância do scan de malware (ClamAV
  `StreamMaxLength` ≥ limite) e dos delegates de imagem controlados; prompt de IA mantém o nonce
  anti-injection (#97). LLM correm externos à app; nada de PII em claro nos saltos registados.
