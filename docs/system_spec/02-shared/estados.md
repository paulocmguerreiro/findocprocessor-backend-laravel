# System Spec — Shared: Estados e Contratos

> `app/Shared/States/`

---

## Ciclo de estados do `Documento`

Fluxo feliz na horizontal; ramos de falha/risco em baixo:

```
PENDENTE → AGUARDA_ENVIO → ENVIADO → AGUARDA_RESPOSTA → PROCESSADO
    ↘ ERRO (falha do scan de malware, #91)              ↘ ERRO
                                                       ↘ PERIGOSO
```

### Semântica dos estados

| Estado (enum)     | Value BD           | Significado                                                                            |
| ----------------- | ------------------ | -------------------------------------------------------------------------------------- |
| `Pendente`        | `PENDENTE`         | Documento recebido; campos de domínio podem estar a null (registo automático iniciado) |
| `AguardaEnvio`    | `AGUARDA_ENVIO`    | Pronto a ser enviado para o serviço de extracção                                       |
| `Enviado`         | `ENVIADO`          | Enviado para o serviço de extracção (IA / OCR)                                         |
| `AguardaResposta` | `AGUARDA_RESPOSTA` | À espera da resposta do serviço de extracção                                           |
| `Processado`      | `PROCESSADO`       | Processamento concluído com sucesso; todos os campos preenchidos                       |
| `Erro`            | `ERRO`             | Falha no processamento — recuperável (permite reprocessar)                             |
| `Perigoso`        | `PERIGOSO`         | Documento marcado como potencialmente malicioso/suspeito                               |

### Mapeamento estado → disco de storage

| Estado                       | `disco_storage` |
| ---------------------------- | --------------- |
| `Pendente`, `AguardaEnvio`   | `entrada`       |
| `Enviado`, `AguardaResposta` | `enviado`       |
| `Processado`                 | `processado`    |
| `Erro`                       | `erro`          |
| `Perigoso`                   | `perigoso`      |

---

## Racional de design — porquê state objects (e não `if ($doc->status == ...)`)

Os state objects (`app/Shared/States/`) **não são decoração**: são o andaime deliberado do
pipeline de ingestão (OCR / análise de imagem / extracção por IA) que se pendura em cada estado.

**Intenção:** cada estado expõe **apenas** os dados e as transições **válidos nessa fase**. Ao
encapsular o comportamento por estado, restringido, por construção, operações que ainda não
existem (ou que não fazem sentido) numa dada fase — em vez de as espalhar por condicionais
`if ($doc->status == ...)` que crescem sem controlo à medida que o pipeline evolui.

**Consequências que justificam o custo:**

- **Superfície limitada por fase** — um `Documento` em `Pendente` não expõe operações de
  `Processado`; o PHPStan garante-o.
- **Extensível sem tocar no existente** — acrescentar comportamento a `Enviado` /
  `AguardaResposta` (envio ao serviço de extracção, recepção da resposta) é adicionar a esse
  state object, sem `switch` central.
- **Transições explícitas e testáves** — o mapa de transições vive num só sítio; uma
  transição inválida falha de forma previsível (`422`), não por omissão de um `if`.

---

## Interface — `ContratoEstadoDocumento`

**Ficheiro:** `app/Shared/States/ContratoEstadoDocumento.php`

```php
interface ContratoEstadoDocumento
{
    public function estado(): EstadoDocumento;
    public function id(): string;
    public function discoStorage(): string;
    public function nomeFicheiroStorage(): string;
}
```

Declara apenas os **4 getters comuns a todos os 7 estados**. Campos adicionais vivem nas classes concretas.

---

## State objects — `app/Shared/States/Documento*.php`

7 classes `final readonly`, cada uma implementando `ContratoEstadoDocumento`. Construídas via `static deDocumento(Documento $documento): self` — nunca instanciadas directamente pelo consumidor.

**Regra:** a mudança de estado é feita na issue de Lógica (#57) via Actions. Aqui os state objects são **read-only** — sem método `correct()`.

### Grupos de campos

| Grupo    | Classes                                                                                      | Getters específicos (além dos 4 comuns)                                                                                  |
| -------- | -------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Parciais | `DocumentoPendente`, `DocumentoAguardaEnvio`, `DocumentoEnviado`, `DocumentoAguardaResposta` | `nomeFicheiroOriginal()`, `hashSha256()`                                                                                 |
| Mínimos  | `DocumentoErro`, `DocumentoPerigoso`                                                         | — (só os 4 da interface)                                                                                                 |
| Completo | `DocumentoProcessado`                                                                        | `nomeFicheiroOriginal()`, `hashSha256()`, `idFornecedor()`, `idCliente()`, `idCategoria()`, `valor()`, `dataDocumento()` |

### Padrão de implementação (exemplo `DocumentoPendente`)

```php
final readonly class DocumentoPendente implements ContratoEstadoDocumento
{
    public function __construct(
        private string $id,
        private string $discoStorage,
        private string $nomeFicheiroStorage,
        private string $nomeFicheiroOriginal,
        private string $hashSha256,
    ) {}

    public static function deDocumento(Documento $documento): self
    {
        return new self(
            id: $documento->id,
            discoStorage: $documento->disco_storage,
            nomeFicheiroStorage: $documento->nome_ficheiro_storage,
            nomeFicheiroOriginal: $documento->nome_ficheiro_original,
            hashSha256: $documento->hash_sha256,
        );
    }

    public function estado(): EstadoDocumento { return EstadoDocumento::Pendente; }
    public function id(): string { return $this->id; }
    public function discoStorage(): string { return $this->discoStorage; }
    public function nomeFicheiroStorage(): string { return $this->nomeFicheiroStorage; }
    public function nomeFicheiroOriginal(): string { return $this->nomeFicheiroOriginal; }
    public function hashSha256(): string { return $this->hashSha256; }
}
```

### Invocação no Model

```php
$documento->estado(); // → ContratoEstadoDocumento (match exaustivo, sem default)
```

O `match` em `Documento::estado()` cobre os 7 casos sem `default` — Larastan 9 valida a exaustividade.

---

## Regras de transição (implementadas — Issue #57)

A mudança de estado é sempre feita por Actions de transição, **nunca** com `if ($doc->status == ...)`.
O mapa central é validado por `RegraTransicaoEstado` (ver `02-shared/regras-negocio.md`).

### Mapa De → Para (mapa central)

| De                | Para              | Action                                  | Via                  |
| ----------------- | ----------------- | --------------------------------------- | -------------------- |
| `Pendente`        | `AguardaEnvio`    | `MarcarAguardaEnvioDocumentoAction` (via `TriarDocumentoPendenteAction`, #91) | pipeline |
| `Pendente`        | `Perigoso`        | `MarcarPerigosoDocumentoAction` (via `TriarDocumentoPendenteAction`, #91)    | pipeline (pré-scan)  |
| `Pendente`        | `Erro`            | `MarcarErroDocumentoAction` (via `TriarDocumentoPendenteAction`, #91)        | pipeline (falha do scan de malware) |
| `AguardaEnvio`    | `Enviado`         | `MarcarEnviadoDocumentoAction`          | pipeline             |
| `Enviado`         | `AguardaResposta` | `MarcarAguardaRespostaDocumentoAction`  | pipeline             |
| `AguardaResposta` | `Processado`      | `TransicionarProcessadoDocumentoAction` | pipeline             |
| `AguardaResposta` | `Erro`            | `MarcarErroDocumentoAction`             | pipeline             |
| `AguardaResposta` | `Perigoso`        | `MarcarPerigosoDocumentoAction`         | pipeline (guardrail) |
| `Erro`            | `AguardaEnvio`    | `ReprocessarDocumentoAction`            | HTTP                 |
| `Processado`      | `Processado`      | `CorrigirDocumentoAction`               | HTTP (self-loop)     |

Qualquer par não listado lança `TransicaoInvalidaException` (→ 422).

Os state objects da issue #45 são read-only — sem método `correct()`. A transição, o movimento de
ficheiro entre discos e o registo em `EtapaDocumento` (issue #56) são feitos pelas Actions (#57).

---

## Modelo de 2 dimensões — estado de negócio × etapa de extracção (#94)

`Documento.status` (`EstadoDocumento`) e `ExtracaoDocumento.etapa_extracao` (`EtapaExtracao`) são
**duas dimensões independentes**: o estado de negócio segue o ciclo de vida do documento (ver acima);
a etapa de extracção segue o progresso do pipeline de IA/OCR sobre o conteúdo do ficheiro. Um
documento pode, por exemplo, estar em `AguardaResposta` (negócio) enquanto a sua extracção está em
`NecessitaOcr` (IA) — os dois avançam a ritmos diferentes e são geridos por Actions distintas
(`Marcar*`/`ExecutorTransicaoDocumento` vs. `RegistarEtapaExtracaoAction`).

| Estado de negócio (`status`) | Etapa de extracção possível | Lock (`extracao_reclamada_em`) |
|---|---|---|
| `Pendente` | Sem linha `ExtracaoDocumento` (ainda não reivindicado) ou `Pendente` | `null` |
| `AguardaEnvio`, `Enviado`, `AguardaResposta` | `Pendente` → `NecessitaOcr`/`TextoPronto`/`NecessitaCloud` → `Concluido`/`Falhado` | preenchido enquanto reivindicado pelo orquestrador (#97/#98); `null` fora da janela de lease |
| `Processado` | Tipicamente `Concluido` (não enforçado por esta issue) | `null` |
| `Erro` | Etapa congelada no valor anterior à transição — **resetada para `Pendente`** ao reprocessar (`ReprocessarDocumentoAction`, RN-03/RN-04) | `null` após reset |
| `Perigoso` | Documento nunca chega a ter linha de extracção nesta transição (ficheiro nunca é enviado ao pipeline de IA) | — |

- **`ExtracaoDocumento` é opcional** — `Documento::extracao()` devolve `null` para qualquer documento
  que nunca tenha entrado na dimensão de extracção (registo manual via
  `RegistarDocumentoManualAction`, ou erro de scan de malware em `Pendente` antes de qualquer
  reivindicação). Ver `03-models/extracao-documento.md`.
- **`etapas_documento` regista ambas as dimensões na mesma tabela** — uma linha de negócio (gravada
  por `ExecutorTransicaoDocumento`) tem `passo`/`resultado` a `null`; uma linha de IA (gravada por
  `RegistarEtapaExtracaoAction`) tem `estado` igual ao `status` **actual** do documento (não muda) e
  `passo`/`resultado` preenchidos — permite reconstruir a história completa (negócio + IA) numa única
  query ordenada por `created_at`.
- **Enforcement fora desta issue (#94)**: reivindicação real com `lockForUpdate()`/libertação por TTL
  sobre `extracao_reclamada_em`, e a transição automática ao esgotar `extracao_tentativas`, ficam
  para o orquestrador de pipeline (#97/#98). Esta issue só define o modelo de dados e o recorder.

---

## Contrato de atomicidade ficheiro↔BD (#90)

`ExecutorTransicaoDocumento` move o ficheiro **antes** de abrir a `DB::transaction()` (ver
`04-infra/transactions.md`) — o filesystem não participa no rollback da BD. Se a compensação
best-effort (repor o ficheiro na origem) também falhar, existe uma **janela de inconsistência**:
a BD reflecte o estado anterior à transição, mas o ficheiro físico pode estar no disco de destino.

Como o conjunto de discos é fixo (5: `entrada`, `enviado`, `processado`, `erro`, `perigoso`, mapa em
`RegraMoverFicheiro::discoParaEstado()`), esta janela é **detectável e reversível**, não uma
inconsistência permanente:

- **Detecção:** `ReconciliarFicheirosJob` (agendado a cada 5 min, `onOneServer`) varre `Documento`s
  presos num estado transitório (`AguardaEnvio`/`Enviado`/`AguardaResposta`) há mais tempo que
  `config('pipeline.reconciliacao_limiar_minutos')` (default 15 min — não é uma janela de
  recência, é um limiar de "parado há mais tempo que uma transição normal demora").
- **Resolução:** `RegraReconciliarLocalizacaoFicheiro` verifica se o ficheiro existe no
  `disco_storage` actual; se não, procura-o nos 4 discos restantes comparando `hash_sha256` (o
  nome mantém-se igual entre discos, excepto no caso `Processado`/`RegraNomearProcessado`, fora do
  âmbito desta reconciliação). Se localizado noutro disco, `ReconciliarFicheirosJob` **repõe
  automaticamente** `disco_storage`/`nome_ficheiro_storage` na BD (decisão do Brief #90 — reposição
  automática, não apenas sinalização).
- **Caso irrecuperável:** se o ficheiro não existir em nenhum dos 5 discos, o Job regista
  `Log::error` estruturado (id do documento, disco/nome esperados — sem dados sensíveis) e não
  altera a BD; um ficheiro genuinamente perdido exige intervenção manual, fora do âmbito da
  reconciliação automática.
- **Custo:** proporcional ao nº de documentos presos (scan limitado pelo índice composto
  `(status, updated_at)`, migration `2026_07_13_112928`), nunca à tabela `documentos` completa.

---

## DTOs partilhados (`app/Shared/DTOs/`)

_Vazio até à primeira issue implementada._

DTOs de feature vivem dentro da sua slice (`app/Features/<Feature>/`). Ver `01-features/<slug>.md` para DTOs específicos de cada feature.
